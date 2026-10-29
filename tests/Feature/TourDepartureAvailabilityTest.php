<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\TourDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourDepartureAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tour_with_multiple_departures_renders_under_strict_loading_and_only_offers_bookable_dates(): void
    {
        $this->withoutVite();
        $tour = Tour::factory()->create();
        $attributes = [
            'departure_date' => today()->addWeek(),
            'return_date' => today()->addWeeks(2),
            'status' => 'open', 'available_seats' => 10,
        ];
        $available = TourDeparture::factory()->count(2)->for($tour)->create($attributes);
        $inProgress = TourDeparture::factory()->for($tour)->create($attributes);
        $inProgress->forceFill(['operation_status' => 'in_progress'])->save();
        $soldOut = TourDeparture::factory()->for($tour)->create(array_replace($attributes, ['available_seats' => 0]));
        $private = TourDeparture::factory()->for($tour)->create(array_replace($attributes, ['is_private' => true]));
        $past = TourDeparture::factory()->for($tour)->create(array_replace($attributes, ['departure_date' => today()->subDay()]));

        $response = $this->get(route('tours.show', $tour))->assertOk();
        $response->assertViewHas('tour', function (Tour $rendered) use ($available) {
            $this->assertSame($available->modelKeys(), $rendered->departures->modelKeys());
            foreach ($rendered->departures as $departure) {
                $this->assertTrue($departure->relationLoaded('tour'));
                $this->assertSame($rendered, $departure->tour);
            }

            return true;
        });
        foreach ($available as $departure) {
            $response->assertSee(route('bookings.create', $departure), false);
        }
        foreach ([$inProgress, $soldOut, $private, $past] as $departure) {
            $response->assertDontSee(route('bookings.create', $departure), false);
        }
        $tour->update(['is_active' => false]);
        $this->get(route('tours.show', $tour))->assertNotFound();
    }
}
