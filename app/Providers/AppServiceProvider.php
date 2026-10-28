<?php

namespace App\Providers;

use App\Services\Media\ImageStorage;
use App\Services\Media\LocalImageStorage;
use App\Services\Media\SupabaseImageStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ImageStorage::class, function () {
            return match (config('media.driver')) {
                'supabase' => new SupabaseImageStorage,
                'local' => new LocalImageStorage,
                default => throw new \RuntimeException('MEDIA_DRIVER không hợp lệ.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }
}
