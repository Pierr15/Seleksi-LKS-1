<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAutomationExecutionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'rule_id'       => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20],
            'tick_key'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'status'        => ['type' => "ENUM('executed','skipped','failed','conflict')", 'null' => false, 'default' => 'skipped'],
            'reason'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'details_json'  => ['type' => 'JSON', 'null' => true],
            'executed_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['rule_id', 'tick_key'], false, true, 'uq_executions_rule_tick');
        $this->forge->addKey('status');
        $this->forge->addKey('executed_at');
        $this->forge->addForeignKey('rule_id', 'automation_rules', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('automation_executions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('automation_executions', true);
    }
}
