<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\ItineraryConsultation;
use App\Models\TourDeparture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptItineraryQuote
{
    public function handle(int $customerId, ItineraryConsultation $consultation, array $input): Booking
    {
        return DB::transaction(function () use ($customerId, $consultation, $input) {
            $quote = ItineraryConsultation::query()->lockForUpdate()->findOrFail($consultation->getKey());
            $quote->load('itinerary.tour');
            abort_unless($quote->itinerary->customer_id === $customerId, 403);

            // A browser retry must never create a second private departure or charge.
            if ($existing = $quote->booking()->first()) {
                return $existing;
            }

            $data = Validator::make($input, [
                'version' => ['required', 'integer', 'min:1'],
                'terms_accepted' => ['required', 'accepted'],
                'passengers' => ['required', 'array', 'min:1', 'max:100'],
                'passengers.*' => ['array'],
                'passengers.*.full_name' => ['required', 'string', 'max:100'],
                'passengers.*.id_card' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z]{8,20}$/'],
                'passengers.*.date_of_birth' => ['nullable', 'date', 'before:today'],
                'pickup_type' => ['nullable', 'in:self,door'],
                'pickup_address' => ['nullable', 'required_if:pickup_type,door', 'string', 'max:255'],
                'pickup_note' => ['nullable', 'string', 'max:255'],
            ], [
                'terms_accepted.accepted' => 'Vui lòng đồng ý với lịch trình và điều kiện báo giá.',
                'passengers.*.id_card.regex' => 'CCCD hoặc Hộ chiếu phải từ 8 đến 20 ký tự chữ và số.',
            ])->validate();

            abort_if($quote->version !== (int) $data['version'], 409, 'Báo giá đã thay đổi. Hãy tải lại và xem báo giá mới trước khi chấp nhận.');
            abort_unless($quote->canBeAccepted(), 422, 'Báo giá không còn hiệu lực hoặc yêu cầu đã kết thúc.');
            $tour = $quote->itinerary->tour;
            abort_unless($tour->is_active, 422, 'Tour hiện không còn nhận đặt chỗ.');

            $passengerCount = (int) $quote->adults + (int) $quote->children;
            if (count($data['passengers']) !== $passengerCount) {
                throw ValidationException::withMessages(['passengers' => 'Danh sách hành khách phải có đúng '.$passengerCount.' người theo báo giá.']);
            }

            $departureTime = $quote->departureTime();
            $departureAt = $quote->travel_date->copy()->setTimeFromTimeString($departureTime);
            abort_if($departureAt->lte(now()), 422, 'Đã quá giờ khởi hành của phương án này. Vui lòng yêu cầu báo giá mới.');

            $durationDays = max(1, (int) ($quote->snapshot['duration_days']
                ?? collect($quote->snapshot['days'] ?? [])->max('number')));
            $durationNights = (int) ($quote->snapshot['duration_nights'] ?? $durationDays - 1);
            $returnDate = $quote->travel_date->copy()->addDays($durationDays - 1);
            $holdExpiresAt = now()->addMinutes(max(1, (int) config('booking.hold_minutes', 30)));
            if ($holdExpiresAt->gt($quote->valid_until->copy()->endOfDay())) {
                $holdExpiresAt = $quote->valid_until->copy()->endOfDay();
            }
            if ($holdExpiresAt->gt($departureAt)) {
                $holdExpiresAt = $departureAt;
            }

            // A separate departure preserves the shared Booking/Payment lifecycle without
            // selling the customised itinerary or its group price through public checkout.
            $departure = TourDeparture::create([
                'tour_id' => $tour->getKey(), 'is_private' => true,
                'departure_date' => $quote->travel_date, 'return_date' => $returnDate,
                'departure_time' => $departureTime,
                'max_seats' => $passengerCount, 'available_seats' => 0,
                'adult_price' => 0, 'child_price' => 0, 'status' => 'full',
            ]);

            $snapshot = [
                ...$quote->snapshot,
                'consultation_id' => $quote->getKey(), 'consultation_version' => $quote->version,
                'itinerary_version' => $quote->itinerary_version,
                'travel_date' => $quote->travel_date->toDateString(), 'return_date' => $returnDate->toDateString(),
                'departure_time' => $departureTime, 'duration_days' => $durationDays, 'duration_nights' => $durationNights,
                'adults' => (int) $quote->adults, 'children' => (int) $quote->children,
                'quoted_total' => $quote->quoted_total, 'currency' => 'VND',
                'reply' => $quote->reply, 'valid_until' => $quote->valid_until->toDateString(),
                'accepted_at' => now()->toIso8601String(),
                'contact_name' => $quote->contact_name, 'contact_phone' => $quote->contact_phone,
                'contact_email' => $quote->contact_email,
                'pickup_point' => array_key_exists('pickup_point', $quote->snapshot)
                    ? $quote->snapshot['pickup_point'] : $tour->pickup_point,
            ];

            $booking = Booking::create([
                'booking_code' => 'BK'.now()->format('ymd').Str::upper(Str::random(6)),
                'customer_id' => $customerId, 'departure_id' => $departure->getKey(),
                'consultation_id' => $quote->getKey(), 'quote_snapshot' => $snapshot,
                'total_adults' => $quote->adults, 'total_children' => $quote->children,
                'total_amount' => $quote->quoted_total, 'discount_amount' => 0,
                'status' => 'pending', 'hold_expires_at' => $holdExpiresAt,
                'created_by_employee' => $quote->employee_id,
                'pickup_type' => $data['pickup_type'] ?? 'self',
                'pickup_address' => ($data['pickup_type'] ?? 'self') === 'door' ? $data['pickup_address'] : null,
                'pickup_note' => $data['pickup_note'] ?? null,
            ]);

            foreach (array_values($data['passengers']) as $index => $passenger) {
                $booking->passengers()->create([
                    'full_name' => $passenger['full_name'], 'id_card' => $passenger['id_card'],
                    'date_of_birth' => $passenger['date_of_birth'] ?? null,
                    'passenger_type' => $index < (int) $quote->adults ? 'adult' : 'child',
                ]);
            }

            $quote->update(['status' => 'accepted', 'accepted_at' => now(), 'version' => $quote->version + 1]);

            return $booking;
        });
    }
}
