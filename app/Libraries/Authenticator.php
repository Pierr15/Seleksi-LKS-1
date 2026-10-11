<?php

namespace App\Libraries;

use App\Models\UserModel;
use App\Services\AuditLogService;

/**
 * Autentikasi berbasis session CodeIgniter.
 */
class Authenticator
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    private ?array $cachedUser = null;

    public function __construct(
        private ?UserModel $users = null,
        private ?AuditLogService $audit = null,
    ) {
        $this->users ??= model(UserModel::class);
        $this->audit ??= service('auditLog');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $userId = session('orbit_user_id');

        if ($userId === null) {
            return null;
        }

        $user = $this->users->find($userId);

        if ($user === null || (int) $user['is_active'] !== 1) {
            $this->logout();

            return null;
        }

        return $this->cachedUser = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user !== null ? (int) $user['id'] : null;
    }

    public function hasRole(string ...$roles): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user['role'], $roles, true);
    }

    /**
     * Mencoba login. Mengembalikan ['ok' => bool, 'message' => string].
     *
     * @return array{ok: bool, message: string}
     */
    public function attempt(string $email, string $password, ?string $ip = null): array
    {
        $email = strtolower(trim($email));
        $user  = $this->users->where('email', $email)->first();

        if ($user === null) {
            // Respon generik: jangan membocorkan keberadaan akun.
            $this->audit->log('auth.login_failed', [
                'user_id'     => null,
                'target_type' => 'auth',
                'result'      => 'failure',
                'ip_address'  => $ip,
                'details'     => ['email' => $email, 'alasan' => 'akun tidak ditemukan'],
            ]);

            return ['ok' => false, 'message' => 'Email atau kata sandi salah.'];
        }

        if ((int) $user['is_active'] !== 1) {
            $this->audit->log('auth.login_failed', [
                'user_id'     => (int) $user['id'],
                'target_type' => 'auth',
                'target_id'   => (int) $user['id'],
                'result'      => 'failure',
                'ip_address'  => $ip,
                'details'     => ['alasan' => 'akun nonaktif'],
            ]);

            return ['ok' => false, 'message' => 'Akun dinonaktifkan. Hubungi administrator.'];
        }

        if ($user['locked_until'] !== null && $user['locked_until'] > orbit_utc_now()) {
            $this->audit->log('auth.login_failed', [
                'user_id'     => (int) $user['id'],
                'target_type' => 'auth',
                'target_id'   => (int) $user['id'],
                'result'      => 'failure',
                'ip_address'  => $ip,
                'details'     => ['alasan' => 'akun terkunci sementara'],
            ]);

            return ['ok' => false, 'message' => 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.'];
        }

        if (! password_verify($password, $user['password_hash'])) {
            $failed  = (int) $user['failed_login_attempts'] + 1;
            $updates = ['failed_login_attempts' => $failed];

            if ($failed >= self::MAX_ATTEMPTS) {
                $updates['locked_until'] = gmdate(
                    'Y-m-d H:i:s',
                    time() + self::LOCK_MINUTES * 60,
                );
            }

            $this->users->update($user['id'], $updates);

            $this->audit->log('auth.login_failed', [
                'user_id'     => (int) $user['id'],
                'target_type' => 'auth',
                'target_id'   => (int) $user['id'],
                'result'      => 'failure',
                'ip_address'  => $ip,
                'details'     => ['alasan' => 'kata sandi salah', 'percobaan' => $failed],
            ]);

            return ['ok' => false, 'message' => 'Email atau kata sandi salah.'];
        }

        // Regenerasi session ID setelah login sukses.
        session()->regenerate(true);

        $this->users->update($user['id'], [
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => orbit_utc_now(),
        ]);

        session()->set([
            'orbit_user_id' => (int) $user['id'],
            'orbit_role'    => $user['role'],
        ]);

        $this->cachedUser = null;

        $this->audit->log('auth.login', [
            'user_id'     => (int) $user['id'],
            'target_type' => 'auth',
            'target_id'   => (int) $user['id'],
            'result'      => 'success',
            'ip_address'  => $ip,
        ]);

        return ['ok' => true, 'message' => 'Login berhasil.'];
    }

    public function logout(): void
    {
        $userId = session('orbit_user_id');

        session()->remove(['orbit_user_id', 'orbit_role']);
        session()->regenerate(true);

        if ($userId !== null) {
            $this->audit->log('auth.logout', [
                'user_id'     => (int) $userId,
                'target_type' => 'auth',
                'target_id'   => (int) $userId,
                'result'      => 'success',
            ]);
        }

        $this->cachedUser = null;
    }
}
