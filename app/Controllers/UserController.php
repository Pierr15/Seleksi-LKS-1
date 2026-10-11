<?php

namespace App\Controllers;

use App\Models\UserModel;

class UserController extends BaseController
{
    public function index()
    {
        $search = trim((string) ($this->request->getGet('q') ?? ''));
        $role   = $this->request->getGet('peran');

        $model   = new UserModel();
        $builder = $model->select('id, name, email, role, is_active, last_login_at, created_at');

        if ($search !== '') {
            $builder->groupStart()
                ->like('name', $search)
                ->like('email', $search)
                ->groupEnd();
        }

        if (in_array($role, ['admin', 'operator', 'viewer'], true)) {
            $builder->where('role', $role);
        }

        $users = $builder->orderBy('name', 'ASC')->paginate(10);
        $pager = $model->pager;

        return view('users/index', $this->layoutData('Manajemen Pengguna', 'pengguna') + [
            'users'   => $users,
            'pager'   => $pager,
            'filters' => ['q' => $search, 'peran' => $role ?? ''],
        ]);
    }
}
