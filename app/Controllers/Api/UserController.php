<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;
use App\Exceptions\ServiceException;
use App\Models\UserModel;

class UserController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $users = (new UserModel())
            ->select('id, name, email, role, is_active, last_login_at, created_at')
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->success(['users' => $users]);
    }

    public function store()
    {
        $body = $this->body();
        $name = trim((string) ($body['name'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $role = (string) ($body['role'] ?? 'viewer');

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Nama wajib diisi.';
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email tidak valid.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password minimal 8 karakter.';
        }

        if (! in_array($role, ['admin', 'operator', 'viewer'], true)) {
            $errors['role'] = 'Peran tidak dikenal.';
        }

        if ($errors === [] && (new UserModel())->where('email', $email)->countAllResults() > 0) {
            $errors['email'] = 'Email sudah terdaftar.';
        }

        if ($errors !== []) {
            return $this->fail(new ServiceException('Data pengguna tidak valid.', 422, $errors));
        }

        $model = new UserModel();
        $userId = $model->insert([
            'name'                 => $name,
            'email'                => $email,
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'role'                 => $role,
            'is_active'            => 1,
            'failed_login_attempts'=> 0,
            'locked_until'         => null,
        ]);

        if ($userId === false) {
            return $this->fail(new ServiceException(
                'Pengguna gagal disimpan.',
                422,
                $model->errors() ?: ['form' => 'Pengguna gagal disimpan.'],
            ));
        }

        service('auditLog')->log('user.create', [
            'user_id'     => $this->auth->id(),
            'target_type' => 'user',
            'target_id'   => (int) $userId,
            'details'     => ['nama' => $name, 'peran' => $role],
        ]);

        $user = (new UserModel())->select('id, name, email, role, is_active, created_at')->find($userId);

        return $this->success(['user' => $user], 'Pengguna ditambahkan.', 201);
    }

    public function update(string $id)
    {
        $model = new UserModel();
        $user  = $model->find((int) $id);

        if ($user === null) {
            return $this->failGeneric('Pengguna tidak ditemukan.', 404);
        }

        $body   = $this->body();
        $errors = [];
        $update = [];

        if (array_key_exists('name', $body)) {
            $name = trim((string) $body['name']);
            if ($name === '') {
                $errors['name'] = 'Nama wajib diisi.';
            } else {
                $update['name'] = $name;
            }
        }

        if (array_key_exists('email', $body)) {
            $email = trim((string) $body['email']);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Email tidak valid.';
            } else {
                $dupes = (new UserModel())
                    ->where('email', $email)
                    ->where('id !=', (int) $id)
                    ->countAllResults();
                if ($dupes > 0) {
                    $errors['email'] = 'Email sudah dipakai pengguna lain.';
                } else {
                    $update['email'] = $email;
                }
            }
        }

        if (array_key_exists('role', $body)) {
            if (! in_array($body['role'], ['admin', 'operator', 'viewer'], true)) {
                $errors['role'] = 'Peran tidak dikenal.';
            } else {
                $update['role'] = $body['role'];
            }
        }

        if (array_key_exists('is_active', $body)) {
            $update['is_active'] = (int) $body['is_active'] ? 1 : 0;
        }

        if (array_key_exists('password', $body) && $body['password'] !== null && $body['password'] !== '') {
            if (strlen((string) $body['password']) < 8) {
                $errors['password'] = 'Password minimal 8 karakter.';
            } else {
                $update['password_hash'] = password_hash((string) $body['password'], PASSWORD_DEFAULT);
            }
        }

        if ($errors !== []) {
            return $this->fail(new ServiceException('Data pengguna tidak valid.', 422, $errors));
        }

        if ($update === []) {
            return $this->failGeneric('Tidak ada perubahan.', 422, ['form' => 'Tidak ada data yang diubah.']);
        }

        // Jangan biarkan admin menonaktifkan akunnya sendiri.
        if ((int) $id === (int) $this->auth->id() && isset($update['is_active']) && $update['is_active'] === 0) {
            return $this->fail(new ServiceException(
                'Tidak dapat menonaktifkan akun sendiri.',
                422,
                ['is_active' => 'Akun aktif Anda tidak dapat dinonaktifkan.'],
            ));
        }

        $model->update((int) $id, $update);

        if ($model->errors() !== []) {
            return $this->fail(new ServiceException('Data pengguna tidak valid.', 422, $model->errors()));
        }

        service('auditLog')->log('user.update', [
            'user_id'     => $this->auth->id(),
            'target_type' => 'user',
            'target_id'   => (int) $id,
            'details'     => ['ubah' => array_keys($update)],
        ]);

        $fresh = (new UserModel())->select('id, name, email, role, is_active, created_at')->find((int) $id);

        return $this->success(['user' => $fresh], 'Pengguna diperbarui.');
    }
}
