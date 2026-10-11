<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Otorisasi berbasis peran, ditegakkan di backend.
 * Digunakan sebagai 'role:admin', 'role:admin,operator', 'role:admin,operator,viewer'.
 *
 * Semua izin WAJIB dicek di sini; menyembunyikan tombol di frontend tidak cukup.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $allowed = array_map('trim', $arguments ?? []);
        $auth    = service('auth');

        if (! $auth->check()) {
            if (str_starts_with((string) $request->getUri()->getPath(), 'api/') || $request->isAJAX()) {
                return $request->respond(
                    ['success' => false, 'message' => 'Sesi tidak valid. Silakan login kembali.'],
                    401,
                );
            }

            return redirect()->to('/login');
        }

        $user = $auth->user();

        $uri = ltrim((string) $request->getUri()->getPath(), '/');

        if (! in_array($user['role'], $allowed, true)) {
            if (str_starts_with($uri, 'api/') || $request->isAJAX()) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Anda tidak memiliki hak akses untuk tindakan ini.',
                        'errors'  => ['role' => 'Dibutuhkan peran: ' . implode(', ', $allowed)],
                    ]);
            }

            return redirect()->back()->with('error', 'Anda tidak memiliki hak akses untuk halaman tersebut.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
