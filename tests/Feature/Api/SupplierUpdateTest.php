<?php

namespace Tests\Feature\Api;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->supplier = $this->createSupplier();
    }

    private function createSupplier(): Supplier
    {
        return Supplier::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'supplier_name' => 'Supplier',
            'phone' => '0812345678',
            'address' => 'Jakarta',
        ]);
    }

    public function test_update_keeps_its_existing_user(): void
    {
        $this->putJson('/api/suppliers/'.$this->supplier->getKey(), [
            'user_id' => $this->supplier->user_id,
            'supplier_name' => 'Updated supplier',
            'phone' => '0811111111',
            'address' => 'Bandung',
        ])->assertOk()->assertJsonPath('data.supplier_name', 'Updated supplier');

        $this->assertDatabaseHas('suppliers', [
            'supplier_id' => $this->supplier->getKey(),
            'user_id' => $this->supplier->user_id,
            'supplier_name' => 'Updated supplier',
            'phone' => '0811111111',
            'address' => 'Bandung',
        ]);
    }

    public function test_update_rejects_another_suppliers_user(): void
    {
        $other = $this->createSupplier();
        $this->putJson('/api/suppliers/'.$this->supplier->getKey(), [
            'user_id' => $other->user_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $this->assertSame($this->supplier->user_id, $this->supplier->fresh()->user_id);
    }

    public function test_unknown_supplier_returns_not_found(): void
    {
        $this->putJson('/api/suppliers/'.Str::uuid(), [
            'supplier_name' => 'Updated supplier',
        ])->assertNotFound();
    }
}