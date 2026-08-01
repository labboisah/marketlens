<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $admin = [
            'name' => 'Admin',
            'email' => 'admin@marketlens.ng',
            'email_verified_at' => now(),
            'is_super_admin'=> true,
            'account_status'=> 'active',
            'super_admin_confirmed_at'=> now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'password'=> bcrypt('Admin@123'),
            'phone' => '+2349012345678',
        ];

        User::create($admin);
    }
}
