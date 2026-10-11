<?php

namespace App\Controllers;

use App\Models\ActivityLogModel;

class ActivityLogController extends BaseController
{
    public function index()
    {
        $get = fn (string $key) => trim((string) ($this->request->getGet($key) ?? ''));

        $model   = new ActivityLogModel();
        $builder = $model
            ->select('activity_logs.*, users.name AS user_name, rooms.name AS room_name,
                      devices.name AS device_name, floors.name AS floor_name')
            ->join('users', 'users.id = activity_logs.user_id', 'left')
            ->join('rooms', 'rooms.id = activity_logs.room_id', 'left')
            ->join('devices', 'devices.id = activity_logs.device_id', 'left')
            ->join('floors', 'floors.id = activity_logs.floor_id', 'left');

        if ($get('mulai') !== '') {
            $builder->where('activity_logs.created_at >=', $this->toUtc($get('mulai') . ' 00:00:00'));
        }

        if ($get('akhir') !== '') {
            $builder->where('activity_logs.created_at <=', $this->toUtc($get('akhir') . ' 23:59:59'));
        }

        if (is_numeric($get('pengguna'))) {
            $builder->where('activity_logs.user_id', (int) $get('pengguna'));
        }

        if (is_numeric($get('lantai'))) {
            $builder->where('activity_logs.floor_id', (int) $get('lantai'));
        }

        if (is_numeric($get('ruangan'))) {
            $builder->where('activity_logs.room_id', (int) $get('ruangan'));
        }

        if (is_numeric($get('perangkat'))) {
            $builder->where('activity_logs.device_id', (int) $get('perangkat'));
        }

        if ($get('aksi') !== '') {
            $builder->where('activity_logs.action', $get('aksi'));
        }

        if (in_array($get('hasil'), ['success', 'failure'], true)) {
            $builder->where('activity_logs.result', $get('hasil'));
        }

        if (in_array($get('sumber'), ['manual', 'automation', 'scheduler'], true)) {
            $builder->where('activity_logs.source', $get('sumber'));
        }

        $logs = $builder->orderBy('activity_logs.created_at', 'DESC')->paginate(20);
        $pager = $model->pager;

        return view('activity_logs/index', $this->layoutData('Log Aktivitas', 'log') + [
            'logs'      => $logs,
            'pager'     => $pager,
            'filters'   => [
                'mulai'      => $get('mulai'),
                'akhir'      => $get('akhir'),
                'pengguna'   => is_numeric($get('pengguna')) ? (int) $get('pengguna') : '',
                'lantai'     => is_numeric($get('lantai')) ? (int) $get('lantai') : '',
                'ruangan'    => is_numeric($get('ruangan')) ? (int) $get('ruangan') : '',
                'perangkat'  => is_numeric($get('perangkat')) ? (int) $get('perangkat') : '',
                'aksi'       => $get('aksi'),
                'hasil'      => $get('hasil'),
                'sumber'     => $get('sumber'),
            ],
            'users'      => (new \App\Models\UserModel())->orderBy('name')->findAll(),
            'floors'     => (new \App\Models\FloorModel())->orderBy('sort_order')->findAll(),
            'rooms'      => (new \App\Models\RoomModel())->where('archived_at', null)->orderBy('name')->findAll(),
            'actionOptions' => [
                'auth.login' => 'Login', 'auth.logout' => 'Logout', 'auth.login_failed' => 'Login gagal',
                'device.create' => 'Tambah perangkat', 'device.update' => 'Ubah perangkat',
                'device.archive' => 'Arsipkan perangkat', 'device.command' => 'Kontrol perangkat',
                'device.position_update' => 'Ubah posisi perangkat',
                'room.create' => 'Tambah ruangan', 'room.update' => 'Ubah ruangan',
                'room.archive' => 'Arsipkan ruangan', 'room.occupancy' => 'Ubah okupansi',
                'room.mode' => 'Ubah mode ruangan',
                'rule.create' => 'Buat aturan', 'rule.update' => 'Ubah aturan',
                'rule.executed' => 'Aturan dieksekusi',
                'override.create' => 'Buat override', 'override.end' => 'Akhiri override',
                'user.create' => 'Tambah pengguna', 'user.update' => 'Ubah pengguna',
                'setting.update' => 'Ubah pengaturan',
            ],
        ]);
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
