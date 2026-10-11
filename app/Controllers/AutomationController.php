<?php

namespace App\Controllers;

use App\Models\AutomationExecutionModel;
use App\Models\AutomationRuleModel;

class AutomationController extends BaseController
{
    public function index()
    {
        $rules = (new AutomationRuleModel())
            ->select('automation_rules.*, users.name AS creator_name')
            ->join('users', 'users.id = automation_rules.created_by', 'left')
            ->orderBy('automation_rules.is_active', 'DESC')
            ->orderBy('automation_rules.name', 'ASC')
            ->findAll();

        $model       = new AutomationExecutionModel();
        $executions  = $model
            ->select('automation_executions.*, automation_rules.name AS rule_name')
            ->join('automation_rules', 'automation_rules.id = automation_executions.rule_id', 'left')
            ->orderBy('automation_executions.executed_at', 'DESC')
            ->paginate(10);
        $pager = $model->pager;

        $floorList = (new \App\Models\FloorModel())->orderBy('sort_order')->findAll();
        $rooms     = (new \App\Models\RoomModel())
            ->where('archived_at', null)->orderBy('name')->findAll();

        return view('automation/index', $this->layoutData('Otomatisasi', 'otomatisasi') + [
            'rules'      => $rules,
            'executions' => $executions,
            'pager'      => $pager,
            'floorList'  => $floorList,
            'rooms'      => $rooms,
        ]);
    }

    public function show(string $id)
    {
        $rule = (new AutomationRuleModel())
            ->select('automation_rules.*, users.name AS creator_name')
            ->join('users', 'users.id = automation_rules.created_by', 'left')
            ->where('automation_rules.id', (int) $id)
            ->first();

        if ($rule === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Aturan tidak ditemukan.');
        }

        $executions = (new AutomationExecutionModel())
            ->where('rule_id', (int) $id)
            ->orderBy('executed_at', 'DESC')
            ->limit(30)
            ->findAll();

        return view('automation/show', $this->layoutData($rule['name'], 'otomatisasi') + [
            'rule'       => $rule,
            'executions' => $executions,
        ]);
    }
}
