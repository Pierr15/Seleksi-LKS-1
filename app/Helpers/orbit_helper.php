<?php

/**
 * Helper global ORBIT Building.
 *
 * Konvensi waktu: seluruh penyimpanan & perhitungan di database/aplikasi memakai UTC.
 * Konversi ke Asia/Jakarta hanya dilakukan pada lapisan tampilan (helper format).
 */

function orbit_utc_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function orbit_to_jakarta(?string $utcDateTime): ?string
{
    if ($utcDateTime === null || $utcDateTime === '') {
        return null;
    }

    try {
        $d = new DateTime($utcDateTime, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Jakarta'));

        return $d->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return $utcDateTime;
    }
}

function orbit_format_datetime(?string $utcDateTime, string $format = 'd M Y, H:i'): string
{
    if ($utcDateTime === null || $utcDateTime === '') {
        return 'Belum tersedia';
    }

    $jkt = orbit_to_jakarta($utcDateTime);

    try {
        $d = new DateTime($jkt, new DateTimeZone('Asia/Jakarta'));

        return $d->format($format);
    } catch (Throwable $e) {
        return $utcDateTime;
    }
}

/**
 * Durasi sejak waktu UTC tertentu sampai sekarang, dalam bahasa Indonesia.
 */
function orbit_duration_since(?string $utcDateTime): string
{
    if ($utcDateTime === null || $utcDateTime === '') {
        return '—';
    }

    try {
        $since = new DateTime($utcDateTime, new DateTimeZone('UTC'));
        $now   = new DateTime('now', new DateTimeZone('UTC'));
        $diff  = $since->getTimestamp() >= $now->getTimestamp()
            ? 0
            : $now->getTimestamp() - $since->getTimestamp();
    } catch (Throwable $e) {
        return '—';
    }

    if ($diff < 60) {
        return $diff . ' detik';
    }

    $menit = intdiv($diff, 60);
    if ($menit < 60) {
        return $menit . ' menit';
    }

    $jam   = intdiv($menit, 60);
    $sisa  = $menit % 60;
    $hasil = $jam . ' jam';

    return $sisa > 0 ? $hasil . ' ' . $sisa . ' menit' : $hasil;
}

/**
 * Label tipe perangkat (bahasa Indonesia, generik tanpa merek).
 */
function orbit_device_type_label(string $type): string
{
    return match ($type) {
        'ac'             => 'AC',
        'ac_central'     => 'AC Sentral',
        'light'          => 'Lampu',
        'computer'       => 'Komputer',
        'projector'      => 'Proyektor',
        'videotron'      => 'Videotron',
        'audio'          => 'Perangkat Audio',
        'exhaust_fan'    => 'Exhaust Fan',
        'studio_lighting' => 'Pencahayaan Studio',
        'other'          => 'Perangkat Lain',
        default          => $type,
    };
}

/**
 * Ikon Bootstrap Icons per tipe perangkat.
 */
function orbit_device_icon(string $type): string
{
    return match ($type) {
        'ac', 'ac_central'   => 'bi-snow',
        'light'              => 'bi-lightbulb',
        'computer'           => 'bi-pc-display',
        'projector'          => 'bi-projector',
        'videotron'          => 'bi-tv',
        'audio'              => 'bi-speaker',
        'exhaust_fan'        => 'bi-fan',
        'studio_lighting'    => 'bi-brightness-high',
        default              => 'bi-cpu',
    };
}

/**
 * Status daya: jangan pernah mengubah unknown menjadi OFF.
 */
function orbit_power_label(string $state): string
{
    return match ($state) {
        'on'      => 'Menyala',
        'off'     => 'Mati',
        'unknown' => 'Tidak diketahui',
        default   => $state,
    };
}

function orbit_connection_label(string $state): string
{
    return match ($state) {
        'online'  => 'Online',
        'offline' => 'Offline',
        'unknown' => 'Tidak diketahui',
        default   => $state,
    };
}

function orbit_room_mode_label(string $mode): string
{
    return match ($mode) {
        'active'   => 'Active',
        'standby'   => 'Standby',
        'inactive' => 'Inactive',
        default    => $mode,
    };
}

function orbit_occupancy_label(string $occupancy): string
{
    return match ($occupancy) {
        'occupied' => 'Terisi',
        'vacant'   => 'Kosong',
        default    => $occupancy,
    };
}

function orbit_inventory_label(string $status): string
{
    return match ($status) {
        'needs_verification' => 'Perlu verifikasi',
        'verified'           => 'Terverifikasi',
        default              => $status,
    };
}

/**
 * Encoding JSON aman untuk atribut HTML.
 */
function orbit_attr(?string $json): string
{
    return htmlspecialchars((string) $json, ENT_QUOTES, 'UTF-8');
}


/**
 * Ikon SVG inline (stroke-based, 24x24). Tanpa dependensi CDN.
 */
function orbit_icon(string $name, int $size = 20): string
{
    static $paths = [
        'home'      => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'map'       => '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14"/><path d="M15 6v14"/>',
        'door'      => '<path d="M4 21h16"/><path d="M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17"/><circle cx="14.5" cy="12" r="1"/>',
        'cpu'       => '<rect x="7" y="7" width="10" height="10" rx="1.5"/><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/>',
        'bolt'      => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'gear'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.55-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h.09a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.09a1.7 1.7 0 0 0 1.55 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.55 1Z"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'      => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
        'system'    => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'filter'    => '<path d="M3 5h18l-7 8v5l-4 2v-7L3 5Z"/>',
        'check'     => '<path d="m5 13 4 4L19 7"/>',
        'x'         => '<path d="M6 6l12 12M18 6 6 18"/>',
        'alert'     => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'power'     => '<path d="M12 3v9"/><path d="M6.6 6.6a8 8 0 1 0 10.8 0"/>',
        'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'edit'      => '<path d="M11 4H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-6"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/>',
        'trash'     => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/>',
        'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'refresh'   => '<path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'wifi-off'  => '<path d="m2 2 20 20"/><path d="M5 12.5a11 11 0 0 1 4-2.5M2 8.8a16 16 0 0 1 5.2-3.2M17 5.5a16 16 0 0 1 5 3.3M15.5 10.5a11 11 0 0 1 3.5 2M8.5 16a6 6 0 0 1 7 0"/><path d="M12 20h.01"/>',
        'drag'      => '<circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/>',
        'thermo'    => '<path d="M14 14.8V5a2 2 0 0 0-4 0v9.8a4 4 0 1 0 4 0Z"/>',
        'droplet'   => '<path d="M12 3s6 6.1 6 10.5a6 6 0 0 1-12 0C6 9.1 12 3 12 3Z"/>',
        'zap'       => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'activity'  => '<path d="M22 12h-4l-3 8-6-16-3 8H2"/>',
        'save'      => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'undo'      => '<path d="M3 7v6h6"/><path d="M3.5 13a9 9 0 1 0 2.1-9.4L3 7"/>',
        'lock'      => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'building'  => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/><path d="M10 21v-3h4v3"/>',
        'chevron'   => '<path d="m9 6 6 6-6 6"/>',
        'wrench'    => '<path d="M14.7 6.3a4.5 4.5 0 0 0-6 5.7L3 17.7V21h3.3l5.7-5.7a4.5 4.5 0 0 0 5.7-6L15.4 12l-2.6-2.6 2.7-3.1Z"/>',
    ];

    $path = $paths[$name] ?? $paths['info'];

    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true">' . $path . '</svg>';
}
