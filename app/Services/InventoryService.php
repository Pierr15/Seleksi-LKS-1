<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\DeviceModel;
use App\Models\RoomModel;

/**
 * Pengelolaan inventaris ruangan dan perangkat.
 *
 * Aturan MVP:
 * - Nama perangkat bersifat generik (tanpa merek/model) dan boleh sama
 *   untuk banyak unit; identitas unik tetap pada ID.
 * - Tidak ada hard-delete untuk data ber-riwayat: arsipkan saja.
 * - Seeder tidak pernah menimpa perubahan Admin (lihat seeder idempoten).
 */
class InventoryService
{
    public function __construct(
        private ?RoomModel $rooms = null,
        private ?DeviceModel $devices = null,
        private ?AuditLogService $audit = null,
        private ?DeviceControlService $control = null,
    ) {
        $this->rooms   ??= model(RoomModel::class);
        $this->devices ??= model(DeviceModel::class);
        $this->audit   ??= service('auditLog');
        $this->control ??= service('deviceControl');
    }

    // ------------------------------------------------------------------ Ruangan

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createRoom(array $data, ?int $userId = null): array
    {
        $data = $this->validateRoomPayload($data, null);

        $exists = $this->rooms
            ->where('floor_id', $data['floor_id'])
            ->where('name', $data['name'])
            ->first();

        if ($exists !== null) {
            throw new ServiceException(
                'Nama ruangan sudah dipakai di lantai tersebut.',
                422,
                ['name' => 'Nama ruangan sudah ada di lantai ini.'],
            );
        }

        $data['created_at'] = orbit_utc_now();

        $id = $this->rooms->insert($data);

        if ($id === false) {
            throw new ServiceException(
                'Ruangan gagal disimpan.',
                422,
                $this->rooms->errors() ?: ['form' => 'Ruangan gagal disimpan.'],
            );
        }

        $room = $this->rooms->find($id);

        $this->audit->log('room.create', [
            'user_id'     => $userId,
            'target_type' => 'room',
            'target_id'   => (int) $id,
            'room_id'     => (int) $id,
            'floor_id'    => (int) $data['floor_id'],
            'details'     => ['nama' => $data['name']],
        ]);

        return $room;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateRoom(int $roomId, array $data, ?int $userId = null): array
    {
        $room = $this->rooms->find($roomId);

        if ($room === null) {
            throw new ServiceException('Ruangan tidak ditemukan.', 404);
        }

        $data = $this->validateRoomPayload($data, $roomId);

        if (isset($data['name'], $data['floor_id'])) {
            $exists = $this->rooms
                ->where('floor_id', $data['floor_id'])
                ->where('name', $data['name'])
                ->where('id !=', $roomId)
                ->first();

            if ($exists !== null) {
                throw new ServiceException(
                    'Nama ruangan sudah dipakai di lantai tersebut.',
                    422,
                    ['name' => 'Nama ruangan sudah ada di lantai ini.'],
                );
            }
        }

        $before = $this->auditSnapshot($room);
        $ok     = $this->rooms->update($roomId, $data);

        if ($ok === false) {
            throw new ServiceException('Ruangan gagal diperbarui.', 500);
        }

        $fresh = $this->rooms->find($roomId);

        $this->audit->log('room.update', [
            'user_id'     => $userId,
            'target_type' => 'room',
            'target_id'   => $roomId,
            'room_id'     => $roomId,
            'floor_id'    => (int) $fresh['floor_id'],
            'details'     => ['sebelum' => $before, 'sesudah' => $this->auditSnapshot($fresh)],
        ]);

        return $fresh;
    }

    public function archiveRoom(int $roomId, ?bool $archive = true, ?int $userId = null): array
    {
        $room = $this->rooms->find($roomId);

        if ($room === null) {
            throw new ServiceException('Ruangan tidak ditemukan.', 404);
        }

        $this->rooms->update($roomId, [
            'is_active'   => $archive ? 0 : 1,
            'archived_at' => $archive ? orbit_utc_now() : null,
        ]);

        $fresh = $this->rooms->find($roomId);

        $this->audit->log($archive ? 'room.archive' : 'room.unarchive', [
            'user_id'     => $userId,
            'target_type' => 'room',
            'target_id'   => $roomId,
            'room_id'     => $roomId,
            'floor_id'    => (int) $fresh['floor_id'],
            'details'     => ['nama' => $room['name']],
        ]);

        return $fresh;
    }

    // ------------------------------------------------------------------ Perangkat

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createDevice(array $data, ?int $userId = null): array
    {
        $data = $this->validateDevicePayload($data, null);

        $room = $this->rooms->find($data['room_id']);

        if ($room === null) {
            throw new ServiceException('Ruangan tidak ditemukan.', 404, ['room_id' => 'Ruangan tidak valid.']);
        }

        if ($room['archived_at'] !== null || (int) $room['is_active'] !== 1) {
            throw new ServiceException(
                'Tidak dapat menambahkan perangkat ke ruangan yang diarsipkan atau tidak aktif.',
                409,
                ['room_id' => 'Ruangan tidak aktif.'],
            );
        }

        if (array_key_exists('position_x', $data) || array_key_exists('position_y', $data)) {
            $x = $data['position_x'] ?? null;
            $y = $data['position_y'] ?? null;
            unset($data['position_x'], $data['position_y']);

            if ($x !== null && $y !== null) {
                service('devicePosition')->assertValidCoordinate((float) $x, 'position_x');
                service('devicePosition')->assertValidCoordinate((float) $y, 'position_y');
                $data['position_x'] = round((float) $x, 2);
                $data['position_y'] = round((float) $y, 2);
            } elseif ($x !== null || $y !== null) {
                throw new ServiceException(
                    'Koordinat X dan Y harus diisi bersamaan.',
                    422,
                    ['position' => 'Koordinat tidak lengkap.'],
                );
            }
        }

        $data['created_at'] = orbit_utc_now();

        $id = $this->devices->insert($data);

        if ($id === false) {
            throw new ServiceException('Perangkat gagal disimpan ke database.', 500);
        }

        // State awal: unknown — jangan berasumsi ON/OFF.
        $this->control->stateFor((int) $id);

        $device = $this->devices->find($id);

        $this->audit->log('device.create', [
            'user_id'     => $userId,
            'target_type' => 'device',
            'target_id'   => (int) $id,
            'device_id'   => (int) $id,
            'room_id'     => (int) $data['room_id'],
            'floor_id'    => (int) $room['floor_id'],
            'details'     => [
                'nama'       => $data['name'],
                'tipe'       => $data['device_type'],
                'ruangan'    => $room['name'],
                'ditempatkan' => $device['position_x'] !== null,
            ],
        ]);

        return $device;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateDevice(int $deviceId, array $data, ?int $userId = null): array
    {
        $device = $this->devices->find($deviceId);

        if ($device === null) {
            throw new ServiceException('Perangkat tidak ditemukan.', 404);
        }

        $originalRoomId = (int) $device['room_id'];
        $data           = $this->validateDevicePayload($data, $deviceId);

        $roomChanged = isset($data['room_id']) && (int) $data['room_id'] !== $originalRoomId;
        $details     = ['sebelum' => $this->auditDeviceSnapshot($device)];

        if ($roomChanged) {
            $newRoom = $this->rooms->find($data['room_id']);

            if ($newRoom === null) {
                throw new ServiceException('Ruangan tujuan tidak ditemukan.', 404, ['room_id' => 'Ruangan tidak valid.']);
            }

            if ($newRoom['archived_at'] !== null || (int) $newRoom['is_active'] !== 1) {
                throw new ServiceException(
                    'Tidak dapat memindahkan perangkat ke ruangan yang diarsipkan atau tidak aktif.',
                    409,
                    ['room_id' => 'Ruangan tujuan tidak aktif.'],
                );
            }

            // Pindah ruangan: posisi lama tidak lagi valid di ruangan baru.
            // Admin harus menempatkan ulang di ruangan tujuan.
            if (! array_key_exists('position_x', $data)) {
                $data['position_x'] = null;
                $data['position_y'] = null;
            }

            $details['pindah_ruangan'] = [
                'dari' => $originalRoomId,
                'ke'   => (int) $data['room_id'],
            ];
        }

        if (array_key_exists('position_x', $data)) {
            $x = $data['position_x'];
            $y = $data['position_y'] ?? null;

            if ($x !== null && $y !== null) {
                service('devicePosition')->assertValidCoordinate((float) $x, 'position_x');
                service('devicePosition')->assertValidCoordinate((float) $y, 'position_y');
                $data['position_x'] = round((float) $x, 2);
                $data['position_y'] = round((float) $y, 2);
            } elseif ($x === null) {
                $data['position_x'] = null;
                $data['position_y'] = null;
            } else {
                throw new ServiceException('Koordinat X dan Y harus diisi bersamaan.', 422);
            }
        }

        $ok = $this->devices->update($deviceId, $data);

        if ($ok === false) {
            throw new ServiceException('Perangkat gagal diperbarui.', 500);
        }

        $fresh = $this->devices->find($deviceId);

        // Riwayat perintah & aktivitas tetap utuh: semua menunjuk device_id yang sama.
        $details['sesudah'] = $this->auditDeviceSnapshot($fresh);

        $this->audit->log('device.update', [
            'user_id'     => $userId,
            'target_type' => 'device',
            'target_id'   => $deviceId,
            'device_id'   => $deviceId,
            'room_id'     => (int) $fresh['room_id'],
            'details'     => $details,
        ]);

        return $fresh;
    }

    public function archiveDevice(int $deviceId, ?bool $archive = true, ?int $userId = null): array
    {
        $device = $this->devices->find($deviceId);

        if ($device === null) {
            throw new ServiceException('Perangkat tidak ditemukan.', 404);
        }

        $this->devices->update($deviceId, [
            'is_active'   => $archive ? 0 : 1,
            'archived_at' => $archive ? orbit_utc_now() : null,
        ]);

        $fresh = $this->devices->find($deviceId);

        $this->audit->log($archive ? 'device.archive' : 'device.unarchive', [
            'user_id'     => $userId,
            'target_type' => 'device',
            'target_id'   => $deviceId,
            'device_id'   => $deviceId,
            'room_id'     => (int) $fresh['room_id'],
            'details'     => ['nama' => $device['name']],
        ]);

        return $fresh;
    }

    // ------------------------------------------------------------------ Validasi

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function validateRoomPayload(array $data, ?int $roomId): array
    {
        $clean = [];

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new ServiceException('Nama ruangan wajib diisi.', 422, ['name' => 'Nama ruangan wajib diisi.']);
            }
            $clean['name'] = $name;
        }

        if (array_key_exists('floor_id', $data)) {
            $floor = model(\App\Models\FloorModel::class)->find((int) $data['floor_id']);
            if ($floor === null) {
                throw new ServiceException('Lantai tidak valid.', 422, ['floor_id' => 'Lantai tidak valid.']);
            }
            $clean['floor_id'] = (int) $data['floor_id'];
        }

        foreach (['description', 'room_type', 'mode', 'occupancy', 'inventory_status', 'floor_verification_status'] as $field) {
            if (array_key_exists($field, $data)) {
                $clean[$field] = $data[$field];
            }
        }

        if (isset($clean['room_type']) && ! in_array($clean['room_type'], ['closed', 'open_area'], true)) {
            throw new ServiceException('Tipe ruangan tidak valid.', 422, ['room_type' => 'Tipe tidak valid.']);
        }

        if (isset($clean['mode']) && ! in_array($clean['mode'], ['active', 'standby', 'inactive'], true)) {
            throw new ServiceException('Mode ruangan tidak valid.', 422, ['mode' => 'Mode tidak valid.']);
        }

        $map = $this->validateMapRect($data);
        $clean = $map === null ? $clean : array_merge($clean, $map);

        return $clean;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function validateDevicePayload(array $data, ?int $deviceId): array
    {
        $clean = [];

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new ServiceException('Nama perangkat wajib diisi.', 422, ['name' => 'Nama perangkat wajib diisi.']);
            }
            if (mb_strlen($name) > 100) {
                throw new ServiceException('Nama perangkat maksimal 100 karakter.', 422, ['name' => 'Terlalu panjang.']);
            }
            $clean['name'] = $name;
        }

