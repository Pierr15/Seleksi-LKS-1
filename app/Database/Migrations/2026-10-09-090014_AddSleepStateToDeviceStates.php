<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSleepStateToDeviceStates extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `device_states` MODIFY `power_state` ENUM('on','off','sleep','unknown') NOT NULL DEFAULT 'unknown'"
        );
    }

    public function down(): void
    {
        $this->db->query(
            "ALTER TABLE `device_states` MODIFY `power_state` ENUM('on','off','unknown') NOT NULL DEFAULT 'unknown'"
        );
    }
}
