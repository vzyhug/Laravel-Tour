<?php

namespace App\Http\Controllers;

use App\Models\CustomerItinerary;
use App\Models\ItineraryConsultation;
use App\Models\Tour;
use App\Services\TourItineraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TourItineraryController extends Controller
{
    public function update(Request $request, Tour $tour, TourItineraryService $service)
    {
        return response()->json($service->save($tour, (int) session('customer_id'), $request->all()));
    }

    public function consult(Request $request, Tour $tour, TourItineraryService $service)
    {
        abort_unless($tour->is_active, 404);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'], 'request_key' => ['required', 'uuid'],
            'travel_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'adults' => ['required', 'integer', 'between:1,50'],
            'contact_name' => ['required', 'string', 'max:100'], 'contact_phone' => ['required', 'regex:/^[0-9+ ().-]{8,30}$/'],
            'contact_email' => ['required', 'email', 'max:255'], 'message' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['children'] = 0; // Legacy column retained for previously submitted consultations.
        $consultation = DB::transaction(function () use ($data, $tour, $service) {
            $plan = CustomerItinerary::where('customer_id', session('customer_id'))->where('tour_id', $tour->getKey())->lockForUpdate()->firstOrFail();
            $existing = ItineraryConsultation::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->itinerary_id === $plan->id, 409);

                return $existing;
            }
            abort_if((int) $plan->version !== (int) $data['version'], 409, 'Lịch đã thay đổi. Hãy lưu và xem lại trước khi gửi.');
            // Snapshot never follows subsequent edits to the customer's saved plan.
            $payload = $service->payload($tour, (int) session('customer_id'));

            return $plan->consultations()->create([
                ...collect($data)->except('version')->all(), 'itinerary_version' => $plan->version,
                'snapshot' => ['tour' => $tour->title, 'days' => $payload['days'], 'items' => $payload['items'], 'places' => $payload['places']],
            ]);
        });

        return response()->json(['message' => 'Đã gửi lịch trình cho nhân viên tư vấn.', 'id' => $consultation->id,
            'url' => route('itinerary-consultations.show', $consultation)]);
    }

    public function consultation(ItineraryConsultation $consultation)
    {
        $consultation->load('itinerary');
        abort_unless($consultation->itinerary->customer_id === (int) session('customer_id'), 403);

        return view('itineraries.consultation', compact('consultation'));
    }

    public function index()
    {
        $plans = CustomerItinerary::where('customer_id', session('customer_id'))->with(['tour.images', 'consultations'])->latest('updated_at')->paginate(12);

        return view('itineraries.index', compact('plans'));
    }
}
