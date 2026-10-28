<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('role_name', 'Sale')
            ->update([
                'role_name' => 'Staff',
                'description' => 'Nhân viên vận hành và kinh doanh',
            ]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('role_name', 'Staff')
            ->update([
                'role_name' => 'Sale',
                'description' => 'Tư vấn và bán tour',
            ]);
    }
};
