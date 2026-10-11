<?php

namespace App\Commands;

use Config\Services;

/**
 * Satu tick pemeriksaan otomatisasi & simulasi.
 *
 * Dijalankan oleh container scheduler setiap 30 detik:
 *   php spark orbit:scheduler:tick
 *
 * Perintah ini idempoten dan aman dipanggil bersamaan:
 * lock MySQL + UNIQUE (rule_id, tick_key) mencegah eksekusi ganda.
 */
class SchedulerTick extends \CodeIgniter\CLI\BaseCommand
{
    protected $group       = 'ORBIT';
    protected $name        = 'orbit:scheduler:tick';
    protected $description = 'Menjalankan satu tick pemeriksaan scheduler (otomatisasi, override, sensor simulasi).';

    public function run(array $params)
    {
        $result = Services::scheduler()->tick();

        if ($result['ran'] === false) {
            \CodeIgniter\CLI\CLI::write('Dilewati: ' . $result['reason'], 'yellow');

            return EXIT_SUCCESS;
        }

        \CodeIgniter\CLI\CLI::write('Tick ' . $result['tick'] . ' selesai.', 'green');

        foreach ($result['steps'] as $step => $data) {
            \CodeIgniter\CLI\CLI::write('  - ' . $step . ': ' . json_encode($data));
        }

        if ($result['errors'] !== []) {
            \CodeIgniter\CLI\CLI::error('Tahap gagal: ' . json_encode($result['errors']), 'red');

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
