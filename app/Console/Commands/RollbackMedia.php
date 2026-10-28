<?php

namespace App\Console\Commands;

use App\Services\Media\MediaMigration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RollbackMedia extends Command
{
    protected $signature = 'media:rollback {--apply : Restore old database references and cloud manifest}';

    protected $description = 'Restore references from the local migration journal; keeps shared cloud objects';

    public function handle(MediaMigration $migration): int
    {
        try {
            return Cache::lock('media-migration', 3600)->block(3, function () use ($migration) {
                $count = $migration->rollback((bool) $this->option('apply'));
                $this->info($count.' bản ghi: '.($this->option('apply') ? 'đã khôi phục; chuyển MEDIA_DRIVER=local để dùng ảnh nguồn.' : 'có thể khôi phục. Dùng --apply để thực hiện.'));

                return self::SUCCESS;
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
