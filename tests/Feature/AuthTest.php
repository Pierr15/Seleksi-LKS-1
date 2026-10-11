<?php

use App\Models\ActivityLogModel;
use App\Models\UserModel;
use Tests\Support\OrbitFeatureTestCase;

/**
 * Skenario 1-5: autentikasi, sesi, dan otorisasi peran.
 *
 * @internal
 */
final class AuthTest extends OrbitFeatureTestCase
{
    public function testLoginSuccessRedirectsToDashboardAndWritesAudit(): void
    {
        $mark = $this->logIdMax();

        $result = $this->withSession([])
            ->post('login', [
                'csrf_test_name' => self::CSRF,
                'email'          => 'admin@orbit.local',
                'password'       => 'Orbit-Admin-2026!',
            ]);

        $result->assertRedirectTo('/dashboard');
        $this->assertSame(1, $_SESSION['orbit_user_id'] ?? null);
        $this->assertSame('admin', $_SESSION['orbit_role'] ?? null);

        $log = $this->latestLog('auth.login', $mark);
        $this->assertNotNull($log, 'Log auth.login tidak ditulis.');
        $this->assertSame('success', $log['result']);
        $this->assertSame(1, (int) $log['user_id']);
    }

    public function testLoginFailureRejectsAndWritesAuditLog(): void
    {
        $users = new UserModel();
        $users->update(1, ['failed_login_attempts' => 0, 'locked_until' => null]);
        $mark = $this->logIdMax();

        $result = $this->withSession([])
            ->withHeaders(['Referer' => 'http://example.com/login'])
            ->post('login', [
                'csrf_test_name' => self::CSRF,
                'email'          => 'admin@orbit.local',
                'password'       => 'kata-sandi-salah',
            ]);

        $result->assertStatus(302);
        $this->assertArrayNotHasKey('orbit_user_id', $_SESSION);

        $fresh = $users->find(1);
        $this->assertSame(1, (int) $fresh['failed_login_attempts'], 'Percobaan gagal tidak tercatat.');

        $log = $this->latestLog('auth.login_failed', $mark);
        $this->assertNotNull($log, 'Log auth.login_failed tidak ditulis.');
        $this->assertSame('failure', $log['result']);

        // Pulihkan agar akun admin tidak terkunci oleh pengujian berikutnya.
        $users->update(1, ['failed_login_attempts' => 0, 'locked_until' => null]);
    }

    public function testGuestPageRedirectsToLogin(): void
    {
        $this->withSession([])->get('dashboard')->assertRedirectTo('/login');
    }

    public function testApiWithoutSessionReturns401Json(): void
    {
        $result = $this->guestApi()->get('api/rooms');

        $result->assertStatus(401);
        $body = $this->json($result);
        $this->assertFalse($body['success']);
        $this->assertNotEmpty($body['message']);
    }

    public function testViewerAndOperatorForbiddenOnAdminEndpoint(): void
    {
        $viewerId   = $this->makeUser('viewer');
        $operatorId = $this->makeUser('operator');

        $viewer = $this->apiAs($viewerId, 'viewer')->get('api/users');
        $viewer->assertStatus(403);
        $this->assertFalse($this->json($viewer)['success']);

        $operator = $this->apiAs($operatorId, 'operator')->post('api/rooms', [
            'name'     => 'Tidak Boleh ' . uniqid(),
            'floor_id' => $this->floorId(),
        ]);
        $operator->assertStatus(403);
        $this->assertFalse($this->json($operator)['success']);
    }
}
