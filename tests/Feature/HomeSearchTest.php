<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_search_opens_province_results_and_preserves_filters_across_pages(): void
    {
        $this->withoutVite();
        $province = Province::factory()->create(['province_name' => 'Tỉnh tìm kiếm', 'region' => 'Trung']);
        $tours = Tour::factory()->count(10)->create(['province_id' => $province->getKey(), 'title' => 'Hành trình phù hợp']);
        Tour::factory()->create(['title' => 'Tour khác tỉnh']);
        Tour::factory()->create(['province_id' => $province->getKey(), 'title' => 'Tour đã ẩn', 'is_active' => false]);

        $this->get(route('home'))->assertOk()
            ->assertSee('<form action="'.route('tours.index').'" method="GET"', false)
            ->assertSee($province->province_name);
        $filters = ['province' => $province->getKey(), 'q' => 'Hành trình', 'sort' => 'price_asc'];
        $this->get(route('tours.index', $filters))->assertOk()->assertSee('Tour tại '.$province->province_name)
            ->assertDontSee('Tour khác tỉnh')->assertDontSee('Tour đã ẩn')
            ->assertSee('province='.$province->getKey(), false)
            ->assertViewHas('selectedProvince', fn ($selected) => $selected->is($province))
            ->assertViewHas('tours', function ($results) use ($tours) {
                $this->assertSame(10, $results->total());
                $this->assertCount(9, $results->items());
                foreach ($results as $tour) {
                    $this->assertContains($tour->getKey(), $tours->modelKeys());
                }

                return true;
            });
        $this->get(route('tours.index', [...$filters, 'page' => 2]))->assertOk()
            ->assertViewHas('tours', fn ($results) => $results->total() === 10 && count($results->items()) === 1);
        $this->get(route('provinces.tours', $province))->assertOk()
            ->assertSee('<form action="'.route('tours.index').'" method="GET" id="tour-filters"', false)
            ->assertViewHas('selectedProvince', fn ($selected) => $selected->is($province));
    }

    public function test_search_reports_no_matches_and_rejects_invalid_province_filters(): void
    {
        $this->withoutVite();
        $province = Province::factory()->create();
        Tour::factory()->create(['province_id' => $province->getKey(), 'title' => 'Tour đang có']);
        $this->get(route('tours.index', ['province' => $province->getKey(), 'q' => 'khongcoketqua']))
            ->assertOk()->assertSee('0 hành trình')->assertSee('Chưa tìm thấy hành trình phù hợp.')
            ->assertViewHas('selectedProvince', fn ($selected) => $selected->is($province));
        $this->get(route('tours.index', ['province' => '']))->assertOk()
            ->assertViewHas('selectedProvince', null)->assertViewHas('tours', fn ($results) => $results->total() === 1);
        $this->getJson(route('tours.index', ['province' => 999999]))->assertUnprocessable()->assertJsonValidationErrors('province');
        $this->getJson(route('tours.index', ['province' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('province');
    }

    public function test_province_region_conflicts_are_reported_and_corrected_without_losing_other_filters(): void
    {
        $this->withoutVite();
        $province = Province::factory()->create(['province_name' => 'Lào Cai', 'region' => 'Bắc']);
        Tour::factory()->create(['province_id' => $province->getKey(), 'title' => 'Khám phá Sa Pa']);
        $filters = ['province' => $province->getKey(), 'region' => 'Nam', 'q' => 'Sa Pa', 'sort' => 'price_asc', 'page' => 2];
        $corrected = [...$filters, 'region' => 'Bắc', 'page' => 1];
        $response = $this->get(route('tours.index', $filters))->assertRedirect(route('tours.index', $corrected));
        $this->withCookie(config('session.cookie'), app('session.store')->getId());
        $this->followRedirects($response)->assertOk()
            ->assertSee('Lào Cai thuộc miền Bắc.')->assertSee('Đã điều chỉnh vùng miền theo tỉnh đã chọn.')
            ->assertSee('Miền tự theo tỉnh đã chọn.')
            ->assertViewHas('tours', fn ($results) => $results->total() === 1);
        $this->getJson(route('tours.index', $filters))->assertUnprocessable()->assertJsonValidationErrors('region');
        $this->get(route('provinces.tours', ['province' => $province->getKey(), 'region' => 'Nam']))
            ->assertRedirect()->assertSessionHasErrors('region');
    }

    public function test_region_only_and_matching_province_region_searches_still_work(): void
    {
        $this->withoutVite();
        $north = Province::factory()->create(['region' => 'Bắc']);
        $south = Province::factory()->create(['region' => 'Nam']);
        $tour = Tour::factory()->create(['province_id' => $north->getKey()]);
        Tour::factory()->create(['province_id' => $south->getKey()]);
        foreach ([['region' => 'Bắc'], ['province' => $north->getKey()], ['province' => $north->getKey(), 'region' => 'Bắc']] as $filters) {
            $this->get(route('tours.index', $filters))->assertOk()->assertSessionHasNoErrors()
                ->assertViewHas('tours', fn ($results) => $results->total() === 1 && $results->first()->is($tour));
        }
        $this->getJson(route('tours.index', ['region' => 'Không hợp lệ']))->assertUnprocessable()->assertJsonValidationErrors('region');
        $this->getJson(route('tours.index', ['region' => ['Bắc']]))->assertUnprocessable()->assertJsonValidationErrors('region');
    }
}
