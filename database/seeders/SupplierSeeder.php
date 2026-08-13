<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // Menggunakan lokal Indonesia

        for ($i = 0; $i < 10; $i++) {
            DB::table('suppliers')->insert([
                'supplier_id'   => (string) Str::uuid(),
                'supplier_name' => substr('PT ' . $faker->company(), 0, 50), // Memastikan max 50 karakter
                'phone'         => substr($faker->numerify('08##########'), 0, 12), // Tepat 12 karakter angka
                'address'       => substr($faker->address(), 0, 60), // Memastikan max 60 karakter
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }
}
