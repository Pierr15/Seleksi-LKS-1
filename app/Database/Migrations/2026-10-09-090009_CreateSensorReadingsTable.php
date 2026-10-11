<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSensorReadingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'room_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'device_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'metric'      => ['type' => "ENUM('temperature','humidity','occupancy','energy_estimate')", 'null' => false],
            'value'       => ['type' => 'DECIMAL', 'constraint' => [10, 3]],
            'unit'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'recorded_at' => ['type' => 'DATETIME'],
            'source'      => ['type' => "ENUM('simulation','iot')", 'null' => false, 'default' => 'simulation'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['room_id', 'metric', 'recorded_at']);
        $this->forge->addKey(['metric', 'recorded_at']);
        $this->forge->addKey('recorded_at');
        $this->forge->addForeignKey('room_id', 'rooms', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('device_id', 'devices', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('sensor_readings', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('sensor_readings', true);
    }
}
