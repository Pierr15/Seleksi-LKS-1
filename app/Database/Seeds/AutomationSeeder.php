<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Aturan otomatisasi contoh.
 *
 * Dibuat dalam keadaan NONAKTIF — contoh ini bukan tindakan nyata
 * dan bukan kebijakan permanen tanpa persetujuan Admin.
 */
class AutomationSeeder extends Seeder
{
    use IdempotentSeeder;

    private const RULE_NAME = 'Hemat Energi Ruang Kosong';

    public function run()
    {
        if ($this->exists('automation_rules', ['name' => self::RULE_NAME])) {
            return; // Tidak menimpa perubahan Admin.
        }

        $minutes = $this->db->table('app_settings')
            ->where('setting_key', 'vacant_duration_minutes')
            ->get()
            ->getRowArray();
        $minutes = (int) ($minutes['setting_value'] ?? 5);

        $admin = $this->db->table('users')->where('role', 'admin')->get()->getRowArray();

        $actions = [
            ['type' => 'set_room_mode', 'mode' => 'standby'],
            [
                'type'                => 'turn_off',
                'target_device_types' => ['ac', 'ac_central', 'light', 'computer', 'projector', 'exhaust_fan'],
            ],
        ];

        $this->db->table('automation_rules')->insert([
            'name'             => self::RULE_NAME,
            'description'      => 'Jika suatu ruangan kosong selama ' . $minutes
                . ' menit, ubah mode ke Standby dan matikan perangkat non-esensial yang memenuhi syarat, '
                . 'kecuali perangkat yang dilindungi override. Contoh simulasi — nonaktif sampai Admin mengaktifkannya.',
            'trigger_type'     => 'vacant_duration',
            'duration_seconds' => $minutes * 60,
            'condition_json'   => json_encode(['vacant_duration_minutes' => $minutes]),
            'actions_json'     => json_encode($actions),
            'target_json'      => json_encode(['room_ids' => []]),
            'exceptions_json'  => json_encode([
                'protect_essential' => true,
                'protect_overrides' => true,
                'note'               => 'Perangkat esensial dan override selalu dilindungi.',
            ]),
            'is_active'        => 0,
            'created_by'       => $admin !== null ? (int) $admin['id'] : null,
            'created_at'       => gmdate('Y-m-d H:i:s'),
            'updated_at'       => gmdate('Y-m-d H:i:s'),
        ]);

        CLI::write("Aturan contoh '" . self::RULE_NAME . "' dibuat (NONAKTIF).", 'green');
    }
}
