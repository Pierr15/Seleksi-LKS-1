<?php

namespace App\Controllers;

class FloorController extends BaseController
{
    /**
     * Halaman denah. Data lantai & ruangan diambil via GET /api/floors/:id
     * agar posisi perangkat selalu dibaca dari database.
     */
    public function index()
    {
        $floors = (new \App\Models\FloorModel())
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        $needsVerification = (int) \Config\Database::connect()
            ->table('rooms')
            ->where('floor_verification_status', 'needs_verification')
            ->where('archived_at', null)
            ->countAllResults();

        return view('floors/index', $this->layoutData('Denah Gedung', 'denah') + [
            'floors'            => $floors,
            'initialFloor'      => (int) ($this->request->getGet('lantai') ?: ($floors[0]['id'] ?? 1)),
            'needsVerification' => $needsVerification,
        ]);
    }
}
