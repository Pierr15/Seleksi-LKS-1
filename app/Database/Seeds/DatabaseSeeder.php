<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeeder;
use CodeIgniter\Database\Seeder;

/**
 * Seeder induk — aman dijalankan berulang kali.
 *
 * Prinsip idempoten:
 * - Data hanya DITAMBAHAN bila belum ada.
 * - Tidak PERNAH menimpa baris yang sudah ada (perubahan Admin, termasuk
 *   posisi perangkat pada denah, tetap utuh saat seeder dijalankan ulang).
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call(AdminUserSeeder::class);
        $this->call(FloorsSeeder::class);
        $this->call(RoomsSeeder::class);
        $this->call(DevicesSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(AutomationSeeder::class);
    }
}
