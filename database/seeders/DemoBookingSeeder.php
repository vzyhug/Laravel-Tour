<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Tour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoBookingSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = json_decode(file_get_contents(database_path('data/tours.json')), true, 512, JSON_THROW_ON_ERROR);
        $customers = Customer::whereIn('email', array_map(fn ($i) => sprintf('demo%02d@vietgo.test', $i), range(1, 24)))
            ->orderBy('email')->get();
        if ($customers->count() !== 24) {
            throw new RuntimeException('Hãy chạy DemoAccountSeeder và TourSeeder trước DemoBookingSeeder.');
        }

        DB::transaction(function () use ($catalogue, $customers) {
            foreach ($catalogue as $index => $data) {
                $tour = Tour::where('title', $data['title'])->firstOrFail();
                $future = $tour->departures()->whereDate('departure_date', '>', today())->orderBy('departure_date')->take(3)->get();
                $past = $tour->departures()->whereDate('return_date', '<', today())->orderByDesc('departure_date')->firstOrFail();
                if ($future->count() < 3) {
                    throw new RuntimeException('Thiếu lịch khởi hành: '.$data['title']);
                }
                $examples = [
                    ['C', 'confirmed', $future[0], 2],
                    ['P', 'pending', $future[1], 3],
                    ['X', 'cancelled', $future[2], 1],
                    ['D', 'completed', $past, 3],
                ];

                foreach ($examples as $sampleIndex => [$suffix, $status, $departure, $passengerCount]) {
                    $customer = $customers[($index + $sampleIndex) % $customers->count()];
                    $code = 'DEMO-'.$data['slug'].'-'.$suffix;
                    $booking = Booking::firstOrCreate(['booking_code' => $code], fn () => Booking::factory()->raw([
                        'booking_code' => $code,
                        'customer_id' => $customer->getKey(),
                        'departure_id' => $departure->getKey(),
                        'total_adults' => $passengerCount,
                        'total_children' => 0,
                        'status' => $status,
                    ]));

                    if ($booking->wasRecentlyCreated) {
                        // Set historic dates explicitly; these columns are intentionally not mass assignable.
                        $booking->forceFill(['booking_date' => $departure->departure_date->copy()->subDays(5)->min(now())])->save();
                        BookingPassenger::factory()->count($passengerCount)->create(['booking_id' => $booking->getKey()]);
                        $booking->passengers()->orderBy('passenger_id')->first()->update(['full_name' => $customer->full_name]);

                        if ($status !== 'cancelled') {
                            $locked = $departure->newQuery()->lockForUpdate()->findOrFail($departure->getKey());
                            $seats = $passengerCount;
                            if ($locked->available_seats < $seats) {
                                throw new RuntimeException('Không đủ chỗ cho đơn mẫu '.$code);
                            }
                            $locked->available_seats -= $seats;
                            $locked->status = $locked->available_seats === 0 ? 'full' : 'open';
                            $locked->save();
                        }
                    }

                    if (in_array($booking->status, ['confirmed', 'completed'], true)) {
                        $payment = Payment::firstOrCreate(['booking_id' => $booking->getKey()], fn () => Payment::factory()->raw([
                            'booking_id' => $booking->getKey(),
                            'transaction_code' => 'TXN-'.$code,
                            'payment_method' => ['bank_transfer', 'momo', 'card', 'cash'][$index % 4],
                        ]));
                        if ($payment->wasRecentlyCreated) {
                            $payment->forceFill(['payment_date' => $booking->booking_date])->save();
                        }
                        $bill = Bill::firstOrCreate(['bill_number' => 'INV-'.$code], fn () => Bill::factory()->raw([
                            'bill_number' => 'INV-'.$code,
                            'booking_id' => $booking->getKey(),
                        ]));
                        if ($bill->wasRecentlyCreated) {
                            $bill->forceFill(['bill_date' => $booking->booking_date])->save();
                        }
                    }

                    if ($booking->status === 'completed') {
                        $review = Review::firstOrCreate([
                            'customer_id' => $booking->customer_id,
                            'tour_id' => $booking->departure->tour_id,
                        ], fn () => Review::factory()->raw([
                            'customer_id' => $booking->customer_id,
                            'tour_id' => $booking->departure->tour_id,
                            'rating' => $index % 3 === 0 ? 4 : 5,
                        ]));
                        if ($review->wasRecentlyCreated) {
                            $review->forceFill(['created_at' => $booking->departure->return_date->copy()->addDay()])->save();
                        }
                    }
                }
            }
        });
    }
}
