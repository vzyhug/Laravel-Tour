<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Local demo ledger only. No real money, credentials, or external gateway. */
class MockPaymentGateway
{
    public function transact(string $kind, string $key, float $amount, string $outcome = 'succeeded'): object
    {
        $existing = DB::table('mock_gateway_transactions')->where('idempotency_key', $key)->first();
        if ($existing) {
            return $existing;
        }
        $id = DB::table('mock_gateway_transactions')->insertGetId([
            'reference' => 'DEMO-'.Str::uuid(), 'kind' => $kind, 'idempotency_key' => $key,
            'amount' => $amount, 'status' => $outcome, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('mock_gateway_transactions')->find($id);
    }
}
