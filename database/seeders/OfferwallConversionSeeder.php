<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OfferwallConversion;

class OfferwallConversionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 50 offerwall conversions using the factory.
        // This will use the logic in OfferwallConversionFactory to associate
        // them with the single existing Integration and Company.
        OfferwallConversion::factory()->count(50)->create();
    }
}
