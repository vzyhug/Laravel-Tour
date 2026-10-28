<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\ItineraryPlace;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\DestinationDirectorySeeder;
use Database\Seeders\TourItinerarySeeder;
use Database\Seeders\TourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DestinationDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function seedDirectory(): void
    {
        $this->seed([DemoAccountSeeder::class, TourSeeder::class, TourItinerarySeeder::class, DestinationDirectorySeeder::class]);
        $this->withoutVite();
    }

    public function test_seed_reuses_original_places_and_keeps_tour_stops_and_business_records(): void
    {
        $this->seed([DemoAccountSeeder::class, TourSeeder::class, TourItinerarySeeder::class]);
        $original = ItineraryPlace::pluck('id', 'slug')->all();
        $stops = DB::table('tour_itinerary_stops')->orderBy('id')->get()->toJson();
        $this->seed(DestinationDirectorySeeder::class);
        $this->seed(DestinationDirectorySeeder::class);
        $this->assertDatabaseCount('destinations', 31);
        $this->assertDatabaseCount('itinerary_places', 120);
        $this->assertDatabaseCount('itinerary_place_images', 120);
        $this->assertDatabaseCount('hotels', 64);
        $this->assertDatabaseCount('hotel_images', 64);
        $this->assertDatabaseCount('tours', 30);
        $this->assertSame(120, ItineraryPlace::has('destinations')->count());
        $this->assertSame($original, ItineraryPlace::whereIn('slug', array_keys($original))->pluck('id', 'slug')->all());
        $this->assertSame($stops, DB::table('tour_itinerary_stops')->orderBy('id')->get()->toJson());
        $vungTau = Destination::where('slug', 'vung-tau')->firstOrFail();
        $this->assertSame(8, $vungTau->places()->count());
        $this->assertSame(4, $vungTau->hotels()->count());
        $this->assertSame(31, Destination::has('hotels', '>=', 2)->count());
        $this->assertSame(0, Destination::doesntHave('hotels')->count());
    }

    public function test_public_directory_search_filters_pagination_and_details(): void
    {
        $this->seedDirectory();
        $this->get('/destinations?q=Vũng')->assertOk()->assertSee('Vũng Tàu')->assertSee('8 địa danh')->assertSee('4 khách sạn');
        $this->get('/destinations/vung-tau')->assertOk()->assertSee('Tháp Tam Thắng')->assertSee('Pullman Vũng Tàu');
        $this->get('/places?destination=vung-tau&category=museum')->assertOk()->assertSee('2 địa danh')->assertSee('Robert Taylor')->assertDontSee('Tháp Tam Thắng');
        $this->get('/places?destination=hue&category=museum')->assertOk()->assertSee('Bảo tàng Cổ vật Cung đình Huế')->assertSee('1 địa danh');
        $this->get('/hotels?destination=hue')->assertOk()->assertSee('2 khách sạn')->assertSee('Saigon Morin')->assertDontSee('Pullman Vũng Tàu');
        $this->get('/hotels?q=Thi%20Sách')->assertOk()->assertSee('2 khách sạn')->assertSee('Pullman Vũng Tàu')->assertSee('The Cap Hotel');
        $this->get('/places?page=2&destination=vung-tau')->assertOk()->assertSee('Chưa tìm thấy kết quả phù hợp');
        $this->get('/hotels?destination=ba-be')->assertOk()->assertSee('2 khách sạn')->assertSee('Ba Be Lakeside Bungalow')->assertSee('Ba Be Jungle Houses');
        $this->get('/hotels?destination=mang-den')->assertOk()->assertSee('2 khách sạn')->assertSee('Toong Dam')->assertSee('Đắk Ke');
        $this->get('/hotels?q=khongcokhachsan')->assertOk()->assertSee('Chưa tìm thấy kết quả phù hợp');
        $this->get('/places/hon-ba-vung-tau')->assertOk()->assertDontSee('Nguồn ảnh')->assertDontSee('CC BY')->assertDontSee('Đối chiếu nguồn')->assertSee('Khách sạn cùng điểm đến');
        $this->get('/hotels/pullman-vung-tau')->assertOk()->assertSee('15 Thi Sách')->assertSee('Website khách sạn')->assertSee('https://www.pullmanvungtau.com/', false)->assertDontSee('Nguồn thông tin')->assertDontSee('giấy phép')->assertSee('Địa danh cùng điểm đến');
        $this->get('/places/bao-tang-vu-khi-co-robert-taylor')->assertOk()->assertSee('Hiện vật giáp trưng bày tại Bảo tàng Vũ khí cổ Robert Taylor');
        $this->get('/destinations?page=2')->assertOk()->assertSee('page=3', false);
    }

    public function test_invalid_filters_and_missing_records_are_handled(): void
    {
        $this->seedDirectory();
        $this->getJson('/places?category=invalid')->assertUnprocessable();
        $this->getJson('/places?destination=missing')->assertUnprocessable();
        $this->getJson('/hotels?q[]=array')->assertUnprocessable();
        $this->getJson('/hotels?category=museum')->assertUnprocessable();
        $this->getJson('/destinations?q='.str_repeat('a', 101))->assertUnprocessable();
        foreach (['/destinations/missing', '/places/missing', '/hotels/missing'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_factory_pairs_real_hotel_photo_with_its_catalog_record(): void
    {
        $hotel = Hotel::factory()->create();
        $image = HotelImage::factory()->for($hotel)->create();
        $this->assertSame($hotel->getKey(), $image->hotel_id);
        $this->assertStringContainsString($hotel->name, $image->caption);
        $this->assertSame(hash_file('sha256', public_path(ltrim($image->path, '/'))), $image->sha256);
        $this->assertNotNull(ItineraryPlace::factory()->create()->verified_on);
    }

    public function test_missing_cloud_photos_fail_before_any_directory_records_are_written(): void
    {
        config(['media.driver' => 'supabase', 'media.manifest' => storage_path('framework/testing/nonexistent-directory-manifest.json')]);
        try {
            $this->seed(DestinationDirectorySeeder::class);
            $this->fail('Missing cloud photo must fail');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('media:migrate', $exception->getMessage());
        }
        $this->assertDatabaseCount('destinations', 0);
        $this->assertDatabaseCount('hotels', 0);
    }
}
