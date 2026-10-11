<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeviceCommandsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                     => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'device_id'              => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20],
            'room_id'                => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'requested_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'automation_execution_id' => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'command'                => ['type' => "ENUM('on','off')", 'null' => false],
            'source'                 => ['type' => "ENUM('manual','automation')", 'null' => false, 'default' => 'manual'],
            'status'                 => ['type' => "ENUM('pending','success','failed')", 'null' => false, 'default' => 'pending'],
            'result_message'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'executed_at'            => ['type' => 'DATETIME', 'null' => true],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('device_id');
        $this->forge->addKey('room_id');
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('device_id', 'devices', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('room_id', 'rooms', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('requested_by', 'users', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('automation_execution_id', 'automation_executions', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('device_commands', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('device_commands', true);
    }
}
