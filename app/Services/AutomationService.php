<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\AutomationExecutionModel;
use App\Models\AutomationRuleModel;
use App\Models\DeviceModel;
use App\Models\RoomModel;

/**
 * Mesin otomatisasi.
 *
 * Prinsip:
 * - Aturan hanya dipertimbangkan ketika kondisi DAN waktunya terpenuhi.
 * - Perangkat esensial tidak pernah dimatikan oleh aturan hemat energi umum.
 * - Override aktif dihormati (dicek lagi tepat sebelum eksekusi).
 * - Tindakan yang saling bertentangan antar aturan tidak dijalankan;
 *   konflik dicatat pada riwayat eksekusi.
 * - Eksekusi ganda untuk pekerjaan yang sama dicegah oleh
 *   UNIQUE (rule_id, tick_key) + pemeriksaan sebelum insert.
 */
class AutomationService
{
    public function __construct(
        private ?AutomationRuleModel $rules = null,
        private ?AutomationExecutionModel $executions = null,
        private ?RoomModel $rooms = null,
        private ?DeviceModel $devices = null,
        private ?AuditLogService $audit = null,
        private ?OccupancyService $occupancy = null,
        private ?OverrideService $overrides = null,
        private ?DeviceControlService $control = null,
    ) {
        $this->rules      ??= model(AutomationRuleModel::class);
        $this->executions ??= model(AutomationExecutionModel::class);
        $this->rooms      ??= model(RoomModel::class);
        $this->devices    ??= model(DeviceModel::class);
        $this->audit      ??= service('auditLog');
        $this->occupancy  ??= service('occupancy');
        $this->overrides  ??= service('overrideService');
        $this->control    ??= service('deviceControl');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createRule(array $data, ?int $userId = null): array
    {
        $data = $this->validateRulePayload($data);
        $data['created_by'] = $userId;
        $data['is_active']  = $data['is_active'] ?? 0;
        $data['created_at'] = orbit_utc_now();

        $id = $this->rules->insert($data);

        if ($id === false) {
            throw new ServiceException('Aturan gagal disimpan.', 500);
        }

        $rule = $this->rules->find($id);

        $this->audit->log('rule.create', [
            'user_id'     => $userId,
            'target_type' => 'rule',
            'target_id'   => (int) $id,
            'details'     => ['nama' => $rule['name'], 'aktif' => (int) $rule['is_active']],
        ]);

        return $rule;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateRule(int $ruleId, array $data, ?int $userId = null): array
    {
        $rule = $this->rules->find($ruleId);

        if ($rule === null) {
            throw new ServiceException('Aturan tidak ditemukan.', 404);
        }

        $data = $this->validateRulePayload($data, false);

        $ok = $this->rules->update($ruleId, $data);

        if ($ok === false) {
            throw new ServiceException('Aturan gagal diperbarui.', 500);
        }

        $fresh = $this->rules->find($ruleId);

        $this->audit->log('rule.update', [
            'user_id'     => $userId,
            'target_type' => 'rule',
            'target_id'   => $ruleId,
            'details'     => [
                'nama'  => $fresh['name'],
                'aktif' => (int) $fresh['is_active'],
            ],
        ]);

        return $fresh;
    }

    public function deleteRule(int $ruleId, ?int $userId = null): void
    {
        $rule = $this->rules->find($ruleId);

        if ($rule === null) {
            throw new ServiceException('Aturan tidak ditemukan.', 404);
        }

        $execCount = $this->executions->where('rule_id', $ruleId)->countAllResults();

        if ($execCount > 0) {
            throw new ServiceException(
                'Aturan memiliki riwayat eksekusi dan tidak dapat dihapus. Nonaktifkan saja.',
                409,
                ['rule' => 'Nonaktifkan aturan alih-alih menghapusnya.'],
            );
        }

        $this->rules->delete($ruleId);

        $this->audit->log('rule.delete', [
            'user_id'     => $userId,
            'target_type' => 'rule',
            'target_id'   => $ruleId,
            'details'     => ['nama' => $rule['name']],
        ]);
    }

    /**
     * Satu tick scheduler: evaluasi seluruh aturan aktif.
     *
     * @return array{due: int, executed: int, skipped: int, conflict: int, failed: int}
     */
    public function processDueRules(string $tickKey): array
    {
        $summary = ['due' => 0, 'executed' => 0, 'skipped' => 0, 'conflict' => 0, 'failed' => 0];
        $now     = orbit_utc_now();

        $rules = $this->rules
            ->where('is_active', 1)
            ->where('trigger_type', 'vacant_duration')
            ->findAll();

        /** @var array<int, array{rooms: list<int>, devices: array<int, string>, modes: array<int, string>}> $candidates */
        $candidates = [];

        foreach ($rules as $rule) {
            $dueRooms = $this->dueRooms($rule, $now);

            if ($dueRooms === []) {
                continue; // Kondisi/waktu belum terpenuhi: aturan tidak dievaluasi sama sekali.
            }

            $summary['due']++;

            $candidate = ['rooms' => [], 'devices' => [], 'modes' => []];

            foreach ($dueRooms as $room) {
                $candidate['rooms'][] = (int) $room['id'];

                foreach ($this->targetDevices($rule, (int) $room['id']) as $device) {
                    $candidate['devices'][(int) $device['id']] = 'off';
                }

                foreach ($this->decodeJson($rule['actions_json']) as $action) {
                    if (($action['type'] ?? '') === 'set_room_mode') {
                        $candidate['modes'][(int) $room['id']] = $action['mode'] ?? 'standby';
                    }
                }
            }

            $candidates[(int) $rule['id']] = $candidate;
        }

        // --- Deteksi konflik antar aturan pada tick yang sama -------------
        $deviceIntentions = [];
        $roomIntentions   = [];

        foreach ($candidates as $ruleId => $candidate) {
            foreach ($candidate['devices'] as $deviceId => $action) {
                $deviceIntentions[$deviceId][$action][] = $ruleId;
            }
            foreach ($candidate['modes'] as $roomId => $mode) {
                $roomIntentions[$roomId][$mode][] = $ruleId;
            }
        }

        $deviceConflicts = [];
        foreach ($deviceIntentions as $deviceId => $byAction) {
            if (count($byAction) > 1) {
                $deviceConflicts[$deviceId] = $byAction;
            }
        }

        $roomConflicts = [];
        foreach ($roomIntentions as $roomId => $byMode) {
            if (count($byMode) > 1) {
                $roomConflicts[$roomId] = $byMode;
            }
        }

        // --- Eksekusi per aturan ------------------------------------------
        foreach ($candidates as $ruleId => $candidate) {
            try {
                $result = $this->executeRule(
                    (int) $ruleId,
                    $candidate,
                    $deviceConflicts,
                    $roomConflicts,
                    $tickKey,
                    $now,
                );
                $summary[$result]++;
            } catch (\Throwable $e) {
                log_message('error', 'Eksekusi aturan {id} gagal: {msg}', ['id' => $ruleId, 'msg' => $e->getMessage()]);
                $this->recordExecution(
                    $ruleId,
                    $tickKey,
                    'failed',
                    'Kesalahan sistem: ' . $e->getMessage(),
                    ['exception' => $e::class],
                    $now,
                );
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * @param array{rooms: list<int>, devices: array<int, string>, modes: array<int, string>} $candidate
     * @param array<int, array<int, list<int>>>                                              $deviceConflicts
     * @param array<int, array<int, list<int>>>                                              $roomConflicts
     *
     * @return 'executed'|'skipped'|'conflict'
     */
    private function executeRule(
        int $ruleId,
        array $candidate,
        array $deviceConflicts,
        array $roomConflicts,
        string $tickKey,
        string $now,
    ): string {
        $rule = $this->rules->find($ruleId);

        if ($rule === null || (int) $rule['is_active'] !== 1) {
            return 'skipped';
        }

        // Anti-duplikat: baris eksekusi untuk (rule, tick) yang sama sudah ada.
        $existing = $this->executions
            ->where('rule_id', $ruleId)
            ->where('tick_key', $tickKey)
            ->first();

        if ($existing !== null) {
            return 'skipped';
        }

        $executed = [];
        $skipped  = [];
        $conflict = [];

        // Validasi ulang kondisi tepat sebelum mengeksekusi.
        foreach ($candidate['rooms'] as $roomId) {
            $room = $this->rooms->find($roomId);

            if ($room === null
                || $room['archived_at'] !== null
                || $room['occupancy'] !== 'vacant'
                || $room['vacant_since'] === null
            ) {
                continue;
            }

            if (isset($roomConflicts[$roomId])) {
                $conflict['rooms'][$roomId] = 'Mode tujuan bertentangan antar aturan.';
                continue;
            }

            $mode = $candidate['modes'][$roomId] ?? null;

            if ($mode !== null && $room['mode'] !== $mode) {
                $this->occupancy->setMode($roomId, $mode, null, 'automation');
                $executed['modes'][] = $roomId;
            }
        }

        foreach ($candidate['devices'] as $deviceId => $action) {
            if (isset($deviceConflicts[$deviceId])) {
                $conflict['devices'][$deviceId] = 'Tindakan bertentangan antar aturan.';
                continue;
            }

            $device = $this->devices->find($deviceId);

            if ($device === null || (int) $device['is_active'] !== 1 || $device['archived_at'] !== null) {
                $skipped['devices'][$deviceId] = 'Perangkat tidak aktif.';
                continue;
            }

            if ((int) $device['is_essential'] === 1) {
                $skipped['devices'][$deviceId] = 'Perangkat esensial dilindungi dari aturan hemat energi.';
                continue;
            }

            if ($device['control_capability'] !== 'controllable') {
                $skipped['devices'][$deviceId] = 'Perangkat hanya monitor.';
                continue;
            }

            if ($this->overrides->hasActive($deviceId)) {
                $skipped['devices'][$deviceId] = 'Dilindungi override aktif.';
                continue;
            }

            $room = $this->rooms->find((int) $device['room_id']);

            if ($room === null || $room['occupancy'] !== 'vacant') {
                $skipped['devices'][$deviceId] = 'Ruangan sudah tidak kosong.';
                continue;
            }

            try {
                $this->control->execute($deviceId, $action, 'automation', null, null, false);
                $executed['devices'][] = $deviceId;
            } catch (ServiceException $e) {
                $skipped['devices'][$deviceId] = $e->getMessage();
            }
        }

        $hasActions = $executed !== [];
        $status     = $conflict !== []
            ? 'conflict'
            : ($hasActions ? 'executed' : 'skipped');

        $reason = match (true) {
            $conflict !== []  => 'Konflik antar aturan: ' . implode('; ', array_merge(
                array_map(static fn ($r, $m) => "ruangan {$r}: {$m}", array_keys($conflict['rooms'] ?? []), $conflict['rooms'] ?? []),
                array_map(static fn ($d, $m) => "perangkat {$d}: {$m}", array_keys($conflict['devices'] ?? []), $conflict['devices'] ?? []),
            )),
            $hasActions       => 'Tindakan dijalankan: ' . count($executed['devices'] ?? []) . ' perangkat, '
                . count($executed['modes'] ?? []) . ' mode ruangan.',
            default           => 'Semua tindakan dilewati: ' . implode(' | ', $skipped['devices'] ?? []),
        };

        $this->recordExecution(
            $ruleId,
            $tickKey,
            $status,
            mb_substr($reason, 0, 500),
            [
                'dieksekusi' => $executed,
                'dilewati'   => $skipped,
                'konflik'    => $conflict,
            ],
            $now,
        );

        $this->rules->update($ruleId, ['last_executed_at' => $now]);

        if ($status === 'executed') {
            $this->audit->log('rule.executed', [
                'user_id'     => null,
                'target_type' => 'rule',
                'target_id'   => $ruleId,
                'source'      => 'automation',
                'details'     => ['nama' => $rule['name'], 'ringkasan' => $reason],
            ]);
        }

        return $status;
    }

    private function recordExecution(
        int $ruleId,
        string $tickKey,
        string $status,
        string $reason,
        array $details,
        string $now,
    ): void {
        try {
            $this->executions->insert([
                'rule_id'      => $ruleId,
                'tick_key'     => $tickKey,
                'status'       => $status,
                'reason'       => $reason,
                'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'executed_at'  => $now,
                'created_at'   => $now,
            ], false);
        } catch (\Throwable $e) {
            // UNIQUE (rule_id, tick_key) — eksekusi ganda dicegah di level database.
            log_message('debug', 'Eksekusi ganda dicegah untuk aturan {id}: {msg}', ['id' => $ruleId, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Ruangan yang memenuhi kondisi aturan: kosong cukup lama sesuai durasi aturan.
     *
     * @return list<array<string, mixed>>
     */
    private function dueRooms(array $rule, string $now): array
    {
        $duration = (int) $rule['duration_seconds'];

        if ($duration <= 0) {
            return [];
        }

        $cutoff = gmdate('Y-m-d H:i:s', strtotime($now) - $duration);

        $builder = $this->rooms
            ->where('is_active', 1)
            ->where('archived_at', null)
            ->where('occupancy', 'vacant')
            ->where('vacant_since is not null', null, false)
            ->where('vacant_since <=', $cutoff);

        $target = $this->decodeJson($rule['target_json']);
        $roomIds = $target['room_ids'] ?? [];

        if (is_array($roomIds) && $roomIds !== []) {
            $builder->whereIn('id', array_map('intval', $roomIds));
        }

        return $builder->findAll();
    }

    /**
     * Perangkat dalam ruangan yang menjadi target aturan (kandidat tindakan).
     *
     * @return list<array<string, mixed>>
     */
    private function targetDevices(array $rule, int $roomId): array
    {
        $actions = $this->decodeJson($rule['actions_json']);

        $types = null;
        foreach ($actions as $action) {
            if (($action['type'] ?? '') === 'turn_off') {
                $requested = $action['target_device_types'] ?? null;
                if (is_array($requested) && $requested !== []) {
                    $types = array_map('strval', $requested);
                }
            }
        }

        $builder = $this->devices
            ->where('room_id', $roomId)
            ->where('is_active', 1)
            ->where('archived_at', null)
            ->where('control_capability', 'controllable')
            ->where('is_essential', 0);

        if ($types !== null) {
            $builder->whereIn('device_type', $types);
        }

        return $builder->findAll();
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function validateRulePayload(array $payload, bool $isCreate = true): array
    {
        $clean = [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                throw new ServiceException('Nama aturan wajib diisi.', 422, ['name' => 'Wajib diisi.']);
            }
            $clean['name'] = $name;
        }

        if (array_key_exists('description', $payload)) {
            $clean['description'] = $payload['description'];
        }

        if (array_key_exists('trigger_type', $payload)) {
            if (! in_array($payload['trigger_type'], ['vacant_duration', 'manual'], true)) {
                throw new ServiceException('Tipe pemicu tidak valid.', 422, ['trigger_type' => 'Tipe tidak valid.']);
            }
            $clean['trigger_type'] = $payload['trigger_type'];
        }

        if (array_key_exists('duration_seconds', $payload)) {
            $duration = (int) $payload['duration_seconds'];
            if ($duration < 0) {
                throw new ServiceException('Durasi tidak boleh negatif.', 422, ['duration_seconds' => 'Tidak valid.']);
            }
            $clean['duration_seconds'] = $duration;
        }

        foreach (['condition_json', 'target_json', 'exceptions_json'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = $payload[$field];
                if ($value !== null && $value !== '' && ! $this->isValidJson($value)) {
                    throw new ServiceException('Konfigurasi JSON tidak valid.', 422, [$field => 'JSON tidak valid.']);
                }
                $clean[$field] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('actions_json', $payload)) {
            $actions = $payload['actions_json'];

            if (! $this->isValidJson($actions)) {
                throw new ServiceException('Konfigurasi tindakan harus berupa JSON yang valid.', 422, ['actions_json' => 'JSON tidak valid.']);
            }

            $decoded = json_decode(is_string($actions) ? $actions : json_encode($actions), true);

            if (! is_array($decoded) || $decoded === []) {
                throw new ServiceException('Minimal satu tindakan harus ditentukan.', 422, ['actions_json' => 'Tindakan kosong.']);
            }

            $allowed = ['set_room_mode', 'turn_off'];
            foreach ($decoded as $action) {
                $type = $action['type'] ?? '';
                if (! in_array($type, $allowed, true)) {
                    throw new ServiceException(
                        'Tindakan tidak dikenal: ' . $type,
                        422,
                        ['actions_json' => 'Tindakan harus set_room_mode atau turn_off.'],
                    );
                }
                if ($type === 'set_room_mode' && ! in_array($action['mode'] ?? '', ['active', 'standby', 'inactive'], true)) {
                    throw new ServiceException('Mode ruangan pada tindakan tidak valid.', 422, ['actions_json' => 'Mode tidak valid.']);
                }
            }

            $clean['actions_json'] = is_string($actions) ? $actions : json_encode($actions, JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('is_active', $payload)) {
            $clean['is_active'] = (int) $payload['is_active'] ? 1 : 0;
        }

        if ($isCreate && ! isset($clean['name'])) {
            throw new ServiceException('Nama aturan wajib diisi.', 422, ['name' => 'Wajib diisi.']);
        }

        if (isset($clean['name'], $clean['actions_json']) === false && $isCreate) {
            throw new ServiceException('Tindakan aturan wajib diisi.', 422, ['actions_json' => 'Wajib diisi.']);
        }

        return $clean;
    }

    private function isValidJson(mixed $value): bool
    {
        if (is_array($value)) {
            return true;
        }

        if (! is_string($value) || $value === '') {
            return false;
        }

        json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
