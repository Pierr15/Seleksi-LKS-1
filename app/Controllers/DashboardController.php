<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        $summary = service('dashboard')->summary();

        return view('dashboard/index', $this->layoutData('Dashboard', 'dashboard') + [
            'summary' => $summary,
        ]);
    }
}
