<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;
use App\Models\DeviceModel;
use App\Models\FloorModel;
use App\Models\RoomModel;

class FloorController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $floors = (new FloorModel())->orderBy('sort_order', 'ASC')->findAll();

        foreach ($floors as &$floor) {
            $floor['room_count'] = (int) (new RoomModel())
                ->where('floor_id', $floor['id'])
                ->where('archived_at', null)
                ->countAllResults();
        }
        unset($floor);

        return $this->success(['floors' => $floors]);
    }

    /**
     * Data satu lantai untuk denah: ruangan (geometri + status) dan
     * perangkat (posisi relatif ruangan). Semua dibaca dari database.
     */
    public function show(string $id)
    {
        $floor = (new FloorModel())->find((int) $id);

        if ($floor === null) {
            return $this->failGeneric('Lantai tidak ditemukan.', 404);
        }

        $rooms = (new RoomModel())
            ->where('floor_id', (int) $id)
            ->where('archived_at', null)
            ->orderBy('name', 'ASC')
            ->findAll();

        $roomIds = array_map(static fn ($r) => (int) $r['id'], $rooms);

        $devices = [];

        if ($roomIds !== []) {
            $devices = (new DeviceModel())
                ->select('devices.*, device_states.power_state, device_states.connection_state,
                          device_states.target_temperature, device_states.ac_mode, device_states.ac_boost,
                          rooms.name AS room_name, rooms.floor_id')
                ->join('device_states', 'device_states.device_id = devices.id', 'left')
                ->join('rooms', 'rooms.id = devices.room_id', 'left')
                ->whereIn('devices.room_id', $roomIds)
                ->where('devices.archived_at', null)
                ->orderBy('devices.name', 'ASC')
                ->findAll();
        }

        $placed   = count(array_filter($devices, static fn ($d) => $d['position_x'] !== null));
        $unplaced = count($devices) - $placed;

        foreach ($rooms as &$room) {
            $room['device_count'] = count(array_filter(
                $devices,
                static fn ($d) => (int) $d['room_id'] === (int) $room['id'],
            ));
            $room['placed_count'] = count(array_filter(
                $devices,
                static fn ($d) => (int) $d['room_id'] === (int) $room['id'] && $d['position_x'] !== null,
            ));
            $room['sensors'] = service('simulationSensor')->latestForRoom((int) $room['id']);
        }
        unset($room);

        return $this->success([
            'floor'    => $floor,
            'rooms'    => $rooms,
            'devices'  => $devices,
            'counts'   => ['placed' => $placed, 'unplaced' => $unplaced, 'total' => count($devices)],
        ]);
    }
}
