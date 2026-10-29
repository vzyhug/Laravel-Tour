<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Services\SerpApiKeyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class TourMapPoiController extends Controller
{
    public function __invoke(Request $request, Tour $tour, SerpApiKeyResolver $keyResolver): JsonResponse
    {
        abort_unless($tour->is_active, 404);
        $validated = $request->validate([
            'points' => ['required', 'array', 'min:1', 'max:25'],
            'points.*' => ['required', 'array', 'size:2'],
            'points.*.0' => ['required', 'numeric', 'between:-90,90'],
            'points.*.1' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $tour->loadMissing(['province', 'schedules.stops.place']);
        $knownPlaces = $tour->schedules->flatMap(fn ($schedule) => $schedule->stops->map(fn ($stop) => $stop->place))
            ->filter(fn ($place) => $place && is_numeric($place->latitude) && is_numeric($place->longitude))
            ->keyBy(fn ($place) => $this->coordinateKey((float) $place->latitude, (float) $place->longitude));
        $points = array_map(static fn (array $point): array => [(float) $point[0], (float) $point[1]], $validated['points']);
        $places = collect($points)->map(function (array $point) use ($knownPlaces) {
            return $knownPlaces->get($this->coordinateKey($point[0], $point[1]));
        });
        if ($places->contains(null)) {
            return response()->json(['message' => 'Một địa điểm trên bản đồ không thuộc tour này.'], 422);
        }

        $apiKey = $keyResolver->resolve();
        if (! $apiKey) {
            return response()->json(['message' => 'Tính năng tìm địa điểm xung quanh chưa được cấu hình.'], 503);
        }

        $center = [
            array_sum(array_column($points, 0)) / count($points),
            array_sum(array_column($points, 1)) / count($points),
        ];
        $anchor = Str::ascii((string) $places->first()->name);
        $province = (string) $tour->province->province_name;
        $query = 'cafes and restaurants near '.$anchor.', '.$province.', Vietnam';
        $cacheKey = 'tour-map-pois:'.$tour->getKey().':'.hash('sha256', json_encode([$query, $points], JSON_THROW_ON_ERROR));

        try {
            $results = Cache::remember($cacheKey, now()->addHour(), function () use ($apiKey, $query, $center, $points): array {
                $response = Http::acceptJson()->timeout(20)->get('https://serpapi.com/search.json', [
                    'api_key' => $apiKey,
                    'engine' => 'google_maps',
                    'type' => 'search',
                    'q' => $query,
                    'll' => sprintf('@%.6f,%.6f,13z', $center[0], $center[1]),
                    'hl' => 'vi',
                ]);

                if (! $response->successful() || $response->json('search_metadata.status') !== 'Success') {
                    throw new \RuntimeException('SerpApi place search failed.');
                }

                $pois = collect($response->json('local_results', []))
                    ->map(function (array $result) use ($points): ?array {
                        $latitude = data_get($result, 'gps_coordinates.latitude');
                        $longitude = data_get($result, 'gps_coordinates.longitude');
                        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                            return null;
                        }

                        $type = (string) ($result['type'] ?? '');
                        if (! preg_match('/restaurant|caf[eé]|coffee|bar|bakery|bistro|fast food|tea|food|dessert|brunch|pub|pizza|noodle|kitchen|quán|phở/iu', $type)) {
                            return null;
                        }

                        $distance = min(array_map(
                            fn (array $point): float => $this->distanceMeters($point[0], $point[1], (float) $latitude, (float) $longitude),
                            $points,
                        ));
                        if ($distance > 25000) {
                            return null;
                        }

                        return [
                            'id' => (string) ($result['data_id'] ?? $result['place_id'] ?? Str::slug((string) ($result['title'] ?? 'place'))),
                            'name' => (string) ($result['title'] ?? ''),
                            'type' => $type,
                            'address' => (string) ($result['address'] ?? ''),
                            'rating' => is_numeric($result['rating'] ?? null) ? (float) $result['rating'] : null,
                            'reviews' => is_numeric($result['reviews'] ?? null) ? (int) $result['reviews'] : null,
                            'latitude' => (float) $latitude,
                            'longitude' => (float) $longitude,
                            'distance_m' => (int) round($distance),
                        ];
                    })
                    ->filter(fn (?array $poi) => $poi && $poi['name'] !== '')
                    ->unique('id')
                    ->sortBy('distance_m')
                    ->take(20)
                    ->values()
                    ->all();

                return ['places' => $pois, 'query_area' => $query];
            });

            return response()->json([
                ...$results,
                'provider' => 'Google Maps via SerpApi',
                'cached_for_minutes' => 60,
            ])->header('Cache-Control', 'private, max-age=300');
        } catch (Throwable) {
            return response()->json(['message' => 'Chưa thể tải địa điểm xung quanh. Vui lòng thử lại sau.'], 502);
        }
    }

    private function coordinateKey(float $latitude, float $longitude): string
    {
        return sprintf('%.6f,%.6f', $latitude, $longitude);
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }
}
