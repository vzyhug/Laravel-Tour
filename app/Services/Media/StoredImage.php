<?php

namespace App\Services\Media;

final readonly class StoredImage
{
    public function __construct(
        public string $disk,
        public ?string $bucket,
        public string $path,
        public string $url,
        public string $sha256,
    ) {}

    public function columns(string $urlColumn = 'img_url'): array
    {
        return [$urlColumn => $this->url, 'storage_disk' => $this->disk,
            'storage_bucket' => $this->bucket, 'storage_path' => $this->path, 'sha256' => $this->sha256];
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self($data['disk'], $data['bucket'] ?? null, $data['path'], $data['url'], $data['sha256']);
    }
}
