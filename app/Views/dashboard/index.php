<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>
<?php
$r = $summary['ruangan'];
$d = $summary['perangkat'];
$s = $summary['scheduler'];
$o = $summary['otomatisasi'];
$energi = $summary['energi'];
?>

<div class="page-head">
    <div>
        <h2>Ringkasan Gedung</h2>
        <p>Data nyata dari basis data · scheduler berjalan setiap 30 detik</p>
    </div>
    <div class="actions">
        <a class="btn btn-outline btn-sm" href="<?= site_url('dashboard') ?>">
            <?= orbit_icon('refresh', 16) ?> Muat ulang
        </a>
        <?php if ($canControl): ?>
            <a class="btn btn-sm" href="<?= site_url('denah') ?>"><?= orbit_icon('map', 16) ?> Buka Denah</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($s['fresh']): ?>
    <div class="alert alert-success"><?= orbit_icon('check') ?>
        <span>Scheduler aktif · terakhir berjalan <strong><?= esc(orbit_format_datetime($s['last_run'])) ?></strong></span>
    </div>
<?php else: ?>
    <div class="alert alert-warn"><?= orbit_icon('alert') ?>
        <span>Scheduler belum berjalan baru-baru ini
            (status: <?= esc($s['status']) ?><?= $s['last_run'] !== null ? ', terakhir ' . esc(orbit_format_datetime($s['last_run'])) : '' ?>).
            Pastikan kontainer <span class="mono">orbit-scheduler</span> berjalan.</span>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat accent-primary">
        <span class="stat-label"><?= orbit_icon('door', 16) ?> Ruangan aktif</span>
        <span class="stat-value"><?= (int) $r['aktif'] ?></span>
        <span class="stat-sub"><?= (int) $r['total'] ?> total · <?= (int) $r['perlu_verifikasi'] ?> perlu verifikasi</span>
    </div>
    <div class="stat accent-success">
        <span class="stat-label"><?= orbit_icon('check', 16) ?> Ruangan terisi</span>
        <span class="stat-value"><?= (int) $r['terisi'] ?></span>
        <span class="stat-sub"><?= (int) $r['kosong'] ?> kosong · mode aktif <?= (int) $r['mode_active'] ?></span>
    </div>
    <div class="stat accent-primary">
        <span class="stat-label"><?= orbit_icon('cpu', 16) ?> Perangkat aktif</span>
        <span class="stat-value"><?= (int) $d['aktif'] ?></span>
        <span class="stat-sub"><?= (int) $d['menyala'] ?> menyala · <?= (int) $d['mati'] ?> mati · <?= (int) $d['unknown'] ?> tidak diketahui</span>
    </div>
    <div class="stat accent-warn">
        <span class="stat-label"><?= orbit_icon('wifi-off', 16) ?> Perangkat bermasalah</span>
        <span class="stat-value"><?= (int) $d['offline'] ?></span>
        <span class="stat-sub">offline · <?= (int) $d['belum_ditempatkan'] ?> belum ditempatkan di denah</span>
    </div>
</div>

