<?php

namespace App\Services;

use App\Models\AppSettingModel;

/**
 * Pengaturan aplikasi berbasis tabel app_settings.
 */
class SettingsService
{
    private const DEFAULT_VACANT_MINUTES = 5;
    private const AC_TEMP_MIN = 16.0;
    private const AC_TEMP_MAX = 30.0;
    private const AC_TEMP_DEFAULT = 24.0;

    public function __construct(private ?AppSettingModel $model = null)
    {
        $this->model ??= model(AppSettingModel::class);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->model->where('setting_key', $key)->first();

        return $row !== null ? $row['setting_value'] : $default;
    }

    public function set(string $key, string $value, ?int $updatedBy = null): void
    {
        $existing = $this->model->where('setting_key', $key)->first();

        if ($existing !== null) {
            $this->model->update($existing['id'], [
                'setting_value' => $value,
                'updated_by'    => $updatedBy,
            ]);

            return;
        }

        $this->model->insert([
            'setting_key'   => $key,
            'setting_value' => $value,
            'updated_by'    => $updatedBy,
        ]);
    }

    /**
     * Durasi vacant (menit) sebelum aturan otomatisasi dianggap jatuh tempo.
     * Nilai awal 5 menit hanya untuk pengujian MVP, dapat diubah Admin.
     */
    public function vacantDurationMinutes(): int
    {
        $value = $this->get('vacant_duration_minutes');

        if ($value !== null && is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        $env = $_SERVER['orbit.vacantDurationMinutes'] ?? getenv('orbit.vacantDurationMinutes');

        if (is_string($env) && is_numeric($env) && (int) $env > 0) {
            return (int) $env;
        }

        return self::DEFAULT_VACANT_MINUTES;
    }

    public function acTempMin(): float
    {
        $value = $this->get('ac_temp_min');

        return $value !== null && is_numeric($value) ? (float) $value : self::AC_TEMP_MIN;
    }

    public function acTempMax(): float
    {
        $value = $this->get('ac_temp_max');

        return $value !== null && is_numeric($value) ? (float) $value : self::AC_TEMP_MAX;
    }

    public function acTempDefault(): float
    {
        $value = $this->get('ac_temp_default');

        return $value !== null && is_numeric($value) ? (float) $value : self::AC_TEMP_DEFAULT;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->model->findAll() as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }

        return $out;
    }
}
