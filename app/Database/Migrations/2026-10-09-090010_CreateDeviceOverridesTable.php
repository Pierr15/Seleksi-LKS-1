<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeviceOverridesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'device_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20],
            'override_type' => ['type' => "ENUM('persistent','time_limited')", 'null' => false, 'default' => 'persistent'],
            'reason'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'starts_at'     => ['type' => 'DATETIME'],
            'expires_at'    => ['type' => 'DATETIME', 'null' => true],
            'ended_at'      => ['type' => 'DATETIME', 'null' => true],
            'ended_by'      => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'chk_override_expiry' => "CHECK ((override_type = 'persistent' AND expires_at IS NULL) OR (override_type = 'time_limited' AND expires_at IS NOT NULL))",
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['device_id', 'expires_at']);
        $this->forge->addKey('ended_at');
        $this->forge->addForeignKey('device_id', 'devices', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('ended_by', 'users', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('device_overrides', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('device_overrides', true);
    }
}
