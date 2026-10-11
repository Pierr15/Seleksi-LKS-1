<?php

use Config\Database;
use Tests\Support\OrbitFeatureTestCase;

/**
 * Skenario 19-21: override perangkat.
 *
 * @internal
 */
final class OverrideTest extends OrbitFeatureTestCase
{
    public function testCreatePersistentOverrideListAndDuplicateRejected(): void
    {
        $mark = $this->logIdMax();
        $room   = $this->makeRoom('Ruang Override ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Lampu Override ' . uniqid());

        $result = $this->admin()->post('api/overrides', [
            'device_id'     => $device['id'],
            'override_type' => 'persistent',
            'reason'        => 'Uji override ' . uniqid(),
        ]);

        $result->assertStatus(201);
        $body = $this->json($result);
        $overrideId = (int) $body['data']['override']['id'];
        $this->assertSame('persistent', $body['data']['override']['override_type']);
        $this->assertNull($body['data']['override']['ended_at']);

        $list = $this->json($this->admin()->get('api/overrides'));
        $ids  = array_map(static fn (array $r): int => (int) $r['id'], $list['data']['overrides']);
        $this->assertContains($overrideId, $ids);

        $this->assertNotNull($this->latestLog('override.create', $mark));

        // Duplikat saat masih aktif → 409.
        $dup = $this->admin()->post('api/overrides', [
            'device_id'     => $device['id'],
            'override_type' => 'persistent',
            'reason'        => 'Duplikat',
        ]);
        $dup->assertStatus(409);

        // time_limited tanpa expires_at → 422.
        $bad = $this->admin()->post('api/overrides', [
            'device_id'     => $device['id'],
            'override_type' => 'time_limited',
            'reason'        => 'Tanpa waktu berakhir',
        ]);
        $bad->assertStatus(422);
        $this->assertNotEmpty($this->json($bad)['errors']['expires_at']);
    }

    public function testOverrideBlocksAutomationButNotManualCommand(): void
    {
        $this->deactivateRules();

        $room   = $this->makeRoom('Ruang Terlindungi ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Lampu Terlindungi ' . uniqid());

        service('deviceControl')->execute($device['id'], 'on', 'manual', 1);
        service('overrideService')->create($device['id'], 'persistent', 'Uji blokir otomatis', 1);

        // Manual TIDAK diblokir override.
        $manual = $this->admin()->post('api/devices/' . $device['id'] . '/command', ['command' => 'off']);
        $manual->assertStatus(200);
        $this->assertSame('off', $this->json($manual)['data']['state']['power_state']);
        service('deviceControl')->execute($device['id'], 'on', 'manual', 1);

        $rule = service('automation')->createRule([
            'name'            => 'Otomatisasi Terlindungi ' . uniqid(),
            'trigger_type'    => 'vacant_duration',
            'duration_seconds'=> 60,
            'target_json'     => json_encode(['room_ids' => [(int) $room['id']]]),
            'actions_json'    => json_encode([['type' => 'turn_off']]),
            'is_active'       => 1,
        ], 1);

        $this->makeVacant($room['id'], 7200);

        $mark = $this->logIdMax();
        $summary = service('automation')->processDueRules('UJI-OVR-' . uniqid());

        $this->assertSame(1, $summary['due']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(0, $summary['executed']);

        $execution = Database::connect('tests')
            ->table('automation_executions')
            ->where('rule_id', $rule['id'])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        $this->assertSame('skipped', $execution['status']);
        $this->assertStringContainsString('Dilindungi override aktif.', (string) $execution['reason']);

        $state = Database::connect('tests')
            ->table('device_states')
            ->where('device_id', $device['id'])
            ->get()
            ->getRowArray();
        $this->assertSame('on', $state['power_state'], 'Perangkat tak boleh dimatikan otomatis.');
        $this->assertNull($this->latestLog('device.command', $mark));
    }

    public function testEndOverrideAllowsSubsequentAutomation(): void
    {
        $this->deactivateRules();

        $room   = $this->makeRoom('Ruang Akhiri Override ' . uniqid());
        $device = $this->makeDevice($room['id'], 'Lampu Akhir Override ' . uniqid());

        service('deviceControl')->execute($device['id'], 'on', 'manual', 1);
        $override = service('overrideService')->create($device['id'], 'persistent', 'Uji akhiri', 1);

        $rule = service('automation')->createRule([
            'name'            => 'Setelah Override ' . uniqid(),
            'trigger_type'    => 'vacant_duration',
            'duration_seconds'=> 60,
            'target_json'     => json_encode(['room_ids' => [(int) $room['id']]]),
            'actions_json'    => json_encode([['type' => 'turn_off']]),
            'is_active'       => 1,
        ], 1);

        $this->makeVacant($room['id'], 7200);

        // Akhiri override lewat API.
        $this->admin()->post('api/overrides/' . $override['id'] . '/end', [])->assertStatus(200);
        $this->assertFalse(service('overrideService')->hasActive($device['id']));

        // Akhiri dua kali → 409.
        $this->admin()->post('api/overrides/' . $override['id'] . '/end', [])->assertStatus(409);

        $mark = $this->logIdMax();
        $summary = service('automation')->processDueRules('UJI-END-' . uniqid());

        $this->assertSame(1, $summary['due']);
        $this->assertSame(1, $summary['executed']);

        $state = Database::connect('tests')
            ->table('device_states')
            ->where('device_id', $device['id'])
            ->get()
            ->getRowArray();
        $this->assertSame('off', $state['power_state']);

        $execution = Database::connect('tests')
            ->table('automation_executions')
            ->where('rule_id', $rule['id'])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        $this->assertSame('executed', $execution['status']);
        $this->assertNotNull($this->latestLog('rule.executed', $mark));
    }
}
