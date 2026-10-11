<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table            = 'activity_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $updatedField     = '';
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'floor_id',
        'room_id',
        'device_id',
        'result',
        'source',
        'details_json',
        'ip_address',
    ];

    protected $validationRules = [
        'action'      => 'required|max_length[60]',
        'target_type' => 'required|in_list[user,floor,room,device,rule,override,auth,setting,sensor]',
        'result'      => 'permit_empty|in_list[success,failure]',
        'source'      => 'permit_empty|in_list[manual,automation,scheduler]',
    ];
}
