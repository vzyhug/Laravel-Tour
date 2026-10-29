<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\Payments\PaymentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['payments.demo_enabled' => true]);
        Http::preventStrayRequests();
    }

    public static function paymentResults(): array
    {
        $cases = [];
        foreach (['cash', 'card'] as $method) {
            foreach (['succeeded' => 'success', 'failed' => 'error', 'pending' => 'warning'] as $outcome => $variant) {
                $cases[$method.'-'.$outcome] = [$method, $outcome, $variant];
            }
        }

        return $cases;
    }

    #[DataProvider('paymentResults')]
    public function test_payment_redirect_uses_the_correct_alert_and_renders_the_shared_dialog(string $method, string $outcome, string $variant): void
    {
        $booking = Booking::factory()->create();
        $this->withSession(['customer_id' => $booking->customer_id]);
        $response = $this->post(route('payments.store', $booking), ['payment_method' => $method, 'demo_outcome' => $outcome]);
        $response->assertRedirect()->assertSessionHas($variant)->assertSessionHas('alert_title');
        foreach (array_diff(['success', 'error', 'warning'], [$variant]) as $other) {
            $response->assertSessionMissing($other);
        }
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('flash-alert--'.$variant, false)
            ->assertSee('data-flash-alert', false)->assertSee('data-flash-close', false);
        $this->assertSame($outcome === 'succeeded' ? 1 : 0, $booking->bill()->count());
    }

    public function test_resolving_a_pending_payment_reports_the_actual_result_and_not_a_generic_success(): void
    {
        foreach (['failed' => 'error', 'succeeded' => 'success'] as $outcome => $variant) {
            $booking = Booking::factory()->create();
            $attempt = app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'pending');
            $this->withSession(['customer_id' => $booking->customer_id]);
            $response = $this->post(route('payments.demo.resolve', $attempt), ['outcome' => $outcome]);
            $response->assertRedirect()->assertSessionHas($variant);
            $this->get($response->headers->get('Location'))->assertOk()->assertSee('flash-alert--'.$variant, false);
        }
    }

    public function test_success_arriving_after_booking_expiry_does_not_display_a_success_alert(): void
    {
        $booking = Booking::factory()->create();
        $attempt = app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'pending');
        $booking->update(['hold_expires_at' => now()->subMinute()]);
        $this->withSession(['customer_id' => $booking->customer_id])
            ->post(route('payments.demo.resolve', $attempt), ['outcome' => 'succeeded'])
            ->assertRedirect()->assertSessionHas('warning')->assertSessionMissing('success');
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(0, $booking->bill()->count());
    }

    public static function qrResults(): array
    {
        return [
            'paid' => ['success', 'success', false],
            'failed' => ['failed', 'error', false],
            'expired' => ['expired', 'warning', false],
            'expired-before-confirmation' => ['success', 'warning', true],
        ];
    }

    #[DataProvider('qrResults')]
    public function test_qr_confirmation_alert_uses_the_stored_status(string $outcome, string $variant, bool $alreadyExpired): void
    {
        $booking = Booking::factory()->create();
        $this->withSession(['customer_id' => $booking->customer_id]);
        $this->post(route('payments.store', $booking), ['payment_method' => 'vietqr_demo'])->assertRedirect();
        $intent = $booking->paymentIntents()->firstOrFail();
        if ($alreadyExpired) {
            $intent->update(['expires_at' => now()->subSecond()]);
        }
        $response = $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => $outcome]);
        $response->assertRedirect()->assertSessionHas($variant)->assertSessionHas('alert_title');
        if ($variant !== 'success') {
            $response->assertSessionMissing('success');
            $this->assertSame(0, $booking->bill()->count());
        }
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('flash-alert--'.$variant, false);
    }
}
