<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Membuat akun Admin awal HANYA dari konfigurasi environment.
 *
 * Tidak ada kredensial default yang ditulis ke repository:
 * isi SEED_ADMIN_NAME, SEED_ADMIN_EMAIL, dan SEED_ADMIN_PASSWORD
 * pada .env (lihat .env.example), jalankan seeder, lalu kosongkan kembali.
 */
class AdminUserSeeder extends Seeder
{
    use IdempotentSeeder;

    public function run()
    {
        $name     = $_SERVER['SEED_ADMIN_NAME'] ?? getenv('SEED_ADMIN_NAME');
        $email    = $_SERVER['SEED_ADMIN_EMAIL'] ?? getenv('SEED_ADMIN_EMAIL');
        $password = $_SERVER['SEED_ADMIN_PASSWORD'] ?? getenv('SEED_ADMIN_PASSWORD');

        if (! is_string($name) || ! is_string($email) || ! is_string($password)
            || trim($name) === '' || trim($email) === '' || trim($password) === ''
        ) {
            CLI::write('SEED_ADMIN_* belum diisi — pembuatan akun Admin dilewati.', 'yellow');

            return;
        }

        $email = strtolower(trim($email));

        if ($this->exists('users', ['email' => $email])) {
            CLI::write("Akun admin '{$email}' sudah ada — dilewati (tidak menimpa).", 'yellow');

            return;
        }

        $this->db->table('users')->insert([
            'name'                 => trim($name),
            'email'                => $email,
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'role'                 => 'admin',
            'is_active'            => 1,
            'failed_login_attempts' => 0,
            'created_at'           => gmdate('Y-m-d H:i:s'),
            'updated_at'           => gmdate('Y-m-d H:i:s'),
        ]);

        CLI::write("Akun admin '{$email}' dibuat dari konfigurasi environment.", 'green');
    }
}
