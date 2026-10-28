<?php

namespace App\Console\Commands;

use App\Services\Media\MediaMigration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MigrateMedia extends Command
{
    protected $signature = 'media:migrate {--apply : Upload, verify and update database/manifest}';

    protected $description = 'Inventory local images; --apply migrates them to Supabase without deleting sources';

    public function handle(MediaMigration $migration): int
    {
        try {
            return Cache::lock('media-migration', 3600)->block(3, function () use ($migration) {
                $inventory = $migration->inventory();
                $this->info(count($inventory['sources']).' nguồn ảnh; '.count($inventory['rows']).' bản ghi cần chuyển.');
                if (! $this->option('apply')) {
                    $this->line('Chỉ kiểm tra. Dùng --apply để upload và cập nhật. Chưa thay đổi dữ liệu.');

                    return self::SUCCESS;
                }
                $result = $migration->apply($inventory, fn ($path) => $this->line('Đã xác minh: '.$path));
                $this->info('Hoàn tất: '.$result['objects'].' file dùng chung; '.$result['rows'].' bản ghi cập nhật.');

                return self::SUCCESS;
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Chưa hoàn tất chuyển ảnh: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
