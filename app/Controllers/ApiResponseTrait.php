<?php

namespace App\Controllers;

use App\Exceptions\ServiceException;

/**
 * Respons JSON dengan format konsisten untuk seluruh endpoint /api.
 *
 * Sukses : { success: true, data: ... , message: ... }
 * Gagal  : { success: false, message: ..., errors: {...} } + HTTP status tepat
 */
trait ApiResponseTrait
{
    /**
     * @param array<string, mixed>|list<mixed>|null $data
     */
    protected function success(array $data = null, string $message = '', int $status = 200)
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'success' => true,
                'message' => $message,
                'data'    => $data,
            ]);
    }

    protected function fail(ServiceException $e)
    {
        return $this->response
            ->setStatusCode($e->statusCode)
            ->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->getErrors(),
                'data'    => $e->getPayload() ?: null,
            ]);
    }

    protected function failGeneric(string $message, int $status = 400, array $errors = [])
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ]);
    }

    /**
     * Body request: JSON (fetch) atau form-encoded (fallback).
     *
     * @return array<string, mixed>
     */
    protected function body(): array
    {
        $json = $this->request->getJSON(true);

        if (is_array($json)) {
            return $json;
        }

        $raw     = $this->request->getBody();
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        if (is_array($decoded)) {
            return $decoded;
        }

        $post = $this->request->getPost();

        if (is_array($post)) {
            unset($post['csrf_token']);

            return $post;
        }

        return [];
    }
}
