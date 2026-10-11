<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\RoomModel;

/**
 * Okupansi dan mode ruangan — dua hal yang sengaja dipisah:
 * ruangan kosong tidak otomatis berarti mode Inactive.
 */
class OccupancyService
{
    public function __construct(
        private ?RoomModel $rooms = null,
        private ?AuditLogService $audit = null,
    ) {
        $this->rooms ??= model(RoomModel::class);
        $this->audit ??= service('auditLog');
    }

    /**
     * Mengubah status okupansi.
     *
     * - occupied → vacant : simpan vacant_since (waktu mulai kosong)
     * - vacant → occupied : reset vacant_since (tindakan otomatis yang
     *   menunggu kondisi vacant sebelumnya otomatis batal karena evaluasi
     *   aturan selalu membaca vacant_since terbaru)
     *
     * @param 'manual'|'simulation'|'automation'|'scheduler' $source
     *
     * @return array<string, mixed>
     */
    public function setOccupancy(int $roomId, string $occupancy, ?int $userId = null, string $source = 'manual'): array
    {
        if (! in_array($occupancy, ['occupied', 'vacant'], true)) {
            throw new ServiceException('Status okupansi tidak valid.', 422, ['occupancy' => 'Nilai harus occupied atau vacant.']);
        }

        $room = $this->rooms->find($roomId);

        if ($room === null) {
            throw new ServiceException('Ruangan tidak ditemukan.', 404);
        }

        if ($room['archived_at'] !== null) {
            throw new ServiceException('Ruangan sudah diarsipkan.', 409);
        }

        $current = $room['occupancy'];

        if ($current === $occupancy) {
            // Tidak ada perubahan: pertahankan vacant_since yang sudah ada.
            return $room;
        }

        $updates = ['occupancy' => $occupancy];

        if ($occupancy === 'vacant') {
            $updates['vacant_since'] = orbit_utc_now();
        } else {
            $updates['vacant_since'] = null;
        }

        $ok = $this->rooms->update($roomId, $updates);

        if ($ok === false) {
            throw new ServiceException('Status okupansi gagal disimpan.', 500);
        }

        $fresh = $this->rooms->find($roomId);

        $this->audit->log('room.occupancy', [
            'user_id'     => $userId,
            'target_type' => 'room',
            'target_id'   => $roomId,
            'room_id'     => $roomId,
            'floor_id'    => (int) $fresh['floor_id'],
            'source'      => $source === 'simulation' ? 'manual' : ($source === 'scheduler' ? 'scheduler' : 'manual'),
            'details'     => [
                'sebelum'      => $current,
                'sesudah'      => $occupancy,
                'vacant_since' => $fresh['vacant_since'],
            ],
        ]);

        return $fresh;
    }

    /**
     * @param 'manual'|'automation' $source
     *
     * @return array<string, mixed>
     */
    public function setMode(int $roomId, string $mode, ?int $userId = null, string $source = 'manual'): array
    {
        if (! in_array($mode, ['active', 'standby', 'inactive'], true)) {
            throw new ServiceException('Mode ruangan tidak valid.', 422, ['mode' => 'Mode tidak valid.']);
        }

        $room = $this->rooms->find($roomId);

        if ($room === null) {
            throw new ServiceException('Ruangan tidak ditemukan.', 404);
        }

        if ($room['archived_at'] !== null) {
            throw new ServiceException('Ruangan sudah diarsipkan.', 409);
        }

        if ($room['mode'] === $mode) {
            return $room;
        }

        $ok = $this->rooms->update($roomId, ['mode' => $mode]);

        if ($ok === false) {
            throw new ServiceException('Mode ruangan gagal disimpan.', 500);
        }

        $fresh = $this->rooms->find($roomId);

        $this->audit->log('room.mode', [
            'user_id'     => $userId,
            'target_type' => 'room',
            'target_id'   => $roomId,
            'room_id'     => $roomId,
            'floor_id'    => (int) $fresh['floor_id'],
            'source'      => $source === 'automation' ? 'automation' : 'manual',
            'details'     => ['sebelum' => $room['mode'], 'sesudah' => $mode],
        ]);

        return $fresh;
    }
}
