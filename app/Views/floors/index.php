<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<div class="page-head">
    <div>
        <h2>Denah Gedung</h2>
        <p>Posisi ruangan & perangkat dibaca langsung dari basis data (update tanpa muat ulang).</p>
    </div>
    <div class="actions">
        <a class="btn btn-outline btn-sm" href="<?= site_url('ruangan') ?>"><?= orbit_icon('door', 16) ?> Daftar Ruangan</a>
        <?php if ($isAdmin): ?>
            <button class="btn btn-sm" type="button" id="btn-edit-mode">
                <?= orbit_icon('drag', 16) ?> Mode Edit Posisi
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($needsVerification > 0): ?>
    <div class="alert alert-warn"><?= orbit_icon('alert') ?>
        <span><strong><?= (int) $needsVerification ?> ruangan</strong> berstatus <em>perlu verifikasi</em>
            (lantai dugaan awal, belum dikonfirmasi). Semua ruangan sementara ditempatkan di Lantai 1 sesuai
            data awal — verifikasi melalui halaman Ruangan.</span>
    </div>
<?php endif; ?>

<div class="editor-bar" id="editor-bar">
    <strong><?= orbit_icon('drag', 16) ?> Mode Edit Posisi</strong>
    <span class="muted">Seret perangkat antar ruangan, atau ketuk untuk mengisi koordinat manual.</span>
    <span class="spacer"></span>
    <span id="dirty-status" class="badge badge-warn plain">0 perubahan belum disimpan</span>
    <button class="btn btn-outline btn-sm" type="button" id="btn-revert"><?= orbit_icon('undo', 15) ?> Batalkan</button>
    <button class="btn btn-sm" type="button" id="btn-save" disabled><?= orbit_icon('save', 15) ?> Simpan</button>
    <button class="btn btn-ghost btn-sm" type="button" id="btn-exit-edit">Selesai</button>
</div>

<div class="denah-toolbar">
    <div class="floor-tabs" id="floor-tabs">
        <?php foreach ($floors as $i => $floor): ?>
            <button class="floor-tab <?= (int) $floor['id'] === (int) $initialFloor ? 'active' : '' ?>"
                    type="button" data-floor="<?= (int) $floor['id'] ?>">
                <?= esc($floor['name']) ?>
            </button>
        <?php endforeach; ?>
    </div>
    <span class="spacer right muted small" id="denah-loading">Memuat denah…</span>
</div>

<div class="denah-layout">
    <div>
        <div class="denah" id="denah" aria-label="Denah lantai"></div>
        <div class="legend">
            <span><span class="dot" style="border-color:var(--primary);background:var(--primary-soft)"></span>Mode aktif</span>
            <span><span class="dot" style="border-color:var(--info);background:var(--info-soft)"></span>Vacant</span>
            <span><span class="dot" style="border-color:var(--warn);background:var(--warn-soft)"></span>Standby</span>
            <span><span class="dot" style="border-color:var(--muted);background:var(--surface-2)"></span>Inaktif</span>
            <span><span class="dot" style="border-color:var(--success);background:var(--success-soft)"></span>Perangkat menyala</span>
            <span><span class="dot" style="border-color:var(--danger);background:var(--danger-soft)"></span>Perangkat mati</span>
        </div>
    </div>

    <aside class="card">
        <div class="card-header">
            Perangkat di lantai ini
            <span class="right muted small" id="device-counts">—</span>
        </div>
        <div class="card-body">
            <div class="side-list" id="device-list">
                <div class="empty">Memuat…</div>
            </div>

            <div id="device-detail" class="mt-3" hidden>
                <hr style="border:0;border-top:1px solid var(--border);margin:14px 0">
                <div id="device-detail-body"></div>
            </div>

            <form id="position-form" class="mt-3" hidden>
                <hr style="border:0;border-top:1px solid var(--border);margin:14px 0">
                <div class="label" style="margin-bottom:8px">Atur posisi manual (%)</div>

                <div class="field">
                    <label class="label" for="pos-room">Ruangan</label>
                    <select class="select" id="pos-room" name="room_id"></select>
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label class="label" for="pos-x">posisi_x (%)</label>
                        <input class="input" type="number" id="pos-x" name="position_x"
                               min="0" max="100" step="0.01" required>
                        <div class="hint">Relatif lebar ruangan</div>
                        <div class="error-text"></div>
                    </div>
                    <div class="field">
                        <label class="label" for="pos-y">posisi_y (%)</label>
                        <input class="input" type="number" id="pos-y" name="position_y"
                               min="0" max="100" step="0.01" required>
                        <div class="hint">Relatif tinggi ruangan</div>
                        <div class="error-text"></div>
                    </div>
                </div>

                <div class="flex gap-2 mt-2">
                    <button class="btn btn-sm" type="submit"><?= orbit_icon('check', 15) ?> Terapkan</button>
                    <button class="btn btn-outline btn-sm" type="button" id="btn-unplace">Kosongkan posisi</button>
                </div>
                <p class="hint mt-2 mb-0">Terapkan menambahkan perubahan ke antrean; tekan <strong>Simpan</strong> untuk
                    menulis ke server.</p>
            </form>
        </div>
    </aside>
</div>

<?php $this->endSection() ?>

<?php $this->setVar('initialFloor', $initialFloor) ?>
<?php $this->section('scripts') ?>
<script>
    window.ORBIT_DENAH = {
        initialFloor: <?= (int) $initialFloor ?>,
        isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
        canControl: <?= ! empty($canControl) ? 'true' : 'false' ?>
    };
</script>
<script src="<?= base_url('assets/js/denah.js') ?>"></script>
<?php $this->endSection() ?>
