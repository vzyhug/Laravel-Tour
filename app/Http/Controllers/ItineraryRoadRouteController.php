<?php

namespace App\Http\Controllers;

use App\Models\ItineraryPlace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ItineraryRoadRouteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'array', 'min:2', 'max:25'],
            'points.*' => ['required', 'array', 'size:2'],
            'points.*.0' => ['required', 'numeric', 'between:-90,90'],
            'points.*.1' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $apiKey = config('services.graphhopper.key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            return response()->json(['message' => 'Road routing is not configured.'], 503);
        }

        $points = array_map(
            static fn (array $point): array => [(float) $point[0], (float) $point[1]],
            $validated['points'],
        );
        $knownPoints = ItineraryPlace::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude'])
            ->mapWithKeys(static fn (ItineraryPlace $place): array => [
                sprintf('%.6f,%.6f', (float) $place->latitude, (float) $place->longitude) => true,
            ]);
        foreach ($points as [$latitude, $longitude]) {
            if (! $knownPoints->has(sprintf('%.6f,%.6f', $latitude, $longitude))) {
                return response()->json(['message' => 'A route point is not part of the itinerary catalog.'], 422);
            }
        }
        $cacheKey = 'itinerary-road-route:'.hash('sha256', json_encode($points, JSON_THROW_ON_ERROR));

        try {
            $route = Cache::remember($cacheKey, now()->addDay(), function () use ($apiKey, $points): array {
                $graphHopperPoints = array_map(
                    static fn (array $point): array => [$point[1], $point[0]],
                    $points,
                );
                $response = Http::acceptJson()
                    ->timeout(20)
                    ->post('https://graphhopper.com/api/1/route?key='.rawurlencode($apiKey), [
                        'points' => $graphHopperPoints,
                        'profile' => 'car',
                        'points_encoded' => false,
                        'instructions' => false,
                    ]);

                if (! $response->successful()) {
                    throw new \RuntimeException('GraphHopper route request failed.');
                }

                $path = $response->json('paths.0');
                $coordinates = data_get($path, 'points.coordinates', data_get($path, 'points'));
                if (! is_array($coordinates) || count($coordinates) < 2) {
                    throw new \RuntimeException('GraphHopper returned no route geometry.');
                }

                return [
                    'coordinates' => array_values(array_map(
                        static fn (array $coordinate): array => [(float) $coordinate[1], (float) $coordinate[0]],
                        $coordinates,
                    )),
                    'distance_m' => (float) data_get($path, 'distance', 0),
                    'duration_ms' => (int) data_get($path, 'time', 0),
                ];
            });

            return response()->json($route)->header('Cache-Control', 'private, max-age=3600');
        } catch (Throwable) {
            return response()->json(['message' => 'Unable to calculate the road route.'], 502);
        }
    }
}
