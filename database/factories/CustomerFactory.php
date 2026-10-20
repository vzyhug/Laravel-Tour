<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'full_name' => fake('vi_VN')->name(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('Customer@123'),
            'phone' => fake()->numerify('09########'),
            'address' => fake('vi_VN')->address(),
            'is_active' => true,
        ];
    }
}
