<?php

namespace App\Models;

use CodeIgniter\Model;

class SensorReadingModel extends Model
{
    protected $table            = 'sensor_readings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $updatedField     = '';
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'room_id',
        'device_id',
        'metric',
        'value',
        'unit',
        'recorded_at',
        'source',
    ];

    protected $validationRules = [
        'metric' => 'required|in_list[temperature,humidity,occupancy,energy_estimate,light]',
        'value'  => 'required|decimal',
    ];
}
