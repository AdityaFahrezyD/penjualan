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

    public function test_api_mutation_requires_a_token(): void
    {
        $this->postJson('/api/units', [
            'unit_name' => 'Piece', 'unit_code' => 'PCS',
        ])->assertUnauthorized();
        $this->assertDatabaseCount('units', 0);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('invalid-token')->postJson('/api/units', [
            'unit_name' => 'Piece', 'unit_code' => 'PCS',
        ])->assertUnauthorized();
        $this->assertDatabaseCount('units', 0);
    }

    public function test_bearer_token_allows_mutations_without_csrf_cookie(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/units', [
            'unit_name' => 'Piece', 'unit_code' => 'PCS',
        ])->assertCreated();
        $this->assertDatabaseHas('units', ['unit_code' => 'PCS']);
        $this->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_bearer_token_does_not_bypass_roles(): void
    {
        $user = User::factory()->create(['role' => 'supplier']);
        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/units', [
                'unit_name' => 'Piece', 'unit_code' => 'PCS',
            ])->assertForbidden();
        $this->assertDatabaseCount('units', 0);
    }
}