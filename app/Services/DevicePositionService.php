<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\DeviceModel;

/**
 * Pengelolaan posisi perangkat pada denah.
 *
 * Format koordinat: persen 0–100 relatif terhadap AREA RUANGAN
 * (bukan kanvas lantai), sehingga marker tetap tepat saat ukuran layar
 * berubah. Posisi boleh NULL (belum ditempatkan) dan tidak pernah
 * ditebak/diisi otomatis.
 */
class DevicePositionService
{
    public function __construct(
        private ?DeviceModel $devices = null,
        private ?AuditLogService $audit = null,
    ) {
        $this->devices ??= model(DeviceModel::class);
        $this->audit   ??= service('auditLog');
    }

    /**
     * Menyimpan posisi perangkat (atau mengosongkannya).
     *
     * @param int|null      $roomId  Ruangan tujuan; harus sama dengan ruangan induk perangkat
     * @param float|null    $x       0–100 atau null
     * @param float|null    $y       0–100 atau null
     *
     * @return array<string, mixed> baris perangkat terbaru
     */
    public function place(int $deviceId, ?int $roomId, ?float $x, ?float $y, ?int $userId = null): array
    {
        $device = $this->devices->find($deviceId);

        if ($device === null) {
            throw new ServiceException('Perangkat tidak ditemukan.', 404, ['device' => 'Perangkat tidak ditemukan.']);
        }

        $room = model(\App\Models\RoomModel::class)->find((int) $device['room_id']);

        if ($room === null || $room['archived_at'] !== null || (int) $room['is_active'] !== 1) {
            throw new ServiceException(
                'Ruangan induk perangkat tidak valid atau tidak aktif.',
                409,
                ['room' => 'Ruangan tidak valid.'],
            );
        }

        if ($roomId !== null && $roomId !== (int) $device['room_id']) {
            throw new ServiceException(
                'Ruangan pada posisi tidak sama dengan ruangan induk perangkat. '
                . 'Gunakan edit perangkat untuk memindahkan ruangan.',
                409,
                ['room_id' => 'Ruangan tidak sesuai dengan perangkat.'],
            );
        }

        $before = [
            'position_x' => $device['position_x'],
            'position_y' => $device['position_y'],
            'room_id'    => (int) $device['room_id'],
        ];

        if ($x === null && $y === null) {
            $newX = null;
            $newY = null;
        } elseif ($x === null || $y === null) {
            throw new ServiceException(
                'Koordinat X dan Y harus diisi bersamaan atau dikosongkan bersamaan.',
                422,
                ['position' => 'Koordinat tidak lengkap.'],
            );
        } else {
            $this->assertValidCoordinate($x, 'position_x');
            $this->assertValidCoordinate($y, 'position_y');
            $newX = round($x, 2);
            $newY = round($y, 2);
        }

        $ok = $this->devices->update($device['id'], [
            'position_x' => $newX,
            'position_y' => $newY,
        ]);

        if ($ok === false) {
            throw new ServiceException('Posisi gagal disimpan ke database.', 500);
        }

        $fresh = $this->devices->find($device['id']);

        if ($fresh === null) {
            throw new ServiceException('Perangkat tidak ditemukan setelah penyimpanan.', 500);
        }

        $consistent = true;
        if ($newX !== null) {
            $consistent = (float) $fresh['position_x'] === (float) $newX
                && (float) $fresh['position_y'] === (float) $newY;
        } else {
            $consistent = $fresh['position_x'] === null && $fresh['position_y'] === null;
        }

        if (! $consistent) {
            throw new ServiceException('Posisi tidak konsisten setelah penyimpanan.', 500);
        }

        $this->audit->log('device.position_update', [
            'user_id'     => $userId,
            'target_type' => 'device',
            'target_id'   => (int) $device['id'],
            'device_id'   => (int) $device['id'],
            'room_id'     => (int) $device['room_id'],
            'floor_id'    => (int) ($room['floor_id'] ?? 0),
            'result'      => 'success',
            'details'     => [
                'sebelum' => $before,
                'sesudah' => [
                    'position_x' => $newX !== null ? number_format($newX, 2, '.', '') : null,
                    'position_y' => $newY !== null ? number_format($newY, 2, '.', '') : null,
                    'room_id'    => (int) $device['room_id'],
                ],
            ],
        ]);

        return $fresh;
    }

    /**
     * Validasi batas koordinat 0–100 (ditegakkan juga oleh CHECK constraint DB).
     *
     * @throws ServiceException
     */
    public function assertValidCoordinate(float $value, string $field): void
    {
        if ($value < 0 || $value > 100) {
            throw new ServiceException(
                'Koordinat harus berada dalam rentang 0 sampai 100.',
                422,
                [$field => 'Koordinat harus antara 0 dan 100.'],
            );
        }

        if (round($value, 2) !== $value) {
            // Batasi presisi agar marker tidak bergeser karena pembulatan berlebih.
            throw new ServiceException(
                'Koordinat maksimal 2 digit desimal.',
                422,
                [$field => 'Gunakan maksimal 2 digit desimal.'],
            );
        }
    }
}

