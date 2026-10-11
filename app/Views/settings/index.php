<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<div class="page-head">
    <div>
        <h2>Pengaturan</h2>
        <p>Konfigurasi sistem yang memengaruhi perilaku otomatisasi dan tampilan</p>
    </div>
</div>

<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header"><?= orbit_icon('gear', 18) ?> Otomatisasi &amp; Umum</div>
        <div class="card-body">
            <form id="settings-form">
                <div class="field">
                    <label class="label" for="vacant_duration_minutes">Durasi ruangan kosong (menit)</label>
                    <input class="input" type="number" id="vacant_duration_minutes" name="vacant_duration_minutes"
                           min="1" max="1440" step="1"
                           value="<?= esc((string) ($settings['vacant_duration_minutes'] ?? 5)) ?>">
                    <div class="hint">
                        Ruangan dianggap "kosong lama" setelah durasi ini tanpa okupansi;
                        aturan pemicu <em>vacant_duration</em> baru dijalankan scheduler.
                        Saat ini <strong><?= esc((string) ($settings['vacant_duration_minutes'] ?? 5)) ?> menit</strong>.
                    </div>
                    <div class="error-text"></div>
                </div>

                <div class="field">
                    <label class="label">Zona waktu tampilan</label>
                    <input class="input" type="text" value="<?= esc((string) ($settings['timezone_display'] ?? 'Asia/Jakarta')) ?>" disabled>
                    <div class="hint">Penyimpanan basis data selalu UTC; konversi hanya saat ditampilkan.</div>
                </div>

                <div class="control-row mt-0">
                    <button class="btn" type="submit" id="settings-submit"><?= orbit_icon('save', 16) ?> Simpan Pengaturan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><?= orbit_icon('activity', 18) ?> Status Scheduler</div>
        <div class="card-body">
            <?php if ($scheduler['fresh']): ?>
                <div class="alert alert-success mb-3"><?= orbit_icon('check') ?>
                    <span>Scheduler aktif — terakhir berjalan <strong><?= esc(orbit_format_datetime($scheduler['last_run'])) ?></strong>.</span>
                </div>
            <?php else: ?>
                <div class="alert alert-warn mb-3"><?= orbit_icon('alert') ?>
                    <span>Scheduler belum berjalan baru-baru ini (status: <?= esc($scheduler['status']) ?>).
                        Pastikan kontainer <span class="mono">orbit-scheduler</span> berjalan.</span>
                </div>
            <?php endif; ?>

            <div class="kv">
                <span>Status</span>
                <strong><?= esc($scheduler['status']) ?></strong>
                <span>Terakhir berjalan (UTC)</span>
                <strong><?= $scheduler['last_run'] !== null ? esc($scheduler['last_run']) : '—' ?></strong>
                <span>Terakhir ditampilkan (WIB)</span>
                <strong><?= $scheduler['last_run'] !== null ? esc(orbit_format_datetime($scheduler['last_run'])) : '—' ?></strong>
                <span>Interval</span>
                <strong>30 detik</strong>
            </div>

            <div class="hint mt-3">
                Perintah manual:
                <code class="mono">docker compose exec app php spark orbit:scheduler:tick</code>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= orbit_icon('lock', 18) ?> Akun Admin Bawaan</div>
    <div class="card-body">
        <div class="kv">
            <span>Nama</span>
            <strong><?= esc($envAdmin['name'] !== '' ? $envAdmin['name'] : '—') ?></strong>
            <span>Email</span>
            <strong><?= esc($envAdmin['email'] !== '' ? $envAdmin['email'] : '—') ?></strong>
        </div>
        <div class="hint">
            Akun dibuat oleh seeder dari variabel lingkungan
            <span class="mono">SEED_ADMIN_NAME</span>, <span class="mono">SEED_ADMIN_EMAIL</span>,
            dan <span class="mono">SEED_ADMIN_PASSWORD</span>. Ubah sandi melalui halaman Pengguna.
        </div>
    </div>
</div>

<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
(function () {
  var O = window.orbit;
  var form = document.getElementById('settings-form');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    O.clearFieldErrors(form);

    var minutes = parseInt(form.elements.vacant_duration_minutes.value, 10);
    if (isNaN(minutes) || minutes < 1 || minutes > 1440) {
      O.showFieldErrors(form, { vacant_duration_minutes: 'Durasi harus antara 1 dan 1440 menit.' });
      return;
    }

    O.fetch('/api/settings', { method: 'PUT', body: { vacant_duration_minutes: minutes } })
      .then(function (res) {
        O.toast(res.message, 'success');
        setTimeout(function () { window.location.reload(); }, 600);
      })
      .catch(function (err) {
        if (err.errors) { O.showFieldErrors(form, err.errors); }
        O.toast(err.message, 'error');
      });
  });
})();
</script>
<?php $this->endSection() ?>
