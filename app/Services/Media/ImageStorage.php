<?php

namespace App\Services\Media;

interface ImageStorage
{
    public function store(string $filename, ?string $expectedHash = null): StoredImage;

    public function verify(StoredImage $image): void;
}
