<?php

use App\Models\AutomationRuleModel;
use App\Models\RoomModel;
use Config\Database;
use Tests\Support\OrbitFeatureTestCase;

/**
 * Skenario 22-24: aturan otomatisasi (validasi dan eksekusi).
 *
 * @internal
 */
final class AutomationTest extends OrbitFeatureTestCase
{
    public function testCreateValidRulePersistsListAndLogs(): void
    {
        $mark = $this->logIdMax();
        $name = 'Aturan Uji ' . uniqid();

        $result = $this->admin()->post('api/automation/rules', [
            'name'            => $name,
            'description'     => 'Dibuat oleh pengujian.',
            'trigger_type'    => 'vacant_duration',
            'duration_seconds'=> 300,
            'target_json'     => '{"room_ids":[]}',
            'actions_json'    => '[{"type":"set_room_mode","mode":"standby"}]',
            'is_active'       => 1,
        ]);

        $result->assertStatus(201);
        $body = $this->json($result);
        $this->assertSame($name, $body['data']['rule']['name']);
        $this->assertSame(1, (int) $body['data']['rule']['is_active']);

        $stored = (new AutomationRuleModel())->find((int) $body['data']['rule']['id']);
        $this->assertNotNull($stored);
        $this->assertSame(300, (int) $stored['duration_seconds']);

        $list = $this->json($this->admin()->get('api/automation/rules'));
        $names = array_column($list['data']['rules'], 'name');
        $this->assertContains($name, $names);

        $this->assertNotNull($this->latestLog('rule.create', $mark));
    }

    public function testInvalidRulePayloadRejected(): void
    {
        $mark = $this->logIdMax();
        $name = 'Sampah ' . uniqid();

        $bad = $this->admin()->post('api/automation/rules', [
            'name'            => $name,
            'trigger_type'    => 'vacant_duration',
            'duration_seconds'=> 60,
            'actions_json'    => '[{"type":"meledakkan"}]',
        ]);

        $bad->assertStatus(422);
        $body = $this->json($bad);
        $this->assertStringContainsString('Tindakan tidak dikenal', (string) $body['message']);
        $this->assertSame(0, (new AutomationRuleModel())->where('name', $name)->countAllResults());

        $noName = $this->admin()->post('api/automation/rules', [
            'name'            => '',
            'trigger_type'    => 'vacant_duration',
            'duration_seconds'=> 60,
            'actions_json'    => '[{"type":"turn_off"}]',
        ]);
        $noName->assertStatus(422);
        $this->assertNotEmpty($this->json($noName)['errors']['name']);

        $this->assertNull($this->latestLog('rule.create', $mark));
    }

    public function testVacantRuleExecutesSetRoomMode(): void
    {
        $this->deactivateRules();

        $room = $this->makeRoom('Ruang Aturan Vacant ' . uniqid());
        $this->makeVacant($room['id'], 7200);

        $rule = service('automation')->createRule([
            'name'            => 'Vacant Jalan ' . uniqid(),
            'trigger_type'    => 'vacant_duration',
            'duration_seconds'=> 60,
            'target_json'     => json_encode(['room_ids' => [(int) $room['id']]]),
            'actions_json'    => json_encode([['type' => 'set_room_mode', 'mode' => 'standby']]),
            'is_active'       => 1,
        ], 1);

        $mark = $this->logIdMax();
        $summary = service('automation')->processDueRules('UJI-VAC-' . uniqid());

        $this->assertSame(1, $summary['due']);
        $this->assertSame(1, $summary['executed']);
        $this->assertSame(0, $summary['failed']);

        $roomRow = (new RoomModel())->find($room['id']);
        $this->assertSame('standby', $roomRow['mode']);

        $execution = Database::connect('tests')
            ->table('automation_executions')
            ->where('rule_id', $rule['id'])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        $this->assertNotNull($execution);
        $this->assertSame('executed', $execution['status']);

        $this->assertNotNull($this->latestLog('rule.executed', $mark));
    }
}
