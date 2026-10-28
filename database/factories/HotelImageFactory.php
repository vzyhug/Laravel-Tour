<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\HotelImage;
use App\Services\Media\MediaCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/** @extends Factory<HotelImage> */
class HotelImageFactory extends Factory
{
    protected $model = HotelImage::class;

    public function definition(): array
    {
        return ['hotel_id' => Hotel::factory()];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (HotelImage $image) {
            $data = json_decode(file_get_contents(database_path('data/destination-directory.json')), true, 512, JSON_THROW_ON_ERROR);
            $row = collect($data['hotels'])->firstWhere('name', Hotel::findOrFail($image->hotel_id)->name)
                ?? throw new InvalidArgumentException('Khách sạn ngoài danh mục: cung cấp ảnh và nguồn riêng.');
            $photo = $row['image'];
            $image->fill(app(MediaCatalog::class)->reference($photo)->columns('path') + [
                'caption' => $photo['caption'], 'source_url' => $photo['source_page'], 'author' => $photo['author'],
                'license' => $photo['license'], 'license_url' => $photo['license_url'] ?: null,
            ]);
        });
    }
}
