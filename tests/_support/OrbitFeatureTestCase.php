<?php

namespace Tests\Support;

use App\Models\ActivityLogModel;
use App\Models\DeviceModel;
use App\Models\FloorModel;
use App\Models\RoomModel;
use App\Models\UserModel;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Basis seluruh pengujian skenario ORBIT Building.
 *
 * - Database: grup `tests` (orbit_building_test). Migrasi semua namespace +
 *   seeder idempoten dijalankan tiap test, tanpa regress (data antar test
 *   dipisah lewat nama unik + data buatan sendiri).
 * - CSRF mode cookie: cookie dan header dibuat identik agar filter lolos.
 * - Login disuntikkan lewat withSession() (kunci orbit_user_id / orbit_role).
 */
abstract class OrbitFeatureTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = null;
    protected $refresh = false;
    protected $seed = \App\Database\Seeds\DatabaseSeeder::class;

    // Harus 32 karakter hex — Security::isHashInCookie() memvalidasi pola ini.
    protected const CSRF = '0f1e2d3c4b5a69788796a5b4c3d2e1f0';

    protected function setUp(): void
    {
        // Buang service bersama (cache Authenticator, Security, ...) agar
        // tiap test dimulai dari keadaan bersih.
        Services::reset();

        // BaseConfig membaca $_ENV lebih dulu — paksa grup default aplikasi
        // ke DB uji supaya model/service tidak menyentuh DB produksi.
        $_ENV['database.default.hostname'] = 'db';
        $_ENV['database.default.database'] = 'orbit_building_test';
        $_ENV['database.default.username'] = 'orbit';
        $_ENV['database.default.password'] = 'orbit_pw';
        $_ENV['database.default.DBDriver'] = 'MySQLi';
        $_ENV['database.default.DBPrefix'] = '';
        $_ENV['database.default.port']     = '3306';

        parent::setUp();

        $_COOKIE['csrf_cookie_name'] = self::CSRF;
    }

    // ---------------------------------------------------------------- HTTP

    /**
     * Request JSON sebagai tamu (tanpa session) + token CSRF.
     *
     * @return $this
     */
    protected function guestApi()
    {
        return $this->withSession([])
            ->withHeaders(['X-CSRF-TOKEN' => self::CSRF, 'Accept' => 'application/json'])
            ->withBodyFormat('json');
    }

    /**
     * Request JSON dengan session pengguna tertentu.
     *
     * @return $this
     */
    protected function apiAs(int $userId, string $role)
    {
        return $this
            ->withSession(['orbit_user_id' => $userId, 'orbit_role' => $role])
            ->withHeaders(['X-CSRF-TOKEN' => self::CSRF, 'Accept' => 'application/json'])
            ->withBodyFormat('json');
    }

    /**
     * Request JSON sebagai AdminSeeder (id 1).
     *
     * @return $this
     */
    protected function admin()
    {
        return $this->apiAs(1, 'admin');
    }

    /**
     * Decode body JSON respons feature test.
     *
     * @return array<string, mixed>
     */
    protected function json($result): array
    {
        return json_decode((string) $result->getJSON(), true);
    }

    // --------------------------------------------------------------- Data

    protected function floorId(): int
    {
        $floor = (new FloorModel())->where('slug', 'lantai-1')->first();

        $this->assertNotNull($floor, 'FloorsSeeder belum menghasilkan lantai-1.');

        return (int) $floor['id'];
    }

    /**
     * Buat ruangan uji (default: aktif, closed, occupied).
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    protected function makeRoom(string $name, array $extra = []): array
    {
        $model = new RoomModel();
        $id    = $model->insert($extra + [
            'floor_id'  => $this->floorId(),
            'name'      => $name,
            'room_type' => 'closed',
            'mode'      => 'active',
            'occupancy' => 'occupied',
            'is_active' => 1,
        ]);

        $this->assertIsInt($id, 'Gagal membuat ruangan uji: ' . json_encode($model->errors()));

        return $model->find($id);
    }

    /**
     * Buat perangkat uji (default: light, controllable, tidak esensial).
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    protected function makeDevice(int $roomId, string $name, array $extra = []): array
    {
        $model = new DeviceModel();
        $id    = $model->insert($extra + [
            'room_id'            => $roomId,
            'name'               => $name,
            'device_type'        => 'light',
            'control_capability' => 'controllable',
            'is_essential'       => 0,
            'inventory_status'   => 'verified',
            'is_active'          => 1,
        ]);

        $this->assertIsInt($id, 'Gagal membuat perangkat uji: ' . json_encode($model->errors()));

        return $model->find($id);
    }

    protected function makeUser(string $role): int
    {
        $model = new UserModel();
        $id    = $model->insert([
            'name'                  => 'Pengguna ' . $role . ' ' . uniqid(),
            'email'                 => $role . '-' . uniqid() . '@orbit.test',
            'password_hash'         => password_hash('Sandi-Uji-123!', PASSWORD_DEFAULT),
            'role'                  => $role,
            'is_active'             => 1,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);

        $this->assertIsInt($id, 'Gagal membuat pengguna uji: ' . json_encode($model->errors()));

        return (int) $id;
    }

    /** Tandai ruangan kosong sejak $secondsAgo detik yang lalu (langsung ke DB). */
    protected function makeVacant(int $roomId, int $secondsAgo = 7200): void
    {
        (new RoomModel())->update($roomId, [
            'occupancy'    => 'vacant',
            'vacant_since' => gmdate('Y-m-d H:i:s', time() - $secondsAgo),
        ]);
    }

    /** Nonaktifkan semua aturan otomatisasi (isolasi antar test). */
    protected function deactivateRules(): void
    {
        \Config\Database::connect('tests')
            ->table('automation_rules')
            ->where('is_active', 1)
            ->update(['is_active' => 0]);
    }

    /** ID activity_logs terbesar saat ini — penanda sebelum menjalankan aksi. */
    protected function logIdMax(): int
    {
        $row = (new ActivityLogModel())->selectMax('id')->get()->getRow();

        return $row !== null && $row->id !== null ? (int) $row->id : 0;
    }

    /** Baris activity_logs terbaru untuk aksi tertentu (setelah $sinceId). */
    protected function latestLog(string $action, int $sinceId = 0): ?array
    {
        return (new ActivityLogModel())
            ->where('action', $action)
            ->where('id >', $sinceId)
            ->orderBy('id', 'DESC')
            ->first();
    }
}
