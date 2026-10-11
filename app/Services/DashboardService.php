<?php

namespace App\Services;

use App\Models\ActivityLogModel;
use App\Models\AutomationExecutionModel;
use App\Models\AutomationRuleModel;
use App\Models\DeviceModel;
use App\Models\RoomModel;

/**
 * Agregasi data dashboard — semua angka diambil dari database,
 * tanpa metrik karangan. Data yang belum tersedia dilaporkan apa adanya.
 */
class DashboardService
{
    public function __construct(
        private ?RoomModel $rooms = null,
        private ?DeviceModel $devices = null,
        private ?ActivityLogModel $logs = null,
        private ?AutomationRuleModel $rules = null,
        private ?AutomationExecutionModel $executions = null,
        private ?SimulationSensorService $sensors = null,
        private ?SchedulerService $scheduler = null,
    ) {
        $this->rooms      ??= model(RoomModel::class);
        $this->devices    ??= model(DeviceModel::class);
        $this->logs       ??= model(ActivityLogModel::class);
        $this->rules      ??= model(AutomationRuleModel::class);
        $this->executions ??= model(AutomationExecutionModel::class);
        $this->sensors    ??= service('simulationSensor');
        $this->scheduler  ??= service('scheduler');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $db = \Config\Database::connect();

        // ---- Ruangan -----------------------------------------------------
        $roomStats = $db->table('rooms')
            ->select("COUNT(*) AS total,
                SUM(is_active = 1 AND archived_at IS NULL) AS aktif,
                SUM(occupancy = 'occupied' AND archived_at IS NULL) AS terisi,
                SUM(occupancy = 'vacant' AND archived_at IS NULL) AS kosong,
                SUM(mode = 'active' AND archived_at IS NULL) AS mode_active,
                SUM(mode = 'standby' AND archived_at IS NULL) AS mode_standby,
                SUM(mode = 'inactive' AND archived_at IS NULL) AS mode_inactive,
                SUM(inventory_status = 'needs_verification' AND archived_at IS NULL) AS perlu_verifikasi")
            ->get()
            ->getRowArray();

        // ---- Perangkat ---------------------------------------------------
        $deviceStats = $db->table('devices d')
            ->select("COUNT(*) AS total,
                SUM(d.is_active = 1 AND d.archived_at IS NULL) AS aktif,
                SUM(d.position_x IS NOT NULL AND d.archived_at IS NULL) AS ditempatkan,
                SUM(d.position_x IS NULL AND d.archived_at IS NULL) AS belum_ditempatkan,
                SUM(d.inventory_status = 'needs_verification' AND d.archived_at IS NULL) AS perlu_verifikasi")
            ->join('device_states s', 's.device_id = d.id', 'left')
            ->get()
            ->getRowArray();

        $powerStats = $db->table('devices d')
            ->join('device_states s', 's.device_id = d.id', 'inner')
            ->where('d.is_active', 1)
            ->where('d.archived_at', null)
            ->select("SUM(s.power_state = 'on') AS menyala,
                SUM(s.power_state = 'off') AS mati,
                SUM(s.power_state = 'unknown') AS unknown,
                SUM(s.connection_state = 'offline') AS offline,
                SUM(s.connection_state = 'unknown') AS koneksi_unknown")
            ->get()
            ->getRowArray();

        $problemDevices = $this->devices
            ->select('devices.*, device_states.power_state, device_states.connection_state, rooms.name AS room_name')
            ->join('device_states', 'device_states.device_id = devices.id', 'left')
            ->join('rooms', 'rooms.id = devices.room_id', 'left')
            ->where('devices.is_active', 1)
            ->where('devices.archived_at', null)
            ->where('device_states.connection_state', 'offline')
            ->orderBy('devices.id', 'ASC')
            ->limit(10)
            ->findAll();

        // ---- Otomatisasi -------------------------------------------------
        $activeRules = $this->rules->where('is_active', 1)->countAllResults();

        $recentExecutions = $this->executions
            ->select('automation_executions.*, automation_rules.name AS rule_name')
            ->join('automation_rules', 'automation_rules.id = automation_executions.rule_id', 'left')
            ->orderBy('automation_executions.executed_at', 'DESC')
            ->limit(5)
            ->findAll();

        // ---- Aktivitas terbaru -------------------------------------------
        $recentActivity = $this->logs
            ->select('activity_logs.*, users.name AS user_name')
            ->join('users', 'users.id = activity_logs.user_id', 'left')
            ->orderBy('activity_logs.created_at', 'DESC')
            ->limit(8)
            ->findAll();

        $energy = $this->sensors->latestBuildingEnergy();

        return [
            'ruangan' => [
                'total'            => (int) ($roomStats['total'] ?? 0),
                'aktif'            => (int) ($roomStats['aktif'] ?? 0),
                'terisi'           => (int) ($roomStats['terisi'] ?? 0),
                'kosong'           => (int) ($roomStats['kosong'] ?? 0),
                'mode_active'      => (int) ($roomStats['mode_active'] ?? 0),
                'mode_standby'     => (int) ($roomStats['mode_standby'] ?? 0),
                'mode_inactive'    => (int) ($roomStats['mode_inactive'] ?? 0),
                'perlu_verifikasi' => (int) ($roomStats['perlu_verifikasi'] ?? 0),
            ],
            'perangkat' => [
                'total'             => (int) ($deviceStats['total'] ?? 0),
                'aktif'             => (int) ($deviceStats['aktif'] ?? 0),
                'menyala'           => (int) ($powerStats['menyala'] ?? 0),
                'mati'              => (int) ($powerStats['mati'] ?? 0),
                'unknown'           => (int) ($powerStats['unknown'] ?? 0),
                'offline'           => (int) ($powerStats['offline'] ?? 0),
                'koneksi_unknown'   => (int) ($powerStats['koneksi_unknown'] ?? 0),
                'ditempatkan'       => (int) ($deviceStats['ditempatkan'] ?? 0),
                'belum_ditempatkan' => (int) ($deviceStats['belum_ditempatkan'] ?? 0),
                'perlu_verifikasi'  => (int) ($deviceStats['perlu_verifikasi'] ?? 0),
                'bermasalah'        => array_map(static fn (array $d) => [
                    'id'      => (int) $d['id'],
                    'nama'    => $d['name'],
                    'ruangan' => $d['room_name'] ?? '—',
                    'daya'    => $d['power_state'] ?? 'unknown',
                    'koneksi' => $d['connection_state'] ?? 'unknown',
                ], $problemDevices),
            ],
            'scheduler' => $this->scheduler->status(),
            'otomatisasi' => [
                'aturan_aktif'    => $activeRules,
                'total_aturan'    => $this->rules->countAllResults(),
                'eksekusi_terbaru' => array_map(static fn (array $e) => [
                    'nama_aturan' => $e['rule_name'] ?? '—',
                    'status'      => $e['status'],
                    'alasan'      => $e['reason'],
                    'waktu'       => $e['executed_at'],
                ], $recentExecutions),
            ],
            'aktivitas' => array_map(static fn (array $log) => [
                'aksi'     => $log['action'],
                'pengguna' => $log['user_name'] ?? ($log['source'] === 'manual' ? '—' : 'Sistem'),
                'hasil'    => $log['result'],
                'sumber'   => $log['source'],
                'waktu'    => $log['created_at'],
                'target'   => $log['target_type'],
                'id_target' => $log['target_id'],
            ], $recentActivity),
            'energi' => $energy,
        ];
    }
}
