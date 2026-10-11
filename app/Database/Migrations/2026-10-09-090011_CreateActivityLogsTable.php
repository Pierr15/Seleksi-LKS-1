<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'user_id'       => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'action'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'target_type'   => ['type' => "ENUM('user','floor','room','device','rule','override','auth','setting','sensor')", 'null' => false, 'default' => 'setting'],
            'target_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'floor_id'      => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'room_id'       => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'device_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'result'        => ['type' => "ENUM('success','failure')", 'null' => false, 'default' => 'success'],
            'source'        => ['type' => "ENUM('manual','automation','scheduler')", 'null' => false, 'default' => 'manual'],
            'details_json'  => ['type' => 'JSON', 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('created_at');
        $this->forge->addKey('user_id');
        $this->forge->addKey('room_id');
        $this->forge->addKey('device_id');
        $this->forge->addKey('action');
        $this->forge->addKey(['target_type', 'target_id']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('floor_id', 'floors', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('room_id', 'rooms', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('device_id', 'devices', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('activity_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('activity_logs', true);
    }
}
