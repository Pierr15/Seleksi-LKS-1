<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoomsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                        => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'floor_id'                  => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20],
            'name'                      => ['type' => 'VARCHAR', 'constraint' => 100],
            'description'               => ['type' => 'TEXT', 'null' => true],
            'room_type'                 => ['type' => "ENUM('closed','open_area')", 'null' => false, 'default' => 'closed'],
            'mode'                      => ['type' => "ENUM('active','standby','inactive')", 'null' => false, 'default' => 'active'],
            'occupancy'                 => ['type' => "ENUM('occupied','vacant')", 'null' => false, 'default' => 'vacant'],
            'vacant_since'              => ['type' => 'DATETIME', 'null' => true],
            'inventory_status'          => ['type' => "ENUM('needs_verification','verified')", 'null' => false, 'default' => 'needs_verification'],
            'floor_verification_status' => ['type' => "ENUM('verified','needs_verification')", 'null' => false, 'default' => 'needs_verification'],
            'is_active'                 => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'archived_at'               => ['type' => 'DATETIME', 'null' => true],
            'map_x'                     => ['type' => 'DECIMAL', 'constraint' => [5, 2], 'null' => true],
            'map_y'                     => ['type' => 'DECIMAL', 'constraint' => [5, 2], 'null' => true],
            'map_w'                     => ['type' => 'DECIMAL', 'constraint' => [5, 2], 'null' => true],
            'map_h'                     => ['type' => 'DECIMAL', 'constraint' => [5, 2], 'null' => true],
            'created_at'                => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                => ['type' => 'DATETIME', 'null' => true],
            'chk_maps'                  => 'CHECK (map_x IS NULL OR (map_x >= 0 AND map_x <= 100)), CHECK (map_y IS NULL OR (map_y >= 0 AND map_y <= 100)), CHECK ((map_x IS NULL AND map_y IS NULL AND map_w IS NULL AND map_h IS NULL) OR (map_x + map_w <= 100 AND map_y + map_h <= 100 AND map_w > 0 AND map_h > 0))',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['floor_id', 'name'], false, true, 'uq_rooms_floor_name');
        $this->forge->addKey('floor_id');
        $this->forge->addKey('is_active');
        $this->forge->addKey('occupancy');
        $this->forge->addForeignKey('floor_id', 'floors', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('rooms', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('rooms', true);
    }
}