        if (array_key_exists('room_id', $data)) {
            $roomId = (int) $data['room_id'];
            if ($roomId <= 0 || $this->rooms->find($roomId) === null) {
                throw new ServiceException('Ruangan tidak valid.', 422, ['room_id' => 'Ruangan tidak valid.']);
            }
            $clean['room_id'] = $roomId;
        }

        if (array_key_exists('device_type', $data)) {
            $types = ['ac', 'ac_central', 'light', 'computer', 'projector', 'videotron', 'audio', 'exhaust_fan', 'studio_lighting', 'other'];
            if (! in_array($data['device_type'], $types, true)) {
                throw new ServiceException('Tipe perangkat tidak valid.', 422, ['device_type' => 'Tipe tidak valid.']);
            }
            $clean['device_type'] = $data['device_type'];
        }

        foreach (['control_capability', 'is_essential', 'inventory_status', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $clean[$field] = $data[$field];
            }
        }

        if (isset($clean['control_capability']) && ! in_array($clean['control_capability'], ['controllable', 'monitor_only'], true)) {
            throw new ServiceException('Kemampuan kontrol tidak valid.', 422, ['control_capability' => 'Nilai tidak valid.']);
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    private function validateMapRect(array $data): ?array
    {
        $keys = ['map_x', 'map_y', 'map_w', 'map_h'];

        if (! array_intersect_key($data, array_flip($keys))) {
            return null;
        }

        $values = [];
        foreach ($keys as $key) {
            $values[$key] = array_key_exists($key, $data) && $data[$key] !== '' && $data[$key] !== null
                ? (float) $data[$key]
                : null;
        }

        $allNull = count(array_filter($values, static fn ($v) => $v !== null)) === 0;

        if ($allNull) {
            return array_fill_keys($keys, null);
        }

        if (in_array(null, $values, true)) {
            throw new ServiceException(
                'Rect denah harus diisi lengkap (x, y, lebar, tinggi) atau dikosongkan seluruhnya.',
                422,
                ['map' => 'Rect denah tidak lengkap.'],
            );
        }

        foreach (['map_x', 'map_y'] as $key) {
            if ($values[$key] < 0 || $values[$key] > 100) {
                throw new ServiceException('Posisi rect denah harus antara 0 dan 100.', 422, [$key => 'Rentang 0–100.']);
            }
        }

        if ($values['map_w'] <= 0 || $values['map_h'] <= 0) {
            throw new ServiceException('Lebar dan tinggi rect denah harus lebih besar dari 0.', 422, ['map_w' => 'Harus > 0.']);
        }

        if ($values['map_x'] + $values['map_w'] > 100 || $values['map_y'] + $values['map_h'] > 100) {
            throw new ServiceException('Rect denah melewati batas kanvas 100×100.', 422, ['map' => 'Melewati batas kanvas.']);
        }

        return array_map(static fn ($v) => $v === null ? null : round($v, 2), $values);
    }

    /**
     * @param array<string, mixed> $room
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $room): array
    {
        return [
            'nama'         => $room['name'],
            'lantai'       => (int) $room['floor_id'],
            'tipe'         => $room['room_type'],
            'mode'         => $room['mode'],
            'okupansi'     => $room['occupancy'],
            'inventaris'   => $room['inventory_status'],
            'verifikasi_lantai' => $room['floor_verification_status'],
            'aktif'        => (int) $room['is_active'],
        ];
    }

    /**
     * @param array<string, mixed> $device
     *
     * @return array<string, mixed>
     */
    private function auditDeviceSnapshot(array $device): array
    {
        return [
            'nama'          => $device['name'],
            'ruangan'       => (int) $device['room_id'],
            'tipe'          => $device['device_type'],
            'kontrol'       => $device['control_capability'],
            'esensial'      => (int) $device['is_essential'],
            'inventaris'    => $device['inventory_status'],
            'posisi_x'      => $device['position_x'],
            'posisi_y'      => $device['position_y'],
            'aktif'         => (int) $device['is_active'],
        ];
    }
}
