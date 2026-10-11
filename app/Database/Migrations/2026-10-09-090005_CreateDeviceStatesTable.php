<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeviceStatesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'device_id'        => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20],
            'power_state'      => ['type' => "ENUM('on','off','unknown')", 'null' => false, 'default' => 'unknown'],
            'connection_state' => ['type' => "ENUM('online','offline','unknown')", 'null' => false, 'default' => 'unknown'],
            'last_seen_at'     => ['type' => 'DATETIME', 'null' => true],
            'source'           => ['type' => "ENUM('simulation','iot')", 'null' => false, 'default' => 'simulation'],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('device_id', false, true, 'uq_device_states_device');
        $this->forge->addKey('power_state');
        $this->forge->addForeignKey('device_id', 'devices', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('device_states', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('device_states', true);
    }
}
