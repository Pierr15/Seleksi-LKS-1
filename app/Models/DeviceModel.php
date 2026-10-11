<?php

namespace App\Models;

use CodeIgniter\Model;

class DeviceModel extends Model
{
    protected $table            = 'devices';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'room_id',
        'name',
        'device_type',
        'control_capability',
        'is_essential',
        'inventory_status',
        'is_active',
        'position_x',
        'position_y',
        'source',
        'archived_at',
    ];

    protected $validationRules = [
        'name'              => 'required|max_length[100]',
        'room_id'           => 'required|integer|exists[rooms.id]',
        'device_type'       => 'required|in_list[ac,ac_central,light,computer,projector,videotron,audio,exhaust_fan,studio_lighting,other]',
        'control_capability' => 'permit_empty|in_list[controllable,monitor_only]',
        'inventory_status'  => 'permit_empty|in_list[needs_verification,verified]',
    ];

    protected $validationMessages = [
        'room_id' => [
            'exists' => 'Ruangan yang dipilih tidak valid.',
        ],
    ];
}
