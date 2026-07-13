<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PersonalProfileSeeder::class,
            TrainingPlanSeeder::class,
            GamificationSeeder::class,
        ]);
    }
}
