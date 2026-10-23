<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Models\BusTrip;
use App\Models\Customer;
use App\Models\ServiceBooking;
use App\Models\ServicePackage;
use App\Models\ServiceQuote;
use App\Models\TravelService;
use App\Services\ServiceAvailabilityService;
use App\Services\ServiceBookingService;
use App\Services\ServiceCheckoutService;
use App\Services\ServicePricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TravelServiceController extends Controller
{
    public function index(Request $request, string $type, ServicePricingService $pricing)
    {
        $service = ServiceType::from($type);
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'origin' => ['nullable', 'string', 'max:100'], 'destination' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.today('Asia/Ho_Chi_Minh')->toDateString()], 'passengers' => ['nullable', 'integer', 'between:1,16'], 'sort' => ['nullable', 'in:price_asc,price_desc'],
            'check_in' => ['nullable', 'required_with:check_out', 'date_format:Y-m-d', 'after_or_equal:'.today('Asia/Ho_Chi_Minh')->toDateString()],
            'check_out' => ['nullable', 'required_with:check_in', 'date_format:Y-m-d', 'after:check_in'],
            'rooms' => ['nullable', 'integer', 'between:1,5'], 'adults' => ['nullable', 'integer', 'between:1,20'], 'children' => ['nullable', 'integer', 'between:0,10'],
            'pickup_at' => ['nullable', 'required_with:return_at', 'date_format:Y-m-d\TH:i'], 'return_at' => ['nullable', 'required_with:pickup_at', 'date_format:Y-m-d\TH:i', 'after:pickup_at'],
            'visit_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.today('Asia/Ho_Chi_Minh')->toDateString()]]);
        if ($request->filled('origin') && $request->input('origin') === $request->input('destination')) {
            throw ValidationException::withMessages(['destination' => 'Điểm đến phải khác điểm đi.']);
        }
        $products = TravelService::where('type', $type)->where('is_active', true)->whereHas('supplier', fn ($q) => $q->where('is_active', true))
            ->with(['supplier', 'options.resource.trips.stops'])->whereHas('options', fn ($q) => $q->where('service_packages.is_active', true)->whereHas('resource', fn ($r) => $r->where('is_active', true)))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->input('q').'%')->orWhere('location', 'like', '%'.$request->input('q').'%')))
            ->when($request->filled('origin'), fn ($q) => $q->where('location', 'like', '%'.$request->input('origin').'%'))
            ->when($request->filled('destination'), fn ($q) => $q->where('location', 'like', '%'.$request->input('destination').'%'))
            ->orderBy('id')->get();
        $offers = collect();
        foreach ($products as $product) {
            $available = [];
            foreach ($product->options->where('is_active', true) as $option) {
                if (! $option->resource->is_active) {
                    continue;
                }
                if ($type === 'bus' && $request->filled('date')) {
                    $trips = $option->resource->trips->where('is_active', true)->filter(fn ($trip) => $trip->starts_at->copy()->timezone('Asia/Ho_Chi_Minh')->toDateString() === $request->input('date') && $trip->starts_at->isFuture());
                    if ($trips->isEmpty()) {
                        continue;
                    }
                }
                $price = null;
                if ($type === 'bus' && ($request->filled('date') || $request->filled('origin') || $request->filled('destination'))) {
                    $tripPrices = [];
                    foreach ($option->resource->trips->where('is_active', true)->filter(fn ($trip) => $trip->starts_at->isFuture()) as $trip) {
                        if ($request->filled('date') && $trip->starts_at->copy()->timezone('Asia/Ho_Chi_Minh')->toDateString() !== $request->input('date')) {
                            continue;
                        }
                        $from = $request->filled('origin') ? $trip->stops->first(fn ($s) => str_contains(mb_strtolower($s->name), mb_strtolower($request->input('origin')))) : $trip->stops->first();
                        $to = $request->filled('destination') ? $trip->stops->first(fn ($s) => str_contains(mb_strtolower($s->name), mb_strtolower($request->input('destination')))) : $trip->stops->last();
                        if (! $from || ! $to || $from->position >= $to->position) {
                            continue;
                        }
                        $busy = app(ServiceAvailabilityService::class)->active($option->resource, $from->time_at, $to->time_at)->pluck('seat')->all();
                        $free = array_values(array_diff($option->resource->attributes['seats'] ?? [], $busy));
                        $quantity = $request->integer('passengers', 1);
                        if (count($free) < $quantity) {
                            continue;
                        }
                        try {
                            $tripPrices[] = $pricing->calculate($option, ['trip_id' => $trip->id, 'boarding_stop_id' => $from->id, 'alighting_stop_id' => $to->id, 'seats' => array_slice($free, 0, $quantity)])['total'];
                        } catch (ValidationException) {
                            continue;
                        }
                    }
                    if (! $tripPrices) {
                        continue;
                    } $price = min($tripPrices);
                }
                $hasSearch = match ($type) {
                    'stay' => $request->filled(['check_in', 'check_out']), 'rental' => $request->filled(['pickup_at', 'return_at']), 'activity' => $request->filled('visit_date'), default => false
                };
                if ($hasSearch) {
                    $config = $request->all() + ['rooms' => 1, 'adults' => 1, 'children' => 0, 'passengers' => 1, 'slot' => $option->attributes['slots'][0] ?? '08:00'];
                    try {
                        $price = $pricing->calculate($option, $config)['total'];
                    } catch (ValidationException) {
                        continue;
                    }
                }
                $available[] = ['price' => $price ?? $option->price, 'is_total' => $price !== null];
            }
            if ($available) {
                $best = collect($available)->sortBy('price')->first();
                $offers->push(['product' => $product, ...$best]);
            }
        }
        $offers = ($request->input('sort') === 'price_desc' ? $offers->sortByDesc('price') : $offers->sortBy('price'))->values();

        return view('services.index', compact('service', 'offers'));
    }

    public function show(TravelService $product)
    {
        abort_unless($product->is_active && $product->supplier->is_active, 404);
        $product->load(['options.resource.trips.stops', 'resources']);
        $options = $product->options->filter(fn ($option) => $option->is_active && $option->resource->is_active);
        $service = ServiceType::from($product->type);

        return view('services.show', compact('product', 'options', 'service'));
    }

    public function quote(Request $request, ServicePackage $option, ServiceCheckoutService $checkout)
    {
        $quote = $checkout->quote((int) session('customer_id'), $option, $request->all());

        return redirect()->route('services.quotes.show', $quote);
    }

    public function quoteForm(ServiceQuote $quote, ServiceCheckoutService $checkout)
    {
        abort_unless($quote->customer_id === (int) session('customer_id'), 403);

        return view('services.checkout', ['quote' => $quote, 'customer' => Customer::findOrFail(session('customer_id')),
            'travelerCount' => $checkout->travelerCount($quote), 'checkoutKey' => old('checkout_key', (string) Str::uuid())]);
    }

    public function reserve(Request $request, ServiceQuote $quote, ServiceCheckoutService $checkout)
    {
        $order = $checkout->reserve((int) session('customer_id'), $quote, $request->all());

        return redirect()->route('services.orders.show', $order)->with('success', 'Đã giữ chỗ. Hãy kiểm tra thời hạn và hoàn tất thanh toán mô phỏng.');
    }

    public function orders(Request $request)
    {
        ServiceBooking::where('customer_id', session('customer_id'))->where('reservation_status', 'held')->where('expires_at', '<=', now())
            ->eachById(fn ($order) => app(ServiceBookingService::class)->expire($order));
        $orders = ServiceBooking::where('customer_id', session('customer_id'))->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))->latest()->paginate(12)->withQueryString();

        return view('services.orders', compact('orders'));
    }

    private function owned(ServiceBooking $order): void
    {
        abort_unless($order->customer_id === (int) session('customer_id'), 403);
    }

    public function order(ServiceBooking $order, ServiceBookingService $orders)
    {
        $this->owned($order);
        $orders->expire($order);

        return view('services.order', ['order' => $order->fresh()->load('payments', 'refund', 'voucher', 'events', 'inspections'), 'attemptKey' => (string) Str::uuid()]);
    }

    public function pay(Request $request, ServiceBooking $order, ServiceBookingService $orders)
    {
        $this->owned($order);
        $orders->pay($order, $request->all());

        return back()->with('success', 'Đã xử lý kịch bản thanh toán demo. Kiểm tra riêng trạng thái tiền và chứng từ bên dưới.');
    }

    public function cancel(Request $request, ServiceBooking $order, ServiceBookingService $orders)
    {
        $this->owned($order);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $orders->cancel($order, $data['reason']);

        return back()->with('success', 'Đã tiếp nhận thao tác hủy. Nếu đơn đã trả tiền, yêu cầu cần được duyệt trước khi hoàn mô phỏng.');
    }

    public function document(ServiceBooking $order, string $document)
    {
        $this->owned($order);
        abort_unless(in_array($document, ['voucher', 'receipt'], true), 404);
        if ($document === 'voucher') {
            abort_unless($order->voucher && in_array($order->reservation_status, ['confirmed', 'completed', 'cancellation_pending'], true)
                && in_array($order->voucher->status, ['issued', 'used'], true) && $order->payment_status === 'captured', 404);
        } else {
            abort_unless($order->moneyTransactions()->where('type', 'capture')->exists(), 404);
        }

        return view('services.document', compact('order', 'document'));
    }

    public function seats(Request $request, BusTrip $trip, ServiceAvailabilityService $inventory)
    {
        abort_unless($trip->is_active && $trip->resource->is_active && $trip->resource->product->is_active && $trip->resource->product->supplier->is_active, 404);
        $data = $request->validate(['from' => ['required', 'integer'], 'to' => ['required', 'integer']]);
        $from = $trip->stops->firstWhere('id', $data['from']);
        $to = $trip->stops->firstWhere('id', $data['to']);
        abort_unless($from && $to && $from->position < $to->position, 422);
        $occupied = $inventory->active($trip->resource, $from->time_at, $to->time_at)->with('order')->get()->keyBy('seat');
        $seats = collect($trip->resource->attributes['seats'] ?? [])->map(fn ($seat) => [
            'label' => $seat, 'status' => ! $occupied->has($seat) ? 'available' : ($occupied[$seat]->order->reservation_status === 'held' ? 'held' : 'sold'),
        ]);

        return response()->json(['seats' => $seats, 'timezone' => 'Asia/Ho_Chi_Minh']);
    }

    public function callback(Request $request, ServiceBookingService $orders)
    {
        $signature = hash_hmac('sha256', $request->getContent(), config('travel_services.demo_callback_secret'));
        abort_unless(hash_equals($signature, (string) $request->header('X-Demo-Signature')), 403);
        $orders->processPaymentEvent($request->json()->all());

        return response()->json(['received' => true]);
    }
}
