<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAcColumnsToDeviceStates extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('device_states', [
            'target_temperature' => [
                'type'       => 'DECIMAL',
                'constraint' => [3, 1],
                'null'       => true,
                'default'    => 24.0,
                'after'      => 'power_state',
            ],
            'ac_mode' => [
                'type'       => "ENUM('cool','dry')",
                'null'       => true,
                'default'    => 'cool',
                'after'      => 'target_temperature',
            ],
            'ac_boost' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'ac_mode',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('device_states', 'target_temperature');
        $this->forge->dropColumn('device_states', 'ac_mode');
        $this->forge->dropColumn('device_states', 'ac_boost');
    }
}
