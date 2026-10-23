<?php

namespace Database\Factories;

use App\Models\ServiceInventoryDay;
use App\Models\ServiceResource;
use App\Models\TravelService;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceResourceFactory extends Factory
{
    protected $model = ServiceResource::class;

    public function definition(): array
    {
        return ['product_id' => TravelService::factory(), 'name' => 'Quỹ tồn demo '.fake()->unique()->numerify('######'),
            'kind' => 'room_pool', 'capacity' => 5, 'buffer_minutes' => 0, 'attributes' => [], 'is_active' => true];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ServiceResource $resource) {
            $rows = [];
            for ($i = 0; $i <= 60; $i++) {
                $rows[] = ['resource_id' => $resource->id, 'day' => today('Asia/Ho_Chi_Minh')->addDays($i)->toDateString(), 'capacity' => $resource->capacity, 'price_adjustment' => 0, 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()];
            }
            ServiceInventoryDay::insertOrIgnore($rows);
        });
    }

    public function bus(): static
    {
        return $this->state(['product_id' => TravelService::factory()->bus(), 'kind' => 'bus', 'capacity' => 4, 'attributes' => ['seats' => ['A1', 'A2', 'B1', 'B2']]]);
    }

    public function rental(): static
    {
        return $this->state(['product_id' => TravelService::factory()->rental(), 'kind' => 'vehicle', 'capacity' => 1, 'buffer_minutes' => 60]);
    }

    public function transfer(): static
    {
        return $this->state(['product_id' => TravelService::factory()->transfer(), 'kind' => 'vehicle_pool', 'capacity' => 2, 'buffer_minutes' => 30]);
    }

    public function activity(): static
    {
        return $this->state(['product_id' => TravelService::factory()->activity(), 'kind' => 'ticket_pool', 'capacity' => 20]);
    }
}
