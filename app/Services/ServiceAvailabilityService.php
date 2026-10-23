<?php

namespace App\Services;

use App\Models\ServiceAllocation;
use App\Models\ServiceInventoryDay;
use App\Models\ServiceResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ServiceAvailabilityService
{
    public function active(ServiceResource $resource, Carbon $start, Carbon $end): Builder
    {
        return ServiceAllocation::where('resource_id', $resource->id)->whereNull('released_at')
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->whereHas('order', fn ($q) => $q->whereNotIn('reservation_status', ['cancelled', 'expired'])
                ->where(fn ($w) => $w->where('reservation_status', '!=', 'held')->orWhere('expires_at', '>', now())));
    }

    public function peakUsage(ServiceResource $resource, Carbon $start, Carbon $end): int
    {
        $points = [];
        foreach ($this->active($resource, $start, $end)->get() as $allocation) {
            $points[] = [max($start->timestamp, $allocation->starts_at->timestamp), $allocation->quantity];
            $points[] = [min($end->timestamp, $allocation->ends_at->timestamp), -$allocation->quantity];
        }
        usort($points, fn ($a, $b) => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));
        $used = $peak = 0;
        foreach ($points as [, $quantity]) {
            $used += $quantity;
            $peak = max($peak, $used);
        }

        return $peak;
    }

    public function assertAvailable(ServiceResource $resource, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $start = Carbon::parse($allocation['starts_at']);
            $end = Carbon::parse($allocation['ends_at']);
            $day = $start->copy()->timezone('Asia/Ho_Chi_Minh')->startOfDay();
            $lastDay = $end->copy()->subSecond()->timezone('Asia/Ho_Chi_Minh')->startOfDay();
            while ($day->lte($lastDay)) {
                $inventory = ServiceInventoryDay::where('resource_id', $resource->id)->whereDate('day', $day->toDateString())->first();
                if (! $inventory || $inventory->is_closed) {
                    $this->unavailable();
                }
                $partStart = $start->copy()->max($day->copy()->utc());
                $partEnd = $end->copy()->min($day->copy()->addDay()->utc());
                $requested = array_sum(array_map(fn ($item) => Carbon::parse($item['starts_at'])->lt($partEnd) && Carbon::parse($item['ends_at'])->gt($partStart) ? $item['quantity'] : 0, $allocations));
                if ($this->peakUsage($resource, $partStart, $partEnd) + $requested > min($inventory->capacity, $resource->capacity)) {
                    $this->unavailable();
                }
                $day->addDay();
            }
            if (isset($allocation['seat']) && $this->active($resource, $start, $end)->where('seat', $allocation['seat'])->exists()) {
                $this->unavailable('Ghế đã có người giữ hoặc mua trên đoạn tuyến này. Vui lòng chọn ghế khác.');
            }
        }
    }

    private function unavailable(string $message = 'Không còn đủ tồn cho toàn bộ thời gian đã chọn hoặc lịch chưa mở bán. Vui lòng đổi ngày/gói.'): never
    {
        throw ValidationException::withMessages(['availability' => $message]);
    }
}
