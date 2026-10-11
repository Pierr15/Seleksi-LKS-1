<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->success(service('dashboard')->summary());
    }
}
