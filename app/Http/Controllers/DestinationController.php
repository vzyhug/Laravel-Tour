<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\Hotel;
use App\Models\ItineraryPlace;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DestinationController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'between:1,100000']]);
        $destinations = Destination::query()->withCount(['places', 'hotels'])->with(['places.images'])
            ->when($data['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', '%'.trim($term).'%'))
            ->orderBy('name')->paginate(12)->withQueryString();

        return view('directory.destinations', compact('destinations'));
    }

    public function show(Destination $destination): View
    {
        $destination->load([
            'places.images', 'places.destinations', 'hotels.images', 'hotels.destination',
            'hotels.roomTypes' => fn ($rooms) => $rooms->where('is_active', true)->withMin(['inventory as min_nightly_price' => fn ($nights) => $nights->whereDate('stay_date', '>=', today())], 'nightly_price'),
        ]);

        $tours = Tour::active()->with(['province', 'category', 'images'])
            ->whereHas('schedules.stops.place.destinations', fn ($query) => $query->where('destinations.id', $destination->id))
            ->orderBy('tour_id')->get();

        return view('directory.destination', compact('destination', 'tours'));
    }

    public function places(Request $request): View
    {
        $filters = $this->filters($request, true);
        $records = $this->search(ItineraryPlace::query()->whereHas('destinations')->with(['destinations', 'images']), $filters, true)
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->orderBy('name')->paginate(9)->withQueryString();

        return view('directory.index', ['records' => $records, 'kind' => 'places', 'destinations' => Destination::orderBy('name')->get(), 'categories' => ItineraryPlace::CATEGORIES]);
    }

    public function hotels(Request $request): View
    {
        $filters = $this->filters($request);
        $request->validate(['sort' => ['nullable', Rule::in(['name'])]]);
        $query = $this->search(Hotel::with(['destination', 'images', 'roomTypes' => fn ($rooms) => $rooms->where('is_active', true)->withMin(['inventory as min_nightly_price' => fn ($nights) => $nights->whereDate('stay_date', '>=', today())], 'nightly_price')]), $filters);
        $query->orderBy('name');
        $records = $query->paginate(9)->withQueryString();
        $destinations = Destination::query()->whereHas('hotels')->withCount('hotels')->orderBy('name')->get();
        $featuredSlugs = ['vung-tau', 'ha-noi', 'da-nang', 'da-lat', 'phu-quoc', 'ha-long'];
        $featuredDestinations = $destinations->filter(fn (Destination $destination) => in_array($destination->slug, $featuredSlugs, true))
            ->sortBy(fn (Destination $destination) => array_search($destination->slug, $featuredSlugs, true))
            ->values();

        return view('directory.index', ['records' => $records, 'kind' => 'hotels', 'destinations' => $destinations, 'featuredDestinations' => $featuredDestinations, 'categories' => []]);
    }

    public function place(ItineraryPlace $place): View
    {
        $place->load(['destinations', 'images']);
        $hotels = Hotel::whereIn('destination_id', $place->destinations->modelKeys())->with([
            'images', 'destination',
            'roomTypes' => fn ($rooms) => $rooms->where('is_active', true)->withMin(['inventory as min_nightly_price' => fn ($nights) => $nights->whereDate('stay_date', '>=', today())], 'nightly_price'),
        ])->orderBy('name')->limit(4)->get();

        return view('directory.show', ['record' => $place, 'kind' => 'places', 'hotels' => $hotels]);
    }

    public function hotel(Hotel $hotel): View
    {
        $hotel->load(['destination', 'images', 'roomTypes' => fn ($query) => $query->where('is_active', true)->withMin(['inventory as min_nightly_price' => fn ($nights) => $nights->whereDate('stay_date', '>=', today())], 'nightly_price')]);
        $places = $hotel->destination->places()->with(['images', 'destinations'])->orderBy('name')->limit(6)->get();
        $tours = Tour::active()->with(['province', 'category', 'images'])
            ->whereHas('schedules.stops.place.destinations', fn ($query) => $query->where('destinations.id', $hotel->destination_id))
            ->orderBy('tour_id')->limit(3)->get();

        return view('directory.show', ['record' => $hotel, 'kind' => 'hotels', 'places' => $places, 'tours' => $tours]);
    }

    private function filters(Request $request, bool $places = false): array
    {
        return $request->validate([
            'page' => ['nullable', 'integer', 'between:1,100000'],
            'q' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', Rule::exists('destinations', 'slug')],
            'category' => $places ? ['nullable', Rule::in(array_keys(ItineraryPlace::CATEGORIES))] : ['prohibited'],
        ]);
    }

    private function search(Builder $query, array $filters, bool $places = false): Builder
    {
        return $query->when($filters['destination'] ?? null, fn ($query, $slug) => $query->whereHas($places ? 'destinations' : 'destination', fn ($destination) => $destination->where('slug', $slug)))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search->where('name', 'like', '%'.trim($term).'%')->orWhere('address', 'like', '%'.trim($term).'%')));
    }
}
