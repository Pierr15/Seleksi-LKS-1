<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/**
 * Proteksi CSRF global.
 * Token dapat dikirim melalui field form `csrf_token` atau header
 * `X-CSRF-TOKEN` (dipakai seluruh fetch AJAX pada frontend).
 * Gagal → JSON 403 untuk endpoint /api, redirect untuk halaman web.
 */
class CsrfFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $method = strtoupper($request->getMethod());

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        try {
            service('security')->verify($request);
        } catch (SecurityException $e) {
            $uri = ltrim((string) $request->getUri()->getPath(), '/');

            if (str_starts_with($uri, 'api/') || $request->isAJAX()) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.']);
            }

            return redirect()->back()->with('error', 'Token keamanan kedaluwarsa. Silakan muat ulang halaman.');
        }

        return null;
    }

    public function after(RequestInterface $request, $response, $arguments = null)
    {
        return null;
    }
}
