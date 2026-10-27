<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerItinerary;
use App\Models\Employee;
use App\Models\ItineraryConsultation;
use App\Models\ItineraryPlace;
use App\Models\Tour;
use App\Services\TourItineraryService;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\TourItinerarySeeder;
use Database\Seeders\TourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TourItineraryTest extends TestCase
{
    use RefreshDatabase;

    private function sample(): array
    {
        $this->withoutVite();
        $tour = Tour::factory()->create(['title' => 'Tour lịch trình kiểm thử', 'duration_days' => 2]);
        $places = [];
        foreach ([1, 2] as $day) {
            $schedule = $tour->schedules()->create(['day_number' => $day, 'title' => 'Ngày '.$day, 'activities' => 'Chương trình gốc '.$day]);
            $place = ItineraryPlace::create(['slug' => 'place-'.$day, 'name' => 'Điểm '.$day,
                'source_url' => 'https://vietnam.travel/', 'verified_on' => today()]);
            $schedule->stops()->create(['position' => 1, 'place_id' => $place->id]);
            $places[] = $place;
        }
        $customer = Customer::factory()->create();

        return [$tour, $customer, $places];
    }

    private function items(array $places): array
    {
        return [['place_id' => $places[0]->id, 'day_number' => 2, 'title' => 'Client không được đổi tên điểm thật', 'start_time' => '09:00', 'duration_minutes' => 60, 'notes' => 'Đi cùng gia đình'],
            ['place_id' => null, 'day_number' => 1, 'title' => 'Nghỉ ngơi', 'start_time' => null, 'duration_minutes' => null, 'notes' => '']];
    }

    private function inquiry(int $version = 1): array
    {
        return ['version' => $version, 'request_key' => (string) Str::uuid(), 'travel_date' => today()->addMonth()->toDateString(),
            'adults' => 2, 'children' => 1, 'contact_name' => 'Khách kiểm thử', 'contact_phone' => '0901234567',
            'contact_email' => 'customer@example.test', 'message' => 'Nhờ tư vấn cung đường và dịch vụ.'];
    }

    public function test_all_catalog_tours_have_complete_day_links_and_checksummed_photographs(): void
    {
        $this->withoutVite();
        $this->seed([DemoAccountSeeder::class, TourSeeder::class]);
        $before = Tour::with('schedules')->get()->mapWithKeys(fn ($tour) => [$tour->tour_id => $tour->schedules->toArray()])->all();
        $this->seed(TourItinerarySeeder::class);
        $this->assertDatabaseCount('itinerary_places', 112);
        $this->assertDatabaseCount('itinerary_place_images', 112);
        $this->assertDatabaseCount('tour_itinerary_stops', 120);
        $this->artisan('itineraries:audit', ['--json' => true])->assertSuccessful();
        foreach (Tour::with('schedules')->get() as $tour) {
            $this->assertSame($before[$tour->tour_id], $tour->schedules->toArray());
            $this->get(route('tours.show', $tour))->assertOk()->assertSee('Tùy chỉnh lịch');
        }
        $this->seed(TourItinerarySeeder::class);
        $this->assertDatabaseCount('itinerary_place_images', 112);
        $this->assertDatabaseCount('tour_itinerary_stops', 120);
    }

    public function test_customer_saves_only_their_plan_without_changing_the_original(): void
    {
        [$tour, $customer, $places] = $this->sample();
        $this->get('/tour/'.$tour->getKey())->assertRedirect(route('tours.show', $tour));
        $this->withSession(['customer_id' => $customer->getKey()])->putJson(route('tour-itinerary.update', $tour),
            ['version' => 0, 'items' => $this->items($places), 'customer_id' => 999])->assertOk()->assertJsonPath('version', 1);
        $plan = CustomerItinerary::with('items')->firstOrFail();
        $this->assertSame($customer->getKey(), $plan->customer_id);
        $this->assertSame('Điểm 1', $plan->items->last()->title);
        $this->assertSame('Chương trình gốc 1', $tour->schedules()->first()->activities);
        $this->get(route('tours.show', $tour))->assertOk()->assertSee('data-personal-itinerary', false);
        $other = Customer::factory()->create();
        $payload = app(TourItineraryService::class)->payload($tour, $other->getKey());
        $this->assertSame(0, $payload['version']);
        $this->assertSame([], $payload['consultations']);
        $this->assertSame(1, $payload['items'][0]['day_number']);
    }

    public function test_cross_tour_places_invalid_days_overlaps_and_stale_versions_are_rejected(): void
    {
        [$tour, $customer, $places] = $this->sample();
        $this->withSession(['customer_id' => $customer->getKey()]);
        $url = route('tour-itinerary.update', $tour);
        $this->putJson($url, ['version' => 0, 'items' => [['place_id' => 99999, 'day_number' => 1, 'title' => 'Fake']]])->assertUnprocessable();
        $items = $this->items($places);
        $items[0]['day_number'] = 3;
        $this->putJson($url, ['version' => 0, 'items' => $items])->assertUnprocessable();
        $items = $this->items($places);
        $items[1] = [...$items[1], 'day_number' => 2, 'start_time' => '09:30', 'duration_minutes' => 30];
        $this->putJson($url, ['version' => 0, 'items' => $items])->assertUnprocessable();
        $this->putJson($url, ['version' => 0, 'items' => $this->items($places)])->assertOk();
        $this->putJson($url, ['version' => 0, 'items' => $this->items($places)])->assertConflict();
        $this->assertDatabaseCount('customer_itineraries', 1);
        $this->assertSame(1, CustomerItinerary::first()->version);
    }

    public function test_consultation_is_idempotent_and_keeps_an_immutable_sent_snapshot(): void
    {
        [$tour, $customer, $places] = $this->sample();
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->putJson(route('tour-itinerary.update', $tour), ['version' => 0, 'items' => $this->items($places)])->assertOk();
        $data = $this->inquiry();
        $this->postJson(route('tour-itinerary.consult', $tour), $data)->assertOk();
        $this->postJson(route('tour-itinerary.consult', $tour), $data)->assertOk();
        $this->assertDatabaseCount('itinerary_consultations', 1);
        $request = ItineraryConsultation::firstOrFail();
        $snapshot = $request->snapshot;
        $items = $this->items($places);
        $items[0]['notes'] = 'Nội dung sửa sau khi gửi';
        $this->putJson(route('tour-itinerary.update', $tour), ['version' => 1, 'items' => $items])->assertOk();
        $this->assertSame($snapshot, $request->fresh()->snapshot);
        $this->get(route('itinerary-consultations.show', $request))->assertOk()->assertDontSee('Nội dung sửa sau khi gửi');
        $this->get(route('tour-itinerary.index'))->assertOk()->assertSee('Yêu cầu #'.$request->id);
        $this->withSession(['customer_id' => Customer::factory()->create()->getKey()])
            ->get(route('itinerary-consultations.show', $request))->assertForbidden();
    }

    public function test_staff_quotes_sent_plan_and_customer_can_read_quote_without_creating_a_booking(): void
    {
        [$tour, $customer, $places] = $this->sample();
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->putJson(route('tour-itinerary.update', $tour), ['version' => 0, 'items' => $this->items($places)])->assertOk();
        $this->postJson(route('tour-itinerary.consult', $tour), $this->inquiry())->assertOk();
        $request = ItineraryConsultation::firstOrFail();
        $staff = Employee::factory()->admin()->create();
        $this->withSession(['employee_id' => $staff->getKey(), 'employee_role' => 'Admin']);
        $this->get(route('admin.itinerary-consultations.index'))->assertOk();
        $this->get(route('admin.itinerary-consultations.show', $request))->assertOk();
        $quote = ['version' => 1, 'status' => 'quoted', 'reply' => 'Giá gồm xe và hướng dẫn; chưa gồm vé tham quan.',
            'quoted_total' => 5500000, 'valid_until' => today()->addWeek()->toDateString()];
        $this->patch(route('admin.itinerary-consultations.update', $request), $quote)->assertRedirect();
        $this->assertSame(5500000, $request->fresh()->quoted_total);
        $this->get(route('itinerary-consultations.show', $request))->assertOk()->assertSee('5.500.000')->assertSee('tổng cả nhóm');
        $this->patchJson(route('admin.itinerary-consultations.update', $request), $quote)->assertConflict();
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->withSession(['employee_role' => 'Guide'])->get(route('admin.itinerary-consultations.index'))->assertForbidden();
    }

    public function test_guest_cannot_save_and_closed_request_cannot_be_rewritten(): void
    {
        [$tour, $customer, $places] = $this->sample();
        $this->putJson(route('tour-itinerary.update', $tour), ['version' => 0, 'items' => $this->items($places)])->assertRedirect(route('login'));
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->putJson(route('tour-itinerary.update', $tour), ['version' => 0, 'items' => $this->items($places)])->assertOk();
        $this->postJson(route('tour-itinerary.consult', $tour), $this->inquiry())->assertOk();
        $request = ItineraryConsultation::firstOrFail();
        $staff = Employee::factory()->admin()->create();
        $this->withSession(['employee_id' => $staff->getKey(), 'employee_role' => 'Sale']);
        $this->patchJson(route('admin.itinerary-consultations.update', $request), ['version' => 1, 'status' => 'quoted', 'reply' => 'Thiếu giá'])->assertUnprocessable();
        $this->patch(route('admin.itinerary-consultations.update', $request), ['version' => 1, 'status' => 'closed', 'reply' => 'Khách chưa xác định được ngày đi.'])->assertRedirect();
        $this->patchJson(route('admin.itinerary-consultations.update', $request), ['version' => 2, 'status' => 'closed', 'reply' => 'Sửa lại'])->assertUnprocessable();
    }
}
