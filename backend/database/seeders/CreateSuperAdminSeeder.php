<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'akudanh@gmail.com',
            'password' => Hash::make('123456'),
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
    }
} 