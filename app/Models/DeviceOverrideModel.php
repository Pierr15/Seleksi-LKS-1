<?php

namespace App\Models;

use CodeIgniter\Model;

class DeviceOverrideModel extends Model
{
    protected $table            = 'device_overrides';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'device_id',
        'override_type',
        'reason',
        'created_by',
        'starts_at',
        'expires_at',
        'ended_at',
        'ended_by',
    ];

    protected $validationRules = [
        'device_id'     => 'required|integer|exists[devices.id]',
        'override_type' => 'required|in_list[persistent,time_limited]',
        'reason'        => 'required|max_length[255]',
    ];
}
