<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Inventaris perangkat awal (§7) — nama GENERIK tanpa merek/model.
 *
 * Idempoten: unit yang sudah ada tidak pernah ditimpa. Bila Admin menambah
 * atau memindahkan perangkat, seeder tidak merusaknya; seeder hanya
 * melengkapi jumlah unit yang belum ada per nama generik di ruangan.
 *
 * Penamaan: "AC 1", "Lampu 2", dst. — angka adalah nomor urut unit,
 * bukan model atau merek.
 */
class DevicesSeeder extends Seeder
{
    use IdempotentSeeder;

    /**
     * room => [ [name, type, capability, essential, inventory, count], ... ]
     *
     * Semua perangkat terverifikasi dan kontrol penuh (kecuali tipe esensial
     * yang dilindungi dari aturan hemat energi umum).
     * Lampu digabung menjadi 1 unit per ruangan.
     */
    private array $inventory = [
        'Ruang Kurikulum' => [
            ['AC', 'ac', 'controllable', 0, 'verified', 2],
            ['Lampu', 'light', 'controllable', 0, 'verified', 1],
            ['Komputer', 'computer', 'controllable', 0, 'verified', 5],
        ],
        'Ruang Tata Usaha' => [
            ['AC', 'ac', 'controllable', 0, 'verified', 2],
            ['Lampu', 'light', 'controllable', 0, 'verified', 1],
            ['Komputer', 'computer', 'controllable', 0, 'verified', 5],
        ],
        'Ruang Meeting' => [
            ['AC Sentral', 'ac_central', 'controllable', 0, 'verified', 1],
            ['Proyektor', 'projector', 'controllable', 0, 'verified', 1],
            ['Lampu', 'light', 'controllable', 0, 'verified', 1],
        ],
        'Auditorium' => [
            ['AC', 'ac', 'controllable', 0, 'verified', 4],
            ['Lampu', 'light', 'controllable', 0, 'verified', 1],
            ['Proyektor', 'projector', 'controllable', 0, 'verified', 4],
            ['Videotron', 'videotron', 'controllable', 1, 'verified', 1],
            ['Perangkat Audio', 'audio', 'controllable', 1, 'verified', 4],
        ],
        'Office Atas' => [
            ['AC Sentral', 'ac_central', 'controllable', 0, 'verified', 1],
            ['Komputer', 'computer', 'controllable', 0, 'verified', 5],
            ['Lampu', 'light', 'controllable', 0, 'verified', 1],
        ],
        'Ruang Podcast' => [
            ['AC', 'ac', 'controllable', 0, 'verified', 1],
            ['Lampu', 'light', 'controllable', 0, 'verified', 1],
            ['Pencahayaan Studio', 'studio_lighting', 'controllable', 1, 'verified', 1],
        ],
        'Ruang Operator' => [
            ['Lampu', 'light', 'controllable', 0, 'verified', 1],
            ['Komputer', 'computer', 'controllable', 0, 'verified', 2],
        ],
        'Toilet' => [
            ['Lampu', 'light', 'controllable', 0, 'verified', 1],
            ['Exhaust Fan', 'exhaust_fan', 'controllable', 0, 'verified', 2],
        ],
        'Resepsionis' => [],
    ];

    public function run()
    {
        $now      = gmdate('Y-m-d H:i:s');
        $inserted = 0;

        foreach ($this->inventory as $roomName => $items) {
            $room = $this->db->table('rooms')->where('name', $roomName)->get()->getRowArray();

            if ($room === null) {
                CLI::write("Ruangan '{$roomName}' belum ada — dilewati.", 'yellow');

                continue;
            }

            foreach ($items as [$name, $type, $capability, $essential, $inventory, $count]) {
                // Pencocokan berdasarkan TIPE di ruangan (bukan nama), sehingga
                // penamaan bernomor ("AC 1") dan perubahan nama oleh Admin
                // tidak menyebabkan duplikat saat seeder dijalankan ulang.
                $existing = $this->db->table('devices')
                    ->where('room_id', $room['id'])
                    ->where('device_type', $type)
                    ->countAllResults();

                $missing = max(0, $count - $existing);

                for ($i = 1; $i <= $missing; $i++) {
                    $unitNumber = $existing + $i;

                    $this->db->table('devices')->insert([
                        'room_id'           => (int) $room['id'],
                        'name'              => $count > 1 ? $name . ' ' . $unitNumber : $name,
                        'device_type'       => $type,
                        'control_capability' => $capability,
                        'is_essential'      => $essential,
                        'inventory_status'  => $inventory,
                        'is_active'         => 1,
                        'position_x'        => null, // Belum ditempatkan — tidak ditebak.
                        'position_y'        => null,
                        'source'            => 'simulation',
                        'archived_at'       => null,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);

                    $deviceId = $this->db->insertID();

                    // State awal: unknown — tidak berasumsi ON/OFF.
                    $this->db->table('device_states')->insert([
                        'device_id'        => (int) $deviceId,
                        'power_state'      => 'unknown',
                        'connection_state' => 'unknown',
                        'source'           => 'simulation',
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);

                    $inserted++;
                }
            }
        }

        CLI::write("Devices seeder selesai — {$inserted} unit baru ditambahkan (idempoten).", 'green');
    }
}
