<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentReconciliation;
use Illuminate\Support\Facades\DB;

class ReconcilePayments
{
    public function handle(): int
    {
        $mismatches = 0;
        Payment::with(['attempts', 'refunds', 'booking.bill'])->chunkById(100, function ($payments) use (&$mismatches) {
            foreach ($payments as $payment) {
                $issues = [];
                foreach ($payment->attempts as $record) {
                    $ledger = $record->provider_reference ? DB::table('mock_gateway_transactions')->where('reference', $record->provider_reference)->first() : null;
                    if (! $ledger || $ledger->kind !== 'payment'
                        || $ledger->idempotency_key !== 'payment:'.$record->idempotency_key
                        || $ledger->status !== $record->status
                        || round((float) $ledger->amount, 2) !== round((float) $record->amount, 2)
                        || round((float) $record->amount, 2) !== round((float) $payment->amount, 2)) {
                        $issues[] = 'Lần thử thanh toán không khớp sổ demo.';
                    }
                }
                $successful = $payment->attempts->where('status', 'succeeded');
                if ($successful->count() > 1 || ($successful->isNotEmpty() && ! in_array($payment->status, ['completed', 'refunded'], true))) {
                    $issues[] = 'Trạng thái tổng hợp thanh toán không khớp các lần thử.';
                }
                $latest = $payment->attempts->sortBy('id')->last();
                if ($successful->isEmpty() && $latest && $payment->status !== ($latest->status === 'failed' ? 'failed' : 'pending')) {
                    $issues[] = 'Trạng thái thanh toán không khớp lần thử gần nhất.';
                }
                $attempt = $successful->sortBy('id')->last();
                $remote = $attempt ? DB::table('mock_gateway_transactions')->where('reference', $attempt->provider_reference)->first() : null;
                if (in_array($payment->status, ['completed', 'refunded'], true)) {
                    if (! $remote) {
                        $issues[] = 'Chưa có bằng chứng trong sổ giao dịch demo (bao gồm dữ liệu cũ).';
                    } elseif ($remote->status !== 'succeeded' || round((float) $remote->amount, 2) !== round((float) $payment->amount, 2)) {
                        $issues[] = 'Trạng thái hoặc số tiền không khớp sổ demo.';
                    }
                    if ($payment->booking->status !== 'cancelled' && ! $payment->booking->bill) {
                        $issues[] = 'Thiếu hóa đơn cho đơn đã thu tiền.';
                    }
                    if (round((float) $payment->amount, 2) !== round((float) $payment->booking->total_amount, 2)
                        || ($payment->booking->bill && round((float) $payment->booking->bill->final_amount, 2) !== round((float) $payment->amount, 2))) {
                        $issues[] = 'Số tiền thanh toán không khớp đơn hàng hoặc hóa đơn.';
                    }
                    if ($remote && $payment->transaction_code !== $remote->reference) {
                        $issues[] = 'Mã giao dịch thanh toán không khớp sổ demo.';
                    }
                }
                $total = (float) $payment->refunds->where('status', 'succeeded')->sum('amount');
                if ($total > (float) $payment->amount) {
                    $issues[] = 'Tiền hoàn vượt tiền thu.';
                }
                if (($payment->status === 'refunded' && round($total, 2) !== round((float) $payment->amount, 2))
                    || ($total > 0 && round($total, 2) === round((float) $payment->amount, 2) && $payment->status !== 'refunded')) {
                    $issues[] = 'Trạng thái thanh toán không khớp tổng tiền đã hoàn.';
                }
                foreach ($payment->refunds->whereIn('status', ['succeeded', 'failed']) as $refund) {
                    $remoteRefund = DB::table('mock_gateway_transactions')->where('reference', $refund->provider_reference)->first();
                    if (! $remoteRefund || $remoteRefund->kind !== 'refund'
                        || $remoteRefund->idempotency_key !== 'refund:'.$refund->idempotency_key.':'.$refund->attempt_count
                        || $remoteRefund->status !== $refund->status || round((float) $remoteRefund->amount, 2) !== round((float) $refund->amount, 2)) {
                        $issues[] = 'Hoàn tiền không khớp sổ demo.';
                    }
                }
                if ($issues) {
                    $mismatches++;
                }
                PaymentReconciliation::create(['payment_id' => $payment->getKey(), 'status' => $issues ? 'mismatch' : 'matched', 'details' => $issues ? implode(' ', $issues) : 'Khớp dữ liệu nội bộ và sổ giao dịch demo.']);
            }
        }, 'payment_id');

        return $mismatches;
    }
}
