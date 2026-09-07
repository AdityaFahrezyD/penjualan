<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['transaksi-delta.vercel.app']]);
        // Enable real CSRF checks; the test database remains SQLite :memory:.
        $this->app->instance('env', 'local');
        $this->withHeaders([
            'Origin' => 'https://transaksi-delta.vercel.app',
            'Referer' => 'https://transaksi-delta.vercel.app/',
        ]);
    }

    public function test_api_login_issues_token_without_csrf_cookie(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_wrong_password_returns_validation_error(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_web_routes_still_require_csrf(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')->post('/csrf-check', fn () => response()->json(['ok' => true]));
        $this->postJson('/csrf-check', [
            'email' => 'test@example.test',
            'password' => 'password',
        ])->assertStatus(419);
    }

    public function test_other_api_routes_retain_stateful_csrf_checks(): void
    {
        $this->postJson('/api/logout')->assertStatus(419);
    }
}