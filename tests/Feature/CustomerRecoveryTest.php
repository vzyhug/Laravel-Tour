<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\CustomerResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class CustomerRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create(['email' => 'recovery@example.test', 'full_name' => 'Recovery', 'phone' => '0901234567', 'password_hash' => Hash::make('OldPassword123'), 'is_active' => true]);
    }

    public function test_reset_link_is_customer_specific_and_response_does_not_expose_account(): void
    {
        Notification::fake();
        $customer = $this->customer();
        User::factory()->create(['email' => $customer->email]);
        $known = $this->from('/forgot-password')->post(route('customer.password.email'), ['email' => $customer->email]);
        $unknown = $this->from('/forgot-password')->post(route('customer.password.email'), ['email' => 'missing@example.test']);
        $known->assertRedirect('/forgot-password');
        $unknown->assertRedirect('/forgot-password');
        Notification::assertSentTo($customer, CustomerResetPassword::class);
        $this->assertDatabaseHas('customer_password_reset_tokens', ['email' => $customer->email]);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_reset_consumes_token_changes_only_customer_and_revokes_old_sessions(): void
    {
        $customer = $this->customer();
        $user = User::factory()->create(['email' => $customer->email]);
        $oldUserHash = $user->password;
        $token = Password::broker('customers')->createToken($customer);
        $data = ['email' => $customer->email, 'token' => $token, 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'];
        $this->post(route('customer.password.update'), $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPassword123', $customer->fresh()->password_hash));
        $this->assertSame(1, $customer->fresh()->session_version);
        $this->assertSame($oldUserHash, $user->fresh()->password);
        $this->post(route('customer.password.update'), $data)->assertSessionHasErrors('email');
        $this->withSession(['customer_id' => $customer->getKey(), 'customer_session_version' => 0])->get(route('customer.dashboard'))->assertRedirect(route('login'));
    }

    public function test_expired_token_and_disabled_customer_cannot_reset(): void
    {
        $customer = $this->customer();
        $token = Password::broker('customers')->createToken($customer);
        DB::table('customer_password_reset_tokens')->update(['created_at' => now()->subHours(2)]);
        $data = ['email' => $customer->email, 'token' => $token, 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'];
        $this->post(route('customer.password.update'), $data)->assertSessionHasErrors('email');
        $customer->update(['is_active' => false]);
        $this->post(route('customer.password.update'), $data)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPassword123', $customer->fresh()->password_hash));
    }

    public function test_change_password_requires_current_password_and_preserves_only_current_session(): void
    {
        $customer = $this->customer();
        $data = ['current_password' => 'wrong', 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'];
        $this->withSession(['customer_id' => $customer->getKey()])->put(route('customer.password.change.update'), $data)->assertSessionHasErrors('current_password');
        $data['current_password'] = 'OldPassword123';
        $this->put(route('customer.password.change.update'), $data)->assertSessionHas('customer_session_version', 1);
        $this->assertTrue(Hash::check('ChangedPassword123', $customer->fresh()->password_hash));
    }

    public function test_legacy_session_works_until_password_version_is_revoked(): void
    {
        $customer = $this->customer();
        $this->withSession(['customer_id' => $customer->getKey()])->get(route('customer.password.change'))->assertOk();
        $customer->forceFill(['session_version' => 1])->save();
        $this->get(route('customer.password.change'))->assertRedirect(route('login'));
    }

    public function test_mail_transport_outage_keeps_the_same_generic_reset_response(): void
    {
        $customer = $this->customer();
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));
        $known = $this->from('/forgot-password')->post(route('customer.password.email'), ['email' => $customer->email]);
        $known->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
        $message = session('success');
        $this->from('/forgot-password')->post(route('customer.password.email'), ['email' => 'missing@example.test'])
            ->assertRedirect('/forgot-password')->assertSessionHas('success', $message);
        $this->assertTrue(Hash::check('OldPassword123', $customer->fresh()->password_hash));
    }
}
