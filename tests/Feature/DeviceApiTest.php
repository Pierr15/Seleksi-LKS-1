<?php

use App\Models\DeviceModel;
use Config\Database;
use Tests\Support\OrbitFeatureTestCase;

/**
 * Skenario 13-18: API perangkat (buat, perintah, monitor, posisi, arsip).
 *
 * @internal
 */
final class DeviceApiTest extends OrbitFeatureTestCase
{
    public function testCreateDeviceCreatesUnknownState(): void
    {
        $mark = $this->logIdMax();
        $room = $this->makeRoom('Ruang Perangkat ' . uniqid());
        $name = 'Lampu Uji ' . uniqid();

        $result = $this->admin()->post('api/devices', [
            'name'        => $name,
            'room_id'     => $room['id'],
            'device_type' => 'light',
        ]);

        $result->assertStatus(201);
        $body     = $this->json($result);
        $deviceId = (int) $body['data']['device']['id'];

        $device = (new DeviceModel())->find($deviceId);
        $this->assertNotNull($device);
        $this->assertSame($name, $device['name']);
        $this->assertNull($device['position_x'], 'Perangkat baru harus belum ditempatkan.');

        $state = Database::connect('tests')
            ->table('device_states')
            ->where('device_id', $deviceId)
            ->get()
            ->getRowArray();
        $this->assertNotNull($state, 'device_states wajib dibuat saat perangkat dibuat.');
        $this->assertSame('unknown', $state['power_state']);

        $log = $this->latestLog('device.create', $mark);
        $this->assertNotNull($log, 'Log device.create tidak ditulis.');
        $this->assertSame($deviceId, (int) $log['target_id']);
    }

    public function testCommandOnTurnsDeviceOnAndLogs(): void
    {
        $mark = $this->logIdMax();
        $room = $this->makeRoom('Ruang Perintah ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Lampu Perintah ' . uniqid());

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/command', ['command' => 'on']);

        $result->assertStatus(200);
        $body = $this->json($result);
        $this->assertSame('on', $body['data']['state']['power_state']);

        $state = Database::connect('tests')
            ->table('device_states')
            ->where('device_id', $device['id'])
            ->get()
            ->getRowArray();
        $this->assertSame('on', $state['power_state']);

        $commands = Database::connect('tests')
            ->table('device_commands')
            ->where('device_id', $device['id'])
            ->where('command', 'on')
            ->where('status', 'success')
            ->countAllResults();
        $this->assertSame(1, $commands, 'device_commands tidak dicatat.');

        $log = $this->latestLog('device.command', $mark);
        $this->assertNotNull($log, 'Log device.command tidak ditulis.');
        $this->assertSame('success', $log['result']);
        $details = json_decode((string) $log['details_json'], true);
        $this->assertSame('on', $details['command']);

        $this->admin()->post('api/devices/' . $device['id'] . '/command', ['command' => 'off'])
            ->assertStatus(200);
        $state = Database::connect('tests')
            ->table('device_states')
            ->where('device_id', $device['id'])
            ->get()
            ->getRowArray();
        $this->assertSame('off', $state['power_state']);
    }

    public function testMonitorOnlyDeviceRejectsCommand(): void
    {
        $room   = $this->makeRoom('Ruang Pantau ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Sensor Uji ' . uniqid(), [
            'control_capability' => 'monitor_only',
        ]);

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/command', ['command' => 'on']);

        $result->assertStatus(403);
        $body = $this->json($result);
        $this->assertFalse($body['success']);
        $this->assertNotEmpty($body['errors']['control_capability']);

        $commands = Database::connect('tests')
            ->table('device_commands')
            ->where('device_id', $device['id'])
            ->countAllResults();
        $this->assertSame(0, $commands, 'Perintah monitor-only tidak boleh tereksekusi.');
    }

