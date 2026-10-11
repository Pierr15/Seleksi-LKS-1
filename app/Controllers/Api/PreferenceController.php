<?php

namespace App\Controllers\Api;

use App\Controllers\ApiResponseTrait;
use App\Controllers\BaseController;
use App\Models\UserModel;

/**
 * Preferensi personal (tema) — boleh diubah semua peran.
 */
class PreferenceController extends BaseController
{
    use ApiResponseTrait;

    public function update()
    {
        $theme = (string) ($this->body()['theme'] ?? '');

        if (! in_array($theme, ['light', 'dark', 'system'], true)) {
            return $this->failGeneric(
                'Tema tidak valid.',
                422,
                ['theme' => 'Tema harus light, dark, atau system.'],
            );
        }

        (new UserModel())->update($this->auth->id(), ['theme' => $theme]);

        return $this->success(['theme' => $theme], 'Tema disimpan.');
    }
}
