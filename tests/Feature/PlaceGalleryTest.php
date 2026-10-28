<?php

namespace Tests\Feature;

use App\Models\ItineraryPlace;
use App\Models\ItineraryPlaceImage;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\DestinationDirectorySeeder;
use Database\Seeders\PlaceGallerySeeder;
use Database\Seeders\TourItinerarySeeder;
use Database\Seeders\TourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PlaceGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlaces(): void
    {
        $this->seed([DemoAccountSeeder::class, TourSeeder::class, TourItinerarySeeder::class, DestinationDirectorySeeder::class]);
        $this->withoutVite();
    }

    public function test_every_place_has_a_specific_introduction_and_distinct_photos_without_network_access(): void
    {
        Http::preventStrayRequests();
        $this->seedPlaces();
        $primaryIds = ItineraryPlace::with('images')->get()->mapWithKeys(fn ($place) => [$place->id => $place->images->first()->id]);
        $stops = DB::table('tour_itinerary_stops')->orderBy('id')->get()->toJson();
        $this->seed(PlaceGallerySeeder::class);
        $places = ItineraryPlace::with('images')->get();
        $this->assertCount(120, $places);
        $this->assertDatabaseCount('itinerary_place_images', 335);
        $this->assertCount(120, $places->pluck('description')->unique());
        foreach ($places as $place) {
            $this->assertGreaterThanOrEqual(60, mb_strlen($place->description), $place->slug);
            $this->assertStringNotContainsString('Địa điểm có thật được đưa vào chương trình hiện có', $place->description);
            $this->assertGreaterThanOrEqual(2, $place->images->count(), $place->slug);
            $this->assertLessThanOrEqual(3, $place->images->count(), $place->slug);
            $this->assertSame($place->images->count(), $place->images->pluck('sha256')->unique()->count(), $place->slug);
            $this->assertSame($primaryIds[$place->id], $place->images->first()->id);
            foreach ($place->images as $image) {
                $filename = public_path(ltrim($image->path, '/'));
                $this->assertFileExists($filename);
                $this->assertSame($image->sha256, hash_file('sha256', $filename));
                $this->assertNotFalse(getimagesize($filename));
            }
        }
        $before = ItineraryPlaceImage::orderBy('id')->get()->toJson();
        $this->seed([TourItinerarySeeder::class, DestinationDirectorySeeder::class, PlaceGallerySeeder::class]);
        $this->assertSame($before, ItineraryPlaceImage::orderBy('id')->get()->toJson());
        $this->assertSame($stops, DB::table('tour_itinerary_stops')->orderBy('id')->get()->toJson());
        foreach (['/places/dong-phong-nha', '/places/cau-rong', '/places/bai-dam-trau'] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('data-image-gallery', false)->assertSee('data-image-viewer', false)
                ->assertSee('data-gallery-item', false)->assertSee('Phóng to')->assertDontSee('Nguồn ảnh')->assertDontSee('giấy phép');
            $this->assertSame(3, substr_count($response->getContent(), 'data-gallery-item'));
        }
        // The existing hotel directory also benefits from the shared viewer with one image.
        $response = $this->get('/hotels/pullman-vung-tau')->assertOk()->assertSee('data-image-viewer', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-gallery-item'));
    }

    public function test_missing_cloud_mapping_fails_before_adding_any_gallery_rows(): void
    {
        $this->seedPlaces();
        $before = ItineraryPlaceImage::orderBy('id')->get()->toJson();
        // Every mapping in the real manifest belongs to the configured project, so an empty
        // manifest path makes this independent of the developer's cloud configuration.
        config(['media.driver' => 'supabase', 'media.manifest' => storage_path('framework/testing/missing-gallery-manifest.json')]);
        try {
            $this->seed(PlaceGallerySeeder::class);
            $this->fail('A missing cloud image must stop the entire gallery seed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('media:migrate', $exception->getMessage());
        }
        $this->assertSame($before, ItineraryPlaceImage::orderBy('id')->get()->toJson());
    }
}
