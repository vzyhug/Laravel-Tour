<?php

namespace Tests\Feature;

use App\Actions\CreateBooking;
use App\Models\Bill;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Province;
use App\Models\Review;
use App\Models\Role;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use App\Models\TourImage;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_builds_complete_bookable_tours_without_network_access(): void
    {
        Http::preventStrayRequests();
        $this->seed();

        foreach (['roles' => 3, 'employees' => 11, 'customers' => 24, 'tour_categories' => 5, 'provinces' => 26, 'tours' => 30, 'images_tour' => 60, 'tour_schedules' => 83, 'tour_departures' => 120, 'bookings' => 120, 'booking_passengers' => 300, 'payments' => 60, 'bill' => 60, 'review' => 30] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        foreach (Tour::with(['schedules', 'images', 'departures', 'reviews'])->get() as $tour) {
            $this->assertCount($tour->duration_days, $tour->schedules);
            $this->assertEquals(range(1, $tour->duration_days), $tour->schedules->pluck('day_number')->all());
            $this->assertSame($tour->duration_days - 1, $tour->duration_nights);
            $this->assertCount(1, $tour->images->where('is_thumbnail', true));
            $this->assertCount(3, $tour->departures->filter(fn ($d) => $d->departure_date->isFuture()));
            $this->assertCount(1, $tour->reviews);
        }
        $this->assertTrue(Hash::check('Customer@123', Customer::where('email', 'demo01@vietgo.test')->firstOrFail()->password_hash));
    }

    public function test_booking_amounts_passengers_inventory_and_historic_dates_are_consistent(): void
    {
        $this->seed();
        foreach (Booking::with(['departure', 'passengers', 'payment', 'bill'])->get() as $booking) {
            $expected = $booking->total_adults * $booking->departure->adult_price + $booking->total_children * $booking->departure->child_price;
            $this->assertEquals($expected, $booking->total_amount);
            $this->assertCount($booking->total_adults, $booking->passengers->where('passenger_type', 'adult'));
            $this->assertCount($booking->total_children, $booking->passengers->where('passenger_type', 'child'));
            $this->assertTrue($booking->booking_date->lessThanOrEqualTo(now()));
            $this->assertTrue($booking->booking_date->lessThan($booking->departure->departure_date));
            if (in_array($booking->status, ['confirmed', 'completed'], true)) {
                $this->assertEquals($booking->total_amount, $booking->payment->amount);
                $this->assertEquals($booking->total_amount, $booking->bill->final_amount);
                $this->assertTrue($booking->booking_date->equalTo($booking->payment->payment_date));
                $this->assertTrue($booking->booking_date->equalTo($booking->bill->bill_date));
            } else {
                $this->assertNull($booking->payment);
                $this->assertNull($booking->bill);
            }
            if ($booking->status === 'completed') {
                $this->assertTrue($booking->departure->return_date->isPast());
                $review = Review::where('tour_id', $booking->departure->tour_id)->where('customer_id', $booking->customer_id)->firstOrFail();
                $this->assertTrue($review->created_at->greaterThan($booking->departure->return_date));
            }
        }
        foreach (TourDeparture::with(['bookings', 'tour'])->get() as $departure) {
            $reserved = $departure->bookings->where('status', '!=', 'cancelled')->sum(fn ($b) => $b->total_adults + $b->total_children);
            $this->assertSame($departure->max_seats - $reserved, $departure->available_seats);
            $this->assertEquals($departure->departure_date->copy()->addDays($departure->tour->duration_days - 1), $departure->return_date);
        }
    }

    public function test_reseeding_preserves_existing_booking_ids_passengers_payments_and_seats(): void
    {
        $this->seed();
        $departure = TourDeparture::whereDate('departure_date', '>', today())->firstOrFail();
        $customer = Customer::firstOrFail();
        $extra = app(CreateBooking::class)->handle($customer->getKey(), [
            'departure_id' => $departure->getKey(),
            'passengers' => [['full_name' => 'Khách bổ sung', 'passenger_type' => 'adult', 'id_card' => '001234567890']],
        ]);
        $customer->update(['phone' => '0912345678']);
        $snapshot = $this->snapshot();

        $this->seed();

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertDatabaseHas('bookings', ['booking_id' => $extra->getKey(), 'booking_code' => $extra->booking_code]);
        $this->assertDatabaseHas('customers', ['customer_id' => $customer->getKey(), 'phone' => '0912345678']);
    }

    public function test_all_domain_factories_can_create_standalone_records(): void
    {
        foreach ([Role::class, Employee::class, Customer::class, Province::class, TourCategory::class, Tour::class, TourImage::class, TourSchedule::class, TourDeparture::class, Booking::class, BookingPassenger::class, Payment::class, Bill::class, Review::class] as $model) {
            $record = $model::factory()->create();
            $this->assertTrue($record->exists, $model);
            $this->assertNotNull($record->getKey(), $model);
        }
    }

    public function test_factory_overrides_keep_dates_prices_and_passenger_counts_consistent(): void
    {
        $tour = Tour::factory()->create(['duration_days' => 4, 'duration_nights' => 3, 'min_price' => 3000000]);
        $departure = TourDeparture::factory()->for($tour, 'tour')->create(['departure_date' => '2027-03-10', 'adult_price' => 3500000, 'max_seats' => 20]);
        $this->assertSame('2027-03-13', $departure->return_date->toDateString());
        $this->assertSame('2450000.00', $departure->child_price);
        $this->assertSame(20, $departure->available_seats);
        $this->assertSame('Guide', $departure->guide->role->role_name);
        $booking = Booking::factory()->for($departure, 'departure')->withPassengers()->create(['total_adults' => 2, 'total_children' => 1]);
        $this->assertSame('9450000.00', $booking->total_amount);
        $this->assertCount(2, $booking->passengers->where('passenger_type', 'adult'));
        $this->assertCount(1, $booking->passengers->where('passenger_type', 'child'));
        $this->assertNull($booking->passengers->firstWhere('passenger_type', 'child')->id_card);
        $this->assertSame($booking->total_amount, Payment::factory()->for($booking, 'booking')->create()->amount);
        $this->assertSame($booking->total_amount, Bill::factory()->for($booking, 'booking')->create()->final_amount);
        $this->assertSame(0, TourDeparture::factory()->full()->create()->available_seats);
        $this->assertSame('cancelled', TourDeparture::factory()->cancelled()->create()->status);
        $completed = Booking::factory()->completed()->create();
        $this->assertTrue($completed->departure->return_date->isPast());
        $this->assertTrue($completed->booking_date->lessThan($completed->departure->departure_date));
    }

    public function test_downloaded_photos_are_valid_local_images_with_complete_attribution(): void
    {
        $images = json_decode(file_get_contents(database_path('data/tour-images.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(60, $images);
        foreach ($images as $image) {
            $path = public_path(ltrim($image['path'], '/'));
            $this->assertFileExists($path);
            $this->assertSame($image['sha256'], hash_file('sha256', $path));
            $size = getimagesize($path);
            $this->assertContains($size['mime'], ['image/jpeg', 'image/png']);
            $this->assertGreaterThanOrEqual(290, min($size[0], $size[1]));
            $this->assertNotEmpty($image['author']);
            $this->assertNotEmpty($image['license']);
            $this->assertStringStartsWith('https://commons.wikimedia.org/wiki/File:', $image['source_page']);
            $this->assertStringStartsWith('/images/tours/demo/', $image['path']);
        }
    }

    public function test_seeded_guides_have_no_overlapping_departures(): void
    {
        $this->seed();
        foreach (Employee::whereHas('role', fn ($q) => $q->where('role_name', 'Guide'))->get() as $guide) {
            $departures = TourDeparture::where('guide_id', $guide->getKey())->orderBy('departure_date')->get();
            for ($index = 1; $index < $departures->count(); $index++) {
                $this->assertTrue($departures[$index - 1]->return_date->lessThan($departures[$index]->departure_date));
            }
        }
    }

    public function test_seeded_tours_and_customer_invoice_render_successfully(): void
    {
        $this->withoutVite();
        $this->seed();
        $this->get('/')->assertOk()->assertSee('Nguồn ảnh địa danh');
        $this->get(route('tours.index'))->assertOk();
        foreach (Tour::all() as $tour) {
            $this->get(route('tours.show', $tour))->assertOk()
                ->assertSee($tour->title)
                ->assertSee('/images/tours/demo/', false);
        }
        $booking = Booking::where('status', 'confirmed')->firstOrFail();
        $this->withSession(['customer_id' => $booking->customer_id])
            ->get(route('customer.bookings.show', $booking))->assertOk()->assertSee($booking->booking_code);
        $this->withSession(['customer_id' => $booking->customer_id])
            ->get(route('customer.bookings.invoice', $booking))->assertOk()->assertSee($booking->bill->bill_number);
    }

    private function snapshot(): array
    {
        $tables = ['roles', 'employees', 'customers', 'provinces', 'tour_categories', 'tours', 'images_tour', 'tour_schedules', 'tour_departures', 'bookings', 'booking_passengers', 'payments', 'bill', 'review'];
        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = hash('sha256', DB::table($table)->orderBy(array_key_first((array) DB::table($table)->first()))->get()->toJson());
        }

        return $snapshot;
    }
}
