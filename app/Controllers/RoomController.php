<?php

namespace App\Controllers;

use App\Models\DeviceModel;
use App\Models\RoomModel;

class RoomController extends BaseController
{
    public function index()
    {
        $search = trim((string) ($this->request->getGet('q') ?? ''));
        $floor  = $this->request->getGet('lantai');
        $status = $this->request->getGet('status');       // occupied|vacant
        $mode   = $this->request->getGet('mode');         // active|standby|inactive
        $verif  = $this->request->getGet('verifikasi');   // needs_verification|verified

        $model   = new RoomModel();
        $builder = $model
            ->select('rooms.*, floors.name AS floor_name')
            ->join('floors', 'floors.id = rooms.floor_id', 'left')
            ->where('rooms.archived_at', null);

        if ($search !== '') {
            $builder->groupStart()
                ->like('rooms.name', $search)
                ->like('rooms.description', $search)
                ->groupEnd();
        }

        if (is_numeric($floor)) {
            $builder->where('rooms.floor_id', (int) $floor);
        }

        if (in_array($status, ['occupied', 'vacant'], true)) {
            $builder->where('rooms.occupancy', $status);
        }

        if (in_array($mode, ['active', 'standby', 'inactive'], true)) {
            $builder->where('rooms.mode', $mode);
        }

        if (in_array($verif, ['needs_verification', 'verified'], true)) {
            $builder->where('rooms.inventory_status', $verif);
        }

        $rooms = $builder->orderBy('rooms.name', 'ASC')->paginate(12);
        $pager = $model->pager;

        $floorOptions = (new \App\Models\FloorModel())->orderBy('sort_order')->findAll();

        return view('rooms/index', $this->layoutData('Ruangan & Perangkat', 'ruangan') + [
            'rooms'       => $rooms,
            'pager'       => $pager,
            'filters'     => [
                'q'          => $search,
                'lantai'     => is_numeric($floor) ? (int) $floor : '',
                'status'     => $status ?? '',
                'mode'       => $mode ?? '',
                'verifikasi' => $verif ?? '',
            ],
            'floorOptions' => $floorOptions,
            'deviceCounts' => $this->deviceCounts($rooms),
        ]);
    }

    public function show(string $id)
    {
        $room = (new RoomModel())
            ->select('rooms.*, floors.name AS floor_name, floors.slug AS floor_slug')
            ->join('floors', 'floors.id = rooms.floor_id', 'left')
            ->where('rooms.id', (int) $id)
            ->where('rooms.archived_at', null)
            ->first();

        if ($room === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Ruangan tidak ditemukan.');
        }

        $deviceId = (int) ($this->request->getGet('perangkat') ?? 0);

        $devices = (new DeviceModel())
            ->select('devices.*, device_states.power_state, device_states.connection_state, device_states.last_seen_at,
                      device_states.target_temperature, device_states.ac_mode, device_states.ac_boost')
            ->join('device_states', 'device_states.device_id = devices.id', 'left')
            ->where('devices.room_id', (int) $id)
            ->where('devices.archived_at', null)
            ->orderBy('devices.name', 'ASC')
            ->findAll();

        $selectedDevice = null;
        foreach ($devices as $device) {
            if ((int) $device['id'] === $deviceId) {
                $selectedDevice = $device;
                break;
            }
        }

        $commands = \Config\Database::connect()
            ->table('device_commands')
            ->select('device_commands.*, devices.name AS device_name, users.name AS requester_name')
            ->join('devices', 'devices.id = device_commands.device_id', 'left')
            ->join('users', 'users.id = device_commands.requested_by', 'left')
            ->where('device_commands.room_id', (int) $id)
            ->orderBy('device_commands.created_at', 'DESC')
            ->limit(15)
            ->get()
            ->getResultArray();

        $overrideDevices = service('overrideService')->activeForRoom((int) $id);

        $activityLogs = \Config\Database::connect()
            ->table('activity_logs')
            ->select('activity_logs.*, users.name AS user_name')
            ->join('users', 'users.id = activity_logs.user_id', 'left')
            ->where('activity_logs.room_id', (int) $id)
            ->orderBy('activity_logs.created_at', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $rules = (new \App\Models\AutomationRuleModel())
            ->where('is_active', 1)
            ->findAll();

        $applicableRules = [];
        foreach ($rules as $rule) {
            $target   = json_decode($rule['target_json'] ?? '[]', true) ?: [];
            $roomIds  = $target['room_ids'] ?? [];
            if ($roomIds === [] || in_array((int) $id, array_map('intval', $roomIds), true)) {
                $applicableRules[] = $rule;
            }
        }

        return view('rooms/show', $this->layoutData($room['name'], 'ruangan') + [
            'room'            => $room,
            'devices'         => $devices,
            'selectedDevice'  => $selectedDevice,
            'commands'        => $commands,
            'overrideDevices' => $overrideDevices,
            'activityLogs'    => $activityLogs,
            'applicableRules' => $applicableRules,
            'sensors'         => service('simulationSensor')->latestForRoom((int) $id),
            'vacantMinutes'   => service('settings')->vacantDurationMinutes(),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rooms
     *
     * @return array<int, array{total: int, placed: int}>
     */
    private function deviceCounts(array $rooms): array
    {
        if ($rooms === []) {
            return [];
        }

        $ids = array_map(static fn ($r) => (int) $r['id'], $rooms);

        $rows = \Config\Database::connect()
            ->table('devices')
            ->select('room_id, COUNT(*) AS total, SUM(position_x IS NOT NULL) AS placed')
            ->whereIn('room_id', $ids)
            ->where('archived_at', null)
            ->groupBy('room_id')
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['room_id']] = [
                'total'  => (int) $row['total'],
                'placed' => (int) $row['placed'],
            ];
        }

        return $out;
    }
}
