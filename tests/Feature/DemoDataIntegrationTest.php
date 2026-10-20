<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_use_the_shared_login(): void
    {
        $this->seed();
        foreach ([
            ['admin', 'Admin@123', 'Admin'],
            ['sale_linh', 'Sale@123', 'Sale'],
            ['guide', 'Guide@123', 'Guide'],
        ] as [$login, $password, $role]) {
            $this->flushSession();
            $this->post(route('login.store'), compact('login', 'password'))
                ->assertRedirect(route('admin.dashboard'))->assertSessionHas('employee_role', $role);
        }
        $this->flushSession();
        $customer = Customer::where('email', 'demo01@vietgo.test')->firstOrFail();
        $this->post(route('login.store'), ['login' => $customer->email, 'password' => 'Customer@123'])
            ->assertRedirect(route('home'))->assertSessionHas('customer_id', $customer->getKey());
    }

    public function test_seeded_customer_can_save_and_remove_a_favorite_tour(): void
    {
        $this->withoutVite();
        $this->seed();
        $customer = Customer::where('email', 'demo01@vietgo.test')->firstOrFail();
        $tour = Tour::where('title', 'Cao Bằng – Bản Giốc – Pác Bó – Động Ngườm Ngao')->firstOrFail();
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->post(route('favorites.toggle', $tour))->assertRedirect();
        $this->assertDatabaseHas('favorites', ['customer_id' => $customer->getKey(), 'tour_id' => $tour->getKey()]);
        $this->assertTrue($customer->favoriteTours()->where('tours.tour_id', $tour->getKey())->exists());
        $this->get(route('favorites.index'))->assertOk()->assertSee($tour->title);
        $this->get(route('home'))->assertOk()->assertSee('Nguồn ảnh địa danh')->assertSee('Yêu thích');
        $this->post(route('favorites.toggle', $tour))->assertRedirect();
        $this->assertDatabaseMissing('favorites', ['customer_id' => $customer->getKey(), 'tour_id' => $tour->getKey()]);
    }

    public function test_new_price_and_duration_filters_work_with_the_demo_catalogue(): void
    {
        $this->withoutVite();
        $this->seed();
        $this->get(route('tours.index', ['price_min' => '1.500.000', 'price_max' => '2.500.000', 'days' => 2, 'region' => 'Nam', 'sort' => 'price_desc']))
            ->assertOk()->assertViewHas('tours', function ($tours) {
                $this->assertNotEmpty($tours->items());
                foreach ($tours as $tour) {
                    $this->assertGreaterThanOrEqual(1500000, (float) $tour->min_price);
                    $this->assertLessThanOrEqual(2500000, (float) $tour->min_price);
                    $this->assertLessThanOrEqual(2, $tour->duration_days);
                    $this->assertSame('Nam', $tour->province->region);
                }
                $prices = $tours->getCollection()->pluck('min_price')->map(fn ($price) => (float) $price)->all();
                $sorted = $prices;
                rsort($sorted);
                $this->assertSame($sorted, $prices);

                return true;
            });
    }
}
