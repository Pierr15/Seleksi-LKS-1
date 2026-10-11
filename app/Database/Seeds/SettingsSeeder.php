<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Pengaturan awal aplikasi. Hanya insert bila kunci belum ada —
 * nilai yang diubah Admin melalui UI tidak pernah ditimpa.
 */
class SettingsSeeder extends Seeder
{
    use IdempotentSeeder;

    public function run()
    {
        $envMinutes = $_SERVER['orbit.vacantDurationMinutes'] ?? getenv('orbit.vacantDurationMinutes');
        $minutes    = is_string($envMinutes) && is_numeric($envMinutes) && (int) $envMinutes > 0
            ? (int) $envMinutes
            : 5;

        $defaults = [
            // Durasi simulasi kekosongan (menit) — untuk pengujian MVP,
            // bukan kebijakan resmi sekolah. Dapat diubah Admin di Pengaturan.
            'vacant_duration_minutes' => (string) $minutes,
            'timezone_display'        => 'Asia/Jakarta',
        ];

        foreach ($defaults as $key => $value) {
            if ($this->exists('app_settings', ['setting_key' => $key])) {
                continue;
            }

            $this->db->table('app_settings')->insert([
                'setting_key'   => $key,
                'setting_value' => $value,
                'created_at'    => gmdate('Y-m-d H:i:s'),
                'updated_at'    => gmdate('Y-m-d H:i:s'),
            ]);
        }

        CLI::write('Settings seeder selesai (idempoten).', 'green');
    }
}
