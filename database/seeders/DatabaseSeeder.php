<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => config('services.admin.email'),
        ], [
            'name' => config('services.admin.name'),
            'password' => config('services.admin.password'),
            'is_admin' => true,
        ]);
    }
}