<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header"><?= orbit_icon('zap', 18) ?> Otomatisasi
            <span class="right">
                <a class="btn btn-outline btn-sm" href="<?= site_url('otomatisasi') ?>">Kelola</a>
            </span>
        </div>
        <div class="card-body">
            <div class="flex gap-3 wrap mb-3">
                <div class="stat" style="flex:1;min-width:130px;box-shadow:none">
                    <span class="stat-label">Aturan aktif</span>
                    <span class="stat-value"><?= (int) $o['aturan_aktif'] ?></span>
                    <span class="stat-sub">dari <?= (int) $o['total_aturan'] ?> aturan</span>
                </div>
                <div class="stat" style="flex:1;min-width:130px;box-shadow:none">
                    <span class="stat-label">Estimasi daya nyala</span>
                    <span class="stat-value">
                        <?= $energi !== null ? number_format((float) $energi['value'], 0, ',', '.') : '—' ?>
                        <span style="font-size:14px;font-weight:600"><?= $energi !== null ? esc($energi['unit']) : '' ?></span>
                    </span>
                    <span class="stat-sub">
                        <?= $energi !== null ? 'terakhir ' . esc(orbit_format_datetime($energi['recorded_at'])) : 'belum ada data' ?>
                    </span>
                </div>
            </div>

            <div class="timeline">
                <?php if ($o['eksekusi_terbaru'] === []): ?>
                    <div class="empty" style="padding:18px">Belum ada eksekusi aturan.</div>
                <?php endif; ?>
                <?php foreach ($o['eksekusi_terbaru'] as $ex): ?>
                    <div class="item">
                        <div>
                            <strong><?= esc($ex['nama_aturan']) ?></strong>
                            <span class="badge <?= $ex['status'] === 'success' ? 'badge-success' : ($ex['status'] === 'skipped' ? 'badge-warn' : 'badge-danger') ?> plain" style="margin-left:6px">
                                <?= esc($ex['status']) ?>
                            </span>
                            <div class="muted small"><?= esc((string) $ex['alasan']) ?></div>
                        </div>
                        <span class="when right"><?= esc(orbit_format_datetime($ex['waktu'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><?= orbit_icon('activity', 18) ?> Aktivitas Terbaru
            <span class="right">
                <a class="btn btn-outline btn-sm" href="<?= site_url('log') ?>">Semua log</a>
            </span>
        </div>
        <div class="card-body">
            <div class="timeline">
                <?php if ($summary['aktivitas'] === []): ?>
                    <div class="empty" style="padding:18px">Belum ada aktivitas tercatat.</div>
                <?php endif; ?>
                <?php foreach ($summary['aktivitas'] as $log): ?>
                    <div class="item">
                        <div>
                            <strong><?= esc($log['aksi']) ?></strong>
                            <span class="badge <?= $log['hasil'] === 'success' ? 'badge-success' : 'badge-danger' ?> plain" style="margin-left:6px">
                                <?= esc($log['hasil']) ?>
                            </span>
                            <div class="muted small">
                                <?= esc($log['pengguna']) ?> · <?= esc($log['sumber']) ?>
                                <?= $log['target'] !== null ? ' · ' . esc($log['target']) . ' #' . (int) $log['id_target'] : '' ?>
                            </div>
                        </div>
                        <span class="when right"><?= esc(orbit_format_datetime($log['waktu'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($d['bermasalah'] !== []): ?>
    <div class="card">
        <div class="card-header"><?= orbit_icon('alert', 18) ?> Perangkat Offline
            <span class="right muted small"><?= count($d['bermasalah']) ?> ditampilkan</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr><th>Perangkat</th><th>Ruangan</th><th>Daya</th><th>Koneksi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($d['bermasalah'] as $p): ?>
                        <tr>
                            <td><a href="<?= site_url('perangkat/' . $p['id']) ?>"><strong><?= esc($p['nama']) ?></strong></a></td>
                            <td><?= esc($p['ruangan']) ?></td>
                            <td><span class="badge <?= $p['daya'] === 'on' ? 'badge-success' : ($p['daya'] === 'off' ? 'badge-danger' : '') ?> plain">
                                <?= esc(orbit_power_label($p['daya'])) ?></span></td>
                            <td><span class="badge badge-danger"><?= esc(orbit_connection_label($p['koneksi'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($r['perlu_verifikasi'] > 0 || $d['perlu_verifikasi'] > 0): ?>
    <div class="alert alert-info mt-3"><?= orbit_icon('info') ?>
        <span>Inventaris belum diverifikasi:
            <strong><?= (int) $r['perlu_verifikasi'] ?> ruangan</strong> dan
            <strong><?= (int) $d['perlu_verifikasi'] ?> perangkat</strong>
            (penempatan lantai masih dugaan). Tinjau melalui halaman Ruangan.</span>
    </div>
<?php endif; ?>

<?php $this->endSection() ?>
