<?php

namespace App\Controllers;

/**
 * Halaman utama → arahkan ke dashboard.
 */
class Home extends BaseController
{
    public function index()
    {
        return redirect()->to('/dashboard');
    }
}
