<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFloorsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'constraint' => 20, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'label'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'sort_order'  => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('name', false, true, 'uq_floors_name');
        $this->forge->addKey('slug', false, true, 'uq_floors_slug');
        $this->forge->createTable('floors', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('floors', true);
    }
}
