<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'platform_suppliers' => 'suppliers',
        'platform_products' => 'travel_services',
        'platform_resources' => 'service_resources',
        'platform_options' => 'service_packages',
        'platform_inventory_days' => 'service_inventory_days',
        'platform_trips' => 'bus_trips',
        'platform_trip_stops' => 'bus_trip_stops',
        'platform_quotes' => 'service_quotes',
        'platform_orders' => 'service_bookings',
        'platform_allocations' => 'service_allocations',
        'platform_payments' => 'service_payments',
        'platform_payment_events' => 'service_payment_events',
        'platform_refunds' => 'service_refunds',
        'platform_money_transactions' => 'service_transactions',
        'platform_vouchers' => 'service_vouchers',
        'platform_events' => 'service_booking_events',
        'platform_inspections' => 'vehicle_inspections',
    ];

    public function up(): void
    {
        $this->renameTables(self::TABLES);
    }

    public function down(): void
    {
        $this->renameTables(array_reverse(array_flip(self::TABLES), true));
    }

    private function renameTables(array $tables): void
    {
        // Validate all names before MySQL's non-transactional DDL starts.
        foreach ($tables as $from => $to) {
            if (Schema::hasTable($from) && Schema::hasTable($to)) {
                throw new RuntimeException("Cannot rename {$from}: {$to} already exists.");
            }
            if (! Schema::hasTable($from) && ! Schema::hasTable($to)) {
                throw new RuntimeException("Cannot rename {$from}: neither table exists.");
            }
        }
        foreach ($tables as $from => $to) {
            if (Schema::hasTable($from)) {
                Schema::rename($from, $to);
            }
        }
    }
};
