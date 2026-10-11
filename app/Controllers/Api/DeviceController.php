<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;
use App\Exceptions\ServiceException;
use App\Models\DeviceModel;

class DeviceController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $roomIds = $this->request->getGet('room_ids');
        $placed  = $this->request->getGet('placed');

        $builder = (new DeviceModel())
            ->select('devices.*, device_states.power_state, device_states.connection_state,
                      device_states.target_temperature, device_states.ac_mode, device_states.ac_boost,
                      rooms.name AS room_name, rooms.floor_id')
            ->join('device_states', 'device_states.device_id = devices.id', 'left')
            ->join('rooms', 'rooms.id = devices.room_id', 'left')
            ->where('devices.archived_at', null);

        if (is_string($roomIds) && $roomIds !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', $roomIds))));
            if ($ids !== []) {
                $builder->whereIn('devices.room_id', $ids);
            }
        }

        if ($placed === '0' || $placed === 'unplaced') {
            $builder->where('devices.position_x', null);
        } elseif ($placed === '1' || $placed === 'placed') {
            $builder->where('devices.position_x is not null', null, false);
        }

        $devices = $builder->orderBy('devices.name', 'ASC')->findAll();

        return $this->success(['devices' => $devices]);
    }

    public function show(string $id)
    {
        $device = (new DeviceModel())
            ->select('devices.*, device_states.power_state, device_states.connection_state,
                      device_states.last_seen_at, device_states.source AS state_source,
                      device_states.target_temperature, device_states.ac_mode, device_states.ac_boost,
                      rooms.name AS room_name, rooms.floor_id, rooms.occupancy, rooms.mode AS room_mode')
            ->join('device_states', 'device_states.device_id = devices.id', 'left')
            ->join('rooms', 'rooms.id = devices.room_id', 'left')
            ->where('devices.id', (int) $id)
            ->first();

        if ($device === null) {
            return $this->failGeneric('Perangkat tidak ditemukan.', 404);
        }

        return $this->success([
            'device'   => $device,
            'override' => service('overrideService')->activeFor((int) $id),
        ]);
    }

    public function store()
    {
        try {
            $device = service('inventory')->createDevice($this->body(), $this->auth->id());

            return $this->success(['device' => $device], 'Perangkat berhasil ditambahkan.', 201);
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function update(string $id)
    {
        try {
            $device = service('inventory')->updateDevice((int) $id, $this->body(), $this->auth->id());

            return $this->success(['device' => $device], 'Perangkat berhasil diperbarui.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function archive(string $id)
    {
        $archive = (bool) ($this->body()['archive'] ?? true);

        try {
            $device = service('inventory')->archiveDevice((int) $id, $archive, $this->auth->id());

            return $this->success(['device' => $device], $archive ? 'Perangkat diarsipkan.' : 'Perangkat dipulihkan.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function command(string $id)
    {
        $body    = $this->body();
        $command = (string) ($body['command'] ?? '');

        $params = [];
        if (isset($body['temperature'])) { $params['temperature'] = $body['temperature']; }
        if (isset($body['mode']))        { $params['mode'] = $body['mode']; }
        if (array_key_exists('boost', $body)) { $params['boost'] = $body['boost']; }

        try {
            $state = service('deviceControl')->execute(
                (int) $id,
                $command,
                'manual',
                $this->auth->id(),
                null,
                false,
                $params,
            );

            return $this->success(['state' => $state], 'Perintah dikirim ke perangkat.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function position(string $id)
    {
        $body     = $this->body();
        $roomId   = isset($body['room_id']) && $body['room_id'] !== null && $body['room_id'] !== ''
            ? (int) $body['room_id']
            : null;
        $x        = $body['position_x'] ?? null;
        $y        = $body['position_y'] ?? null;

        try {
            $device = service('devicePosition')->place(
                (int) $id,
                $roomId,
                $x === null || $x === '' ? null : (float) $x,
                $y === null || $y === '' ? null : (float) $y,
                $this->auth->id(),
            );

            return $this->success(['device' => $device], 'Posisi perangkat disimpan.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function commands(string $id)
    {
        $rows = \Config\Database::connect()
            ->table('device_commands')
            ->select('device_commands.*, users.name AS requester_name')
            ->join('users', 'users.id = device_commands.requested_by', 'left')
            ->where('device_commands.device_id', (int) $id)
            ->orderBy('device_commands.created_at', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();

        return $this->success(['commands' => $rows]);
    }
}
