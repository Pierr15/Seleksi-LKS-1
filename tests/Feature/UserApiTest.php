<?php

use App\Models\UserModel;
use Tests\Support\OrbitFeatureTestCase;

/**
 * Skenario 26-28: API pengguna (buat, validasi, ubah peran/sandi/nonaktif).
 *
 * @internal
 */
final class UserApiTest extends OrbitFeatureTestCase
{
    public function testCreateUserHashesPasswordAndLogs(): void
    {
        $mark = $this->logIdMax();
        $email = 'uji-' . uniqid() . '@orbit.test';

        $result = $this->admin()->post('api/users', [
            'name'     => 'Pengguna Baru Uji',
            'email'    => $email,
            'password' => 'Sandi-Kuat-123!',
            'role'     => 'viewer',
        ]);

        $result->assertStatus(201);
        $body = $this->json($result);
        $this->assertSame('viewer', $body['data']['user']['role']);
        $this->assertSame($email, $body['data']['user']['email']);

        $user = (new UserModel())->where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertNotSame('Sandi-Kuat-123!', $user['password_hash'], 'Sandi tidak boleh disimpan polos.');
        $this->assertTrue(password_verify('Sandi-Kuat-123!', $user['password_hash']));
        $this->assertSame(1, (int) $user['is_active']);

        $log = $this->latestLog('user.create', $mark);
        $this->assertNotNull($log, 'Log user.create tidak ditulis.');
        $this->assertSame((int) $user['id'], (int) $log['target_id']);
    }

    public function testDuplicateEmailRejected(): void
    {
        $email = 'duplikat-' . uniqid() . '@orbit.test';

        $this->admin()->post('api/users', [
            'name'     => 'Duplikat Awal',
            'email'    => $email,
            'password' => 'Sandi-Kuat-123!',
            'role'     => 'viewer',
        ])->assertStatus(201);

        $dup = $this->admin()->post('api/users', [
            'name'     => 'Duplikat Kedua',
            'email'    => $email,
            'password' => 'Sandi-Kuat-123!',
            'role'     => 'viewer',
        ]);

        $dup->assertStatus(422);
        $this->assertSame('Email sudah terdaftar.', $this->json($dup)['errors']['email']);
        $this->assertSame(1, (new UserModel())->where('email', $email)->countAllResults());
    }

    public function testUpdateRolePasswordAndSelfDeactivate(): void
    {
        $userId = $this->makeUser('operator');

        $role = $this->admin()->put('api/users/' . $userId, ['role' => 'viewer']);

        $role->assertStatus(200);
        $this->assertSame('viewer', $this->json($role)['data']['user']['role']);
        $this->assertSame('viewer', (new UserModel())->find($userId)['role']);

        $pass = $this->admin()->put('api/users/' . $userId, ['password' => 'Sandi-Baru-123!']);

        $pass->assertStatus(200);
        $fresh = (new UserModel())->find($userId);
        $this->assertTrue(password_verify('Sandi-Baru-123!', $fresh['password_hash']));

        $self = $this->admin()->put('api/users/1', ['is_active' => 0]);

        $self->assertStatus(422);
        $this->assertNotEmpty($this->json($self)['errors']['is_active']);
        $this->assertSame(1, (int) (new UserModel())->find(1)['is_active'], 'Admin tidak boleh menonaktifkan dirinya.');
    }
}
