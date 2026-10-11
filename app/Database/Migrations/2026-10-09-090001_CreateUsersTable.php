<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                   => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'name'                 => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'                => ['type' => 'VARCHAR', 'constraint' => 190],
            'password_hash'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'                 => ['type' => "ENUM('admin','operator','viewer')", 'null' => false, 'default' => 'viewer'],
            'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'last_login_at'        => ['type' => 'DATETIME', 'null' => true],
            'failed_login_attempts' => ['type' => 'INT', 'null' => false, 'default' => 0],
            'locked_until'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('email', false, true, 'uq_users_email');
        $this->forge->addKey('role');
        $this->forge->createTable('users', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
