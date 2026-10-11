<?php

namespace App\Services;

use App\Models\ActivityLogModel;

/**
 * Pencatat audit trail untuk tindakan penting.
 * Kegagalan pencatatan log tidak boleh menjatuhkan operasi utama,
 * tetapi dicatat ke log aplikasi agar bisa ditelusuri.
 */
class AuditLogService
{
    public function __construct(private ?ActivityLogModel $model = null)
    {
        $this->model ??= model(ActivityLogModel::class);
    }

    /**
     * @param array{
     *     user_id?: int|null,
     *     target_type?: string,
     *     target_id?: int|null,
     *     floor_id?: int|null,
     *     room_id?: int|null,
     *     device_id?: int|null,
     *     result?: 'success'|'failure',
     *     source?: 'manual'|'automation'|'scheduler',
     *     details?: array<string, mixed>,
     *     ip_address?: string|null,
     * } $opts
     */
    public function log(string $action, array $opts = []): void
    {
        $data = [
            'user_id'      => $opts['user_id'] ?? $this->currentUserId(),
            'action'       => $action,
            'target_type'  => $opts['target_type'] ?? 'setting',
            'target_id'    => $opts['target_id'] ?? null,
            'floor_id'     => $opts['floor_id'] ?? null,
            'room_id'      => $opts['room_id'] ?? null,
            'device_id'    => $opts['device_id'] ?? null,
            'result'       => $opts['result'] ?? 'success',
            'source'       => $opts['source'] ?? 'manual',
            'details_json' => isset($opts['details']) ? json_encode($opts['details'], JSON_UNESCAPED_UNICODE) : null,
            'ip_address'   => $opts['ip_address'] ?? $this->currentIp(),
            'created_at'   => orbit_utc_now(),
        ];

        try {
            if ($this->model->insert($data, false) === false) {
                log_message('error', 'Gagal menulis activity log: {msg}', [
                    'msg' => json_encode($this->model->errors()),
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Gagal menulis activity log: {msg}', ['msg' => $e->getMessage()]);
        }
    }

    private function currentUserId(): ?int
    {
        $userId = session('orbit_user_id');

        return $userId !== null ? (int) $userId : null;
    }

    private function currentIp(): ?string
    {
        try {
            $request = service('request');

            return $request !== null ? $request->getIPAddress() : null;
        } catch (\Throwable $e) {
            return null; // CLI / scheduler
        }
    }
}
