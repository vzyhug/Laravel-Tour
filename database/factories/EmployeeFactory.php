<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'password_hash' => static::$password ??= Hash::make('Employee@123'),
            'full_name' => fake('vi_VN')->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('09########'),
            'role_id' => Role::factory(),
            'is_active' => true,
        ];
    }

    public function guide(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(['role_name' => 'Guide'], ['description' => 'Hướng dẫn viên'])->getKey(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(['role_name' => 'Admin'], ['description' => 'Quản trị hệ thống'])->getKey(),
        ]);
    }
}
