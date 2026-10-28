<?php

namespace App\Actions;

use App\Models\Tour;
use App\Services\Media\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveTour
{
    public function __construct(private ImageStorage $storage) {}

    public function handle(?Tour $tour, array $data, ?UploadedFile $upload): Tour
    {
        $image = null;
        if ($upload) {
            try {
                // Upload before changing any business data or the existing thumbnail.
                $image = $this->storage->store($upload->getRealPath());
            } catch (Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages(['image' => 'Không tải được ảnh lên kho lưu trữ. Vui lòng thử lại.']);
            }
        }

        return DB::transaction(function () use ($tour, $data, $image) {
            $tour = $tour ? Tour::whereKey($tour->getKey())->lockForUpdate()->firstOrFail() : new Tour;
            $tour->fill($data)->save();
            if ($image) {
                $tour->images()->update(['is_thumbnail' => false]);
                $tour->images()->updateOrCreate(['sha256' => $image->sha256], array_merge($image->columns(), ['is_thumbnail' => true]));
            }

            // Content-addressed files can be shared. Never delete them during a failed DB transaction.
            return $tour;
        });
    }
}
