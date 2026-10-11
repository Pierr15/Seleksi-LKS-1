<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLightMetricToSensorReadings extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `sensor_readings` MODIFY `metric` ENUM('temperature','humidity','occupancy','energy_estimate','light') NOT NULL"
        );
    }

    public function down(): void
    {
        $this->db->query(
            "ALTER TABLE `sensor_readings` MODIFY `metric` ENUM('temperature','humidity','occupancy','energy_estimate') NOT NULL"
        );
    }
}
