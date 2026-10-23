<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ServiceBooking;
use App\Models\ServicePackage;
use App\Models\ServiceQuote;
use App\Models\ServiceResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceCheckoutService
{
    public function __construct(private ServicePricingService $pricing) {}

    public function quote(int $customerId, ServicePackage $option, array $input): ServiceQuote
    {
        $price = $this->pricing->calculate($option, $input);

        return ServiceQuote::create([
            'token' => (string) Str::uuid(), 'customer_id' => $customerId, 'option_id' => $option->id,
            'configuration' => $price['configuration'], 'snapshot' => $price['snapshot'], 'fingerprint' => $price['fingerprint'],
            'total' => $price['total'], 'expires_at' => now()->addMinutes(config('booking.quote_minutes', 10)),
        ]);
    }

    public function reserve(int $customerId, ServiceQuote $quote, array $input): ServiceBooking
    {
        abort_unless($quote->customer_id === $customerId, 403);
        $data = Validator::make($input, [
            'checkout_key' => ['required', 'uuid'], 'policy_accepted' => ['required', 'accepted'],
            'contact_name' => ['required', 'string', 'max:100'], 'contact_email' => ['required', 'email', 'max:100'],
            'contact_phone' => ['required', 'regex:/^(0|\+84)[0-9]{9}$/'],
            'travelers' => ['required', 'array', 'min:1', 'max:30'], 'travelers.*' => ['array:full_name,date_of_birth,room,license'],
            'travelers.*.full_name' => ['required', 'string', 'max:100'], 'travelers.*.date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'travelers.*.room' => ['nullable', 'integer', 'between:1,5'], 'travelers.*.license' => ['nullable', 'regex:/^[A-Za-z0-9-]{6,30}$/'],
        ])->validate();
        $requestHash = hash('sha256', json_encode([$quote->id, $data], JSON_THROW_ON_ERROR));
        $resourceId = ServicePackage::whereKey($quote->option_id)->value('resource_id');

        return DB::transaction(function () use ($customerId, $quote, $data, $requestHash, $resourceId) {
            Customer::lockForUpdate()->findOrFail($customerId);
            $existing = ServiceBooking::where('customer_id', $customerId)->where(fn ($q) => $q->where('checkout_key', $data['checkout_key'])->orWhere('quote_id', $quote->id))->lockForUpdate()->first();
            if ($existing) {
                if ($existing->request_hash !== $requestHash) {
                    $this->error('checkout_key', 'Báo giá hoặc mã gửi đã được dùng. Vui lòng mở báo giá mới khi thay đổi thông tin.');
                }

                return $existing;
            }
            $lockedQuote = ServiceQuote::lockForUpdate()->findOrFail($quote->id);
            if ($lockedQuote->expires_at->lte(now())) {
                $this->error('quote', 'Báo giá đã hết hạn. Hãy quay lại sản phẩm và lấy báo giá mới.');
            }
            ServiceResource::lockForUpdate()->findOrFail($resourceId);
            $option = ServicePackage::lockForUpdate()->findOrFail($lockedQuote->option_id);
            if ($option->resource_id !== (int) $resourceId) {
                $this->error('quote', 'Quỹ tồn đã thay đổi; cần báo giá mới.');
            }
            $price = $this->pricing->calculate($option, $lockedQuote->configuration);
            if ($price['fingerprint'] !== $lockedQuote->fingerprint) {
                $this->error('quote', 'Giá hoặc điều kiện vừa thay đổi. Vui lòng lấy báo giá mới và xác nhận lại.');
            }
            $this->validateTravelers($lockedQuote, $data['travelers']);
            $order = ServiceBooking::create([
                'reference' => 'VG-'.now()->format('ymd').'-'.Str::upper(Str::random(10)), 'customer_id' => $customerId,
                'option_id' => $option->id, 'quote_id' => $lockedQuote->id, 'checkout_key' => $data['checkout_key'], 'request_hash' => $requestHash,
                'type' => $price['snapshot']['type'], 'configuration' => $price['configuration'], 'snapshot' => $price['snapshot'],
                'contact' => ['name' => $data['contact_name'], 'email' => $data['contact_email'], 'phone' => $data['contact_phone']],
                'travelers' => array_values($data['travelers']), 'total' => $price['total'], 'currency' => 'VND',
                'reservation_status' => 'held', 'payment_status' => 'unpaid', 'fulfillment_status' => 'pending',
                'expires_at' => now()->addMinutes(config('booking.payment_minutes', 15)),
                'service_starts_at' => $price['starts_at'], 'service_ends_at' => $price['ends_at'],
            ]);
            foreach ($price['allocations'] as $allocation) {
                $allocation['starts_at'] = Carbon::parse($allocation['starts_at']);
                $allocation['ends_at'] = Carbon::parse($allocation['ends_at']);
                $order->allocations()->create($allocation + ['resource_id' => $option->resource_id]);
            }
            $order->recordEvent('held', ['amount' => $order->total, 'currency' => 'VND', 'customer_id' => $customerId]);

            return $order;
        });
    }

    public function travelerCount(ServiceQuote $quote): int
    {
        $config = $quote->configuration;

        return match ($quote->snapshot['type']) {
            'stay', 'activity' => $config['adults'] + $config['children'], 'bus' => count($config['seats']), default => 1,
        };
    }

    private function validateTravelers(ServiceQuote $quote, array $travelers): void
    {
        if (count($travelers) !== $this->travelerCount($quote)) {
            $this->error('travelers', 'Số người trong thông tin khách không khớp báo giá.');
        }
        $config = $quote->configuration;
        $snapshot = $quote->snapshot;
        $type = $snapshot['type'];
        $attributes = $snapshot['attributes'] ?? [];
        $roomCounts = [];
        foreach (array_values($travelers) as $index => $traveler) {
            if (in_array($type, ['stay', 'activity'], true) && $index >= $config['adults']) {
                $date = $traveler['date_of_birth'] ?? null;
                if (! $date) {
                    $this->error("travelers.{$index}.date_of_birth", 'Vui lòng nhập ngày sinh trẻ em theo điều kiện gói.');
                }
                $age = (int) Carbon::parse($date)->diffInYears(Carbon::parse($snapshot['starts_at']));
                if ($age < ($attributes['child_min_age'] ?? 0) || $age > ($attributes['child_max_age'] ?? 11)) {
                    $this->error("travelers.{$index}.date_of_birth", 'Tuổi trẻ em tại ngày sử dụng không thuộc nhóm giá của gói.');
                }
            }
            if ($type === 'stay') {
                $room = (int) ($traveler['room'] ?? 0);
                if ($room < 1 || $room > $config['rooms']) {
                    $this->error("travelers.{$index}.room", 'Vui lòng phân khách vào đúng phòng đã đặt.');
                }
                $category = $index < $config['adults'] ? 'adult' : 'child';
                $roomCounts[$room][$category] = ($roomCounts[$room][$category] ?? 0) + 1;
            }
        }
        if ($type === 'stay') {
            for ($room = 1; $room <= $config['rooms']; $room++) {
                $adults = $roomCounts[$room]['adult'] ?? 0;
                $children = $roomCounts[$room]['child'] ?? 0;
                if ($adults < 1 || $adults > $snapshot['max_adults'] || $children > $snapshot['max_children'] || $adults + $children > $snapshot['occupancy']) {
                    $this->error('travelers', 'Phân khách chưa đúng sức chứa từng phòng hoặc có phòng thiếu người lớn.');
                }
            }
        }
        if ($type === 'rental' && ($attributes['mode'] ?? 'self_drive') === 'self_drive') {
            $driver = array_values($travelers)[0];
            if (empty($driver['license']) || empty($driver['date_of_birth'])) {
                $this->error('travelers', 'Thuê tự lái cần ngày sinh và số giấy phép của người lái.');
            }
            $age = (int) Carbon::parse($driver['date_of_birth'])->diffInYears(Carbon::parse($snapshot['starts_at']));
            if ($age < ($attributes['min_driver_age'] ?? 21)) {
                $this->error('travelers', 'Người lái chưa đủ tuổi theo điều kiện gói.');
            }
        }
    }

    private function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
