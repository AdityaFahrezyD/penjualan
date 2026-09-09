<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_counts_successes_and_failures_and_normalizes_email(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/login', ['email' => ' ADMIN@example.test ', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429)->assertJsonPath('message', 'Terlalu banyak permintaan. Silakan coba lagi beberapa saat.')
            ->assertHeader('Retry-After')->assertHeader('X-RateLimit-Limit', '5')
            ->assertHeader('X-RateLimit-Remaining', '0');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson('/api/login', ['email' => 'another@example.test', 'password' => 'wrong'])
            ->assertUnprocessable();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $this->travel(61)->seconds();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_login_ip_limit_applies_across_email_addresses_and_handles_invalid_input(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/login', ['email' => "person{$i}@example.test", 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => ['invalid'], 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('X-RateLimit-Limit', '30');
        $this->travel(61)->seconds();
        $this->postJson('/api/login', ['email' => ['invalid'], 'password' => 'wrong'])
            ->assertUnprocessable();
    }

    public function test_authenticated_default_counts_cache_hits_and_recovers_after_one_minute(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/units')->assertOk()->assertHeader('X-RateLimit-Remaining', (string) (119 - $i));
        }

        $this->getJson('/api/items')->assertStatus(429)->assertHeader('X-RateLimit-Limit', '120');
        $this->travel(61)->seconds();
        $this->getJson('/api/items')->assertOk()->assertHeader('X-RateLimit-Remaining', '119');
    }

    public function test_tokens_and_endpoints_share_user_quota_but_users_are_independent(): void
    {
        config(['api.rate_limits.authenticated_per_minute' => 2]);
        $user = User::factory()->create(['role' => 'admin']);
        $first = $user->createToken('first')->plainTextToken;
        $second = $user->createToken('second')->plainTextToken;
        $other = User::factory()->create(['role' => 'admin'])->createToken('other')->plainTextToken;

        $this->withToken($first)->getJson('/api/units')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/items')->assertOk()->assertHeader('X-RateLimit-Remaining', '0');
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->postJson('/api/logout')->assertStatus(429);
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/units')->assertOk()->assertHeader('X-RateLimit-Remaining', '1');
    }

    public function test_invalidation_keeps_quota_and_headers_are_exposed_to_frontend(): void
    {
        config(['cache.default' => 'database', 'api.rate_limits.authenticated_per_minute' => 2]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->withHeader('Origin', 'http://localhost:5173');
        $this->getJson('/api/units')->assertOk();
        $this->postJson('/api/units', ['unit_name' => 'Piece', 'unit_code' => 'PCS'])
            ->assertCreated()->assertHeader('X-RateLimit-Remaining', '0');
        $response = $this->getJson('/api/units')->assertStatus(429)->assertHeader('Retry-After');
        $this->assertStringContainsString('Retry-After', $response->headers->get('Access-Control-Expose-Headers'));
        $this->assertStringContainsString('X-RateLimit-Remaining', $response->headers->get('Access-Control-Expose-Headers'));
    }

    public function test_configured_login_limits_are_used(): void
    {
        config(['api.rate_limits.login_identity_per_minute' => 1, 'api.rate_limits.login_ip_per_minute' => 2]);
        $this->postJson('/api/login', ['email' => 'first@example.test', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => 'first@example.test', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('X-RateLimit-Limit', '1');
        $this->postJson('/api/login', ['email' => 'second@example.test', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => 'third@example.test', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('X-RateLimit-Limit', '2');
    }
}
