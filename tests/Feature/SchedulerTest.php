<?php

use Tests\Support\OrbitFeatureTestCase;

/**
 * Skenario 25: scheduler tick (status, sensor, eksekusi, status segar).
 *
 * @internal
 */
final class SchedulerTest extends OrbitFeatureTestCase
{
    public function testTickRunsWritesStatusAndFailsafe(): void
    {
        $settings = service('settings');
        $settings->set('scheduler_last_run_at', gmdate('Y-m-d H:i:s', time() - 3600));
        $settings->set('scheduler_last_status', 'stale');

        $before = service('scheduler')->status();
        $this->assertFalse($before['fresh'], 'Status harus basi sebelum tick.');

        // GET_LOCK dipakai bersama dengan container scheduler hidup — coba beberapa kali.
        $result = null;
        for ($i = 0; $i < 20; $i++) {
            $result = service('scheduler')->tick();
            if (($result['ran'] ?? false) === true) {
                break;
            }
            usleep(500000);
        }

        $this->assertTrue($result['ran'] ?? false, 'Tick tidak berjalan: ' . json_encode($result));
        $this->assertSame([], $result['errors'], 'Tahap scheduler gagal.');
        $this->assertArrayHasKey('overrides', $result['steps']);
        $this->assertArrayHasKey('sensors', $result['steps']);
        $this->assertArrayHasKey('automation', $result['steps']);

        // Sensor bisa skip bila interval tulis 300 detik belum lewat — yang wajib: struktur.
        $this->assertIsArray($result['steps']['sensors']);
        $this->assertArrayHasKey('written', $result['steps']['sensors']);
        $this->assertArrayHasKey('skipped', $result['steps']['sensors']);
        $this->assertIsInt($result['steps']['sensors']['written']);

        $status = service('scheduler')->status();
        $this->assertNotSame($before['last_run'], $status['last_run'], 'scheduler_last_run_at tidak diperbarui.');
        $this->assertSame('ok', $status['status']);
        $this->assertTrue($status['fresh'], 'Status harus segar setelah tick.');
    }
}