    public function testPositionUpdatePersistsCoordinatesAndLogs(): void
    {
        $mark = $this->logIdMax();
        $room   = $this->makeRoom('Ruang Posisi ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Lampu Posisi ' . uniqid());

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/position', [
            'position_x' => 12.5,
            'position_y' => 80,
        ]);

        $result->assertStatus(200);

        $fresh = (new DeviceModel())->find($device['id']);
        $this->assertSame(12.5, (float) $fresh['position_x']);
        $this->assertSame(80.0, (float) $fresh['position_y']);

        $log = $this->latestLog('device.position_update', $mark);
        $this->assertNotNull($log, 'Log device.position_update tidak ditulis.');
        $this->assertSame((int) $device['id'], (int) $log['target_id']);
    }

    public function testPositionPartialCoordinatesRejected(): void
    {
        $room   = $this->makeRoom('Ruang Posisi Setengah ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Lampu Setengah ' . uniqid());

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/position', ['position_x' => 10]);

        $result->assertStatus(422);
        $body = $this->json($result);
        $this->assertNotEmpty($body['errors']['position']);

        $fresh = (new DeviceModel())->find($device['id']);
        $this->assertNull($fresh['position_x']);
        $this->assertNull($fresh['position_y']);
    }

    public function testArchivedDeviceRejectsCommand(): void
    {
        $room   = $this->makeRoom('Ruang Arsip Perangkat ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Lampu Arsip ' . uniqid());

        $this->admin()->post('api/devices/' . $device['id'] . '/archive', ['archive' => true])->assertStatus(200);

        $fresh = (new DeviceModel())->find($device['id']);
        $this->assertNotNull($fresh['archived_at']);

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/command', ['command' => 'on']);

        $result->assertStatus(409);
        $body = $this->json($result);
        $this->assertNotEmpty($body['errors']['device']);
    }

    public function testAcSetTemperaturePersistsTargetAndValidatesRange(): void
    {
        $room   = $this->makeRoom('Ruang AC Suhu ' . uniqid());
        $device = $this->makeDevice($room['id'], 'AC Suhu ' . uniqid(), ['device_type' => 'ac']);

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/command', [
            'command'     => 'set_temperature',
            'temperature' => 22,
        ]);
        $result->assertStatus(200);
        $body = $this->json($result);
        $this->assertSame(22.0, (float) $body['data']['state']['target_temperature']);

        $state = Database::connect('tests')->table('device_states')
            ->where('device_id', $device['id'])->get()->getRowArray();
        $this->assertSame(22.0, (float) $state['target_temperature']);

        $this->admin()->post('api/devices/' . $device['id'] . '/command', [
            'command'     => 'set_temperature',
            'temperature' => 10,
        ])->assertStatus(422);
    }

    public function testAcSetModeAndBoost(): void
    {
        $room   = $this->makeRoom('Ruang AC Mode ' . uniqid());
        $device = $this->makeDevice($room['id'], 'AC Mode ' . uniqid(), ['device_type' => 'ac']);

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/command', [
            'command' => 'set_mode',
            'mode'    => 'dry',
        ]);
        $result->assertStatus(200);
        $body = $this->json($result);
        $this->assertSame('dry', $body['data']['state']['ac_mode']);

        $result2 = $this->admin()->post('api/devices/' . $device['id'] . '/command', [
            'command' => 'set_boost',
            'boost'   => true,
        ]);
        $result2->assertStatus(200);
        $body2 = $this->json($result2);
        $this->assertEquals(1, (int) $body2['data']['state']['ac_boost']);

        $this->admin()->post('api/devices/' . $device['id'] . '/command', [
            'command' => 'set_mode',
            'mode'    => 'turbo',
        ])->assertStatus(422);
    }

    public function testComputerSleepAndShutdown(): void
    {
        $room   = $this->makeRoom('Ruang Komputer ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Komputer Uji ' . uniqid(), ['device_type' => 'computer']);

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/command', ['command' => 'sleep']);
        $result->assertStatus(200);
        $body = $this->json($result);
        $this->assertSame('sleep', $body['data']['state']['power_state']);

        $result2 = $this->admin()->post('api/devices/' . $device['id'] . '/command', ['command' => 'shutdown']);
        $result2->assertStatus(200);
        $body2 = $this->json($result2);
        $this->assertSame('off', $body2['data']['state']['power_state']);

        $this->admin()->post('api/devices/' . $device['id'] . '/command', ['command' => 'sleep'])->assertStatus(200);
        $state = Database::connect('tests')->table('device_states')
            ->where('device_id', $device['id'])->get()->getRowArray();
        $this->assertSame('sleep', $state['power_state']);
    }

    public function testAcCommandRejectedOnNonAcDevice(): void
    {
        $room   = $this->makeRoom('Ruang Salah Tipe ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Lampu Salah Tipe ' . uniqid(), ['device_type' => 'light']);

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/command', [
            'command'     => 'set_temperature',
            'temperature' => 24,
        ]);
        $result->assertStatus(422);
    }

    public function testSleepRejectedOnNonComputerDevice(): void
    {
        $room   = $this->makeRoom('Ruang Sleep Salah ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Lampu Sleep ' . uniqid(), ['device_type' => 'light']);

        $result = $this->admin()->post('api/devices/' . $device['id'] . '/command', ['command' => 'sleep']);
        $result->assertStatus(422);
    }
}
