<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Bug review DATA-21: wrap the entire seed batch in a single
     * transaction. Without it, a failure halfway through (bad fixture,
     * FK constraint, unique collision) leaves the DB in an inconsistent
     * state — some tables seeded, others empty. Rolling back on failure
     * lets us re-run cleanly.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([
                RolesAndPermissionsSeeder::class,
                SuperAdminSeeder::class,
            ]);
        });

        $this->command->info('Production-safe seeders completed. Use LocalDemoSeeder for tenant demo data.');
    }
}
