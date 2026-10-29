<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $staffRoleIds = DB::table('roles')->whereIn('role_name', ['Staff', 'Sale'])->pluck('role_id');

            if ($staffRoleIds->isEmpty()) {
                return;
            }

            // Staff accounts are removed with the role; related nullable employee references use ON DELETE SET NULL.
            DB::table('employees')->whereIn('role_id', $staffRoleIds)->delete();
            DB::table('roles')->whereIn('role_id', $staffRoleIds)->delete();
        });
    }

    public function down(): void
    {
        if (! DB::table('roles')->where('role_name', 'Staff')->exists()) {
            DB::table('roles')->insert([
                'role_name' => 'Staff',
                'description' => 'Legacy staff role',
            ]);
        }
    }
};
