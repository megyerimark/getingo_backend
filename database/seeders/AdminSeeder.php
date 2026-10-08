<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        throw new RuntimeException(
            'Biztonsági okból az AdminSeeder le van tiltva. Használd: php artisan getingo:create-admin'
        );
    }
}
