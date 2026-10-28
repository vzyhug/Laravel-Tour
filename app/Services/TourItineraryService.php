<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerItinerary;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TourItineraryService
{
    public function payload(Tour $tour, ?int $customerId): array
    {
        $tour->loadMissing('schedules.stops.place.images');
        $places = $tour->schedules->flatMap->stops->pluck('place')->unique('id')->values();
        $plan = $customerId ? $tour->personalItineraries()->where('customer_id', $customerId)->with(['items', 'consultations'])->first() : null;
        $defaults = $tour->schedules->flatMap(fn ($day) => $day->stops->map(fn ($stop) => [
            'place_id' => $stop->place_id, 'day_number' => $day->day_number, 'title' => $stop->place->name,
            'start_time' => null, 'duration_minutes' => null, 'notes' => '',
        ])->push(['place_id' => null, 'day_number' => $day->day_number,
            'title' => 'Chương trình & dịch vụ ngày '.$day->day_number, 'start_time' => null,
            'duration_minutes' => null, 'notes' => $day->activities ?? '']))->values()->all();

        return [
            'version' => $plan?->version ?? 0,
            'days' => $tour->schedules->map(fn ($day) => ['number' => $day->day_number, 'title' => $day->title, 'activities' => $day->activities])->values(),
            'places' => $places->map(fn ($place) => [
                'id' => $place->id, 'name' => $place->name, 'description' => $place->description,
                'source_url' => $place->source_url, 'verified_on' => $place->verified_on,
                'image' => $place->images->first() ? ['url' => $place->images->first()->url,
                    'caption' => $place->images->first()->caption, 'author' => $place->images->first()->author,
                    'source_url' => $place->images->first()->source_url, 'license' => $place->images->first()->license,
                    'license_url' => $place->images->first()->license_url] : null,
            ])->values(),
            'defaults' => $defaults,
            'items' => $plan ? $plan->items->map(fn ($item) => [
                'place_id' => $item->place_id, 'day_number' => $item->day_number, 'title' => $item->title,
                'start_time' => $item->start_time ? substr($item->start_time, 0, 5) : null,
                'duration_minutes' => $item->duration_minutes, 'notes' => $item->notes ?? '',
            ])->all() : $defaults,
            'save_url' => $customerId ? route('tour-itinerary.update', $tour) : null,
            'consult_url' => $customerId ? route('tour-itinerary.consult', $tour) : null,
            'login_url' => route('login'),
            'consultations' => $plan?->consultations->map(fn ($request) => [
                'id' => $request->id, 'status' => $request->status, 'itinerary_version' => $request->itinerary_version,
                'travel_date' => $request->travel_date->toDateString(), 'adults' => $request->adults, 'children' => $request->children,
                'quoted_total' => $request->quoted_total, 'reply' => $request->reply,
                'valid_until' => $request->valid_until?->toDateString(),
                'url' => route('itinerary-consultations.show', $request),
            ])->all() ?? [],
        ];
    }

    public function save(Tour $tour, int $customerId, array $input): array
    {
        abort_unless($tour->is_active, 404);
        $tour->loadMissing('schedules.stops.place');
        $places = $tour->schedules->flatMap->stops->pluck('place')->keyBy('id');
        $days = $tour->schedules->pluck('day_number')->all();
        $data = Validator::make($input, [
            'version' => ['required', 'integer', 'min:0'], 'items' => ['required', 'array', 'min:1', 'max:120'],
            'items.*.day_number' => ['required', 'integer', Rule::in($days)],
            'items.*.place_id' => ['nullable', 'integer', Rule::in($places->keys()->all())],
            'items.*.title' => ['required', 'string', 'max:160'],
            'items.*.start_time' => ['nullable', 'date_format:H:i'],
            'items.*.duration_minutes' => ['nullable', 'integer', 'between:5,720'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();
        foreach (collect($data['items'])->groupBy('day_number') as $items) {
            if ($items->count() > 25) {
                throw ValidationException::withMessages(['items' => 'Mỗi ngày tối đa 25 hoạt động.']);
            }
            $until = null;
            foreach ($items->filter(fn ($item) => ! empty($item['start_time']))->sortBy('start_time') as $item) {
                [$hour, $minute] = array_map('intval', explode(':', $item['start_time']));
                $start = $hour * 60 + $minute;
                $end = $start + ($item['duration_minutes'] ?? 0);
                if (($until !== null && $start < $until) || $end > 1440) {
                    throw ValidationException::withMessages(['items' => 'Giờ hoạt động bị chồng nhau hoặc vượt quá ngày. Hãy điều chỉnh thời gian.']);
                }
                $until = $end;
            }
        }
        DB::transaction(function () use ($tour, $customerId, $data, $places) {
            Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            $plan = CustomerItinerary::where('customer_id', $customerId)->where('tour_id', $tour->getKey())->lockForUpdate()->first();
            abort_if((int) ($plan?->version ?? 0) !== (int) $data['version'], 409, 'Lịch trình đã thay đổi ở phiên khác. Hãy tải lại trang trước khi lưu.');
            $plan ??= new CustomerItinerary(['customer_id' => $customerId, 'tour_id' => $tour->getKey(), 'version' => 0]);
            $plan->version++;
            $plan->save();
            $plan->items()->delete();
            $positions = [];
            foreach ($data['items'] as $item) {
                $day = $item['day_number'];
                $positions[$day] = ($positions[$day] ?? 0) + 1;
                $plan->items()->create([
                    'place_id' => $item['place_id'] ?? null, 'day_number' => $day, 'position' => $positions[$day],
                    'title' => isset($item['place_id']) ? $places[$item['place_id']]->name : $item['title'],
                    'start_time' => $item['start_time'] ?? null, 'duration_minutes' => $item['duration_minutes'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        });

        return $this->payload($tour, $customerId);
    }
}
