<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Destination;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourImage;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\DestinationDirectorySeeder;
use Database\Seeders\DestinationTourSeeder;
use Database\Seeders\TourItinerarySeeder;
use Database\Seeders\TourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DestinationTourTest extends TestCase
{
    use RefreshDatabase;

    private function base(): void
    {
        $this->seed([DemoAccountSeeder::class, TourSeeder::class, TourItinerarySeeder::class, DestinationDirectorySeeder::class]);
        $this->withoutVite();
    }

    private function newTours()
    {
        $titles = array_column(json_decode(file_get_contents(database_path('data/destination-tours.json')), true)['tours'], 'title');

        return Tour::with(['schedules.stops.place.images', 'departures', 'images', 'reviews'])->whereIn('title', $titles)->orderBy('tour_id')->get();
    }

    public function test_new_programs_cover_every_vung_tau_place_and_have_bookable_departures(): void
    {
        $this->seed();
        $this->assertDatabaseCount('tours', 32);
        $this->assertDatabaseCount('tour_schedules', 88);
        $this->assertDatabaseCount('tour_itinerary_stops', 133);
        $this->assertDatabaseCount('tour_departures', 126);
        $this->assertDatabaseCount('images_tour', 64);
        $this->assertDatabaseCount('itinerary_place_images', 335);
        $tours = $this->newTours();
        $this->assertCount(2, $tours);
        $allPlaces = [];
        foreach ($tours as $tour) {
            $this->assertSame($tour->duration_days - 1, $tour->duration_nights);
            $this->assertCount($tour->duration_days, $tour->schedules);
            $this->assertCount(3, $tour->departures);
            $this->assertCount(1, $tour->images->where('is_thumbnail', true));
            $this->assertCount(0, $tour->reviews);
            foreach ($tour->departures as $departure) {
                $this->assertTrue($departure->departure_date->isFuture());
                $this->assertSame('open', $departure->status);
                $this->assertSame(24, $departure->available_seats);
                $this->assertSame($tour->min_price, $departure->adult_price);
                $this->assertTrue($departure->return_date->equalTo($departure->departure_date->copy()->addDays($tour->duration_days - 1)));
                $overlap = TourDeparture::where('guide_id', $departure->guide_id)->whereKeyNot($departure->getKey())->where('status', '!=', 'cancelled')
                    ->whereDate('departure_date', '<=', $departure->return_date)->whereDate('return_date', '>=', $departure->departure_date)->exists();
                $this->assertFalse($overlap);
            }
            foreach ($tour->schedules as $day) {
                $this->assertNotEmpty($day->stops);
                foreach ($day->stops as $stop) {
                    $this->assertNotEmpty($stop->place->images);
                    $allPlaces[] = $stop->place_id;
                }
            }
        }
        $expected = Destination::where('slug', 'vung-tau')->firstOrFail()->places()->pluck('itinerary_places.id')->sort()->values()->all();
        $this->assertSame($expected, collect($allPlaces)->unique()->sort()->values()->all());
        $this->artisan('itineraries:audit', ['--json' => true])->assertSuccessful();
    }

    public function test_reseeding_preserves_base_programs_and_a_booking_on_a_new_tour(): void
    {
        $this->base();
        $base = $this->businessSnapshot();
        $oldIds = Tour::pluck('tour_id')->all();
        $this->seed(DestinationTourSeeder::class);
        foreach ($base as $table => $json) {
            $query = DB::table($table);
            if ($table === 'tour_itinerary_stops') {
                $query->whereIn('schedule_id', DB::table('tour_schedules')->whereIn('tour_id', $oldIds)->pluck('schedule_id'));
            } else {
                $query->whereIn('tour_id', $oldIds);
            }
            $this->assertSame($json, $query->orderBy($this->key($table))->get()->toJson());
        }
        $tour = $this->newTours()->first();
        $departure = $tour->departures()->firstOrFail();
        $customer = Customer::firstOrFail();
        $this->withSession(['customer_id' => $customer->getKey()])->post(route('bookings.store'), [
            'departure_id' => $departure->getKey(), 'pickup_type' => 'self',
            'passengers' => [['full_name' => 'Khách thử tour Vũng Tàu', 'id_card' => '001234567890']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $booking = Booking::where('departure_id', $departure->getKey())->firstOrFail();
        $this->assertSame('pending', $booking->status);
        $this->assertSame(23, $departure->fresh()->available_seats);
        $snapshot = $this->businessSnapshot();
        $this->seed(DestinationTourSeeder::class);
        $this->assertSame($snapshot, $this->businessSnapshot());
        $this->assertDatabaseCount('tours', 32);
        $this->assertDatabaseCount('tour_departures', 126);
        $this->assertSame($booking->getKey(), $booking->fresh()->getKey());
        $this->assertSame(23, $departure->fresh()->available_seats);
    }

    public function test_tours_are_discoverable_from_destination_and_booking_has_no_hotel_selection_yet(): void
    {
        $this->base();
        $this->seed(DestinationTourSeeder::class);
        $customer = Customer::firstOrFail();
        $this->get('/tours?q=Vũng')->assertOk()->assertSee('Vũng Tàu 2N1Đ')->assertSee('Vũng Tàu 3N2Đ');
        $this->get('/destinations/vung-tau')->assertOk()->assertSee('2 tour')->assertSee('Vũng Tàu 3N2Đ');
        foreach ($this->newTours() as $tour) {
            $this->get(route('tours.show', $tour))->assertOk()->assertSee('Đặt chuyến đi')->assertSee('Giá mẫu phục vụ kiểm thử')->assertSee('Chưa áp dụng lựa chọn khách sạn');
            $departure = $tour->departures()->firstOrFail();
            $this->withSession(['customer_id' => $customer->getKey()])->get(route('bookings.create', $departure))->assertOk()
                ->assertSee($tour->title)->assertDontSee('name="hotel_id"', false);
        }
    }

    public function test_explicit_factory_state_resolves_the_new_tour_and_its_real_image(): void
    {
        $tour = Tour::factory()->destinationProgram('vung-tau-bien-di-san-2n1d')->create();
        $image = TourImage::factory()->for($tour)->thumbnail()->create();
        $this->assertSame(2, $tour->duration_days);
        $this->assertSame(1, $tour->duration_nights);
        $this->assertSame('/images/directory/hon-ba.jpg', $image->img_url);
        $this->assertSame(hash_file('sha256', public_path('images/directory/hon-ba.jpg')), $image->sha256);
    }

    public function test_missing_directory_stops_the_seed_before_creating_any_new_tour(): void
    {
        $this->seed([DemoAccountSeeder::class, TourSeeder::class, TourItinerarySeeder::class]);
        try {
            $this->seed(DestinationTourSeeder::class);
            $this->fail('Missing directory must fail');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('DestinationDirectorySeeder', $exception->getMessage());
        }
        $this->assertDatabaseCount('tours', 30);
        $this->assertDatabaseCount('tour_departures', 120);
    }

    private function key(string $table): string
    {
        return ['tours' => 'tour_id', 'tour_schedules' => 'schedule_id', 'tour_itinerary_stops' => 'id', 'tour_departures' => 'departure_id'][$table];
    }

    private function businessSnapshot(): array
    {
        $result = [];
        foreach (['tours', 'tour_schedules', 'tour_itinerary_stops', 'tour_departures'] as $table) {
            $result[$table] = DB::table($table)->orderBy($this->key($table))->get()->toJson();
        }

        return $result;
    }
}
