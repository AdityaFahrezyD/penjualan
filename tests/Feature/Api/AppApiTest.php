<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\msTransaction;

use Database\Seeders\UserSeeder;
use Database\Seeders\ItemSeeder;
use Database\Seeders\SupplierSeeder;

class AppApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test API Login menggunakan UserSeeder
     */
    public function test_api_user_can_login()
    {
        $this->seed(UserSeeder::class);
        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password', 
        ]);
        $response->assertStatus(200);
    }

    /**
     * Test API Menampilkan Item menggunakan ItemSeeder
     */
    public function test_api_can_fetch_items_list()
    {
        $this->seed(UserSeeder::class);
        $this->seed(ItemSeeder::class); 
        $user = User::where('email', 'admin@example.com')->first();
        $response = $this->actingAs($user)->getJson('/api/items');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }

    /**
     * Test API Menampilkan Supplier menggunakan SupplierSeeder
     */
    public function test_api_can_fetch_suppliers_list()
    {
        $this->seed(UserSeeder::class);
        $this->seed(SupplierSeeder::class);

        $user = User::where('email', 'admin@example.com')->first();
        $response = $this->actingAs($user)->getJson('/api/suppliers');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }

    /**
     * Test API Menampilkan Transaksi beserta Relasinya
     */
    // public function test_api_can_fetch_transactions()
    // {
    //     $this->seed(UserSeeder::class);

    //     $user = User::where('email', 'admin@example.com')->first();
    //     $response = $this->actingAs($user)->getJson('/api/transactions');

    //     $response->assertStatus(200);
    // }
}