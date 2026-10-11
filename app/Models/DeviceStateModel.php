<?php

namespace App\Models;

use CodeIgniter\Model;

class DeviceStateModel extends Model
{
    protected $table            = 'device_states';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'device_id',
        'power_state',
        'target_temperature',
        'ac_mode',
        'ac_boost',
        'connection_state',
        'last_seen_at',
        'source',
    ];

    protected $validationRules = [
        'device_id'          => 'required|integer|exists[devices.id]',
        'power_state'        => 'permit_empty|in_list[on,off,sleep,unknown]',
        'target_temperature' => 'permit_empty|decimal|greater_than_equal_to[16]|less_than_equal_to[30]',
        'ac_mode'            => 'permit_empty|in_list[cool,dry]',
        'ac_boost'           => 'permit_empty|in_list[0,1]',
        'connection_state'   => 'permit_empty|in_list[online,offline,unknown]',
    ];
}
