<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Dua lantai gedung utama. Hanya insert bila belum ada.
 */
class FloorsSeeder extends Seeder
{
    use IdempotentSeeder;

    private array $floors = [
        ['name' => 'Lantai 1', 'slug' => 'lantai-1', 'label' => 'Lantai 1 — area bawah', 'sort_order' => 1],
        ['name' => 'Lantai 2', 'slug' => 'lantai-2', 'label' => 'Lantai 2 — area atas', 'sort_order' => 2],
    ];

    public function run()
    {
        foreach ($this->floors as $floor) {
            if ($this->exists('floors', ['slug' => $floor['slug']])) {
                continue;
            }

            $this->db->table('floors')->insert($floor + [
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        CLI::write('Floors seeder selesai (idempoten).', 'green');
    }
}
