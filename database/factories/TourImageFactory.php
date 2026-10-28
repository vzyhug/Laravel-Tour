<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourImage;
use App\Services\Media\MediaCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourImage> */
class TourImageFactory extends Factory
{
    protected $model = TourImage::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'img_url' => function (array $a) {
                $catalogue = json_decode(file_get_contents(database_path('data/tours.json')), true, 512, JSON_THROW_ON_ERROR);
                $images = json_decode(file_get_contents(database_path('data/tour-images.json')), true, 512, JSON_THROW_ON_ERROR);
                $title = Tour::findOrFail($a['tour_id'])->title;
                $destination = collect($catalogue)->firstWhere('title', $title);

                if (! $destination) {
                    throw new \InvalidArgumentException('Truyền img_url cho tour ngoài danh mục mẫu.');
                }

                return app(MediaCatalog::class)->reference(collect($images)->firstWhere('destination', $destination['slug']))->url;
            },
            'is_thumbnail' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (TourImage $record) {
            $catalog = app(MediaCatalog::class);
            $source = collect($catalog->images())->firstWhere('path', $record->img_url);
            if (! $source && config('media.driver') === 'supabase') {
                $path = collect($catalog->cloudMap())->search(fn ($row) => $row['url'] === $record->img_url);
                $source = $path ? collect($catalog->images())->firstWhere('path', $path) : null;
            }
            if ($source) {
                $record->fill($catalog->reference($source)->columns());
            }
        });
    }

    public function thumbnail(): static
    {
        return $this->state(fn () => ['is_thumbnail' => true]);
    }
}
