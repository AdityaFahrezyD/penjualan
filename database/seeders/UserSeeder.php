<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@procurement-api.my.id',
            'password' => Hash::make('Q(?6%87U,SfgvRCK'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Akuntan',
            'email' => 'akuntan@procurement-api.my.id',
            'password' => Hash::make('yA-qv]~s}w]TqQ*}'),
            'role' => 'akuntan',
        ]);
        // User::create([
        //     'name' => 'Supplier',
        //     'email' => 'supplier@example.com',
        //     'password' => Hash::make('password'),
        //     'role' => 'akuntan',
        // ]);
    }
}
