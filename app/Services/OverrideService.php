<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\DeviceModel;
use App\Models\DeviceOverrideModel;

/**
 * Override perangkat: melindungi perangkat dari tindakan otomatis.
 * Override TIDAK memaksa perangkat menyala dan TIDAK memblokir kontrol manual.
 */
class OverrideService
{
    public function __construct(
        private ?DeviceOverrideModel $overrides = null,
        private ?DeviceModel $devices = null,
        private ?AuditLogService $audit = null,
    ) {
        $this->overrides ??= model(DeviceOverrideModel::class);
        $this->devices   ??= model(DeviceModel::class);
        $this->audit     ??= service('auditLog');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeFor(int $deviceId, ?string $now = null): ?array
    {
        $now ??= orbit_utc_now();

        $rows = $this->overrides
            ->where('device_id', $deviceId)
            ->where('ended_at', null)
            ->orderBy('id', 'DESC')
            ->findAll();

        foreach ($rows as $row) {
            if ($row['override_type'] === 'persistent') {
                return $row;
            }

            if ($row['expires_at'] !== null && $row['expires_at'] > $now) {
                return $row;
            }
        }

        return null;
    }

    public function hasActive(int $deviceId): bool
    {
        return $this->activeFor($deviceId) !== null;
    }

    /**
     * @param 'persistent'|'time_limited' $type
     *
     * @return array<string, mixed>
     */
    public function create(
        int $deviceId,
        string $type,
        string $reason,
        ?int $userId = null,
        ?string $expiresAt = null,
    ): array {
        if (! in_array($type, ['persistent', 'time_limited'], true)) {
            throw new ServiceException('Tipe override tidak valid.', 422, ['override_type' => 'Tipe tidak valid.']);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new ServiceException('Alasan override wajib diisi.', 422, ['reason' => 'Alasan wajib diisi.']);
        }

        $device = $this->devices->find($deviceId);

        if ($device === null) {
            throw new ServiceException('Perangkat tidak ditemukan.', 404);
        }

        if ($type === 'time_limited') {
            if ($expiresAt === null || $expiresAt === '') {
                throw new ServiceException(
                    'Override terbatas waktu memerlukan waktu berakhir.',
                    422,
                    ['expires_at' => 'Waktu berakhir wajib diisi.'],
                );
            }

            if ($expiresAt <= orbit_utc_now()) {
                throw new ServiceException(
                    'Waktu berakhir override harus di masa depan.',
                    422,
                    ['expires_at' => 'Waktu berakhir harus setelah sekarang.'],
                );
            }
        } else {
            $expiresAt = null;
        }

        if ($this->hasActive($deviceId)) {
            throw new ServiceException(
                'Perangkat ini sudah memiliki override aktif.',
                409,
                ['override' => 'Akhiri override lama terlebih dahulu.'],
            );
        }

        $id = $this->overrides->insert([
            'device_id'     => $deviceId,
            'override_type' => $type,
            'reason'        => $reason,
            'created_by'    => $userId,
            'starts_at'     => orbit_utc_now(),
            'expires_at'    => $expiresAt,
        ]);

        if ($id === false) {
            throw new ServiceException('Override gagal disimpan.', 500);
        }

        $row = $this->overrides->find($id);

        $this->audit->log('override.create', [
            'user_id'     => $userId,
            'target_type' => 'override',
            'target_id'   => (int) $id,
            'device_id'   => $deviceId,
            'room_id'     => (int) $device['room_id'],
            'details'     => [
                'tipe'      => $type,
                'alasan'    => $reason,
                'berakhir'  => $expiresAt,
                'perangkat' => $device['name'],
            ],
        ]);

        return $row;
    }

    public function end(int $overrideId, ?int $userId = null): array
    {
        $row = $this->overrides->find($overrideId);

        if ($row === null) {
            throw new ServiceException('Override tidak ditemukan.', 404);
        }

        if ($row['ended_at'] !== null) {
            throw new ServiceException('Override sudah berakhir.', 409);
        }

        $this->overrides->update($overrideId, [
            'ended_at' => orbit_utc_now(),
            'ended_by' => $userId,
        ]);

        $fresh = $this->overrides->find($overrideId);

        $this->audit->log('override.end', [
            'user_id'     => $userId,
            'target_type' => 'override',
            'target_id'   => $overrideId,
            'device_id'   => (int) $row['device_id'],
            'details'     => ['alasan' => $row['reason']],
        ]);

        return $fresh;
    }

    /**
     * Menutup override time_limited yang sudah lewat masa berlakunya.
     * Dipanggil oleh scheduler.
     *
     * @return int jumlah override yang ditutup
     */
    public function expireDue(?string $now = null): int
    {
        $now ??= orbit_utc_now();

        $due = $this->overrides
            ->where('ended_at', null)
            ->where('override_type', 'time_limited')
            ->where('expires_at <=', $now)
            ->findAll();

        foreach ($due as $row) {
            $this->overrides->update($row['id'], [
                'ended_at' => $row['expires_at'],
                'ended_by' => null,
            ]);

            $this->audit->log('override.expired', [
                'user_id'     => null,
                'target_type' => 'override',
                'target_id'   => (int) $row['id'],
                'device_id'   => (int) $row['device_id'],
                'source'      => 'scheduler',
                'details'     => ['alasan' => $row['reason'], 'berakhir_pada' => $row['expires_at']],
            ]);
        }

        return count($due);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeForRoom(int $roomId): array
    {
        $now = orbit_utc_now();

        $devices = model(DeviceModel::class)
            ->where('room_id', $roomId)
            ->findAll();

        $active = [];
        foreach ($devices as $device) {
            $override = $this->activeFor((int) $device['id'], $now);
            if ($override !== null) {
                $active[] = [
                    'device'   => $device,
                    'override' => $override,
                ];
            }
        }

        return $active;
    }
}
