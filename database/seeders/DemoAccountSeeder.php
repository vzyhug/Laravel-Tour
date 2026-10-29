<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [];
        foreach (['Admin' => 'Quản trị hệ thống', 'Guide' => 'Hướng dẫn viên'] as $name => $description) {
            $roles[$name] = Role::firstOrCreate(['role_name' => $name], ['description' => $description]);
        }

        $employees = [
            ['admin', 'Quản trị VietGo', 'Admin', 'Admin@123'],
            ['guide', 'Nguyễn Minh Anh', 'Guide', 'Guide@123'],
            ['guide_tuan', 'Trần Quốc Tuấn', 'Guide', 'Guide@123'],
            ['guide_huong', 'Lê Mai Hương', 'Guide', 'Guide@123'],
            ['guide_long', 'Phạm Hoàng Long', 'Guide', 'Guide@123'],
            ['sale_linh', 'Nguyễn Thùy Linh', 'Sale', 'Sale@123'],
            ['sale_nam', 'Đỗ Hoàng Nam', 'Sale', 'Sale@123'],
            ['guide_phuong', 'Trần Thu Phương', 'Guide', 'Guide@123'],
            ['guide_hai', 'Nguyễn Thanh Hải', 'Guide', 'Guide@123'],
            ['guide_mai', 'Đỗ Ngọc Mai', 'Guide', 'Guide@123'],
            ['guide_bao', 'Lê Quốc Bảo', 'Guide', 'Guide@123'],
        ];
        foreach ($employees as $index => [$username, $name, $role, $password]) {
            Employee::firstOrCreate(['username' => $username], fn () => Employee::factory()->raw([
                'username' => $username,
                'full_name' => $name,
                'email' => $username.'@vietgo.test',
                'phone' => '09000000'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'role_id' => $roles[$role]->getKey(),
                'password_hash' => Hash::make($password),
            ]));
        }

        for ($index = 1; $index <= 24; $index++) {
            $email = sprintf('demo%02d@vietgo.test', $index);
            Customer::firstOrCreate(['email' => $email], fn () => Customer::factory()->raw(['email' => $email]));
        }
    }
}
