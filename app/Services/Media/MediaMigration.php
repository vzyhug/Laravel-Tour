<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaMigration
{
    public function __construct(private MediaCatalog $catalog, private ImageStorage $storage) {}

    public function inventory(): array
    {
        $sources = [];
        foreach ($this->catalog->images() as $image) {
            $sources[$image['path']] = ['source' => $image['path'], 'sha256' => $image['sha256']];
        }
        $rows = [];
        foreach (['images_tour' => ['image_id', 'img_url'], 'itinerary_place_images' => ['id', 'path'], 'hotel_images' => ['id', 'path']] as $table => [$id, $url]) {
            foreach (DB::table($table)->get() as $row) {
                if ($row->storage_disk === 'supabase') {
                    if ($row->storage_bucket !== config('media.supabase.bucket')
                        || $row->{$url} !== SupabaseImageStorage::publicUrl($row->storage_path ?? '')) {
                        throw new RuntimeException('Bản ghi ảnh thuộc bucket khác: '.$table.' #'.$row->{$id});
                    }

                    continue;
                }
                $source = $this->localReference($row->{$url});
                if ($source === null) {
                    throw new RuntimeException('Ảnh ngoài kho được quản lý, cần nhập nguồn trước: '.$table.' #'.$row->{$id});
                }
                $sources[$source] ??= ['source' => $source, 'sha256' => null];
                $columns = [$url, 'storage_disk', 'storage_bucket', 'storage_path', 'sha256'];
                $rows[] = ['table' => $table, 'key' => $id, 'id' => $row->{$id}, 'url_column' => $url,
                    'source' => $source, 'before' => array_intersect_key((array) $row, array_flip($columns))];
            }
        }
        foreach ($sources as &$source) {
            $source['filename'] = $this->sourceFile($source['source']);
            // A completed cloud mapping lets a later deployment run without source files.
            if (is_file($source['filename'])) {
                $actual = hash_file('sha256', $source['filename']);
                if ($source['sha256'] && $actual !== $source['sha256']) {
                    throw new RuntimeException('Sai SHA-256: '.$source['source']);
                }
                $source['sha256'] = $actual;
                $source['bytes'] = filesize($source['filename']);
            } else {
                $mapped = $this->catalog->cloudMap()[$source['source']] ?? null;
                if (! $mapped || ($source['sha256'] && $source['sha256'] !== $mapped['sha256'])) {
                    throw new RuntimeException('Thiếu file nguồn: '.$source['source']);
                }
                $source['sha256'] = $mapped['sha256'];
                $source['bytes'] = 0;
            }
        }
        unset($source);

        return ['sources' => $sources, 'rows' => $rows];
    }

    private function localReference(string $value): ?string
    {
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $app = rtrim(config('app.url'), '/');
            if (! str_starts_with($value, $app.'/')) {
                return null;
            }
            $value = substr($value, strlen($app));
        }
        $path = rawurldecode(parse_url($value, PHP_URL_PATH) ?: '');
        $path = '/'.ltrim(str_replace('\\', '/', $path), '/');
        if (str_contains($path, "\0") || in_array('..', explode('/', $path), true)) {
            throw new RuntimeException('Đường dẫn ảnh không hợp lệ.');
        }

        return str_starts_with($path, '/images/') || str_starts_with($path, '/storage/') ? $path : null;
    }

    private function sourceFile(string $source): string
    {
        $path = $this->localReference($source);
        if ($path === null) {
            throw new RuntimeException('Ảnh không nằm trong thư mục nguồn được phép.');
        }
        $root = str_starts_with($path, '/storage/') ? Storage::disk('public')->path('') : public_path('images');
        $relative = str_starts_with($path, '/storage/') ? substr($path, 9) : substr($path, 8);
        $filename = $root.DIRECTORY_SEPARATOR.$relative;
        if (is_file($filename)) {
            $resolved = str_replace('\\', '/', realpath($filename));
            $allowed = rtrim(str_replace('\\', '/', realpath($root)), '/').'/';
            if (! str_starts_with(strtolower($resolved), strtolower($allowed))) {
                throw new RuntimeException('File ảnh nằm ngoài thư mục nguồn được phép.');
            }
        }

        return $filename;
    }

    public function apply(array $inventory, ?callable $progress = null): array
    {
        if (config('media.driver') !== 'supabase' || ! config('media.supabase.bucket')) {
            throw new RuntimeException('Cấu hình MEDIA_DRIVER=supabase và bucket trước khi chuyển ảnh.');
        }
        $journalFile = config('media.journal');
        $journal = is_file($journalFile) ? json_decode(file_get_contents($journalFile), true, 512, JSON_THROW_ON_ERROR) : [
            'bucket' => config('media.supabase.bucket'), 'project_url' => config('media.supabase.url'), 'database' => $this->databaseIdentity(), 'images' => [], 'database_before' => [],
            'manifest_before' => is_file(config('media.manifest')) ? file_get_contents(config('media.manifest')) : null,
        ];
        if (($journal['project_url'] ?? null) !== config('media.supabase.url')
            || $journal['bucket'] !== config('media.supabase.bucket') || ($journal['database'] ?? null) !== $this->databaseIdentity()) {
            throw new RuntimeException('Nhật ký thuộc database hoặc bucket khác. Hãy dùng nhật ký riêng cho mỗi môi trường.');
        }
        $map = $this->catalog->cloudMap();
        $verified = [];
        foreach ($inventory['sources'] as $source => $item) {
            $saved = $journal['images'][$source] ?? $map[$source] ?? null;
            if (isset($verified[$item['sha256']])) {
                $stored = $verified[$item['sha256']];
            } elseif ($saved && $saved['sha256'] === $item['sha256']) {
                $stored = StoredImage::fromArray($saved);
            } else {
                $stored = $this->storage->store($item['filename'], $item['sha256']);
            }
            if ($stored->disk !== 'supabase' || $stored->bucket !== config('media.supabase.bucket')) {
                throw new RuntimeException('Kho ảnh trả về sai bucket.');
            }
            // Always verify actual remote bytes before publishing a reference, including resume.
            if (! isset($verified[$item['sha256']])) {
                $this->storage->verify($stored);
                $verified[$item['sha256']] = $stored;
            }
            $map[$source] = $journal['images'][$source] = $stored->toArray();
            $this->writeJson($journalFile, $journal);
            $progress && $progress($source);
        }
        foreach ($inventory['rows'] as $row) {
            $identity = $row['table'].':'.$row['id'];
            $journal['database_before'][$identity] ??= $row;
            $journal['database_before'][$identity]['after'] = StoredImage::fromArray($map[$row['source']])->columns($row['url_column']);
        }
        // Persist rollback information before the database transaction.
        $this->writeJson($journalFile, $journal);
        DB::transaction(function () use ($inventory, $map) {
            foreach ($inventory['rows'] as $row) {
                $current = DB::table($row['table'])->where($row['key'], $row['id'])->lockForUpdate()->first();
                if (! $current || $current->{$row['url_column']} !== $row['before'][$row['url_column']]) {
                    throw new RuntimeException('Ảnh vừa được thay đổi. Chạy lại chuyển ảnh để tránh ghi đè.');
                }
                DB::table($row['table'])->where($row['key'], $row['id'])->update(StoredImage::fromArray($map[$row['source']])->columns($row['url_column']));
            }
        });
        $this->writeJson(config('media.manifest'), ['version' => 1, 'project_url' => config('media.supabase.url'), 'bucket' => config('media.supabase.bucket'), 'images' => $map]);

        return ['sources' => count($inventory['sources']), 'objects' => count(array_unique(array_column($map, 'path'))), 'rows' => count($inventory['rows'])];
    }

    public function rollback(bool $apply): int
    {
        $file = config('media.journal');
        if (! is_file($file)) {
            throw new RuntimeException('Chưa có nhật ký chuyển ảnh trên máy này.');
        }
        $journal = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (($journal['project_url'] ?? null) !== config('media.supabase.url')
            || $journal['bucket'] !== config('media.supabase.bucket') || ($journal['database'] ?? null) !== $this->databaseIdentity()) {
            throw new RuntimeException('Nhật ký thuộc database hoặc bucket khác.');
        }
        $rows = array_values($journal['database_before']);
        // Local sources must still exist; cloud objects are never deleted by rollback.
        foreach ($rows as $row) {
            $this->sourceFile($row['source']);
            if (! is_file($this->sourceFile($row['source']))) {
                throw new RuntimeException('Cần phục hồi ảnh nguồn trước khi rollback: '.$row['source']);
            }
        }
        if ($apply) {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    $current = DB::table($row['table'])->where($row['key'], $row['id'])->lockForUpdate()->first();
                    if (! $current) {
                        throw new RuntimeException('Bản ghi ảnh đã bị xóa; dừng rollback.');
                    }
                    $actual = array_intersect_key((array) $current, $row['after']);
                    if ($actual == $row['before']) {
                        continue;
                    }
                    if ($actual != $row['after']) {
                        throw new RuntimeException('Ảnh đã được sửa sau khi chuyển; dừng rollback để tránh ghi đè.');
                    }
                    DB::table($row['table'])->where($row['key'], $row['id'])->update($row['before']);
                }
            });
            if ($journal['manifest_before'] === null) {
                if (is_file(config('media.manifest'))) {
                    unlink(config('media.manifest'));
                }
            } else {
                $this->writeText(config('media.manifest'), $journal['manifest_before']);
            }
        }

        return count($rows);
    }

    private function databaseIdentity(): string
    {
        $connection = DB::connection();

        return hash('sha256', json_encode([$connection->getDriverName(), $connection->getDatabaseName(),
            $connection->getConfig('host'), $connection->getConfig('port')], JSON_THROW_ON_ERROR));
    }

    private function writeJson(string $path, array $data): void
    {
        $this->writeText($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }

    private function writeText(string $path, string $text): void
    {
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Không tạo được thư mục manifest/nhật ký.');
        }
        $temporary = tempnam(dirname($path), 'media-');
        try {
            if (file_put_contents($temporary, $text, LOCK_EX) === false || ! rename($temporary, $path)) {
                throw new RuntimeException('Không ghi được manifest/nhật ký ảnh.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
