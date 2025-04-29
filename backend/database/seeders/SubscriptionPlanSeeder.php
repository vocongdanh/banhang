<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SubscriptionPlan::create([
            'name' => 'Basic',
            'description' => 'Gói cơ bản cho doanh nghiệp nhỏ',
            'price' => 299000,
            'max_users' => 3,
            'max_files' => 100,
            'max_storage_mb' => 1000,
            'can_use_vector_search' => true,
            'can_use_ai_chatbot' => false,
            'can_use_messenger_bot' => false,
            'can_use_zalo_bot' => false,
            'can_connect_shopee' => false,
            'can_connect_tiktok' => false,
            'can_connect_google_drive' => true,
        ]);
        
        SubscriptionPlan::create([
            'name' => 'Premium',
            'description' => 'Gói nâng cao cho doanh nghiệp vừa',
            'price' => 599000,
            'max_users' => 10,
            'max_files' => 500,
            'max_storage_mb' => 5000,
            'can_use_vector_search' => true,
            'can_use_ai_chatbot' => true,
            'can_use_messenger_bot' => true,
            'can_use_zalo_bot' => false,
            'can_connect_shopee' => true,
            'can_connect_tiktok' => false,
            'can_connect_google_drive' => true,
        ]);
        
        SubscriptionPlan::create([
            'name' => 'Enterprise',
            'description' => 'Gói doanh nghiệp đầy đủ tính năng',
            'price' => 1299000,
            'max_users' => 30,
            'max_files' => 2000,
            'max_storage_mb' => 20000,
            'can_use_vector_search' => true,
            'can_use_ai_chatbot' => true,
            'can_use_messenger_bot' => true,
            'can_use_zalo_bot' => true,
            'can_connect_shopee' => true,
            'can_connect_tiktok' => true,
            'can_connect_google_drive' => true,
        ]);
    }
}
