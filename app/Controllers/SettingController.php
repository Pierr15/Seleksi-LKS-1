<?php

namespace App\Controllers;

class SettingController extends BaseController
{
    public function index()
    {
        $settings = service('settings')->all();
        $scheduler = service('scheduler')->status();

        $envAdmin = [
            'name'  => (string) ($_SERVER['SEED_ADMIN_NAME'] ?? ''),
            'email' => (string) ($_SERVER['SEED_ADMIN_EMAIL'] ?? ''),
        ];

        return view('settings/index', $this->layoutData('Pengaturan', 'pengaturan') + [
            'settings'  => $settings,
            'scheduler' => $scheduler,
            'envAdmin'  => $envAdmin,
        ]);
    }
}
