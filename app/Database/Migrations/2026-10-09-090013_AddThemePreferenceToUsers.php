<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddThemePreferenceToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'theme' => [
                'type'       => "ENUM('light','dark','system')",
                'null'       => false,
                'default'    => 'system',
                'after'      => 'role',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'theme');
    }
}
