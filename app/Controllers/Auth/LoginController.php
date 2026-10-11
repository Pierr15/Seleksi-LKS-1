<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;

class LoginController extends BaseController
{
    public function loginForm()
    {
        if ($this->auth->check()) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login', [
            'error'   => session('error'),
            'warning' => session('login_required'),
            'success' => session('success'),
        ]);
    }

    public function attempt()
    {
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $result = $this->auth->attempt(
            is_string($email) ? $email : '',
            is_string($password) ? $password : '',
            $this->request->getIPAddress(),
        );

        if (! $result['ok']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }

        return redirect()->to('/dashboard')->with('success', 'Selamat datang kembali.');
    }

    public function logout()
    {
        $this->auth->logout();

        return redirect()->to('/login')->with('success', 'Anda telah keluar.');
    }
}
