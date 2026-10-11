<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;
use App\Exceptions\ServiceException;
use App\Models\DeviceModel;
use App\Models\RoomModel;

class RoomController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $rooms = (new RoomModel())
            ->select('rooms.*, floors.name AS floor_name')
            ->join('floors', 'floors.id = rooms.floor_id', 'left')
            ->where('rooms.archived_at', null)
            ->orderBy('rooms.name', 'ASC')
            ->findAll();

        return $this->success(['rooms' => $rooms]);
    }

    public function show(string $id)
    {
        $room = (new RoomModel())
            ->select('rooms.*, floors.name AS floor_name')
            ->join('floors', 'floors.id = rooms.floor_id', 'left')
            ->where('rooms.id', (int) $id)
            ->first();

        if ($room === null) {
            return $this->failGeneric('Ruangan tidak ditemukan.', 404);
        }

        $devices = (new DeviceModel())
            ->select('devices.*, device_states.power_state, device_states.connection_state,
                      device_states.last_seen_at,
                      device_states.target_temperature, device_states.ac_mode, device_states.ac_boost')
            ->join('device_states', 'device_states.device_id = devices.id', 'left')
            ->where('devices.room_id', (int) $id)
            ->where('devices.archived_at', null)
            ->orderBy('devices.name', 'ASC')
            ->findAll();

        foreach ($devices as &$device) {
            $device['override_active'] = service('overrideService')->hasActive((int) $device['id']);
        }
        unset($device);

        return $this->success([
            'room'     => $room,
            'devices'  => $devices,
            'sensors'  => service('simulationSensor')->latestForRoom((int) $id),
            'overrides' => service('overrideService')->activeForRoom((int) $id),
            'vacant_minutes' => service('settings')->vacantDurationMinutes(),
        ]);
    }

    public function store()
    {
        $body = $this->body();

        try {
            $room = service('inventory')->createRoom($body, $this->auth->id());

            return $this->success(['room' => $room], 'Ruangan berhasil ditambahkan.', 201);
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function update(string $id)
    {
        try {
            $room = service('inventory')->updateRoom((int) $id, $this->body(), $this->auth->id());

            return $this->success(['room' => $room], 'Ruangan berhasil diperbarui.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function archive(string $id)
    {
        $archive = (bool) ($this->body()['archive'] ?? true);

        try {
            $room = service('inventory')->archiveRoom((int) $id, $archive, $this->auth->id());

            return $this->success(['room' => $room], $archive ? 'Ruangan diarsipkan.' : 'Ruangan dipulihkan.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function setOccupancy(string $id)
    {
        $occupancy = (string) ($this->body()['occupancy'] ?? '');

        try {
            $room = service('occupancy')->setOccupancy((int) $id, $occupancy, $this->auth->id());

            return $this->success(['room' => $room], 'Status okupansi diperbarui.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function setMode(string $id)
    {
        $mode = (string) ($this->body()['mode'] ?? '');

        try {
            $room = service('occupancy')->setMode((int) $id, $mode, $this->auth->id());

            return $this->success(['room' => $room], 'Mode ruangan diperbarui.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }
}
