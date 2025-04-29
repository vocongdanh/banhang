<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'akudanh@gmail.com',
            'password' => Hash::make('123456'),
            'role' => 'super_admin'
        ]);
    }
} 