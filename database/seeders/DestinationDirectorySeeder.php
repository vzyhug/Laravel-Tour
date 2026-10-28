<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Hotel;
use App\Models\HotelBooking;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use App\Models\ItineraryPlace;
use App\Services\Media\MediaCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DestinationDirectorySeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/destination-directory.json')), true, 512, JSON_THROW_ON_ERROR);
        $catalog = app(MediaCatalog::class);
        $references = collect(array_merge($data['places'], $data['hotels']))->filter(fn ($row) => isset($row['image']))
            ->mapWithKeys(fn ($row) => [$row['image']['path'] => $catalog->reference($row['image'])]);

        DB::transaction(function () use ($data, $references) {
            $excludedHotels = Hotel::query()->where('slug', 'like', 'muong-thanh-%')->orWhere('name', 'like', 'Mường Thanh%')->get();
            $excludedTypeIds = HotelRoomType::whereIn('hotel_id', $excludedHotels->modelKeys())->pluck('id');
            if ($excludedTypeIds->isNotEmpty() && HotelBooking::whereIn('room_type_id', $excludedTypeIds)->exists()) {
                throw new RuntimeException('Không thể xóa khách sạn Mường Thanh đang được tham chiếu bởi đơn đặt phòng.');
            }
            HotelRoomInventory::whereIn('room_type_id', $excludedTypeIds)->delete();
            HotelRoomType::whereIn('id', $excludedTypeIds)->delete();
            Hotel::whereIn('id', $excludedHotels->modelKeys())->delete();

            $destinations = [];
            foreach ($data['destinations'] as $row) {
                $destination = Destination::updateOrCreate(['slug' => $row['slug']], collect($row)->except('slug', 'place_slugs')->all());
                $destinations[$row['slug']] = $destination;
            }
            foreach ($data['places'] as $row) {
                $place = ItineraryPlace::updateOrCreate(['slug' => $row['slug']], collect($row)->except('slug', 'image')->all());
                if (isset($row['image'])) {
                    $this->image($place, $row['image'], $references);
                }
            }
            foreach ($data['existing_place_categories'] as $slug => $category) {
                ItineraryPlace::where('slug', $slug)->update(['category' => $category]);
            }
            foreach ($data['destinations'] as $row) {
                $places = ItineraryPlace::whereIn('slug', $row['place_slugs'])->get();
                if ($places->count() !== count(array_unique($row['place_slugs']))) {
                    throw new RuntimeException('Thiếu địa danh gốc. Chạy TourItinerarySeeder trước DestinationDirectorySeeder.');
                }
                $destinations[$row['slug']]->places()->syncWithoutDetaching($places->modelKeys());
            }
            foreach ($data['hotels'] as $row) {
                $attributes = collect($row)->except('slug', 'destination', 'image', 'google_maps')->all();
                if (isset($row['google_maps'])) {
                    $google = $row['google_maps'];
                    $attributes = array_merge($attributes, [
                        'google_place_id' => $google['place_id'],
                        'google_data_id' => $google['data_id'] ?? null,
                        'google_rating' => $google['rating'] ?? null,
                        'google_reviews' => $google['reviews'] ?? null,
                        'latitude' => $google['latitude'] ?? null,
                        'longitude' => $google['longitude'] ?? null,
                        'google_maps_url' => $google['url'] ?? null,
                        'google_verified_on' => $google['checked_at'] ?? null,
                    ]);
                }
                $hotel = Hotel::updateOrCreate(['slug' => $row['slug']], array_merge($attributes, [
                    'destination_id' => $destinations[$row['destination']]->id,
                ]));
                if (isset($row['image'])) {
                    $this->image($hotel, $row['image'], $references);
                }
            }
        });
    }

    private function image(Hotel|ItineraryPlace $record, array $image, $references): void
    {
        $record->images()->updateOrCreate(['sha256' => $image['sha256']], array_merge($references[$image['path']]->columns('path'), [
            'caption' => $image['caption'], 'source_url' => $image['source_page'], 'author' => $image['author'],
            'license' => $image['license'], 'license_url' => $image['license_url'] ?: null,
        ]));
    }
}
