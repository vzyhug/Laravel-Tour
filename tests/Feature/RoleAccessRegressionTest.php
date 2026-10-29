<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_cannot_open_admin_dashboard(): void
    {
        $guide = Employee::factory()->guide()->create();
        $this->withSession(['employee_id' => $guide->getKey(), 'employee_role' => 'Guide'])
            ->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_revoked_admin_role_is_checked_again_on_each_request(): void
    {
        $guide = Employee::factory()->guide()->create();
        $this->withSession(['employee_id' => $guide->getKey(), 'employee_role' => 'Admin'])
            ->get(route('admin.employees.index'))->assertForbidden()
            ->assertSessionHas('employee_role', 'Guide');
    }

    public function test_disabled_employee_session_is_cleared(): void
    {
        $admin = Employee::factory()->admin()->create(['is_active' => false]);
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin'])
            ->get(route('admin.dashboard'))->assertRedirect(route('login'))
            ->assertSessionMissing('employee_id');
    }

    public function test_employee_login_clears_customer_identity(): void
    {
        $customer = Customer::factory()->create();
        $admin = Employee::factory()->admin()->create();
        $this->withSession(['customer_id' => $customer->getKey(), 'customer_name' => $customer->full_name])
            ->post(route('login.store'), ['login' => $admin->username, 'password' => 'Employee@123'])
            ->assertRedirect(route('admin.dashboard'))->assertSessionMissing('customer_id');
    }
    public function test_customer_login_clears_employee_identity(): void
    {
        $customer = Customer::factory()->create();
        $admin = Employee::factory()->admin()->create();
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin'])
            ->post(route('login.store'), ['login' => $customer->email, 'password' => 'Customer@123'])
            ->assertRedirect(route('home'))->assertSessionMissing('employee_id');
    }

    public function test_customer_cannot_review_before_return_day_has_finished(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $customer = Customer::factory()->create();
        $departure = \App\Models\TourDeparture::factory()->create(['return_date' => today()]);
        $booking = \App\Models\Booking::factory()->confirmed()->create([
            'customer_id' => $customer->getKey(), 'departure_id' => $departure->getKey(),
        ]);
        $this->withSession(['customer_id' => $customer->getKey()])
            ->post(route('customer.bookings.review', $booking), ['rating' => 5, 'feedback' => 'Very good tour'])
            ->assertForbidden();
    }
}
