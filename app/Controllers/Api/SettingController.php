<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;
use App\Exceptions\ServiceException;

class SettingController extends BaseController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->success([
            'settings'  => service('settings')->all(),
            'scheduler' => service('scheduler')->status(),
        ]);
    }

    public function update()
    {
        $body = $this->body();
        $errors = [];

        if (array_key_exists('vacant_duration_minutes', $body)) {
            $minutes = (int) $body['vacant_duration_minutes'];
            if ($minutes < 1 || $minutes > 1440) {
                $errors['vacant_duration_minutes'] = 'Durasi harus antara 1 dan 1440 menit.';
            }
        }

        if ($errors !== []) {
            return $this->fail(new ServiceException('Pengaturan tidak valid.', 422, $errors));
        }

        $userId = $this->auth->id();

        if (array_key_exists('vacant_duration_minutes', $body)) {
            service('settings')->set('vacant_duration_minutes', (string) (int) $body['vacant_duration_minutes'], $userId);
        }

        service('auditLog')->log('setting.update', [
            'user_id' => $userId,
            'details' => ['kunci' => array_keys($body)],
        ]);

        return $this->success(['settings' => service('settings')->all()], 'Pengaturan disimpan.');
    }
}
