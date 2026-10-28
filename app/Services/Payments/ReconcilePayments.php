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
                $attempt = $payment->attempts->where('status', 'succeeded')->last();
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
                }
                $total = (float) $payment->refunds->where('status', 'succeeded')->sum('amount');
                if ($total > (float) $payment->amount) {
                    $issues[] = 'Tiền hoàn vượt tiền thu.';
                }
                foreach ($payment->refunds->where('status', 'succeeded') as $refund) {
                    $remoteRefund = DB::table('mock_gateway_transactions')->where('reference', $refund->provider_reference)->first();
                    if (! $remoteRefund || $remoteRefund->status !== 'succeeded' || round((float) $remoteRefund->amount, 2) !== round((float) $refund->amount, 2)) {
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
