<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;
use App\Exceptions\ServiceException;

class OverrideController extends BaseController
{
    use ApiResponseTrait;

    public function store()
    {
        $body = $this->body();

        try {
            $override = service('overrideService')->create(
                (int) ($body['device_id'] ?? 0),
                (string) ($body['override_type'] ?? ''),
                (string) ($body['reason'] ?? ''),
                $this->auth->id(),
                isset($body['expires_at']) && $body['expires_at'] !== '' ? (string) $body['expires_at'] : null,
            );

            return $this->success(['override' => $override], 'Override dibuat.', 201);
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function end(string $id)
    {
        try {
            $override = service('overrideService')->end((int) $id, $this->auth->id());

            return $this->success(['override' => $override], 'Override diakhiri.');
        } catch (ServiceException $e) {
            return $this->fail($e);
        }
    }

    public function index()
    {
        $rows = \Config\Database::connect()
            ->table('device_overrides')
            ->select('device_overrides.*, devices.name AS device_name, rooms.name AS room_name,
                      users.name AS creator_name')
            ->join('devices', 'devices.id = device_overrides.device_id', 'left')
            ->join('rooms', 'rooms.id = devices.room_id', 'left')
            ->join('users', 'users.id = device_overrides.created_by', 'left')
            ->where('device_overrides.ended_at', null)
            ->orderBy('device_overrides.starts_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->success(['overrides' => $rows]);
    }
}
