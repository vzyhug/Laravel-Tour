<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\PlatformOption;
use App\Models\PlatformOrder;
use App\Models\PlatformProduct;
use App\Models\PlatformResource;
use App\Models\PlatformSupplier;
use App\Models\PlatformTrip;
use App\Services\PlatformInventory;
use App\Services\PlatformOrders;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformController extends Controller
{
    public function products(Request $request)
    {
        $products = PlatformProduct::with('supplier')->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))->latest()->paginate(20)->withQueryString();

        return view('admin.platform.products', ['products' => $products, 'suppliers' => PlatformSupplier::all()]);
    }

    public function supplier(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:40', 'unique:platform_suppliers,code'], 'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255']]);
        PlatformSupplier::create($data + ['is_active' => true]);

        return back()->with('success', 'Đã thêm nhà cung cấp demo.');
    }

    public function saveProduct(Request $request, ?PlatformProduct $product = null)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:platform_suppliers,id'], 'type' => ['required', Rule::enum(ServiceType::class)],
            'name' => ['required', 'string', 'max:255'], 'location' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'],
            'image_path' => ['nullable', 'regex:#^images/tours/demo/[a-z0-9-]+\.jpg$#'],
            'amenities' => ['nullable', 'string', 'max:1000'], 'stars' => ['nullable', 'integer', 'between:0,5'],
        ]);
        if ($product?->exists && $product->type !== $data['type']) {
            $this->error('type', 'Không đổi loại dịch vụ của sản phẩm đã tạo; hãy tạo sản phẩm mới.');
        }
        $data['attributes'] = ['amenities' => array_values(array_filter(array_map('trim', explode(',', $data['amenities'] ?? '')))), 'stars' => (int) ($data['stars'] ?? 0), 'demo' => true];
        unset($data['amenities'], $data['stars']);
        $data['is_active'] = $request->boolean('is_active');
        if ($product?->exists) {
            $product->update($data);
        } else {
            $product = PlatformProduct::create($data + ['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);
        }

        return redirect()->route('admin.platform.products.edit', $product)->with('success', 'Đã lưu sản phẩm. Lịch sử đơn cũ giữ snapshot riêng.');
    }

    public function edit(PlatformProduct $product)
    {
        return view('admin.platform.edit', ['product' => $product->load('resources.options', 'resources.trips.stops'), 'suppliers' => PlatformSupplier::all()]);
    }

    public function resource(Request $request, PlatformProduct $product)
    {
        $data = $request->validate(['resource_id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'between:1,100'], 'buffer_minutes' => ['required', 'integer', 'between:0,240'],
            'seats' => ['nullable', 'string', 'max:1000']]);
        $kind = match ($product->type) {
            'stay' => 'room_pool', 'bus' => 'bus', 'transfer' => 'vehicle_pool', 'rental' => 'vehicle', 'activity' => 'ticket_pool'
        };
        $seats = $product->type === 'bus' ? array_values(array_filter(array_map('trim', explode(',', $data['seats'] ?? '')))) : [];
        if ($product->type === 'bus' && (count($seats) !== (int) $data['capacity'] || count(array_unique($seats)) !== count($seats) || collect($seats)->contains(fn ($seat) => ! preg_match('/^[A-Za-z0-9-]{1,10}$/', $seat)))) {
            $this->error('seats', 'Số nhãn ghế duy nhất phải bằng sức chứa, mỗi nhãn gồm chữ/số/gạch nối.');
        }
        if ($product->type === 'rental' && (int) $data['capacity'] !== 1) {
            $this->error('capacity', 'Mỗi tài nguyên xe thuê đại diện một xe, sức chứa tồn phải là 1.');
        }
        DB::transaction(function () use ($product, $request, $data, $kind, $seats) {
            $resource = ! empty($data['resource_id']) ? $product->resources()->lockForUpdate()->findOrFail($data['resource_id']) : new PlatformResource(['product_id' => $product->id]);
            if ($resource->exists) {
                $last = $resource->allocations()->whereNull('released_at')->max('ends_at');
                if ($last && app(PlatformInventory::class)->peakUsage($resource, now(), Carbon::parse($last)) > $data['capacity']) {
                    $this->error('capacity', 'Không thể giảm quỹ tồn dưới lượng đã giữ/xác nhận.');
                }
            }
            if ($resource->exists && $product->type === 'bus' && $resource->allocations()->whereNull('released_at')->where('ends_at', '>', now())->whereNotIn('seat', $seats)->exists()) {
                $this->error('seats', 'Không thể xóa nhãn ghế đang có đơn sử dụng.');
            }
            $resource->fill(['name' => $data['name'], 'kind' => $kind, 'capacity' => $data['capacity'], 'buffer_minutes' => $data['buffer_minutes'], 'attributes' => ['seats' => $seats], 'is_active' => $request->boolean('is_active')])->save();
        });

        return back()->with('success', 'Đã lưu quỹ tồn/tài nguyên. Các gói cùng tài nguyên dùng chung sức chứa.');
    }

    public function option(Request $request, PlatformProduct $product)
    {
        $option = $request->filled('option_id') ? PlatformOption::whereHas('resource', fn ($q) => $q->where('product_id', $product->id))->findOrFail($request->integer('option_id')) : null;
        $data = $request->validate([
            'resource_id' => ['required', 'integer', Rule::exists('platform_resources', 'id')->where('product_id', $product->id)],
            'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('platform_options', 'code')->ignore($option?->id)],
            'name' => ['required', 'string', 'max:255'], 'price' => ['required', 'integer', 'between:0,100000000'],
            'child_price' => ['nullable', 'integer', 'between:0,100000000'], 'max_adults' => ['required', 'integer', 'between:1,20'],
            'max_children' => ['required', 'integer', 'between:0,10'], 'occupancy' => ['required', 'integer', 'between:1,30'],
            'cancel_before_hours' => ['required', 'integer', 'between:0,720'], 'refund_percent' => ['required', 'integer', 'between:0,100'],
            'child_min_age' => ['required', 'integer', 'between:0,17'], 'child_max_age' => ['required', 'integer', 'between:0,17', 'gte:child_min_age'],
            'mode' => ['nullable', 'in:self_drive,with_driver'], 'deposit' => ['nullable', 'integer', 'between:0,100000000'],
            'min_driver_age' => ['nullable', 'integer', 'between:18,70'], 'max_bags' => ['nullable', 'integer', 'between:0,20'],
            'airport' => ['nullable', 'string', 'max:100'], 'meeting_point' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'between:15,720'], 'slots' => ['nullable', 'string', 'max:300'], 'zones' => ['nullable', 'string', 'max:2000'],
            'service_fee' => ['nullable', 'integer', 'between:0,10000000'], 'terms' => ['required', 'string', 'max:4000'],
        ]);
        if ($option && $option->resource_id !== (int) $data['resource_id']) {
            $this->error('resource_id', 'Không chuyển quỹ tồn của gói đã tạo. Hãy tạo gói khác.');
        }
        $zones = [];
        foreach (preg_split('/\R/', $data['zones'] ?? '') as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = explode('|', $line);
            if (count($parts) !== 2 || trim($parts[0]) === '' || ! ctype_digit(trim($parts[1])) || (int) $parts[1] > 10000000) {
                $this->error('zones', 'Mỗi dòng vùng phục vụ: Tên vùng|Phụ phí VND không âm.');
            }
            $zones[trim($parts[0])] = (int) trim($parts[1]);
        }
        $slots = array_values(array_filter(array_map('trim', explode(',', $data['slots'] ?? ''))));
        if ($product->type === 'activity' && (! $slots || collect($slots)->contains(fn ($slot) => ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $slot)))) {
            $this->error('slots', 'Cần ít nhất một khung giờ theo HH:mm.');
        }
        if ($product->type === 'transfer' && (! $zones || empty($data['airport']) || empty($data['meeting_point']))) {
            $this->error('zones', 'Đưa đón cần sân bay, điểm gặp và ít nhất một vùng phục vụ.');
        }
        if ($product->type === 'rental' && empty($data['mode'])) {
            $this->error('mode', 'Chọn tự lái hoặc có tài xế.');
        }
        $attributes = array_intersect_key($data, array_flip(['mode', 'deposit', 'min_driver_age', 'max_bags', 'airport', 'meeting_point', 'duration_minutes', 'service_fee', 'child_min_age', 'child_max_age', 'terms']));
        $attributes += ['slots' => $slots, 'zones' => $zones, 'breakfast' => $request->boolean('breakfast'), 'check_in_time' => '14:00', 'check_out_time' => '12:00'];
        $values = array_intersect_key($data, array_flip(['resource_id', 'code', 'name', 'price', 'child_price', 'max_adults', 'max_children', 'occupancy']));
        $values += ['attributes' => $attributes, 'policy' => ['cancel_allowed' => $request->boolean('cancel_allowed'), 'cancel_before_hours' => (int) $data['cancel_before_hours'], 'refund_percent' => (int) $data['refund_percent']], 'is_active' => $request->boolean('is_active')];
        DB::transaction(function () use ($data, $option, $values) {
            PlatformResource::lockForUpdate()->findOrFail($data['resource_id']);
            if ($option) {
                $option->update($values);
            } else {
                PlatformOption::create($values);
            }
        });

        return back()->with('success', 'Đã lưu gói và chính sách. Báo giá cũ sẽ được kiểm tra lại khi đặt.');
    }

    public function calendar(Request $request, PlatformResource $resource, PlatformInventory $inventory)
    {
        $data = $request->validate(['day_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'day_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:day_from'],
            'capacity' => ['required', 'integer', 'min:0', 'max:'.$resource->capacity], 'price_adjustment' => ['required', 'integer', 'between:-100000000,100000000']]);
        $from = Carbon::parse($data['day_from'], 'Asia/Ho_Chi_Minh');
        $to = Carbon::parse($data['day_to'], 'Asia/Ho_Chi_Minh');
        if ($from->diffInDays($to) > 180) {
            $this->error('day_to', 'Mỗi lần cập nhật tối đa 181 ngày.');
        }
        DB::transaction(function () use ($resource, $inventory, $from, $to, $data, $request) {
            $locked = PlatformResource::lockForUpdate()->findOrFail($resource->id);
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                if ($inventory->peakUsage($locked, $day->copy()->utc(), $day->copy()->addDay()->utc()) > $data['capacity']) {
                    $this->error('capacity', 'Không thể giảm sức chứa dưới lượng đã giữ/xác nhận.');
                }
                $locked->inventoryDays()->updateOrCreate(['day' => $day->toDateString()], ['capacity' => $data['capacity'], 'price_adjustment' => $data['price_adjustment'], 'is_closed' => $request->boolean('is_closed')]);
            }
        });

        return back()->with('success', 'Đã cập nhật lịch tồn/giá; đơn cũ giữ nguyên thông tin đã chốt.');
    }

    public function trip(Request $request, PlatformResource $resource)
    {
        abort_unless($resource->product->type === 'bus', 422);
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:60', 'unique:platform_trips,code'], 'stops' => ['required', 'string', 'max:6000']]);
        $stops = [];
        foreach (preg_split('/\R/', trim($data['stops'])) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 3 || strlen($parts[0]) > 255 || strlen($parts[1]) > 255 || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $parts[2])) {
                $this->error('stops', 'Mỗi dòng: Tên trạm|Địa chỉ|YYYY-MM-DDTHH:mm.');
            }
            try {
                $time = Carbon::createFromFormat('Y-m-d\TH:i', $parts[2], 'Asia/Ho_Chi_Minh')->startOfMinute()->utc();
            } catch (\Throwable) {
                $this->error('stops', 'Ngày giờ của trạm không hợp lệ.');
            }
            if ($time->lte(now()) || ($stops && $time->lte($stops[count($stops) - 1]['time_at']))) {
                $this->error('stops', 'Giờ các trạm phải ở tương lai và tăng dần theo tuyến.');
            }
            $stops[] = ['position' => count($stops), 'name' => $parts[0], 'address' => $parts[1], 'time_at' => $time];
        }
        if (count($stops) < 2 || count($stops) > 20) {
            $this->error('stops', 'Chuyến xe cần từ 2 đến 20 trạm.');
        }
        DB::transaction(function () use ($resource, $data, $stops) {
            PlatformResource::lockForUpdate()->findOrFail($resource->id);
            $start = $stops[0]['time_at'];
            $end = $stops[count($stops) - 1]['time_at'];
            if ($resource->trips()->where('is_active', true)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                $this->error('stops', 'Xe đã có chuyến trùng thời gian.');
            }
            $trip = $resource->trips()->create(['code' => $data['code'], 'starts_at' => $start, 'ends_at' => $end, 'is_active' => true]);
            $trip->stops()->createMany($stops);
        });

        return back()->with('success', 'Đã tạo chuyến và thứ tự trạm.');
    }

    public function toggleTrip(Request $request, PlatformTrip $trip)
    {
        DB::transaction(function () use ($request, $trip) {
            PlatformResource::lockForUpdate()->findOrFail($trip->resource_id);
            if ($request->boolean('is_active') && PlatformTrip::where('resource_id', $trip->resource_id)->whereKeyNot($trip->id)->where('is_active', true)->where('starts_at', '<', $trip->ends_at)->where('ends_at', '>', $trip->starts_at)->exists()) {
                $this->error('trip', 'Xe đã có chuyến khác trùng thời gian.');
            }
            $trip->update(['is_active' => $request->boolean('is_active')]);
        });

        return back()->with('success', 'Đã đổi trạng thái mở bán chuyến; đơn đã đặt vẫn được lưu.');
    }

    public function orders(Request $request)
    {
        $orders = PlatformOrder::with('customer')->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('reservation_status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('reference', 'like', '%'.$request->input('q').'%'))->latest()->paginate(20)->withQueryString();

        return view('admin.platform.orders', compact('orders'));
    }

    public function order(PlatformOrder $order, PlatformOrders $orders)
    {
        $orders->expire($order);

        return view('admin.platform.order', ['order' => $order->fresh()->load('payments', 'refund', 'voucher', 'events', 'inspections', 'moneyTransactions')]);
    }

    public function action(Request $request, PlatformOrder $order, PlatformOrders $orders)
    {
        $data = $request->validate(['action' => ['required', 'in:resolve_success,resolve_failed,approve_cancel,reject_cancel,refund,refund_overpayment,redeem,inspect,complete'],
            'note' => ['required_if:action,approve_cancel,reject_cancel', 'nullable', 'string', 'max:1000']]);
        if (in_array($data['action'], ['refund', 'refund_overpayment'], true)) {
            abort_unless(session('employee_role') === 'Admin', 403);
        }
        if ($data['action'] === 'redeem') {
            $request->validate(['quantity' => ['required', 'integer', 'between:1,20'], 'operation_key' => ['required', 'uuid']]);
        }
        match ($data['action']) {
            'resolve_success' => $orders->resolve($order, 'success'), 'resolve_failed' => $orders->resolve($order, 'failed'),
            'approve_cancel' => $orders->decideCancellation($order, true, $data['note']), 'reject_cancel' => $orders->decideCancellation($order, false, $data['note']),
            'refund' => $orders->refund($order),
            'refund_overpayment' => $orders->refundOverpayment($order),
            'redeem' => $orders->redeem($order, (int) $request->input('quantity'), (string) $request->input('operation_key')),
            'inspect' => $orders->inspect($order, $request->all()), 'complete' => $orders->complete($order),
        };

        return back()->with('success', 'Đã xử lý thao tác và ghi lịch sử. Mọi dòng tiền/chứng từ ở đây là demo.');
    }

    private function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
