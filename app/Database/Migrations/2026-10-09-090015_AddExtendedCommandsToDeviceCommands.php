<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExtendedCommandsToDeviceCommands extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `device_commands` MODIFY `command` ENUM('on','off','sleep','shutdown','set_temperature','set_mode','set_boost') NOT NULL"
        );
    }

    public function down(): void
    {
        $this->db->query(
            "ALTER TABLE `device_commands` MODIFY `command` ENUM('on','off') NOT NULL"
        );
    }
}
