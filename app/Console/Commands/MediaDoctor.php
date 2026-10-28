<?php

namespace App\Console\Commands;

use App\Services\Media\ImageStorage;
use App\Services\Media\MediaCatalog;
use Illuminate\Console\Command;
use Throwable;

class MediaDoctor extends Command
{
    protected $signature = 'media:doctor {--probe : Upload one tiny image and verify public download}';

    protected $description = 'Check shared image storage configuration without printing server credentials';

    public function handle(MediaCatalog $catalog): int
    {
        $this->line('Image driver: '.config('media.driver'));
        $this->line('Catalog: '.count($catalog->images()).' references');
        $missing = [];
        foreach (['url' => 'SUPABASE_URL', 'bucket' => 'SUPABASE_STORAGE_BUCKET', 'secret_key' => 'SUPABASE_SECRET_KEY'] as $key => $label) {
            if (! config('media.supabase.'.$key)) {
                $missing[] = $label;
            }
        }
        if ($missing) {
            $this->error('Cần cấu hình: '.implode(', ', $missing));

            return self::FAILURE;
        }
        $this->line('Bucket: '.config('media.supabase.bucket'));
        if (! $this->option('probe')) {
            $this->info('Cấu hình đã có. Chưa kiểm tra quyền truy cập; dùng --probe để kiểm tra thật.');

            return self::SUCCESS;
        }
        $filename = tempnam(sys_get_temp_dir(), 'media-probe-');
        try {
            file_put_contents($filename, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZlJkAAAAASUVORK5CYII='));
            $storage = app(ImageStorage::class);
            $image = $storage->store($filename);
            $storage->verify($image);
            $this->info('Upload, SHA-256 và URL HTTPS đều hoạt động.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Kết nối Supabase chưa đạt. Xem log riêng trên server để kiểm tra quyền và cấu hình.');

            return self::FAILURE;
        } finally {
            unlink($filename);
        }
    }
}
