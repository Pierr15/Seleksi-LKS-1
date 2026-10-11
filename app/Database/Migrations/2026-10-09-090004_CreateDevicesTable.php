<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDevicesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'room_id'           => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 100],
            'device_type'       => ['type' => "ENUM('ac','ac_central','light','computer','projector','videotron','audio','exhaust_fan','studio_lighting','other')", 'null' => false, 'default' => 'other'],
            'control_capability' => ['type' => "ENUM('controllable','monitor_only')", 'null' => false, 'default' => 'controllable'],
            'is_essential'      => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'inventory_status'  => ['type' => "ENUM('needs_verification','verified')", 'null' => false, 'default' => 'needs_verification'],
            'is_active'         => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'position_x'        => ['type' => 'DECIMAL', 'constraint' => [5, 2], 'null' => true],
            'position_y'        => ['type' => 'DECIMAL', 'constraint' => [5, 2], 'null' => true],
            'source'            => ['type' => "ENUM('simulation','iot')", 'null' => false, 'default' => 'simulation'],
            'archived_at'       => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'chk_positions'     => 'CHECK (position_x IS NULL OR (position_x >= 0 AND position_x <= 100)), CHECK (position_y IS NULL OR (position_y >= 0 AND position_y <= 100)), CHECK ((position_x IS NULL AND position_y IS NULL) OR (position_x IS NOT NULL AND position_y IS NOT NULL))',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('room_id');
        $this->forge->addKey('device_type');
        $this->forge->addKey('is_active');
        $this->forge->addKey(['room_id', 'position_x'], false, false, 'idx_devices_room_position');
        $this->forge->addForeignKey('room_id', 'rooms', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('devices', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('devices', true);
    }
}
