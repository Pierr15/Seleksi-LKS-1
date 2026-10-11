<?php

namespace App\Models;

use CodeIgniter\Model;

class AutomationRuleModel extends Model
{
    protected $table            = 'automation_rules';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'name',
        'description',
        'trigger_type',
        'duration_seconds',
        'condition_json',
        'actions_json',
        'target_json',
        'exceptions_json',
        'is_active',
        'created_by',
        'last_executed_at',
    ];

    protected $validationRules = [
        'name'             => 'required|max_length[150]',
        'trigger_type'     => 'permit_empty|in_list[vacant_duration,manual]',
        'duration_seconds' => 'permit_empty|integer|min_length[1]',
        'actions_json'     => 'permit_empty|json_valid',
    ];
}
