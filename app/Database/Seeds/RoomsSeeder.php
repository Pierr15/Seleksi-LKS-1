<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Ruangan gedung utama (inventaris awal §7).
 *
 * Catatan penting:
 * - PENEMPATAN LANTAI BELUM TERVERIFIKASI. Semua ruangan ditempatkan pada
 *   Lantai 1 sebagai placeholder eksplisit dan ditandai
 *   floor_verification_status = 'needs_verification' agar Admin memperbaikinya
 *   melalui UI. Ini BUKAN klaim bahwa seluruh ruangan ada di Lantai 1.
 * - Resepsionis direpresentasikan sebagai area terbuka (open_area),
 *   bukan ruangan tertutup.
 * - Rect denah (map_*) adalah REPRESENTASI ILUSTRATIF, bukan denah arsitektur asli.
 * - Seeder tidak pernah menimpa baris yang sudah ada.
 */
class RoomsSeeder extends Seeder
{
    use IdempotentSeeder;

    /**
     * Layout ilustratif grid 3×3 pada kanvas 100×100 (persen).
     */
    private array $rooms = [
        ['name' => 'Ruang Kurikulum', 'type' => 'closed', 'map' => [2, 2, 30, 30],
            'desc' => 'Ruang kerja tim kurikulum.'],
        ['name' => 'Ruang Tata Usaha', 'type' => 'closed', 'map' => [35, 2, 30, 30],
            'desc' => 'Ruang administrasi tata usaha.'],
        ['name' => 'Ruang Meeting', 'type' => 'closed', 'map' => [68, 2, 30, 30],
            'desc' => 'Ruang rapat dengan proyektor.'],
        ['name' => 'Auditorium', 'type' => 'closed', 'map' => [2, 35, 30, 30],
            'desc' => 'Aula serbaguna sekolah.'],
        ['name' => 'Office Atas', 'type' => 'closed', 'map' => [35, 35, 30, 30],
            'desc' => 'Ruang kepala/kepala tata usaha.'],
        ['name' => 'Ruang Podcast', 'type' => 'closed', 'map' => [68, 35, 30, 30],
            'desc' => 'Studio podcast dan produksi konten.'],
        ['name' => 'Ruang Operator', 'type' => 'closed', 'map' => [2, 68, 30, 30],
            'desc' => 'Ruang operator/jaringan sekolah.'],
        ['name' => 'Toilet', 'type' => 'closed', 'map' => [35, 68, 30, 30],
            'desc' => 'Toilet lantai bawah.'],
        ['name' => 'Resepsionis', 'type' => 'open_area', 'map' => [68, 68, 30, 30],
            'desc' => 'Area terbuka resepsionis, bukan ruangan tertutup.'],
    ];

    public function run()
    {
        $floor = $this->db->table('floors')->where('slug', 'lantai-1')->get()->getRowArray();

        if ($floor === null) {
            CLI::error('FloorsSeeder harus dijalankan sebelum RoomsSeeder.');

            return;
        }

        $now = gmdate('Y-m-d H:i:s');

        foreach ($this->rooms as $room) {
            if ($this->exists('rooms', ['floor_id' => $floor['id'], 'name' => $room['name']])) {
                continue; // Tidak menimpa perubahan Admin.
            }

            $this->db->table('rooms')->insert([
                'floor_id'                  => (int) $floor['id'],
                'name'                      => $room['name'],
                'description'               => $room['desc'],
                'room_type'                 => $room['type'],
                'mode'                      => 'active',
                'occupancy'                 => 'vacant',
                'vacant_since'              => null,
                'inventory_status'          => 'verified',
                'floor_verification_status' => 'needs_verification',
                'is_active'                 => 1,
                'archived_at'               => null,
                'map_x'                     => $room['map'][0],
                'map_y'                     => $room['map'][1],
                'map_w'                     => $room['map'][2],
                'map_h'                     => $room['map'][3],
                'created_at'                => $now,
                'updated_at'                => $now,
            ]);
        }

        CLI::write('Rooms seeder selesai (idempoten, penempatan lantai menunggu verifikasi).', 'green');
    }
}
