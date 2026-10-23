<?php

namespace App\Services;

use App\Models\PlatformEvent;
use App\Models\PlatformOrder;
use App\Models\PlatformPayment;
use App\Models\PlatformPaymentEvent;
use App\Models\PlatformResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformOrders
{
    public function expire(PlatformOrder $order): bool
    {
        return DB::transaction(function () use ($order) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            if ($locked->reservation_status !== 'held' || $locked->expires_at->gt(now()) || in_array($locked->payment_status, ['captured', 'partially_refunded', 'refunded'], true)) {
                return false;
            }
            $this->lockResource($locked);
            $locked->releaseInventory();
            $locked->update(['reservation_status' => 'expired', 'fulfillment_status' => 'void']);
            $locked->recordEvent('hold_expired');

            return true;
        });
    }

    public function pay(PlatformOrder $order, array $input): PlatformOrder
    {
        $data = Validator::make($input, [
            'attempt_key' => ['required', 'uuid'], 'method' => ['required', 'in:bank_transfer,momo,card,cash'],
            'result' => ['required', 'in:success,failed,pending'], 'supplier_result' => ['required', 'in:success,failed,unknown'],
        ])->validate();
        $this->expire($order);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($order, $data, $hash) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            $previous = PlatformPayment::where('attempt_key', $data['attempt_key'])->lockForUpdate()->first();
            if ($previous) {
                if ($previous->order_id !== $locked->id || $previous->request_hash !== $hash) {
                    $this->error('payment', 'Mã lần thanh toán đã được dùng với dữ liệu khác.');
                }

                return $locked;
            }
            if ($locked->reservation_status !== 'held' || $locked->expires_at->lte(now()) || $locked->service_starts_at->lte(now())) {
                $this->error('payment', 'Đơn không còn thời hạn hoặc không còn đủ điều kiện thanh toán.');
            }
            if ($locked->payment_status === 'pending') {
                $this->error('payment', 'Một lần thanh toán đang được kiểm tra. Vui lòng chờ đối soát trước khi thử thêm.');
            }
            $payment = $locked->payments()->create([
                'attempt_key' => $data['attempt_key'], 'request_hash' => $hash, 'reference' => 'DEMO-'.Str::upper(Str::random(20)),
                'method' => $data['method'], 'amount' => $locked->total, 'currency' => 'VND', 'status' => 'pending', 'provider' => 'demo',
            ]);
            $locked->update(['payment_status' => 'pending']);
            $locked->recordEvent('payment_pending_demo', ['reference' => $payment->reference]);
            if ($data['result'] !== 'pending') {
                $this->processPaymentEvent([
                    'event_id' => (string) Str::uuid(), 'payment_reference' => $payment->reference, 'amount' => $payment->amount,
                    'currency' => 'VND', 'result' => $data['result'], 'supplier_result' => $data['supplier_result'], 'occurred_at' => now()->toIso8601String(),
                ]);
            }

            return $locked->fresh();
        });
    }

    /** Only the demo adapter or an authenticated HMAC callback may invoke this handler. */
    public function processPaymentEvent(array $input): void
    {
        $data = Validator::make($input, [
            'event_id' => ['required', 'uuid'], 'payment_reference' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'integer', 'min:0'], 'currency' => ['required', 'string', 'size:3'],
            'result' => ['required', 'in:success,failed'], 'supplier_result' => ['required', 'in:success,failed,unknown'],
            'occurred_at' => ['required', 'date'],
        ])->validate();
        $paymentId = PlatformPayment::where('reference', $data['payment_reference'])->value('id');
        if (! $paymentId) {
            $this->error('payment', 'Không tìm thấy tham chiếu thanh toán demo.');
        }
        $orderId = PlatformPayment::whereKey($paymentId)->value('order_id');
        DB::transaction(function () use ($data, $paymentId, $orderId) {
            $order = PlatformOrder::lockForUpdate()->findOrFail($orderId);
            $payment = PlatformPayment::lockForUpdate()->findOrFail($paymentId);
            $digest = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $old = PlatformPaymentEvent::where('event_id', $data['event_id'])->lockForUpdate()->first();
            if ($old) {
                if ($old->payload_digest !== $digest) {
                    $this->error('payment', 'Event ID đã được dùng với nội dung khác.');
                }

                return;
            }
            $event = PlatformPaymentEvent::create(['event_id' => $data['event_id'], 'payment_reference' => $payment->reference,
                'payload_digest' => $digest, 'status' => 'received', 'occurred_at' => Carbon::parse($data['occurred_at'])->utc(), 'received_at' => now()]);
            if ($payment->status === 'captured') {
                $event->update(['status' => 'ignored']);

                return;
            }
            if ($payment->amount !== $order->total || $data['amount'] !== $payment->amount || $data['currency'] !== $payment->currency
                || Carbon::parse($data['occurred_at'])->gt(now()->addMinutes(5))) {
                $event->update(['status' => 'mismatch']);
                $order->recordEvent('payment_mismatch', ['reference' => $payment->reference]);

                return;
            }
            if ($data['result'] === 'failed') {
                $payment->update(['status' => 'failed']);
                if (! in_array($order->payment_status, ['captured', 'partially_refunded', 'refunded'], true)) {
                    $order->update(['payment_status' => 'failed']);
                }
                $order->recordEvent('payment_failed_demo');
                $event->update(['status' => 'processed']);

                return;
            }
            $payment->update(['status' => 'captured']);
            $order->moneyTransactions()->create(['operation_key' => 'capture:'.$payment->reference, 'type' => 'capture', 'amount' => $payment->amount, 'currency' => 'VND', 'occurred_at' => now()]);
            if (in_array($order->payment_status, ['captured', 'partially_refunded', 'refunded'], true)) {
                $order->update(['reconciliation_status' => 'overpaid']);
                $order->recordEvent('overpayment_detected', ['amount' => $payment->amount]);
                $event->update(['status' => 'processed']);

                return;
            }
            $order->update(['payment_status' => 'captured']);
            $order->recordEvent('payment_captured_demo', ['amount' => $payment->amount]);
            if ($order->reservation_status !== 'held' || $order->expires_at->lte(now()) || $order->service_starts_at->lte(now())) {
                $this->lockResource($order);
                $order->releaseInventory();
                $order->update(['reservation_status' => 'manual_review', 'fulfillment_status' => 'pending']);
                $order->recordEvent('late_payment_manual_review');
                $this->compensation($order, 'Thanh toán về sau hạn hoặc sau khi đơn đã đóng.');
            } else {
                $this->fulfill($order, $data['supplier_result']);
            }
            $event->update(['status' => 'processed']);
        });
    }

    public function resolve(PlatformOrder $order, string $result): void
    {
        if (! in_array($result, ['success', 'failed'], true)) {
            $this->error('result', 'Kết quả không hợp lệ.');
        }
        $payment = $order->payments()->where('status', 'pending')->latest('id')->first();
        if ($payment) {
            $this->processPaymentEvent(['event_id' => (string) Str::uuid(), 'payment_reference' => $payment->reference, 'amount' => $payment->amount,
                'currency' => $payment->currency, 'result' => $result, 'supplier_result' => 'success', 'occurred_at' => now()->toIso8601String()]);

            return;
        }
        DB::transaction(function () use ($order, $result) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            if ($locked->reservation_status !== 'confirming' || $locked->payment_status !== 'captured') {
                $this->error('status', 'Đơn không chờ xác nhận nhà cung cấp.');
            }
            if ($locked->service_starts_at->lte(now())) {
                $this->compensation($locked, 'Quá thời gian sử dụng khi đối soát nhà cung cấp.');

                return;
            }
            $this->fulfill($locked, $result);
        });
    }

    private function fulfill(PlatformOrder $order, string $result): void
    {
        if ($result === 'unknown') {
            $order->update(['reservation_status' => 'confirming', 'fulfillment_status' => 'pending']);
            $order->recordEvent('supplier_unknown_demo');

            return;
        }
        if ($result === 'failed') {
            $this->compensation($order, 'Nhà cung cấp mô phỏng xác nhận không phục vụ được.');

            return;
        }
        $uses = $order->type === 'activity' ? $order->configuration['adults'] + $order->configuration['children'] : 1;
        $order->voucher()->firstOrCreate([], ['code' => (string) Str::uuid(), 'remaining_uses' => $uses, 'status' => 'issued',
            'valid_from' => $order->service_starts_at, 'valid_until' => $order->service_ends_at]);
        $order->update(['reservation_status' => 'confirmed', 'fulfillment_status' => 'issued']);
        $order->recordEvent('voucher_issued_demo');
    }

    private function compensation(PlatformOrder $order, string $reason): void
    {
        $this->lockResource($order);
        $order->releaseInventory();
        $order->voucher()->update(['status' => 'void']);
        $order->update(['reservation_status' => 'cancelled', 'fulfillment_status' => 'void']);
        $order->refund()->firstOrCreate([], ['reason' => $reason, 'amount' => $order->total, 'cancellation_status' => 'approved', 'refund_status' => 'approved']);
        $order->recordEvent('compensation_required', ['amount' => $order->total]);
    }

    public function cancel(PlatformOrder $order, string $reason): void
    {
        $reason = Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:1000']])->validate()['reason'];
        $this->expire($order);
        DB::transaction(function () use ($order, $reason) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            if (! in_array($locked->reservation_status, ['held', 'confirmed'], true)) {
                $this->error('cancel', 'Đơn không còn ở trạng thái được gửi yêu cầu hủy.');
            }
            if ($locked->payment_status === 'pending') {
                $this->error('cancel', 'Thanh toán đang đối soát. Hãy chờ kết quả trước khi hủy.');
            }
            if ($locked->payment_status !== 'captured') {
                $this->lockResource($locked);
                $locked->releaseInventory();
                $locked->update(['reservation_status' => 'cancelled', 'fulfillment_status' => 'void']);
                $locked->recordEvent('cancelled_unpaid');

                return;
            }
            $policy = $locked->snapshot['policy'];
            $deadline = $locked->service_starts_at->copy()->subHours($policy['cancel_before_hours'] ?? 24);
            if (! ($policy['cancel_allowed'] ?? true) || now()->gt($deadline) || $locked->fulfillment_status === 'in_use') {
                $this->error('cancel', 'Đã quá hạn hủy hoặc gói không cho phép hủy trong trường hợp này.');
            }
            $amount = intdiv($locked->total * (int) ($policy['refund_percent'] ?? 0), 100);
            $request = $locked->refund()->firstOrCreate([], ['reason' => $reason, 'amount' => $amount, 'cancellation_status' => 'requested', 'refund_status' => 'requested']);
            if ($request->cancellation_status !== 'requested') {
                $this->error('cancel', 'Yêu cầu hủy đã có quyết định.');
            }
            $locked->update(['reservation_status' => 'cancellation_pending']);
            $locked->recordEvent('cancellation_requested', ['refund_quote' => $amount]);
        });
    }

    public function decideCancellation(PlatformOrder $order, bool $approve, string $note): void
    {
        DB::transaction(function () use ($order, $approve, $note) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            $refund = $locked->refund()->lockForUpdate()->firstOrFail();
            if ($refund->cancellation_status !== 'requested') {
                $this->error('status', 'Yêu cầu đã có quyết định.');
            }
            if ($approve) {
                $this->lockResource($locked);
                $locked->releaseInventory();
                $locked->voucher()->update(['status' => 'void']);
                $locked->update(['reservation_status' => 'cancelled', 'fulfillment_status' => 'void']);
                $refund->update(['cancellation_status' => 'approved', 'refund_status' => $refund->amount > 0 ? 'approved' : 'not_due', 'decision_note' => $note]);
                $locked->recordEvent('cancellation_approved', ['amount' => $refund->amount]);
            } else {
                $refund->update(['cancellation_status' => 'rejected', 'refund_status' => 'rejected', 'decision_note' => $note]);
                $locked->update(['reservation_status' => 'confirmed']);
                $locked->recordEvent('cancellation_rejected');
            }
        });
    }

    public function refund(PlatformOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            $refund = $locked->refund()->lockForUpdate()->firstOrFail();
            if ($refund->refund_status === 'succeeded') {
                return;
            }
            if ($refund->cancellation_status !== 'approved' || $refund->refund_status !== 'approved') {
                $this->error('refund', 'Khoản hoàn chưa được duyệt.');
            }
            $captured = (int) $locked->moneyTransactions()->where('type', 'capture')->sum('amount');
            $returned = (int) $locked->moneyTransactions()->whereIn('type', ['refund', 'overpayment_refund'])->sum('amount');
            if ($refund->amount > $captured - $returned) {
                $this->error('refund', 'Số tiền hoàn vượt số đã thu còn lại.');
            }
            $locked->moneyTransactions()->create(['operation_key' => 'refund:'.$refund->id, 'type' => 'refund', 'amount' => $refund->amount, 'currency' => 'VND', 'occurred_at' => now()]);
            $refund->update(['refund_status' => 'succeeded']);
            $locked->update(['payment_status' => $returned + $refund->amount === $captured ? 'refunded' : 'partially_refunded']);
            $locked->recordEvent('refund_succeeded_demo', ['amount' => $refund->amount]);
        });
    }

    public function refundOverpayment(PlatformOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            $captured = (int) $locked->moneyTransactions()->where('type', 'capture')->sum('amount');
            $extraReturned = (int) $locked->moneyTransactions()->where('type', 'overpayment_refund')->sum('amount');
            $extra = $captured - $locked->total - $extraReturned;
            if ($extra <= 0) {
                return;
            }
            $locked->moneyTransactions()->create(['operation_key' => 'overpayment-return:'.$locked->id.':'.$captured, 'type' => 'overpayment_refund', 'amount' => $extra, 'currency' => 'VND', 'occurred_at' => now()]);
            $locked->update(['reconciliation_status' => 'clear']);
            if ($locked->moneyTransactions()->whereIn('type', ['refund', 'overpayment_refund'])->sum('amount') >= $captured && $locked->reservation_status === 'cancelled') {
                $locked->update(['payment_status' => 'refunded']);
            }
            $locked->recordEvent('overpayment_refunded_demo', ['amount' => $extra]);
        });
    }

    public function redeem(PlatformOrder $order, int $quantity, string $key): void
    {
        Validator::make(['quantity' => $quantity, 'key' => $key], ['quantity' => ['required', 'integer', 'between:1,20'], 'key' => ['required', 'uuid']])->validate();
        DB::transaction(function () use ($order, $quantity, $key) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            $existing = PlatformEvent::where('operation_key', 'redeem:'.$key)->first();
            if ($existing) {
                if ($existing->order_id !== $locked->id || ($existing->details['quantity'] ?? 0) !== $quantity) {
                    $this->error('voucher', 'Mã thao tác đã được dùng cho lượt khác.');
                }

                return;
            }
            $voucher = $locked->voucher()->lockForUpdate()->first();
            if ($locked->type === 'rental') {
                $this->error('voucher', 'Xe thuê phải dùng biên bản bàn giao thay cho thao tác dùng vé.');
            }
            if (! $voucher || $locked->reservation_status !== 'confirmed' || $locked->payment_status !== 'captured' || $voucher->status !== 'issued'
                || now()->lt($voucher->valid_from) || now()->gt($voucher->valid_until) || $voucher->remaining_uses < $quantity) {
                $this->error('voucher', 'Chứng từ không đủ lượt, chưa đến giờ, hết hạn hoặc không còn hiệu lực.');
            }
            $remaining = $voucher->remaining_uses - $quantity;
            $voucher->update(['remaining_uses' => $remaining, 'status' => $remaining === 0 ? 'used' : 'issued']);
            $locked->update(['fulfillment_status' => $locked->type === 'activity' && $remaining === 0 ? 'used' : 'in_use']);
            if ($locked->type === 'activity' && $remaining === 0) {
                $locked->update(['reservation_status' => 'completed']);
            }
            $locked->recordEvent('voucher_redeemed', ['quantity' => $quantity], 'redeem:'.$key);
        });
    }

    public function inspect(PlatformOrder $order, array $input): void
    {
        $data = Validator::make($input, ['phase' => ['required', 'in:handover,return'], 'odometer' => ['required', 'integer', 'min:0'],
            'fuel_percent' => ['required', 'integer', 'between:0,100'], 'condition_note' => ['required', 'string', 'max:1000']])->validate();
        DB::transaction(function () use ($order, $data) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            if ($locked->type !== 'rental' || $locked->payment_status !== 'captured' || ! in_array($locked->reservation_status, ['confirmed', 'completed'], true)) {
                $this->error('inspection', 'Đơn không đủ điều kiện bàn giao xe.');
            }
            if ($locked->inspections()->where('phase', $data['phase'])->exists()) {
                $this->error('inspection', 'Biên bản cho giai đoạn này đã được lập.');
            }
            $deposit = (int) ($locked->snapshot['extra']['deposit_due_at_pickup'] ?? 0);
            if ($data['phase'] === 'handover') {
                if (now()->lt($locked->service_starts_at) || now()->gte($locked->service_ends_at)) {
                    $this->error('inspection', 'Chưa đến thời gian nhận xe hoặc kỳ thuê đã kết thúc.');
                }
                $locked->update(['fulfillment_status' => 'in_use']);
                if ($deposit > 0) {
                    $locked->moneyTransactions()->create(['operation_key' => 'deposit:'.$locked->id, 'type' => 'deposit_hold', 'amount' => $deposit, 'currency' => 'VND', 'occurred_at' => now()]);
                }
            } else {
                $handover = $locked->inspections()->where('phase', 'handover')->first();
                if (! $handover || $data['odometer'] < $handover->odometer) {
                    $this->error('inspection', 'Cần biên bản bàn giao; công-tơ-mét trả xe không được thấp hơn lúc nhận.');
                }
                $this->lockResource($locked);
                $buffer = (int) ($locked->snapshot['buffer_minutes'] ?? 0);
                $resource = PlatformResource::findOrFail($locked->snapshot['resource_id']);
                if (app(PlatformInventory::class)->active($resource, now(), now()->addMinutes(max(1, $buffer)))->where('order_id', '!=', $locked->id)->exists()) {
                    $resource->update(['is_active' => false]);
                    $locked->update(['reconciliation_status' => 'resource_review']);
                    $locked->recordEvent('late_vehicle_return_review');
                }
                $locked->allocations()->whereNull('released_at')->update(['ends_at' => now()->addMinutes($buffer)]);
                $locked->update(['reservation_status' => 'completed', 'fulfillment_status' => 'used']);
                $locked->voucher()->update(['status' => 'used', 'remaining_uses' => 0]);
                if ($deposit > 0) {
                    $locked->moneyTransactions()->create(['operation_key' => 'deposit-return:'.$locked->id, 'type' => 'deposit_release', 'amount' => $deposit, 'currency' => 'VND', 'occurred_at' => now()]);
                }
            }
            $locked->inspections()->create($data + ['deposit_amount' => $deposit, 'occurred_at' => now()]);
            $locked->recordEvent('rental_'.$data['phase'].'_demo', ['deposit_amount' => $deposit]);
        });
    }

    public function complete(PlatformOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = PlatformOrder::lockForUpdate()->findOrFail($order->id);
            if ($locked->reservation_status !== 'confirmed' || $locked->payment_status !== 'captured' || $locked->service_ends_at->gt(now()) || $locked->type === 'rental') {
                $this->error('status', 'Chỉ hoàn tất dịch vụ đã qua giờ kết thúc; xe thuê cần biên bản trả xe.');
            }
            $locked->update(['reservation_status' => 'completed', 'fulfillment_status' => 'used']);
            $locked->voucher()->update(['status' => 'used', 'remaining_uses' => 0]);
            $locked->recordEvent('service_completed');
        });
    }

    private function lockResource(PlatformOrder $order): void
    {
        $ids = $order->allocations()->pluck('resource_id')->unique()->sort();
        PlatformResource::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    private function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
