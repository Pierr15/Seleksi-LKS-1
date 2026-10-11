<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAppSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'setting_key' => ['type' => 'VARCHAR', 'constraint' => 64],
            'setting_value' => ['type' => 'VARCHAR', 'constraint' => 255],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('setting_key', false, true, 'uq_settings_key');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('app_settings', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('app_settings', true);
    }
}
