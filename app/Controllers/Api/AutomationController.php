<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;
use App\Exceptions\ServiceException;
use App\Models\AutomationExecutionModel;
use App\Models\AutomationRuleModel;

class AutomationController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $rules = (new AutomationRuleModel())
            ->select('automation_rules.*, users.name AS creator_name')
            ->join('users', 'users.id = automation_rules.created_by', 'left')
            ->orderBy('automation_rules.is_active', 'DESC')
            ->orderBy('automation_rules.name', 'ASC')
            ->findAll();

        return $this->success(['rules' => $rules]);
    }

    public function store()
    {
        try {
            $rule = service('automation')->createRule($this->body(), $this->auth->id());

            return $this->success(['rule' => $rule], 'Aturan dibuat.', 201);
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function update(string $id)
    {
        try {
            $rule = service('automation')->updateRule((int) $id, $this->body(), $this->auth->id());

            return $this->success(['rule' => $rule], 'Aturan diperbarui.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function destroy(string $id)
    {
        try {
            service('automation')->deleteRule((int) $id, $this->auth->id());

            return $this->success([], 'Aturan dihapus.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function executions()
    {
        $rows = (new AutomationExecutionModel())
            ->select('automation_executions.*, automation_rules.name AS rule_name')
            ->join('automation_rules', 'automation_rules.id = automation_executions.rule_id', 'left')
            ->orderBy('automation_executions.executed_at', 'DESC')
            ->limit(50)
            ->findAll();

        return $this->success(['executions' => $rows]);
    }
}
