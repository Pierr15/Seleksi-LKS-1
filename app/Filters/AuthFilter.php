<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Wajib login untuk seluruh halaman dan endpoint data.
 * - Halaman web   → redirect ke /login
 * - Endpoint /api → respons JSON 401
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (service('auth')->check()) {
            return null;
        }

        $uri = ltrim((string) $request->getUri()->getPath(), '/');

        if (str_starts_with($uri, 'api/') || $request->isAJAX()) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['success' => false, 'message' => 'Sesi tidak valid. Silakan login kembali.']);
        }

        return redirect()->to('/login')->with('login_required', 'Silakan login terlebih dahulu.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
