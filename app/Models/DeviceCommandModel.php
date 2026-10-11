<?php

namespace App\Models;

use CodeIgniter\Model;

class DeviceCommandModel extends Model
{
    protected $table            = 'device_commands';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $updatedField     = '';
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'device_id',
        'room_id',
        'requested_by',
        'automation_execution_id',
        'command',
        'source',
        'status',
        'result_message',
        'executed_at',
    ];

    protected $validationRules = [
        'device_id' => 'required|integer|exists[devices.id]',
        'command'   => 'required|in_list[on,off,sleep,shutdown,set_temperature,set_mode,set_boost]',
        'source'    => 'permit_empty|in_list[manual,automation]',
        'status'    => 'permit_empty|in_list[pending,success,failed]',
    ];
}
