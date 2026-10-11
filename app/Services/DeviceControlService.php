<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\DeviceCommandModel;
use App\Models\DeviceModel;
use App\Models\DeviceStateModel;
use App\Services\Drivers\DeviceDriverInterface;
use App\Services\Drivers\SimulatedDriver;

/**
 * Kontrol perangkat (simulasi pada MVP).
 *
 * Semua validasi dilakukan di sini, bukan di controller:
 * keberadaan perangkat, kelayakan ruangan, kemampuan kontrol,
 * otorisasi sumber perintah, lalu perekaman perintah + aktivitas.
 */
class DeviceControlService
{
    public function __construct(
        private ?DeviceModel $devices = null,
        private ?DeviceStateModel $states = null,
        private ?DeviceCommandModel $commands = null,
        private ?DeviceDriverInterface $driver = null,
        private ?AuditLogService $audit = null,
        private ?OverrideService $overrides = null,
    ) {
        $this->devices  ??= model(DeviceModel::class);
        $this->states   ??= model(DeviceStateModel::class);
        $this->commands ??= model(DeviceCommandModel::class);
        $this->driver   ??= new SimulatedDriver();
        $this->audit    ??= service('auditLog');
        $this->overrides ??= service('overrideService');
    }

    /**
     * Daftar perintah yang didukung, dengan tipe perangkat yang boleh menerimanya.
     * null = semua tipe.
     */
    private const ALLOWED_COMMANDS = [
        'on'              => null,
        'off'             => null,
        'sleep'           => ['computer'],
        'shutdown'        => ['computer'],
        'set_temperature' => ['ac'],
        'set_mode'        => ['ac'],
        'set_boost'       => ['ac'],
    ];

    private const AC_TEMP_MIN = 16.0;
    private const AC_TEMP_MAX = 30.0;

    /**
     * Menjalankan perintah pada perangkat.
     *
     * @param 'manual'|'automation' $source
     * @param array<string, mixed>  $params Parameter tambahan (temperature, mode, boost)
     *
     * @return array<string, mixed> state terbaru perangkat
     */
    public function execute(
        int $deviceId,
        string $command,
        string $source = 'manual',
        ?int $userId = null,
        ?int $automationExecutionId = null,
        bool $respectOverride = false,
        array $params = [],
    ): array {
        if (! array_key_exists($command, self::ALLOWED_COMMANDS)) {
            throw new ServiceException('Perintah tidak dikenal.', 422, ['command' => 'Perintah tidak dikenal.']);
        }

        $device = $this->devices->find($deviceId);

        if ($device === null) {
            throw new ServiceException('Perangkat tidak ditemukan.', 404, ['device' => 'Perangkat tidak ditemukan.']);
        }

        $allowedTypes = self::ALLOWED_COMMANDS[$command];

        if ($allowedTypes !== null && ! in_array($device['device_type'], $allowedTypes, true)) {
            throw new ServiceException(
                'Perintah "' . $command . '" tidak berlaku untuk tipe perangkat ini.',
                422,
                ['command' => 'Perintah tidak berlaku untuk tipe perangkat ini.'],
            );
        }

        if ((int) $device['is_active'] !== 1 || $device['archived_at'] !== null) {
            throw new ServiceException(
                'Perangkat tidak aktif atau sudah diarsipkan.',
                409,
                ['device' => 'Perangkat tidak aktif atau sudah diarsipkan.'],
            );
        }

        if ($device['control_capability'] !== 'controllable') {
            throw new ServiceException(
                'Perangkat ini hanya dapat dipantau, tidak dapat dikontrol.',
                403,
                ['control_capability' => 'Perangkat hanya monitor.'],
            );
        }

        $room = model(\App\Models\RoomModel::class)->find((int) $device['room_id']);

        if ($room === null || $room['archived_at'] !== null) {
            throw new ServiceException(
                'Ruangan perangkat tidak valid atau sudah diarsipkan.',
                409,
                ['room' => 'Ruangan tidak valid.'],
            );
        }

        if ($source === 'manual' && $userId === null) {
            throw new ServiceException('Perintah manual memerlukan pengguna yang login.', 401);
        }

        if ($respectOverride && $this->overrides->hasActive((int) $device['id'])) {
            throw new ServiceException(
                'Perangkat dilindungi override aktif dan tidak boleh diubah otomatis.',
                409,
                ['override' => 'Override aktif melindungi perangkat ini.'],
            );
        }

        $this->validateParams($command, $params);

        $commandId = $this->commands->insert([
            'device_id'               => (int) $device['id'],
            'room_id'                 => (int) $device['room_id'],
            'requested_by'            => $source === 'manual' ? $userId : null,
            'automation_execution_id' => $automationExecutionId,
            'command'                 => $command,
            'source'                  => $source,
            'status'                  => 'pending',
            'created_at'              => orbit_utc_now(),
        ]);

        $result = $this->runDriver($device, $command, $params);

        if (! $result['success']) {
            $this->commands->update($commandId, [
                'status'        => 'failed',
                'result_message' => $result['message'],
                'executed_at'   => orbit_utc_now(),
            ]);

            $this->audit->log('device.command', [
                'user_id'     => $userId,
                'target_type' => 'device',
                'target_id'   => (int) $device['id'],
                'device_id'   => (int) $device['id'],
                'room_id'     => (int) $device['room_id'],
                'result'      => 'failure',
                'source'      => $source,
                'details'     => array_merge(['command' => $command, 'alasan' => $result['message']], $params),
            ]);

            throw new ServiceException('Perintah gagal dieksekusi: ' . $result['message'], 502);
        }

        $now = orbit_utc_now();

        $this->commands->update($commandId, [
            'status'         => 'success',
            'result_message' => $result['message'],
            'executed_at'    => $now,
        ]);

        $state = $this->stateFor((int) $device['id']);

        $stateUpdate = [
            'power_state'      => $result['power_state'],
            'connection_state' => $result['connection_state'],
            'last_seen_at'     => $now,
            'source'           => 'simulation',
        ];

        if (isset($result['target_temperature'])) {
            $stateUpdate['target_temperature'] = $result['target_temperature'];
        }
        if (isset($result['ac_mode'])) {
            $stateUpdate['ac_mode'] = $result['ac_mode'];
        }
        if (array_key_exists('ac_boost', $result)) {
            $stateUpdate['ac_boost'] = $result['ac_boost'] ? 1 : 0;
        }

        $this->states->update($state['id'], $stateUpdate);

        $this->audit->log('device.command', [
            'user_id'     => $userId,
            'target_type' => 'device',
            'target_id'   => (int) $device['id'],
            'device_id'   => (int) $device['id'],
            'room_id'     => (int) $device['room_id'],
            'result'      => 'success',
            'source'      => $source,
            'details'     => array_merge(['command' => $command, 'command_id' => (int) $commandId], $params),
        ]);

        return $this->freshState((int) $device['id']);
    }

