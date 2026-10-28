<?php

namespace Database\Seeders;

use App\Models\ItineraryPlace;
use App\Services\Media\MediaCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlaceGallerySeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/place-galleries.json')), true, 512, JSON_THROW_ON_ERROR);
        $places = ItineraryPlace::whereIn('slug', array_column($data['places'], 'slug'))->get()->keyBy('slug');
        if ($places->count() !== count($data['places'])) {
            throw new RuntimeException('Chạy TourItinerarySeeder và DestinationDirectorySeeder trước để có đủ địa danh cho thư viện ảnh.');
        }
        $catalog = app(MediaCatalog::class);
        $references = collect($data['places'])->pluck('images')->flatten(1)
            ->mapWithKeys(fn ($image) => [$image['path'] => $catalog->reference($image)]);

        DB::transaction(function () use ($data, $places, $references) {
            foreach ($data['places'] as $row) {
                foreach ($row['images'] as $image) {
                    $places[$row['slug']]->images()->updateOrCreate(['sha256' => $image['sha256']], array_merge(
                        $references[$image['path']]->columns('path'), [
                            'caption' => $image['caption'], 'source_url' => $image['source_page'], 'author' => $image['author'],
                            'license' => $image['license'], 'license_url' => $image['license_url'] ?: null,
                        ]));
                }
            }
        });
    }
}
