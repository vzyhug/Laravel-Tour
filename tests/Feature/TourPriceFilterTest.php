<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourPriceFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_reversed_prices_show_an_error_and_preserve_values_and_other_filters(): void
    {
        $this->withoutVite();
        $province = Province::factory()->create(['region' => 'Bắc']);
        Tour::factory()->create(['province_id' => $province->getKey(), 'title' => 'Tour thử giá']);
        $filters = ['province' => $province->getKey(), 'q' => 'Tour', 'sort' => 'price_asc'];
        $response = $this->get(route('tours.index', [...$filters, 'price_min' => '5.000.000', 'price_max' => '111.111', 'page' => 2]))
            ->assertRedirect(route('tours.index', $filters));
        $this->withCookie(config('session.cookie'), app('session.store')->getId());
        $this->followRedirects($response)->assertOk()
            ->assertSee('Giá đến phải lớn hơn hoặc bằng giá từ.')
            ->assertSee('value="5.000.000"', false)->assertSee('value="111.111"', false)
            ->assertViewHas('selectedProvince', fn ($selected) => $selected->is($province));
    }

    public function test_invalid_prices_are_rejected_on_the_server(): void
    {
        foreach ([
            [['price_min' => '5.000.000', 'price_max' => '111.111'], 'price_max'],
            [['price_min' => '-500000'], 'price_min'],
            [['price_max' => '-1'], 'price_max'],
            [['price_min' => 'abc'], 'price_min'],
            [['price_max' => ['1000000']], 'price_max'],
            [['price_min' => '99999999999999'], 'price_min'],
        ] as [$filters, $error]) {
            $this->getJson(route('tours.index', $filters))->assertUnprocessable()->assertJsonValidationErrors($error);
        }
    }

    public function test_valid_equal_and_one_sided_ranges_filter_inclusively(): void
    {
        $this->withoutVite();
        $province = Province::factory()->create();
        foreach ([0, 1000000, 2000000, 5000000] as $price) {
            Tour::factory()->create(['province_id' => $province->getKey(), 'min_price' => $price]);
        }
        foreach ([
            [['price_min' => '1.000.000', 'price_max' => '2.000.000'], [1000000, 2000000]],
            [['price_min' => '2.000.000', 'price_max' => '2.000.000'], [2000000]],
            [['price_min' => '2.000.000'], [2000000, 5000000]],
            [['price_max' => '1,000,000'], [0, 1000000]],
            [['price_min' => '0', 'price_max' => '0'], [0]],
        ] as [$filters, $expected]) {
            $this->get(route('tours.index', [...$filters, 'province' => $province->getKey(), 'sort' => 'price_asc']))->assertOk()
                ->assertViewHas('tours', fn ($tours) => $tours->pluck('min_price')->map(fn ($value) => (int) $value)->all() === $expected);
        }
    }
}
