<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            OrganizationSeeder::class,
            ReferenceDataSeeder::class,
            DemoUserSeeder::class,
            AcademicDemoSeeder::class,
            InstrumentSeeder::class,
            AmiReferenceSeeder::class,
            MonevDemoSeeder::class,
            AmiDemoSeeder::class,
            EvidenceDemoSeeder::class,
            AccreditationSeeder::class,
            ImprovementDemoSeeder::class,
        ]);
    }
}
