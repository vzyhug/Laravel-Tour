<?php

namespace App\Console\Commands;

use App\Services\Media\ImageStorage;
use App\Services\Media\MediaCatalog;
use App\Services\Media\StoredImage;
use App\Services\Media\SupabaseImageStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class VerifyMedia extends Command
{
    protected $signature = 'media:verify {--remote : Download each unique object and verify SHA-256}';

    protected $description = 'Check that all catalog and database images use the configured Supabase bucket';

    public function handle(MediaCatalog $catalog): int
    {
        try {
            if (config('media.driver') !== 'supabase') {
                throw new \RuntimeException('MEDIA_DRIVER chưa phải supabase.');
            }
            $objects = [];
            foreach ($catalog->images() as $image) {
                $ref = $catalog->reference($image);
                $objects[$ref->bucket.'/'.$ref->path] = $ref;
            }
            foreach (['images_tour' => 'img_url', 'itinerary_place_images' => 'path', 'hotel_images' => 'path'] as $table => $column) {
                foreach (DB::table($table)->get() as $row) {
                    if ($row->storage_disk !== 'supabase' || $row->storage_bucket !== config('media.supabase.bucket')
                        || ! $row->storage_path || ! preg_match('/^[a-f0-9]{64}$/', $row->sha256 ?? '')
                        || $row->{$column} !== SupabaseImageStorage::publicUrl($row->storage_path)) {
                        throw new \RuntimeException('Còn ảnh local hoặc tham chiếu chưa hợp lệ trong '.$table);
                    }
                    $ref = new StoredImage('supabase', $row->storage_bucket, $row->storage_path, $row->{$column}, $row->sha256);
                    $objects[$ref->bucket.'/'.$ref->path] = $ref;
                }
            }
            if ($this->option('remote')) {
                foreach ($objects as $object) {
                    app(ImageStorage::class)->verify($object);
                }
            }
            $this->info(count($objects).' file dùng chung; không còn tham chiếu ảnh local.'.($this->option('remote') ? ' Đã xác minh SHA-256 trên Supabase.' : ' Chưa tải file từ Supabase để xác minh.'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
