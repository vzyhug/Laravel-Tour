<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItineraryConsultation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItineraryConsultationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:submitted,quoted,accepted,rejected,closed']]);
        $consultations = ItineraryConsultation::with('itinerary.tour')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.itineraries.index', compact('consultations'));
    }

    public function show(ItineraryConsultation $consultation)
    {
        $consultation->load('itinerary.tour');

        return view('admin.itineraries.show', compact('consultation'));
    }

    public function update(Request $request, ItineraryConsultation $consultation)
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'], 'status' => ['required', 'in:quoted,closed'],
            'reply' => ['required', 'string', 'max:5000'],
            'quoted_total' => ['exclude_unless:status,quoted', 'required_if:status,quoted', 'integer', 'between:1,999999999999'],
            'valid_until' => ['exclude_unless:status,quoted', 'required_if:status,quoted', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$consultation->travel_date->toDateString()],
        ]);
        DB::transaction(function () use ($data, $consultation) {
            $locked = ItineraryConsultation::whereKey($consultation->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->version !== (int) $data['version'], 409, 'Yêu cầu đã được nhân viên khác cập nhật.');
            abort_unless(in_array($locked->status, ['submitted', 'quoted'], true), 422, 'Yêu cầu đã kết thúc, không thể sửa phản hồi hoặc báo giá.');
            $locked->update(['status' => $data['status'], 'reply' => $data['reply'],
                'quoted_total' => $data['status'] === 'quoted' ? $data['quoted_total'] : null,
                'valid_until' => $data['status'] === 'quoted' ? $data['valid_until'] : null,
                'employee_id' => session('employee_id'), 'version' => $locked->version + 1]);
        });

        return back()->with('success', 'Đã gửi phản hồi tư vấn cho khách.');
    }
}
