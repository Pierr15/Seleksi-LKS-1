<?php

namespace App\Services;

/**
 * Scheduler loop yang dijalankan sebagai container terpisah
 * (setiap 30 detik melalui `php spark orbit:scheduler:tick`).
 *
 * Keamanan:
 * - MySQL GET_LOCK mencegah dua tick berjalan bersamaan (anti-duplikat).
 * - Setiap tahap diisolasi: satu tahap gagal tidak menghentikan tick lain.
 * - Eksekusi aturan memakai tick_key + UNIQUE (rule_id, tick_key).
 * - Terus berjalan setelah restart container (state ada di database).
 */
class SchedulerService
{
    private const LOCK_NAME = 'orbit_scheduler_tick';

    public function __construct(
        private ?OverrideService $overrides = null,
        private ?SimulationSensorService $sensors = null,
        private ?AutomationService $automation = null,
        private ?SettingsService $settings = null,
        private ?AuditLogService $audit = null,
    ) {
        $this->overrides ??= service('overrideService');
        $this->sensors   ??= service('simulationSensor');
        $this->automation ??= service('automation');
        $this->settings  ??= service('settings');
        $this->audit     ??= service('auditLog');
    }

    /**
     * Menjalankan satu tick pemeriksaan.
     *
     * @return array<string, mixed>
     */
    public function tick(): array
    {
        $db     = \Config\Database::connect();
        $result = $db->query('SELECT GET_LOCK(?, 0) AS l', [self::LOCK_NAME]);
        $row    = $result->getFirstRow('array');

        if ((int) ($row['l'] ?? 0) !== 1) {
            return [
                'ran'    => false,
                'reason' => 'Tick lain sedang berjalan (lock tidak diperoleh).',
            ];
        }

        $startedAt = orbit_utc_now();
        $tickKey   = gmdate('YmdHis');
        $steps     = [];
        $errors    = [];

        try {
            $steps['overrides'] = $this->runStep(
                'override',
                fn () => ['expired' => $this->overrides->expireDue($startedAt)],
                $errors,
            );

            $steps['sensors'] = $this->runStep(
                'sensor_simulasi',
                fn () => $this->sensors->updateAll($startedAt),
                $errors,
            );

            $steps['automation'] = $this->runStep(
                'otomatisasi',
                fn () => $this->automation->processDueRules($tickKey),
                $errors,
            );

            $this->settings->set('scheduler_last_run_at', $startedAt);
            $this->settings->set('scheduler_last_tick', $tickKey);
            $this->settings->set('scheduler_last_status', $errors === [] ? 'ok' : 'partial_error');
        } finally {
            $db->query('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }

        if ($errors !== []) {
            $this->audit->log('scheduler.error', [
                'user_id'     => null,
                'target_type' => 'setting',
                'source'      => 'scheduler',
                'result'      => 'failure',
                'details'     => ['kesalahan' => $errors, 'tick' => $tickKey],
            ]);
        }

        return [
            'ran'       => true,
            'tick'      => $tickKey,
            'startedAt' => $startedAt,
            'steps'     => $steps,
            'errors'    => $errors,
        ];
    }

    /**
     * @param callable(): mixed                        $fn
     * @param array<string, string>                   $errors
     *
     * @return mixed|null
     */
    private function runStep(string $name, callable $fn, array &$errors)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $errors[$name] = $e->getMessage();
            log_message('error', 'Scheduler step {step} gagal: {msg}', ['step' => $name, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Status scheduler untuk dashboard.
     *
     * @return array{last_run: ?string, status: string, fresh: bool}
     */
    public function status(): array
    {
        $lastRun = $this->settings->get('scheduler_last_run_at');
        $status  = $this->settings->get('scheduler_last_status', 'unknown');

        $fresh = false;
        if ($lastRun !== null) {
            $fresh = (time() - strtotime($lastRun . ' UTC')) <= 90;
        }

        return [
            'last_run' => $lastRun,
            'status'   => $lastRun === null ? 'belum_pernah' : $status,
            'fresh'    => $fresh,
        ];
    }
}
