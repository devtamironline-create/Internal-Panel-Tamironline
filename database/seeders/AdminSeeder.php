<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['mobile' => '09128389135'],
            [
                'first_name' => 'مدیر',
                'last_name' => 'سیستم',
                'mobile_verified_at' => now(),
                'password' => Hash::make('password'),
                'is_staff' => true,
                'is_active' => true,
            ]
        );

        $admin->assignRole('admin');
    }
}
