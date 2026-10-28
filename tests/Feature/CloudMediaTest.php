<?php

namespace Tests\Feature;

use App\Actions\SaveTour;
use App\Models\Employee;
use App\Models\ItineraryPlaceImage;
use App\Models\Tour;
use App\Models\TourImage;
use App\Services\Media\ImageStorage;
use App\Services\Media\MediaCatalog;
use App\Services\Media\StoredImage;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\TourItinerarySeeder;
use Database\Seeders\TourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class CloudMediaTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporary = storage_path('framework/testing/media-'.Str::uuid());
        File::makeDirectory($this->temporary, 0755, true);
        config(['media.manifest' => $this->temporary.'/manifest.json', 'media.journal' => $this->temporary.'/journal.json',
            'media.supabase.bucket' => 'tour-images', 'media.supabase.url' => 'https://testproject.supabase.co', 'cache.default' => 'array']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    private function fakeCloud(): object
    {
        $fake = new class implements ImageStorage
        {
            public array $uploads = [];

            public bool $fail = false;

            public ?int $failAfter = null;

            public int $attempts = 0;

            public function store(string $filename, ?string $expectedHash = null): StoredImage
            {
                if ($this->fail || ($this->failAfter !== null && $this->attempts++ >= $this->failAfter)) {
                    throw new RuntimeException('Storage unavailable');
                }
                $hash = hash_file('sha256', $filename);
                $this->uploads[$hash] = true;

                return new StoredImage('supabase', 'tour-images', 'media/'.$hash.'.jpg',
                    'https://testproject.supabase.co/storage/v1/object/public/tour-images/media/'.$hash.'.jpg', $hash);
            }

            public function verify(StoredImage $image): void
            {
                if ($this->fail) {
                    throw new RuntimeException('Verification unavailable');
                }
            }
        };
        $this->app->instance(ImageStorage::class, $fake);

        return $fake;
    }

    public function test_failed_upload_keeps_existing_tour_and_thumbnail_and_does_not_create_a_tour(): void
    {
        $tour = Tour::factory()->create();
        $old = TourImage::factory()->for($tour)->thumbnail()->create();
        $fake = $this->fakeCloud();
        $fake->fail = true;
        $file = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents(public_path('images/tours/demo/da-nang-2.jpg')));
        $before = $tour->fresh()->getAttributes();
        foreach ([$tour, null] as $target) {
            try {
                app(SaveTour::class)->handle($target, array_merge($tour->getAttributes(), ['title' => 'Không được lưu']), $file);
                $this->fail('Expected validation error');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('image', $exception->errors());
            }
        }
        $this->assertSame($before, $tour->fresh()->getAttributes());
        $this->assertTrue($old->fresh()->is_thumbnail);
        $this->assertDatabaseCount('tours', 1);
        $this->assertDatabaseCount('images_tour', 1);
    }

    public function test_database_failure_rolls_back_tour_changes_and_thumbnail_switch(): void
    {
        $tour = Tour::factory()->create();
        $old = TourImage::factory()->for($tour)->thumbnail()->create();
        $this->fakeCloud();
        $file = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents(public_path('images/tours/demo/da-nang-2.jpg')));
        TourImage::creating(fn () => throw new RuntimeException('Database rejected image'));
        try {
            app(SaveTour::class)->handle($tour, ['title' => 'Không được lưu'], $file);
            $this->fail('Expected database error');
        } catch (RuntimeException $exception) {
            $this->assertSame('Database rejected image', $exception->getMessage());
        } finally {
            TourImage::flushEventListeners();
        }
        $this->assertSame($tour->title, $tour->fresh()->title);
        $this->assertTrue($old->fresh()->is_thumbnail);
        $this->assertDatabaseCount('images_tour', 1);
    }

    public function test_admin_upload_uses_configured_cloud_storage_and_same_content_does_not_duplicate(): void
    {
        $this->withoutVite();
        $this->seed(DemoAccountSeeder::class);
        $tour = Tour::factory()->create();
        $this->fakeCloud();
        $data = array_intersect_key($tour->toArray(), array_flip(['title', 'category_id', 'province_id', 'duration_days', 'duration_nights', 'min_price', 'is_active']));
        $data['image'] = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents(public_path('images/tours/demo/da-nang-2.jpg')));
        $admin = Employee::where('username', 'admin')->firstOrFail();
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin'])->put(route('admin.tours.update', $tour), $data)->assertRedirect(route('admin.tours.index'));
        $this->put(route('admin.tours.update', $tour), $data)->assertRedirect(route('admin.tours.index'));
        $this->assertDatabaseCount('images_tour', 1);
        $image = $tour->images()->firstOrFail();
        $this->assertSame('supabase', $image->storage_disk);
        $this->assertTrue($image->is_thumbnail);
        $this->assertStringStartsWith('https://testproject.supabase.co/storage/v1/object/public/tour-images/', $image->img_url);
    }

    public function test_migration_dry_run_failure_resume_deduplication_reseed_and_safe_rollback(): void
    {
        $this->seed([DemoAccountSeeder::class, TourSeeder::class, TourItinerarySeeder::class]);
        Storage::fake('public');
        Storage::disk('public')->put('tours/custom.jpg', file_get_contents(public_path('images/tours/demo/da-nang-2.jpg')));
        $upload = TourImage::create(['tour_id' => Tour::first()->getKey(), 'img_url' => url('/storage/tours/custom.jpg'), 'is_thumbnail' => false]);
        $beforeTours = DB::table('images_tour')->orderBy('image_id')->get()->toJson();
        $beforePlaces = DB::table('itinerary_place_images')->orderBy('id')->get()->toJson();
        config(['media.driver' => 'supabase']);
        $fake = $this->fakeCloud();
        $this->artisan('media:migrate')->assertSuccessful();
        $this->assertFileDoesNotExist(config('media.manifest'));
        $this->assertSame([], $fake->uploads);
        $this->assertSame($beforeTours, DB::table('images_tour')->orderBy('image_id')->get()->toJson());
        $fake->failAfter = 3;
        $this->artisan('media:migrate', ['--apply' => true])->assertFailed();
        $this->assertCount(3, json_decode(file_get_contents(config('media.journal')), true)['images']);
        $this->assertSame($beforeTours, DB::table('images_tour')->orderBy('image_id')->get()->toJson());
        $fake->failAfter = null;
        $this->artisan('media:migrate', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('images_tour', 61);
        $this->assertDatabaseCount('itinerary_place_images', 112);
        $uniqueHashes = collect(app(MediaCatalog::class)->images())->pluck('sha256')->unique()->count();
        $this->assertCount($uniqueHashes, $fake->uploads);
        $this->assertSame('supabase', $upload->fresh()->storage_disk);
        $this->assertSame(0, TourImage::where('storage_disk', '!=', 'supabase')->count());
        $this->assertSame(0, ItineraryPlaceImage::where('storage_disk', '!=', 'supabase')->count());
        $this->artisan('media:verify')->assertSuccessful();
        $this->artisan('itineraries:audit', ['--json' => true])->assertSuccessful();
        $this->artisan('media:migrate', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('images_tour', 61);
        $this->seed([TourSeeder::class, TourItinerarySeeder::class]);
        $this->assertDatabaseCount('images_tour', 61);
        $this->assertDatabaseCount('itinerary_place_images', 112);
        $this->withoutVite();
        $this->get('/')->assertOk()->assertSee('https://testproject.supabase.co/storage/v1/object/public/tour-images/', false)->assertDontSee('/images/tours/demo/', false);
        $this->get(route('tours.show', Tour::first()))->assertOk()->assertSee('https://testproject.supabase.co/storage/v1/object/public/tour-images/', false)->assertDontSee('/images/itinerary/', false);
        $this->get(route('photo-credits'))->assertOk()->assertSee('Taewangkorea')->assertDontSee('/images/tours/demo/', false);
        $journalFile = config('media.journal');
        $originalJournal = file_get_contents($journalFile);
        $otherDatabaseJournal = json_decode($originalJournal, true);
        $otherDatabaseJournal['database'] = 'different-database';
        file_put_contents($journalFile, json_encode($otherDatabaseJournal));
        $this->artisan('media:rollback', ['--apply' => true])->assertFailed();
        $this->assertSame('supabase', $upload->fresh()->storage_disk);
        file_put_contents($journalFile, $originalJournal);
        $this->artisan('media:rollback')->assertSuccessful();
        $this->assertSame('supabase', $upload->fresh()->storage_disk);
        $this->artisan('media:rollback', ['--apply' => true])->assertSuccessful();
        // Reseeding may switch thumbnails, so compare reference columns, not unrelated flags.
        $this->assertSame(json_decode($beforePlaces, true), json_decode(DB::table('itinerary_place_images')->orderBy('id')->get()->toJson(), true));
        $this->assertSame(url('/storage/tours/custom.jpg'), $upload->fresh()->img_url);
        $this->assertFileDoesNotExist(config('media.manifest'));
        $this->assertFileExists(public_path('images/tours/demo/da-nang-2.jpg'));
    }

    public function test_cloud_seeding_and_rendering_work_without_any_local_photo_directory(): void
    {
        $images = [];
        foreach (app(MediaCatalog::class)->images() as $image) {
            $ref = new StoredImage('supabase', 'tour-images', 'media/'.$image['sha256'].'.jpg',
                'https://testproject.supabase.co/storage/v1/object/public/tour-images/media/'.$image['sha256'].'.jpg', $image['sha256']);
            $images[$image['path']] = $ref->toArray();
        }
        file_put_contents(config('media.manifest'), json_encode(['bucket' => 'tour-images', 'project_url' => 'https://testproject.supabase.co', 'images' => $images]));
        config(['media.driver' => 'supabase']);
        $originalPublic = public_path();
        // Change Laravel's public root instead of moving/deleting the checked-in files.
        $this->app->usePublicPath($this->temporary.'/empty-public');
        try {
            $this->seed([DemoAccountSeeder::class, TourSeeder::class, TourItinerarySeeder::class]);
            $this->withoutVite();
            $this->get('/')->assertOk()->assertSee('https://testproject.supabase.co/storage/v1/object/public/tour-images/', false);
            $this->get(route('photo-credits'))->assertOk();
            $this->get(route('tours.show', Tour::first()))->assertOk();
            $this->assertSame('supabase', TourImage::factory()->create()->storage_disk);
        } finally {
            $this->app->usePublicPath($originalPublic);
        }
    }

    public function test_cloud_seed_fails_before_writing_when_manifest_is_missing(): void
    {
        $this->seed(DemoAccountSeeder::class);
        config(['media.driver' => 'supabase']);
        try {
            $this->seed(TourSeeder::class);
            $this->fail('Missing cloud manifest must fail');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('media:migrate', $exception->getMessage());
        }
        $this->assertDatabaseCount('tours', 0);
    }

    public function test_rollback_refuses_to_overwrite_a_photo_edited_after_migration(): void
    {
        $this->seed([DemoAccountSeeder::class, TourSeeder::class, TourItinerarySeeder::class]);
        config(['media.driver' => 'supabase']);
        $this->fakeCloud();
        $this->artisan('media:migrate', ['--apply' => true])->assertSuccessful();
        $image = TourImage::firstOrFail();
        $image->update(['img_url' => 'https://example.test/new-photo.jpg']);
        $this->artisan('media:rollback', ['--apply' => true])->assertFailed();
        $this->assertSame('https://example.test/new-photo.jpg', $image->fresh()->img_url);
        $this->assertSame(112, ItineraryPlaceImage::where('storage_disk', 'supabase')->count());
    }
}
