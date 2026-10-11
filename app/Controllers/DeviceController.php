<?php

namespace App\Controllers;

use App\Models\DeviceModel;

class DeviceController extends BaseController
{
    public function index()
    {
        $search = trim((string) ($this->request->getGet('q') ?? ''));
        $room   = $this->request->getGet('ruangan');
        $type   = $this->request->getGet('tipe');
        $power  = $this->request->getGet('daya');        // on|off|unknown
        $place  = $this->request->getGet('posisi');      // placed|unplaced
        $verif  = $this->request->getGet('verifikasi');

        $model   = new DeviceModel();
        $builder = $model
            ->select('devices.*, rooms.name AS room_name, floors.id AS floor_id, floors.name AS floor_name,
                      device_states.power_state, device_states.connection_state')
            ->join('rooms', 'rooms.id = devices.room_id', 'left')
            ->join('floors', 'floors.id = rooms.floor_id', 'left')
            ->join('device_states', 'device_states.device_id = devices.id', 'left')
            ->where('devices.archived_at', null);

        if ($search !== '') {
            $builder->like('devices.name', $search);
        }

        if (is_numeric($room)) {
            $builder->where('devices.room_id', (int) $room);
        }

        if (is_string($type) && $type !== '') {
            $builder->where('devices.device_type', $type);
        }

        if (in_array($power, ['on', 'off', 'unknown'], true)) {
            $builder->where('device_states.power_state', $power);
        }

        if ($place === 'placed') {
            $builder->where('devices.position_x is not null', null, false);
        } elseif ($place === 'unplaced') {
            $builder->where('devices.position_x', null);
        }

        if (in_array($verif, ['needs_verification', 'verified'], true)) {
            $builder->where('devices.inventory_status', $verif);
        }

        $devices = $builder->orderBy('rooms.name', 'ASC')
            ->orderBy('devices.name', 'ASC')
            ->paginate(15);
        $pager = $model->pager;

        return view('devices/index', $this->layoutData('Perangkat', 'perangkat') + [
            'devices'      => $devices,
            'pager'        => $pager,
            'filters'      => [
                'q'          => $search,
                'ruangan'    => is_numeric($room) ? (int) $room : '',
                'tipe'       => $type ?? '',
                'daya'       => $power ?? '',
                'posisi'     => $place ?? '',
                'verifikasi' => $verif ?? '',
            ],
            'roomOptions'  => (new \App\Models\RoomModel())
                ->where('archived_at', null)->orderBy('name')->findAll(),
            'typeOptions'  => ['ac' => 'AC', 'ac_central' => 'AC Sentral', 'light' => 'Lampu',
                'computer' => 'Komputer', 'projector' => 'Proyektor', 'videotron' => 'Videotron',
                'audio' => 'Perangkat Audio', 'exhaust_fan' => 'Exhaust Fan',
                'studio_lighting' => 'Pencahayaan Studio', 'other' => 'Lainnya'],
        ]);
    }

    public function show(string $id)
    {
        $device = (new DeviceModel())
            ->select('devices.*, rooms.name AS room_name, rooms.floor_id, rooms.mode AS room_mode,
                      rooms.occupancy, floors.name AS floor_name,
                      device_states.power_state, device_states.connection_state,
                      device_states.last_seen_at, device_states.source AS state_source,
                      device_states.target_temperature, device_states.ac_mode, device_states.ac_boost')
            ->join('rooms', 'rooms.id = devices.room_id', 'left')
            ->join('floors', 'floors.id = rooms.floor_id', 'left')
            ->join('device_states', 'device_states.device_id = devices.id', 'left')
            ->where('devices.id', (int) $id)
            ->first();

        if ($device === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Perangkat tidak ditemukan.');
        }

        $commands = \Config\Database::connect()
            ->table('device_commands')
            ->select('device_commands.*, users.name AS requester_name')
            ->join('users', 'users.id = device_commands.requested_by', 'left')
            ->where('device_commands.device_id', (int) $id)
            ->orderBy('device_commands.created_at', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();

        $override = service('overrideService')->activeFor((int) $id);

        return view('devices/show', $this->layoutData($device['name'], 'perangkat') + [
            'device'   => $device,
            'commands' => $commands,
            'override' => $override,
        ]);
    }
}
