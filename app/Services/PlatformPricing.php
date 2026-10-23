<?php

namespace App\Services;

use App\Enums\ServiceType;
use App\Models\PlatformInventoryDay;
use App\Models\PlatformOption;
use App\Models\PlatformTrip;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformPricing
{
    public function __construct(private PlatformInventory $inventory) {}

    public function calculate(PlatformOption $option, array $input): array
    {
        $option->load('resource.product.supplier');
        $resource = $option->resource;
        $product = $resource->product;
        if (! $option->is_active || ! $resource->is_active || ! $product->is_active || ! $product->supplier->is_active) {
            $this->error('availability', 'Sản phẩm/gói hiện không mở bán.');
        }
        $type = ServiceType::from($product->type);
        $today = Carbon::today('Asia/Ho_Chi_Minh')->toDateString();
        $rules = match ($type) {
            ServiceType::Stay => [
                'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
                'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
                'rooms' => ['required', 'integer', 'between:1,5'], 'adults' => ['required', 'integer', 'between:1,20'],
                'children' => ['required', 'integer', 'between:0,10'],
            ],
            ServiceType::Bus => [
                'trip_id' => ['required', 'integer'], 'boarding_stop_id' => ['required', 'integer'], 'alighting_stop_id' => ['required', 'integer'],
                'seats' => ['required', 'array', 'min:1', 'max:8'], 'seats.*' => ['required', 'string', 'distinct', 'max:10'],
            ],
            ServiceType::Transfer => [
                'pickup_at' => ['required', 'date_format:Y-m-d\TH:i'], 'direction' => ['required', 'in:airport_to_address,address_to_airport'],
                'zone' => ['required', 'string', Rule::in(array_keys($option->attributes['zones'] ?? []))],
                'address' => ['required', 'string', 'max:255'], 'passengers' => ['required', 'integer', 'between:1,16'],
                'bags' => ['required', 'integer', 'between:0,20'], 'flight_number' => ['nullable', 'regex:/^[A-Za-z0-9 -]{2,15}$/'],
            ],
            ServiceType::Rental => [
                'pickup_at' => ['required', 'date_format:Y-m-d\TH:i'], 'return_at' => ['required', 'date_format:Y-m-d\TH:i'],
                'passengers' => ['required', 'integer', 'between:1,16'],
            ],
            ServiceType::Activity => [
                'visit_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
                'slot' => ['required', Rule::in($option->attributes['slots'] ?? [])],
                'adults' => ['required', 'integer', 'between:1,20'], 'children' => ['required', 'integer', 'between:0,20'],
            ],
        };
        $config = Validator::make($input, $rules)->validate();
        foreach (['rooms', 'adults', 'children', 'passengers', 'bags', 'trip_id', 'boarding_stop_id', 'alighting_stop_id'] as $key) {
            if (isset($config[$key])) {
                $config[$key] = (int) $config[$key];
            }
        }
        $lines = [];
        $allocations = [];
        $extra = [];
        $total = 0;
        if ($type === ServiceType::Stay) {
            $start = $this->local($config['check_in'].' '.($option->attributes['check_in_time'] ?? '14:00'));
            $end = $this->local($config['check_out'].' '.($option->attributes['check_out_time'] ?? '12:00'));
            $nights = (int) Carbon::parse($config['check_in'])->diffInDays(Carbon::parse($config['check_out']));
            if ($nights > 30) {
                $this->error('check_out', 'Mỗi đơn lưu trú tối đa 30 đêm.');
            }
            if ($config['adults'] < $config['rooms'] || $config['adults'] > $config['rooms'] * $option->max_adults
                || $config['children'] > $config['rooms'] * $option->max_children
                || $config['adults'] + $config['children'] > $config['rooms'] * $option->occupancy) {
                $this->error('adults', 'Nhóm khách vượt sức chứa phòng hoặc thiếu người lớn đại diện cho mỗi phòng.');
            }
            $day = Carbon::parse($config['check_in'], 'Asia/Ho_Chi_Minh')->startOfDay();
            for ($i = 0; $i < $nights; $i++) {
                $inventory = PlatformInventoryDay::where('resource_id', $resource->id)->whereDate('day', $day->toDateString())->first();
                if (! $inventory || $inventory->is_closed) {
                    $this->error('availability', 'Có ít nhất một đêm chưa mở bán hoặc đã đóng. Hãy chọn ngày khác.');
                }
                $price = max(0, $option->price + $inventory->price_adjustment);
                $amount = $price * $config['rooms'];
                $total += $amount;
                $lines[] = ['label' => 'Đêm '.$day->format('d/m/Y'), 'quantity' => $config['rooms'], 'unit_price' => $price, 'amount' => $amount];
                $allocations[] = $this->allocation($day->copy()->utc(), $day->copy()->addDay()->utc(), $config['rooms']);
                $day->addDay();
            }
            $extra = ['nights' => $nights, 'room_pool' => $resource->name];
        } elseif ($type === ServiceType::Bus) {
            $trip = PlatformTrip::with('stops')->where('resource_id', $resource->id)->where('is_active', true)->find($config['trip_id']);
            if (! $trip) {
                $this->error('trip_id', 'Chuyến xe không thuộc gói đã chọn hoặc đã đóng bán.');
            }
            $from = $trip->stops->firstWhere('id', $config['boarding_stop_id']);
            $to = $trip->stops->firstWhere('id', $config['alighting_stop_id']);
            if (! $from || ! $to || $from->position >= $to->position) {
                $this->error('alighting_stop_id', 'Điểm xuống phải nằm sau điểm lên trên cùng chuyến xe.');
            }
            $allowed = $resource->attributes['seats'] ?? [];
            if (array_diff($config['seats'], $allowed)) {
                $this->error('seats', 'Sơ đồ không có ghế bạn vừa chọn.');
            }
            sort($config['seats']);
            $start = $from->time_at;
            $end = $to->time_at;
            $price = ($to->position - $from->position) * $option->price;
            $quantity = count($config['seats']);
            $total = $price * $quantity;
            $lines[] = ['label' => 'Vé xe '.$from->name.' → '.$to->name, 'quantity' => $quantity, 'unit_price' => $price, 'amount' => $total];
            foreach ($config['seats'] as $seat) {
                $allocations[] = $this->allocation($start, $end, 1) + ['trip_id' => $trip->id, 'seat' => $seat];
            }
            $extra = ['trip_code' => $trip->code, 'boarding' => $from->name, 'boarding_address' => $from->address, 'alighting' => $to->name, 'alighting_address' => $to->address, 'seats' => $config['seats']];
        } elseif ($type === ServiceType::Transfer) {
            $start = $this->local($config['pickup_at']);
            $end = $start->copy()->addMinutes($option->attributes['duration_minutes'] ?? 90);
            if ($config['passengers'] > $option->occupancy || $config['bags'] > ($option->attributes['max_bags'] ?? 0)) {
                $this->error('passengers', 'Xe không đủ số chỗ hoặc sức chứa hành lý. Vui lòng chọn hạng xe khác.');
            }
            $total = $option->price + (int) $option->attributes['zones'][$config['zone']];
            $lines[] = ['label' => 'Xe đưa đón · '.$config['zone'], 'quantity' => 1, 'unit_price' => $total, 'amount' => $total];
            $allocations[] = $this->allocation($start->copy()->subMinutes($resource->buffer_minutes), $end->copy()->addMinutes($resource->buffer_minutes), 1);
            $extra = ['airport' => $option->attributes['airport'] ?? $product->location, 'meeting_point' => $option->attributes['meeting_point'] ?? 'Theo điểm đón của gói'];
        } elseif ($type === ServiceType::Rental) {
            $start = $this->local($config['pickup_at']);
            $end = $this->local($config['return_at']);
            if ($end->lte($start) || $end->gt($start->copy()->addDays(30))) {
                $this->error('return_at', 'Giờ trả phải sau giờ nhận và thời gian thuê không quá 30 ngày.');
            }
            if ($config['passengers'] > $option->occupancy) {
                $this->error('passengers', 'Số người vượt sức chứa xe.');
            }
            $days = (int) ceil($start->diffInSeconds($end) / 86400);
            $total = $days * $option->price;
            $lines[] = ['label' => 'Thuê xe · block 24 giờ, làm tròn lên', 'quantity' => $days, 'unit_price' => $option->price, 'amount' => $total];
            $allocations[] = $this->allocation($start->copy()->subMinutes($resource->buffer_minutes), $end->copy()->addMinutes($resource->buffer_minutes), 1);
            $extra = ['rental_days' => $days, 'mode' => $option->attributes['mode'] ?? 'self_drive', 'deposit_due_at_pickup' => (int) ($option->attributes['deposit'] ?? 0), 'vehicle' => $resource->name];
        } else {
            $start = $this->local($config['visit_date'].' '.$config['slot']);
            $end = $start->copy()->addMinutes($option->attributes['duration_minutes'] ?? 120);
            $quantity = $config['adults'] + $config['children'];
            if ($quantity > 20 || ($config['children'] > 0 && $option->child_price === null)) {
                $this->error('children', 'Gói không có nhóm vé trẻ em hoặc nhóm vượt 20 người.');
            }
            $total = $config['adults'] * $option->price + $config['children'] * (int) $option->child_price;
            $lines[] = ['label' => 'Vé người lớn', 'quantity' => $config['adults'], 'unit_price' => $option->price, 'amount' => $config['adults'] * $option->price];
            if ($config['children'] > 0) {
                $lines[] = ['label' => 'Vé trẻ em', 'quantity' => $config['children'], 'unit_price' => $option->child_price, 'amount' => $config['children'] * $option->child_price];
            }
            $allocations[] = $this->allocation($start, $end, $quantity);
        }
        if ($start->lte(now())) {
            $this->error('availability', 'Ngày/giờ sử dụng dịch vụ đã qua. Vui lòng chọn lịch khác.');
        }
        if ($start->gt(now()->addYear())) {
            $this->error('availability', 'Chỉ hỗ trợ đặt trước trong vòng một năm.');
        }
        $this->inventory->assertAvailable($resource, $allocations);
        $fee = (int) ($option->attributes['service_fee'] ?? 0);
        $total += $fee;
        if ($fee > 0) {
            $lines[] = ['label' => 'Phí dịch vụ công bố', 'quantity' => 1, 'unit_price' => $fee, 'amount' => $fee];
        }
        $snapshot = [
            'product_id' => $product->id, 'product' => $product->name, 'type' => $product->type,
            'supplier' => $product->supplier->name, 'option' => $option->name, 'option_id' => $option->id, 'resource_id' => $resource->id,
            'location' => $product->location, 'attributes' => $option->attributes, 'policy' => $option->policy,
            'max_adults' => $option->max_adults, 'max_children' => $option->max_children, 'occupancy' => $option->occupancy,
            'buffer_minutes' => $resource->buffer_minutes, 'resource_attributes' => $resource->attributes,
            'price_lines' => $lines, 'currency' => 'VND', 'tax_note' => 'Giá demo đã bao gồm thuế theo gói; không cộng thêm thuế lần hai.',
            'total' => $total, 'timezone' => 'Asia/Ho_Chi_Minh', 'demo' => true, 'extra' => $extra,
            'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(),
        ];

        return ['configuration' => $config, 'snapshot' => $snapshot, 'allocations' => $allocations, 'total' => $total,
            'fingerprint' => hash('sha256', json_encode($this->canonical([$config, $snapshot]), JSON_THROW_ON_ERROR)),
            'starts_at' => $start, 'ends_at' => $end];
    }

    private function local(string $date): Carbon
    {
        return Carbon::parse($date, 'Asia/Ho_Chi_Minh')->utc();
    }

    /** MySQL JSON reorders object keys; compare content while retaining list order. */
    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }

    private function allocation(Carbon $start, Carbon $end, int $quantity): array
    {
        return ['starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'quantity' => $quantity];
    }

    private function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
