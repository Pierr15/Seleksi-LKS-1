<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<?php
$actions   = json_decode($rule['actions_json'] ?? '[]', true) ?: [];
$target    = json_decode($rule['target_json'] ?? 'null', true);
$condition = json_decode($rule['condition_json'] ?? 'null', true);
$exceptions = json_decode($rule['exceptions_json'] ?? 'null', true);
$roomIds   = is_array($target) ? ($target['room_ids'] ?? []) : [];
$roomNames = [];
if ($roomIds !== []) {
    $rooms = (new \App\Models\RoomModel())->whereIn('id', $roomIds)->findAll();
    foreach ($rooms as $r) {
        $roomNames[] = $r['name'];
    }
}
$actionLabels = [];
foreach ($actions as $action) {
    if (($action['type'] ?? '') === 'set_room_mode') {
        $actionLabels[] = 'Ubah mode ruangan ke ' . orbit_room_mode_label($action['mode'] ?? 'active');
    } elseif (($action['type'] ?? '') === 'turn_off') {
        $actionLabels[] = 'Matikan perangkat terpilih';
    } else {
        $actionLabels[] = (string) ($action['type'] ?? '-');
    }
}
?>

<div class="page-head">
    <div>
        <h2><?= esc($rule['name']) ?></h2>
        <p>
            <span class="badge <?= (int) $rule['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>">
                <?= (int) $rule['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
            </span>
            <?= $rule['trigger_type'] === 'vacant_duration'
                ? '· pemicu ruangan kosong ' . (int) $rule['duration_seconds'] . ' detik'
                : '· pemicu manual' ?>
            · pembuat <?= esc($rule['creator_name'] ?? 'sistem') ?>
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-outline btn-sm" href="<?= site_url('otomatisasi') ?>"><?= orbit_icon('list', 15) ?> Daftar aturan</a>
        <?php if ($isAdmin && (int) $rule['is_active'] === 1): ?>
            <button class="btn btn-outline btn-sm" type="button" id="btn-disable"><?= orbit_icon('x', 15) ?> Nonaktifkan</button>
        <?php elseif ($isAdmin): ?>
            <button class="btn btn-sm" type="button" id="btn-enable"><?= orbit_icon('check', 15) ?> Aktifkan</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($rule['description']): ?>
    <div class="alert alert-info"><?= orbit_icon('info') ?><span><?= esc($rule['description']) ?></span></div>
<?php endif; ?>

<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header"><?= orbit_icon('bolt', 18) ?> Konfigurasi</div>
        <div class="card-body">
            <div class="kv">
                <span>Pemicu</span>
                <strong><?= $rule['trigger_type'] === 'vacant_duration' ? 'Ruangan kosong' : 'Manual' ?></strong>
                <span>Durasi</span>
                <strong><?= $rule['trigger_type'] === 'vacant_duration' ? (int) $rule['duration_seconds'] . ' detik' : '—' ?></strong>
                <span>Tindakan</span>
                <strong><?= esc(implode('; ', $actionLabels) ?: '—') ?></strong>
                <span>Ruangan tujuan</span>
                <strong><?= $roomIds === [] ? 'Seluruh ruangan' : esc(implode(', ', $roomNames) ?: count($roomIds) . ' ruangan') ?></strong>
                <span>Kondisi</span>
                <strong><?= $condition !== null ? '<code class="mono small">' . esc(json_encode($condition)) . '</code>' : '—' ?></strong>
                <span>Pengecualian</span>
                <strong><?= $exceptions !== null ? '<code class="mono small">' . esc(json_encode($exceptions)) . '</code>' : '—' ?></strong>
                <span>Eksekusi terakhir</span>
                <strong><?= $rule['last_executed_at'] !== null ? esc(orbit_format_datetime($rule['last_executed_at'])) : 'belum pernah' ?></strong>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><?= orbit_icon('clock', 18) ?> Riwayat Eksekusi
            <span class="right muted small">30 terakhir</span>
        </div>
        <div class="card-body">
            <?php if ($executions === []): ?>
                <div class="empty" style="padding:22px"><?= orbit_icon('clock', 30) ?><div>Belum pernah dieksekusi.</div></div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($executions as $ex): ?>
                        <div class="item">
                            <div>
                                <strong><?= esc(strtoupper($ex['status'])) ?></strong>
                                <div class="muted small"><?= esc($ex['reason'] ?? '—') ?></div>
                            </div>
                            <span class="when right"><?= esc(orbit_format_datetime($ex['executed_at'] ?? $ex['created_at'], 'd M Y, H:i:s')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<?php if ($isAdmin): ?>
<script>
(function () {
  var O = window.orbit;
  var ruleId = <?= (int) $rule['id'] ?>;
  var next = <?= (int) $rule['is_active'] === 1 ? 0 : 1 ?>;
  var label = <?= (int) $rule['is_active'] === 1 ? '"Nonaktifkan aturan ini?"' : '"Aktifkan aturan ini?"' ?>;
  ['btn-enable', 'btn-disable'].forEach(function (id) {
    var btn = document.getElementById(id);
    if (!btn) { return; }
    btn.addEventListener('click', function () {
      O.confirm({ title: 'Ubah status aturan?', message: label, yesLabel: 'Lanjutkan', danger: next === 0 })
        .then(function (ok) {
          if (!ok) { return; }
          O.fetch('/api/automation/rules/' + ruleId, { method: 'PUT', body: { is_active: next } })
            .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.reload(); }, 500); })
            .catch(function (err) { O.toast(err.message, 'error'); });
        });
    });
  });
})();
</script>
<?php endif ?>
<?php $this->endSection() ?>
