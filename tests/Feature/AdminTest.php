<?php

namespace Tests\Feature;

use App\Logging\LogRetention;
use App\Models\EcwidApp;
use App\Models\GatewayLog;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function log(array $attributes = []): GatewayLog
    {
        return GatewayLog::create($attributes + [
            'service' => 'thawani', 'operation' => 'checkout.create', 'method' => 'POST',
            'url' => 'https://uatcheckout.thawani.om/api/v1/checkout/session',
            'request_headers' => ['thawani-api-key' => '••••5678'],
            'request_body' => '{"client_reference_id":"12347"}', 'response_status' => 200,
            'response_body' => '{"success":true}', 'success' => true, 'duration_ms' => 120, 'created_at' => now(),
        ]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/logs')->assertRedirect('/admin/login');
        $this->get('/admin/settings')->assertRedirect('/admin/login');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])->assertRedirect('/admin/logs');
        $this->assertAuthenticatedAs($user);

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        foreach (range(1, 5) as $_) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'nope']);
        }

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 60 seconds.']);
        $this->assertGuest();
    }

    public function test_logs_list_filters_and_detail(): void
    {
        $this->actingAs(User::factory()->create());
        $app = EcwidApp::create(['client_id' => 'thawani-gateway', 'client_secret' => 'x', 'gateway' => 'thawani', 'name' => 'Thawani']);
        $transaction = PaymentTransaction::create([
            'ecwid_app_id' => $app->id, 'store_id' => 1003, 'gateway' => 'thawani', 'order_id' => 'transaction_42',
            'order_number' => 'XJ12H', 'amount' => 1.5, 'currency' => 'OMR', 'ecwid_token' => 't', 'return_url' => 'https://example.test',
        ]);
        $ok = $this->log(['payment_transaction_id' => $transaction->id, 'store_id' => 1003]);
        $failed = $this->log(['service' => 'ecwid', 'operation' => 'order.update', 'success' => false, 'response_status' => 403, 'url' => 'https://app.ecwid.com/api/v3/9/orders/x']);

        $this->get('/admin/logs')->assertOk()->assertSee('checkout.create')->assertSee('order.update');
        $this->get('/admin/logs?result=failed')->assertOk()->assertSee('app.ecwid.com')->assertDontSee('uatcheckout.thawani.om');
        $this->get('/admin/logs?transaction=XJ12H')->assertOk()->assertSee('uatcheckout.thawani.om')->assertDontSee('app.ecwid.com');
        $this->get('/admin/logs?store_id=1003&service=thawani')->assertOk()->assertSee('XJ12H');

        $this->get("/admin/logs/{$ok->id}")->assertOk()
            ->assertSee('thawani-api-key: ••••5678')
            ->assertSee('&quot;client_reference_id&quot;: &quot;12347&quot;', false);

        $this->get("/admin/transactions/{$transaction->public_id}")->assertOk()->assertSee('XJ12H')->assertSee('checkout.create');
    }

    public function test_retention_setting(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/settings')->assertOk()->assertSee('90 days (default)');
        $this->assertSame(LogRetention::Days90, LogRetention::current());

        $this->put('/admin/settings', ['log_retention' => '180'])->assertRedirect('/admin/settings');
        $this->assertSame(LogRetention::Months6, LogRetention::current());

        $this->put('/admin/settings', ['log_retention' => 'never'])->assertRedirect();
        $this->assertSame(LogRetention::Never, LogRetention::current());

        $this->put('/admin/settings', ['log_retention' => '12'])->assertSessionHasErrors('log_retention');
        $this->assertSame(LogRetention::Never, LogRetention::current());
    }

    public function test_admin_forms_carry_csrf_token(): void
    {
        $this->actingAs(User::factory()->create());

        // Laravel skips CSRF checks in tests; check the forms carry the token instead.
        $this->get('/admin/settings')->assertSee('name="_token"', false);
    }

    public function test_prune_command_honours_retention(): void
    {
        $old = $this->log(['created_at' => now()->subDays(100)]);
        $recent = $this->log(['created_at' => now()->subDays(10)]);

        Setting::set(LogRetention::SETTING, 'never');
        $this->artisan('model:prune', ['--model' => [GatewayLog::class]])->assertSuccessful();
        $this->assertSame(2, GatewayLog::count());

        Setting::set(LogRetention::SETTING, '90');
        $this->artisan('model:prune', ['--model' => [GatewayLog::class]])->assertSuccessful();
        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_delete_expired_now_button(): void
    {
        $this->actingAs(User::factory()->create());
        $this->log(['created_at' => now()->subDays(40)]);
        $this->log();
        Setting::set(LogRetention::SETTING, '30');

        $this->post('/admin/settings/prune-logs')->assertRedirect('/admin/settings')->assertSessionHas('status', 'Deleted 1 expired log entry.');
        $this->assertSame(1, GatewayLog::count());
    }

    public function test_admin_user_command(): void
    {
        $this->artisan('admin:user', ['email' => 'Admin@Example.com'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->assertFailed();

        $this->artisan('admin:user', ['email' => 'Admin@Example.com'])
            ->expectsQuestion('Password (at least 12 characters)', 'a-long-enough-password')
            ->assertSuccessful();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'a-long-enough-password'])->assertRedirect('/admin/logs');
    }
}
