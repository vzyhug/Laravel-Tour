<?php

namespace App\Services\Media;

use RuntimeException;

class MediaCatalog
{
    public function images(): array
    {
        $tours = json_decode(file_get_contents(database_path('data/tour-images.json')), true, 512, JSON_THROW_ON_ERROR);
        $itineraries = json_decode(file_get_contents(database_path('data/tour-itineraries.json')), true, 512, JSON_THROW_ON_ERROR);

        return array_merge($tours, array_column($itineraries['places'], 'image'));
    }

    public function cloudMap(): array
    {
        $path = config('media.manifest');
        if (! is_file($path)) {
            return [];
        }
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['bucket'] ?? null) !== config('media.supabase.bucket')
            || ($manifest['project_url'] ?? null) !== config('media.supabase.url')) {
            throw new RuntimeException('Manifest ảnh thuộc project/bucket khác. Kiểm tra SUPABASE_URL và SUPABASE_STORAGE_BUCKET.');
        }

        return $manifest['images'] ?? [];
    }

    public function reference(array $image): StoredImage
    {
        if (config('media.driver') === 'supabase') {
            $row = $this->cloudMap()[$image['path']] ?? null;
            if (! $row || $row['sha256'] !== $image['sha256'] || $row['disk'] !== 'supabase'
                || $row['bucket'] !== config('media.supabase.bucket')
                || $row['url'] !== SupabaseImageStorage::publicUrl($row['path'])) {
                throw new RuntimeException('Chưa có ảnh Supabase hợp lệ cho '.$image['path'].'. Chạy php artisan media:migrate --apply trước khi seed.');
            }

            return StoredImage::fromArray($row);
        }
        if (config('media.driver') !== 'local' || app()->isProduction()) {
            throw new RuntimeException('MEDIA_DRIVER phải là supabase trong production; local chỉ dùng phát triển/kiểm thử.');
        }
        $path = public_path(ltrim($image['path'], '/'));
        if (! is_file($path) || ! hash_equals($image['sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('Thiếu ảnh nguồn hoặc sai SHA-256: '.$image['path']);
        }

        return new StoredImage('legacy-public', null, ltrim($image['path'], '/'), $image['path'], $image['sha256']);
    }

    public function url(string $path): string
    {
        $image = collect($this->images())->firstWhere('path', $path)
            ?? throw new RuntimeException('Ảnh không nằm trong danh mục: '.$path);

        return $this->reference($image)->url;
    }

    public function credits(): array
    {
        return array_map(fn ($image) => array_merge($image, ['url' => $this->reference($image)->url]), $this->images());
    }
}
