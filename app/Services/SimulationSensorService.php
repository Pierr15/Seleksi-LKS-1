<?php

namespace App\Services;

use App\Models\DeviceModel;
use App\Models\RoomModel;
use App\Models\SensorReadingModel;

/**
 * Simulator sensor.
 *
 * Semua nilai adalah DATA SIMULASI dan selalu ditandai source='simulation'.
 * Nilai dibentuk secara deterministik (fungsi waktu + ID ruangan), bukan
 * angka acak yang berubah setiap kali halaman dimuat. Pembacaan disimpan
 * ke database dengan interval minimal 5 menit per metrik per ruangan.
 */
class SimulationSensorService
{
    private const WRITE_INTERVAL_SECONDS = 300;

    /**
     * Daya (watt) simulasi per tipe perangkat — hanya untuk estimasi.
     */
    private const WATTS = [
        'ac'              => 700.0,
        'ac_central'      => 1500.0,
        'light'           => 18.0,
        'computer'        => 65.0,
        'projector'       => 250.0,
        'videotron'       => 400.0,
        'audio'           => 120.0,
        'exhaust_fan'     => 45.0,
        'studio_lighting' => 300.0,
        'other'           => 50.0,
    ];

    public function __construct(
        private ?RoomModel $rooms = null,
        private ?DeviceModel $devices = null,
        private ?SensorReadingModel $readings = null,
    ) {
        $this->rooms    ??= model(RoomModel::class);
        $this->devices  ??= model(DeviceModel::class);
        $this->readings ??= model(SensorReadingModel::class);
    }

    /**
     * Memperbarui pembacaan simulasi untuk semua ruangan aktif.
     *
     * @return array{written: int, skipped: int}
     */
    public function updateAll(?string $now = null): array
    {
        $now    ??= orbit_utc_now();
        $written = 0;
        $skipped = 0;

        $rooms = $this->rooms
            ->where('is_active', 1)
            ->where('archived_at', null)
            ->findAll();

        foreach ($rooms as $room) {
            foreach (['temperature', 'humidity', 'occupancy', 'energy_estimate', 'light'] as $metric) {
                if ($this->shouldSkip($room, $metric, $now)) {
                    $skipped++;
                    continue;
                }

                [$value, $unit] = $this->compute($room, $metric, $now);

                $ok = $this->readings->insert([
                    'room_id'    => (int) $room['id'],
                    'metric'     => $metric,
                    'value'      => $value,
                    'unit'       => $unit,
                    'recorded_at' => $now,
                    'source'     => 'simulation',
                    'created_at' => $now,
                ], false);

                if ($ok) {
                    $written++;
                }
            }
        }

        return ['written' => $written, 'skipped' => $skipped];
    }

    private function shouldSkip(array $room, string $metric, string $now): bool
    {
        $last = $this->readings
            ->where('room_id', (int) $room['id'])
            ->where('metric', $metric)
            ->orderBy('recorded_at', 'DESC')
            ->first();

        if ($last === null) {
            return false;
        }

        return (strtotime($now) - strtotime($last['recorded_at'])) < self::WRITE_INTERVAL_SECONDS;
    }

    /**
     * Nilai deterministik berdasarkan waktu (Asia/Jakarta) dan ID ruangan.
     *
     * @return array{0: float, 1: string}
     */
    private function compute(array $room, string $metric, string $now): array
    {
        $jakarta = new \DateTime($now, new \DateTimeZone('UTC'));
        $jakarta->setTimezone(new \DateTimeZone('Asia/Jakarta'));

        $hour = (int) $jakarta->format('G') + ((int) $jakarta->format('i')) / 60;
        $id   = (int) $room['id'];
        $off  = fmod($id * 0.83, 2 * M_PI);

        // Siklus harian halus, bukan angka acak.
        $cycle = sin((($hour - 9) / 24) * 2 * M_PI + $off);

        return match ($metric) {
            'temperature' => [round(26.0 + 1.8 * $cycle, 1), '°C'],
            'humidity'    => [round(66.0 + 6.0 * cos((($hour - 9) / 24) * 2 * M_PI + $off), 1), '%'],
            'occupancy'   => [$room['occupancy'] === 'occupied' ? 1.0 : 0.0, 'status'],
            'energy_estimate' => [round($this->roomWatts((int) $room['id']), 1), 'W'],
            'light'       => [round(max(100.0, 450.0 + 300.0 * $cycle + 80.0 * sin($hour / 6 * M_PI + $off)), 0), 'lux'],
            default       => [0.0, ''],
        };
    }

    /**
     * Total daya perangkat yang menyala di ruangan (estimasi simulasi).
     */
    private function roomWatts(int $roomId): float
    {
        $devices = $this->devices
            ->select('id, device_type')
            ->where('room_id', $roomId)
            ->where('is_active', 1)
            ->where('archived_at', null)
            ->findAll();

        if ($devices === []) {
            return 0.0;
        }

        $ids = array_map(static fn ($d) => (int) $d['id'], $devices);

        $onStates = model(\App\Models\DeviceStateModel::class)
            ->select('device_id')
            ->whereIn('device_id', $ids)
            ->where('power_state', 'on')
            ->findAll();

        $onIds = array_map(static fn ($s) => (int) $s['device_id'], $onStates);

        $watts = 0.0;
        foreach ($devices as $device) {
            if (in_array((int) $device['id'], $onIds, true)) {
                $watts += self::WATTS[$device['device_type']] ?? self::WATTS['other'];
            }
        }

        return $watts;
    }

    /**
     * Pembacaan terbaru per ruangan untuk dashboard/detail ruangan.
     *
     * @return array<string, array{value: float, unit: string, recorded_at: string, source: string}>
     */
    public function latestForRoom(int $roomId): array
    {
        $out = [];

        foreach (['temperature', 'humidity', 'occupancy', 'energy_estimate', 'light'] as $metric) {
            $row = $this->readings
                ->where('room_id', $roomId)
                ->where('metric', $metric)
                ->orderBy('recorded_at', 'DESC')
                ->first();

            if ($row !== null) {
                $out[$metric] = [
                    'value'       => (float) $row['value'],
                    'unit'        => $row['unit'],
                    'recorded_at' => $row['recorded_at'],
                    'source'      => $row['source'],
                ];
            }
        }

        return $out;
    }

    /**
     * Total estimasi daya terbaru seluruh gedung (W), null bila belum ada data.
     *
     * @return array{value: float, unit: string, recorded_at: string, source: string}|null
     */
    public function latestBuildingEnergy(): ?array
    {
        $row = $this->readings
            ->where('metric', 'energy_estimate')
            ->orderBy('recorded_at', 'DESC')
            ->first();

        if ($row === null) {
            return null;
        }

        // Ambil semua ruangan yang memiliki pembacaan pada waktu yang sama.
        $total = $this->readings
            ->selectSum('value')
            ->where('metric', 'energy_estimate')
            ->where('recorded_at', $row['recorded_at'])
            ->get()
            ->getRowArray();

        return [
            'value'       => (float) ($total['value'] ?? 0),
            'unit'        => 'W',
            'recorded_at' => $row['recorded_at'],
            'source'      => $row['source'],
        ];
    }
}
