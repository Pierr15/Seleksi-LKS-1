<?php

namespace App\Models;

use CodeIgniter\Model;

class AutomationExecutionModel extends Model
{
    protected $table            = 'automation_executions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $updatedField     = '';
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'rule_id',
        'tick_key',
        'status',
        'reason',
        'details_json',
        'executed_at',
    ];

    protected $validationRules = [
        'rule_id'  => 'required|integer|exists[automation_rules.id]',
        'tick_key' => 'required|max_length[64]',
        'status'   => 'required|in_list[executed,skipped,failed,conflict]',
    ];
}
