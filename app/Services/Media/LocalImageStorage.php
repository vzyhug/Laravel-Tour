<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Development/tests only. Production must use Supabase. */
class LocalImageStorage implements ImageStorage
{
    public function store(string $filename, ?string $expectedHash = null): StoredImage
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Không cho phép lưu ảnh local trong production.');
        }
        $hash = hash_file('sha256', $filename);
        if ($expectedHash && ! hash_equals($expectedHash, $hash)) {
            throw new RuntimeException('Sai SHA-256 ảnh nguồn.');
        }
        $extension = match ((new \finfo(FILEINFO_MIME_TYPE))->file($filename)) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw new RuntimeException('Định dạng ảnh không được hỗ trợ.'),
        };
        $path = 'media/'.$hash.'.'.$extension;
        $stream = fopen($filename, 'rb');
        try {
            if (! Storage::disk('public')->put($path, $stream)) {
                throw new RuntimeException('Không lưu được ảnh.');
            }
        } finally {
            fclose($stream);
        }

        return new StoredImage('public', null, $path, Storage::disk('public')->url($path), $hash);
    }

    public function verify(StoredImage $image): void
    {
        if (! Storage::disk('public')->exists($image->path)
            || hash('sha256', Storage::disk('public')->get($image->path)) !== $image->sha256) {
            throw new RuntimeException('Không xác minh được ảnh local.');
        }
    }
}