    /**
     * Validasi parameter tambahan per perintah.
     *
     * @param array<string, mixed> $params
     *
     * @throws ServiceException Bila parameter tidak valid
     */
    private function validateParams(string $command, array $params): void
    {
        switch ($command) {
            case 'set_temperature':
                $temp = $params['temperature'] ?? null;
                if (! is_numeric($temp)) {
                    throw new ServiceException('Suhu wajib diisi.', 422, ['temperature' => 'Suhu wajib diisi.']);
                }
                $temp = (float) $temp;
                if ($temp < self::AC_TEMP_MIN || $temp > self::AC_TEMP_MAX) {
                    throw new ServiceException(
                        'Suhu harus antara ' . self::AC_TEMP_MIN . '–' . self::AC_TEMP_MAX . '°C.',
                        422,
                        ['temperature' => 'Suhu di luar rentang 16–30°C.'],
                    );
                }
                break;

            case 'set_mode':
                $mode = $params['mode'] ?? null;
                if (! in_array($mode, ['cool', 'dry'], true)) {
                    throw new ServiceException('Mode harus cool atau dry.', 422, ['mode' => 'Mode harus cool atau dry.']);
                }
                break;

            case 'set_boost':
                $boost = $params['boost'] ?? null;
                if (! is_bool($boost) && ! in_array($boost, [0, 1, '0', '1', true, false], true)) {
                    throw new ServiceException('Nilai boost tidak valid.', 422, ['boost' => 'Nilai boost tidak valid.']);
                }
                break;
        }
    }

    /**
     * Panggil driver sesuai perintah.
     *
     * @param array<string, mixed> $device
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function runDriver(array $device, string $command, array $params): array
    {
        return match ($command) {
            'on'              => $this->driver->turnOn($device),
            'off'             => $this->driver->turnOff($device),
            'sleep'           => $this->driver->sleep($device),
            'shutdown'        => $this->driver->shutdown($device),
            'set_temperature' => $this->driver->setTemperature($device, (float) $params['temperature']),
            'set_mode'        => $this->driver->setMode($device, (string) $params['mode']),
            'set_boost'       => $this->driver->setBoost($device, (bool) ($params['boost'] ?? false)),
            default           => ['success' => false, 'message' => 'Perintah tidak dikenal.', 'power_state' => 'unknown', 'connection_state' => 'unknown'],
        };
    }

    /**
     * Pastikan baris state perangkat ada; default unknown, bukan off.
     *
     * @return array<string, mixed>
     */
    public function stateFor(int $deviceId): array
    {
        $state = $this->states->where('device_id', $deviceId)->first();

        if ($state !== null) {
            return $state;
        }

        $this->states->insert([
            'device_id'        => $deviceId,
            'power_state'      => 'unknown',
            'connection_state' => 'unknown',
            'source'           => 'simulation',
        ]);

        return $this->states->where('device_id', $deviceId)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function freshState(int $deviceId): array
    {
        $state = $this->stateFor($deviceId);
        $state['override_active'] = $this->overrides->hasActive($deviceId);

        return $state;
    }
}

