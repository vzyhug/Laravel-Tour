<?php

namespace App\Services\Media;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseImageStorage implements ImageStorage
{
    private static function settings(): array
    {
        $url = rtrim(config('media.supabase.url', ''), '/');
        $bucket = config('media.supabase.bucket', '');
        // Only send server credentials to the configured hosted Supabase project.
        if (! preg_match('~^https://[a-z0-9-]+\.supabase\.co$~', $url)
            || ! preg_match('/^[a-z0-9][a-z0-9._-]{0,99}$/', $bucket)) {
            throw new RuntimeException('Cần SUPABASE_URL dạng https://project-ref.supabase.co và SUPABASE_STORAGE_BUCKET hợp lệ.');
        }

        return [$url, $bucket];
    }

    public static function publicUrl(string $path): string
    {
        [$url, $bucket] = self::settings();
        $parts = explode('/', $path);
        if (str_contains($path, '\\') || str_contains($path, "\0") || array_intersect(['', '.', '..'], $parts)) {
            throw new RuntimeException('Đường dẫn ảnh Supabase không hợp lệ.');
        }

        return $url.'/storage/v1/object/public/'.rawurlencode($bucket).'/'.implode('/', array_map('rawurlencode', $parts));
    }

    private function request(): PendingRequest
    {
        self::settings();
        $key = config('media.supabase.secret_key', '');
        if (! str_starts_with($key, 'sb_secret_')) {
            throw new RuntimeException('Cần SUPABASE_SECRET_KEY dạng sb_secret_ từ Settings → API Keys, chỉ lưu trên server.');
        }

        // New secret API keys are not JWTs: use apikey, never Authorization: Bearer.
        return $this->http()->withHeaders(['apikey' => $key]);
    }

    private function http(): PendingRequest
    {
        return Http::withOptions(['verify' => config('media.supabase.ca_bundle') ?: true])
            ->connectTimeout(10)->timeout(60)->withoutRedirecting();
    }

    public function store(string $filename, ?string $expectedHash = null): StoredImage
    {
        $sha256 = hash_file('sha256', $filename);
        if ($expectedHash && ! hash_equals($expectedHash, $sha256)) {
            throw new RuntimeException('Ảnh nguồn không khớp SHA-256: '.basename($filename));
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($filename);
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw new RuntimeException('Định dạng ảnh không được hỗ trợ.'),
        };
        $path = 'media/'.substr($sha256, 0, 2).'/'.$sha256.'.'.$extension;
        [$url, $bucket] = self::settings();
        $target = $url.'/storage/v1/object/'.rawurlencode($bucket).'/'.$path;
        $stream = fopen($filename, 'rb');
        try {
            $response = $this->request()->withHeaders(['x-upsert' => 'false', 'cache-control' => 'max-age=86400'])
                ->withBody($stream, $mime)->post($target);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $duplicate = in_array($response->status(), [400, 409], true)
            && ($response->json('error') === 'Duplicate' || $response->json('code') === 'Duplicate'
                || in_array($response->json('message'), ['The resource already exists', 'Asset Already Exists'], true));
        if (! $response->successful() && ! $duplicate) {
            // Never include response bodies/headers or credentials in user-visible errors.
            throw new RuntimeException('Supabase từ chối lưu ảnh (HTTP '.$response->status().'). Kiểm tra bucket và quyền của khóa server.');
        }
        $image = new StoredImage('supabase', $bucket, $path, self::publicUrl($path), $sha256);
        // Public GET verifies both file integrity and that the bucket really is public.
        $this->verify($image);

        return $image;
    }

    public function verify(StoredImage $image): void
    {
        [, $bucket] = self::settings();
        if ($image->disk !== 'supabase' || $image->bucket !== $bucket || $image->url !== self::publicUrl($image->path)) {
            throw new RuntimeException('Ảnh thuộc project/bucket khác với cấu hình Supabase hiện tại.');
        }
        // Intentionally no server secret on public downloads or redirects.
        $response = $this->http()->get($image->url);
        if (! $response->successful() || ! hash_equals($image->sha256, hash('sha256', $response->body()))) {
            throw new RuntimeException('Không xác minh được ảnh công khai trên Supabase: '.$image->path);
        }
    }
}
