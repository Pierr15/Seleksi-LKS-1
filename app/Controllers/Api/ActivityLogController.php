<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;
use App\Models\ActivityLogModel;

class ActivityLogController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        $get = fn (string $key) => trim((string) ($this->request->getGet($key) ?? ''));

        $builder = (new ActivityLogModel())
            ->select('activity_logs.*, users.name AS user_name, rooms.name AS room_name,
                      devices.name AS device_name, floors.name AS floor_name')
            ->join('users', 'users.id = activity_logs.user_id', 'left')
            ->join('rooms', 'rooms.id = activity_logs.room_id', 'left')
            ->join('devices', 'devices.id = activity_logs.device_id', 'left')
            ->join('floors', 'floors.id = activity_logs.floor_id', 'left');

        if (is_numeric($get('device_id'))) {
            $builder->where('activity_logs.device_id', (int) $get('device_id'));
        }

        if (is_numeric($get('room_id'))) {
            $builder->where('activity_logs.room_id', (int) $get('room_id'));
        }

        if ($get('action') !== '') {
            $builder->where('activity_logs.action', $get('action'));
        }

        if (in_array($get('result'), ['success', 'failure'], true)) {
            $builder->where('activity_logs.result', $get('result'));
        }

        if (in_array($get('source'), ['manual', 'automation', 'scheduler'], true)) {
            $builder->where('activity_logs.source', $get('source'));
        }

        if ($get('from') !== '') {
            $builder->where('activity_logs.created_at >=', $this->toUtc($get('from')));
        }

        if ($get('to') !== '') {
            $builder->where('activity_logs.created_at <=', $this->toUtc($get('to')));
        }

        $limit = (int) ($this->request->getGet('limit') ?? 50);
        $limit = max(1, min($limit, 200));

        $rows = $builder->orderBy('activity_logs.created_at', 'DESC')
            ->limit($limit)
            ->findAll();

        return $this->success(['logs' => $rows]);
    }

    private function toUtc(string $datetime): string
    {
        try {
            $d = new \DateTime($datetime, new \DateTimeZone('Asia/Jakarta'));
            $d->setTimezone(new \DateTimeZone('UTC'));

            return $d->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return $datetime;
        }
    }
}
