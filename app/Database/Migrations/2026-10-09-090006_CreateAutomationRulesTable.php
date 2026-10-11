<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAutomationRulesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'trigger_type'     => ['type' => "ENUM('vacant_duration','manual')", 'null' => false, 'default' => 'vacant_duration'],
            'duration_seconds' => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 300],
            'condition_json'   => ['type' => 'JSON', 'null' => true],
            'actions_json'     => ['type' => 'JSON'],
            'target_json'      => ['type' => 'JSON', 'null' => true],
            'exceptions_json'  => ['type' => 'JSON', 'null' => true],
            'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'created_by'       => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'last_executed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('is_active');
        $this->forge->addKey('trigger_type');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('automation_rules', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('automation_rules', true);
    }
}
