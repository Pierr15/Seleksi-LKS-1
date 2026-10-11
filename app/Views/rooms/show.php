<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<?php
$selectedOverride = null;
foreach ($overrideDevices as $entry) {
    if ($selectedDevice !== null && (int) $entry['device']['id'] === (int) $selectedDevice['id']) {
        $selectedOverride = $entry['override'];
        break;
    }
}
$placedCount = 0;
foreach ($devices as $d) {
    if ($d['position_x'] !== null) {
        $placedCount++;
    }
}
$activeCount = 0;
foreach ($devices as $d) {
    if ((int) $d['is_active'] === 1 && $d['archived_at'] === null) {
        $activeCount++;
    }
}
$sensorLabels = ['temperature' => ['Suhu', '°C', 'thermo'],
    'humidity' => ['Kelembapan', '%', 'droplet'],
    'occupancy' => ['Okupansi', '', 'users'],
    'energy_estimate' => ['Estimasi daya', 'W', 'zap'],
    'light' => ['Cahaya', 'lux', 'sun']];
?>

<div class="page-head">
    <div>
        <h2><?= esc($room['name']) ?></h2>
        <p>
            <?= orbit_icon('map', 15) ?> <?= esc($room['floor_name'] ?? '—') ?>
            <?php if ($room['description']): ?> · <?= esc($room['description']) ?><?php endif; ?>
        </p>
    </div>
    <div class="actions">
        <?php if ($canControl): ?>
            <button class="btn btn-outline btn-sm" type="button" id="btn-occupancy">
                <?= orbit_icon('users', 15) ?>
                <?= $room['occupancy'] === 'occupied' ? 'Tandai kosong' : 'Tandai terisi' ?>
            </button>
        <?php endif; ?>
        <?php if ($isAdmin && $room['archived_at'] === null): ?>
            <button class="btn btn-outline btn-sm" type="button" data-modal-open="room-modal"
                    onclick="window.__roomFormReset && window.__roomFormReset()">
                <?= orbit_icon('edit', 15) ?> Ubah
            </button>
            <button class="btn btn-outline btn-sm" type="button" id="btn-archive">
                <?= orbit_icon('alert', 15) ?> Arsipkan
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($room['archived_at'] !== null): ?>
    <div class="alert alert-warn"><?= orbit_icon('alert') ?>
        <span>Ruangan diarsipkan sejak <?= esc(orbit_format_datetime($room['archived_at'])) ?>.</span>
    </div>
<?php endif; ?>

<?php if ($room['floor_verification_status'] === 'needs_verification'): ?>
    <div class="alert alert-info"><?= orbit_icon('info') ?>
        <span>Penempatan ruangan di lantai masih berstatus <strong>perlu verifikasi</strong>
            (dugaan awal, tidak ditebak ulang oleh sistem).</span>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat <?= $room['occupancy'] === 'occupied' ? 'accent-success' : 'accent-primary' ?>">
        <span class="stat-label"><?= orbit_icon('users', 16) ?> Okupansi</span>
        <span class="stat-value" style="font-size:20px"><?= esc(orbit_occupancy_label($room['occupancy'])) ?></span>
        <span class="stat-sub">
            <?php if ($room['occupancy'] === 'vacant' && $room['vacant_since'] !== null): ?>
                kosong <?= esc(orbit_duration_since($room['vacant_since'])) ?> (ambang <?= (int) $vacantMinutes ?> mnt)
            <?php else: ?>
                sejak <?= $room['vacant_since'] !== null ? esc(orbit_format_datetime($room['vacant_since'])) : '—' ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="stat accent-primary">
        <span class="stat-label"><?= orbit_icon('gear', 16) ?> Mode</span>
        <span class="stat-value" style="font-size:20px"><?= esc(orbit_room_mode_label($room['mode'])) ?></span>
        <span class="stat-sub">aturan otomatis mengikuti mode ruangan</span>
    </div>
    <div class="stat accent-primary">
        <span class="stat-label"><?= orbit_icon('cpu', 16) ?> Perangkat</span>
        <span class="stat-value"><?= $activeCount ?></span>
        <span class="stat-sub"><?= count($devices) ?> total · <?= $placedCount ?> di denah</span>
    </div>
    <div class="stat accent-warn">
        <span class="stat-label"><?= orbit_icon('list', 16) ?> Override aktif</span>
        <span class="stat-value"><?= count($overrideDevices) ?></span>
        <span class="stat-sub">perangkat dilindungi override</span>
    </div>
</div>

<?php if ($canControl): ?>
    <div class="card mb-3">
        <div class="card-header"><?= orbit_icon('gear', 18) ?> Mode Ruangan
            <span class="right muted small">berlaku untuk seluruh perangkat di ruangan ini</span>
        </div>
        <div class="card-body">
            <div class="control-row">
                <?php foreach (['active' => 'Aktif', 'standby' => 'Standby', 'inactive' => 'Nonaktif'] as $modeKey => $modeLabel): ?>
                    <button class="btn <?= $room['mode'] === $modeKey ? '' : 'btn-outline' ?>"
                            type="button" data-mode="<?= $modeKey ?>"
                            <?= $room['mode'] === $modeKey ? 'disabled' : '' ?>>
                        <?= esc($modeLabel) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif ?>

<div class="stats-grid mb-3">
    <?php if ($sensors === []): ?>
        <div class="stat">
            <span class="stat-label">Sensor</span>
            <span class="stat-value" style="font-size:16px">Belum ada data</span>
            <span class="stat-sub">scheduler menulis pembacaan tiap 30 detik</span>
        </div>
    <?php else: ?>
        <?php foreach ($sensors as $metric => $reading): ?>
            <div class="stat accent-primary">
                <span class="stat-label">
                    <?= orbit_icon($sensorLabels[$metric][2] ?? 'activity', 16) ?>
                    <?= esc($sensorLabels[$metric][0] ?? $metric) ?>
                </span>
                <span class="stat-value">
                    <?= number_format($reading['value'], $metric === 'energy_estimate' ? 0 : 1, ',', '.') ?>
                    <span style="font-size:14px"><?= esc($reading['unit']) ?></span>
                </span>
                <span class="stat-sub"><?= esc(orbit_format_datetime($reading['recorded_at'])) ?> · <?= esc($reading['source']) ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header"><?= orbit_icon('cpu', 18) ?> Perangkat di Ruangan Ini
            <span class="right muted small"><?= count($devices) ?> unit</span>
        </div>
        <div class="card-body tight">
            <?php if ($devices === []): ?>
                <div class="empty" style="padding:22px"><?= orbit_icon('cpu', 30) ?><div>Belum ada perangkat.</div></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr><th>Perangkat</th><th>Daya</th><th>Koneksi</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($devices as $device): ?>
                            <?php $isSelected = $selectedDevice !== null && (int) $device['id'] === (int) $selectedDevice['id']; ?>
                            <tr<?= $isSelected ? ' class="active"' : '' ?>>
                                <td>
                                    <strong><?= esc($device['name']) ?></strong>
                                    <div class="muted small"><?= esc(orbit_device_type_label($device['device_type'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $device['power_state'] === 'on' ? 'badge-success' : ($device['power_state'] === 'off' ? 'badge-danger' : ($device['power_state'] === 'sleep' ? 'badge-warn' : 'badge-muted')) ?> plain">
                                        <?= esc(orbit_power_label((string) $device['power_state'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($device['connection_state'] === 'offline'): ?>
                                        <span class="badge badge-danger">Offline</span>
                                    <?php elseif ($device['connection_state'] === 'online'): ?>
                                        <span class="badge badge-success">Online</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="nowrap">
                                    <a class="btn btn-outline btn-sm"
                                       href="<?= site_url('ruangan/' . $room['id'] . '?perangkat=' . $device['id']) ?>">
                                        <?= $isSelected ? 'Terpilih' : 'Pilih' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><?= orbit_icon('power', 18) ?> Panel Kendali
            <span class="right">
                <?php if ($selectedDevice !== null): ?>
                    <a class="btn btn-outline btn-sm" href="<?= site_url('perangkat/' . $selectedDevice['id']) ?>">Detail lengkap</a>
                <?php endif; ?>
            </span>
        </div>
        <div class="card-body">
            <?php if ($selectedDevice === null): ?>
                <div class="empty" style="padding:26px"><?= orbit_icon('power', 30) ?>
                    <div>Pilih perangkat dari daftar untuk mengontrolnya.</div>
                </div>
            <?php else: ?>
                <div class="kv">
                    <span>Perangkat</span>
                    <strong><?= esc($selectedDevice['name']) ?></strong>
                    <span>Status</span>
                    <strong>
                        <?= esc(orbit_power_label((string) $selectedDevice['power_state'])) ?>
                        · <?= esc(orbit_connection_label((string) $selectedDevice['connection_state'])) ?>
                    </strong>
                    <span>Kemampuan</span>
                    <strong><?= $selectedDevice['control_capability'] === 'controllable' ? 'Dapat dikontrol' : 'Hanya pantau' ?></strong>
                    <span>Terakhir terlihat</span>
                    <strong><?= $selectedDevice['last_seen_at'] !== null ? esc(orbit_format_datetime($selectedDevice['last_seen_at'])) : '—' ?></strong>
                </div>

                <?php if ($selectedOverride !== null): ?>
                    <div class="alert alert-danger mt-3 mb-0"><?= orbit_icon('lock') ?>
                        <span>Override aktif — perubahan otomatis diblokir, kendali manual tetap diizinkan
                            (<?= esc($selectedOverride['override_type']) ?> · <?= esc($selectedOverride['reason']) ?>).</span>
                    </div>
                <?php endif; ?>
                <?php if ($selectedDevice['control_capability'] !== 'controllable'): ?>
                    <div class="hint mt-3">Perangkat ini hanya dapat dipantau.</div>
                <?php elseif (! $canControl): ?>
                    <div class="hint mt-3">Peran Anda tidak memiliki izin mengontrol perangkat.</div>
                <?php elseif ($room['archived_at'] !== null): ?>
                    <div class="hint mt-3">Ruangan diarsipkan — perintah dinonaktifkan.</div>
                <?php else: ?>
                    <?php
                    $selAc       = in_array($selectedDevice['device_type'], ['ac', 'ac_central'], true);
                    $selComputer = $selectedDevice['device_type'] === 'computer';
                    $selAcTemp   = $selectedDevice['target_temperature'] !== null ? (int) $selectedDevice['target_temperature'] : 24;
                    $selAcMode   = $selectedDevice['ac_mode'] ?? 'cool';
                    $selAcBoost  = (int) ($selectedDevice['ac_boost'] ?? 0) === 1;
                    ?>
                    <div class="control-row mt-3">
                        <button class="btn btn-soft" type="button" data-cmd="on"><?= orbit_icon('power', 16) ?> Nyalakan</button>
                        <button class="btn btn-outline" type="button" data-cmd="off"><?= orbit_icon('x', 16) ?> Matikan</button>
                        <?php if ($selComputer): ?>
                            <button class="btn btn-outline" type="button" data-cmd="sleep"><?= orbit_icon('moon', 16) ?> Sleep</button>
                            <button class="btn btn-outline" type="button" data-cmd="shutdown"><?= orbit_icon('x', 16) ?> Shutdown</button>
                        <?php endif ?>
                    </div>

                    <?php if ($selAc): ?>
                    <div class="ac-panel mt-3" id="room-ac-panel"
                         data-device-id="<?= (int) $selectedDevice['id'] ?>"
                         data-temp="<?= (int) $selAcTemp ?>"
                         data-ac-mode-val="<?= esc($selAcMode) ?>"
                         data-boost="<?= $selAcBoost ? '1' : '0' ?>">
                        <div class="ac-panel-title">Panel Kendali AC</div>
                        <div class="temp-control">
                            <span class="temp-label">Suhu target</span>
                            <div class="temp-stepper">
                                <button type="button" class="temp-btn" id="room-temp-minus">&minus;</button>
                                <span class="temp-value" id="room-temp-value"><?= $selAcTemp ?></span>
                                <span class="temp-unit">°C</span>
                                <button type="button" class="temp-btn" id="room-temp-plus">+</button>
                            </div>
                        </div>
                        <div class="ac-mode-row">
                            <span class="temp-label">Mode</span>
                            <div class="seg" id="room-ac-mode-seg">
                                <button type="button" data-ac-mode="cool" class="<?= $selAcMode !== 'dry' ? 'active' : '' ?>">Cool</button>
                                <button type="button" data-ac-mode="dry" class="<?= $selAcMode === 'dry' ? 'active' : '' ?>">Dry</button>
                            </div>
                        </div>
                        <div class="ac-boost-row">
                            <button type="button" class="btn btn-sm <?= $selAcBoost ? 'btn-soft' : 'btn-outline' ?>" id="room-boost-toggle">
                                <?= orbit_icon('zap', 15) ?> Powerful
                                <span class="boost-state" id="room-boost-state"><?= $selAcBoost ? 'ON' : 'OFF' ?></span>
                            </button>
                        </div>
                    </div>
                    <?php endif ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header"><?= orbit_icon('lock', 18) ?> Override Aktif</div>
        <div class="card-body">
            <?php if ($overrideDevices === []): ?>
                <div class="empty" style="padding:20px"><?= orbit_icon('lock', 28) ?><div>Tidak ada override aktif.</div></div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($overrideDevices as $entry): ?>
                        <div class="item">
                            <div>
                                <strong><?= esc($entry['device']['name']) ?></strong>
                                <span class="badge badge-danger plain" style="margin-left:6px">
                                    <?= $entry['override']['override_type'] === 'persistent' ? 'Permanen' : 'Terbatas waktu' ?>
                                </span>
                                <div class="muted small">
                                    <?= esc($entry['override']['reason']) ?>
                                    <?php if ($entry['override']['expires_at'] !== null): ?>
                                        · sampai <?= esc(orbit_format_datetime($entry['override']['expires_at'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($isAdmin): ?>
                                <span class="when right">
                                    <button class="btn btn-outline btn-sm" type="button"
                                            data-end-override="<?= (int) $entry['override']['id'] ?>">Akhiri</button>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><?= orbit_icon('bolt', 18) ?> Aturan Otomatisasi Berlaku</div>
        <div class="card-body">
            <?php if ($applicableRules === []): ?>
                <div class="empty" style="padding:20px"><?= orbit_icon('bolt', 28) ?><div>Tidak ada aturan aktif untuk ruangan ini.</div></div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($applicableRules as $rule): ?>
                        <div class="item">
                            <div>
                                <a href="<?= site_url('otomatisasi/' . $rule['id']) ?>">
                                    <strong><?= esc($rule['name']) ?></strong>
                                </a>
                                <div class="muted small">
                                    <?= $rule['trigger_type'] === 'vacant_duration'
                                        ? 'Kosong ' . (int) $rule['duration_seconds'] . ' detik'
                                        : 'Pemicu manual' ?>
                                    · terakhir dieksekusi
                                    <?= $rule['last_executed_at'] !== null ? esc(orbit_format_datetime($rule['last_executed_at'])) : 'belum pernah' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header"><?= orbit_icon('clock', 18) ?> Riwayat Perintah
            <span class="right muted small"><?= count($commands) ?> terakhir</span>
        </div>
        <div class="card-body">
            <?php if ($commands === []): ?>
                <div class="empty" style="padding:20px"><?= orbit_icon('clock', 28) ?><div>Belum ada perintah.</div></div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($commands as $cmd): ?>
                        <div class="item">
                            <div>
                                <strong><?= esc($cmd['device_name'] ?? '—') ?></strong>
                                <span class="badge <?= $cmd['command'] === 'on' ? 'badge-success' : 'badge-danger' ?> plain" style="margin-left:6px">
                                    <?= esc(strtoupper($cmd['command'])) ?>
                                </span>
                                <span class="badge <?= ['success' => 'badge-success', 'failed' => 'badge-danger'][$cmd['status']] ?? 'badge-muted' ?> plain" style="margin-left:4px">
                                    <?= esc($cmd['status']) ?>
                                </span>
                                <div class="muted small"><?= esc($cmd['source']) ?> · <?= esc($cmd['requester_name'] ?? 'sistem') ?></div>
                            </div>
                            <span class="when right"><?= esc(orbit_format_datetime($cmd['created_at'], 'd M, H:i:s')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><?= orbit_icon('list', 18) ?> Aktivitas Ruangan
            <span class="right">
                <a class="btn btn-outline btn-sm" href="<?= site_url('log?ruangan=' . $room['id']) ?>">Semua log</a>
            </span>
        </div>
        <div class="card-body">
            <?php if ($activityLogs === []): ?>
                <div class="empty" style="padding:20px"><?= orbit_icon('list', 28) ?><div>Belum ada aktivitas.</div></div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($activityLogs as $log): ?>
                        <div class="item">
                            <div>
                                <strong><?= esc($log['action']) ?></strong>
                                <span class="badge <?= $log['result'] === 'success' ? 'badge-success' : 'badge-danger' ?> plain" style="margin-left:6px">
                                    <?= esc($log['result']) ?>
                                </span>
                                <div class="muted small"><?= esc($log['user_name'] ?? 'sistem') ?> · <?= esc($log['source']) ?></div>
                            </div>
                            <span class="when right"><?= esc(orbit_format_datetime($log['created_at'], 'd M, H:i:s')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
    <div class="modal-root" id="room-modal">
        <div class="modal-card">
            <div class="card-header">Ubah Ruangan</div>
            <div class="card-body">
                <form id="room-form">
                    <div class="field">
                        <label class="label" for="room-name">Nama <span class="req">*</span></label>
                        <input class="input" type="text" id="room-name" name="name" maxlength="100" required
                               value="<?= esc($room['name']) ?>">
                        <div class="error-text"></div>
                    </div>
                    <div class="field">
                        <label class="label" for="room-desc">Deskripsi</label>
                        <textarea class="textarea" id="room-desc" name="description" maxlength="500"><?= esc($room['description']) ?></textarea>
                    </div>
                    <div class="field">
                        <label class="label" for="room-floor">Lantai <span class="req">*</span></label>
                        <select class="select" id="room-floor" name="floor_id" required>
                            <?php foreach ((new \App\Models\FloorModel())->orderBy('sort_order')->findAll() as $floor): ?>
                                <option value="<?= (int) $floor['id'] ?>" <?= (int) $floor['id'] === (int) $room['floor_id'] ? 'selected' : '' ?>>
                                    <?= esc($floor['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hint">Memindahkan ruangan antar-lantai tidak mengubah posisi denah lama.</div>
                    </div>
                    <div class="field">
                        <label class="label" for="room-verif">Status inventaris</label>
                        <select class="select" id="room-verif" name="inventory_status">
                            <option value="needs_verification" <?= $room['inventory_status'] === 'needs_verification' ? 'selected' : '' ?>>Perlu verifikasi</option>
                            <option value="verified" <?= $room['inventory_status'] === 'verified' ? 'selected' : '' ?>>Terverifikasi</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" type="button" data-modal-close="room-modal">Batal</button>
                <button class="btn" type="submit" form="room-form">Simpan</button>
            </div>
        </div>
    </div>
<?php endif ?>

<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
(function () {
  var O = window.orbit;
  var roomId = <?= (int) $room['id'] ?>;
  var selectedId = <?= $selectedDevice !== null ? (int) $selectedDevice['id'] : 'null' ?>;
  <?php
  $cmdAllowed = $canControl
      && $selectedDevice !== null
      && $selectedDevice['control_capability'] === 'controllable'
      && $room['archived_at'] === null;
  ?>
  var canCommand = <?= json_encode($cmdAllowed) ?>;

  var CMD_LABELS = { on: 'dinyalakan', off: 'dimatikan', sleep: 'dimasukkan mode sleep', shutdown: 'shutdown (matikan total)' };

  document.querySelectorAll('[data-cmd]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!canCommand || selectedId === null) { return; }
      var cmd = btn.getAttribute('data-cmd');
      O.confirm({
        title: 'Kirim perintah?',
        message: 'Perangkat akan ' + (CMD_LABELS[cmd] || cmd) + '.',
        yesLabel: 'Kirim',
        danger: cmd === 'off' || cmd === 'shutdown'
      }).then(function (ok) {
        if (!ok) { return; }
        btn.disabled = true;
        O.fetch('/api/devices/' + selectedId + '/command', { method: 'POST', body: { command: cmd } })
          .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.reload(); }, 600); })
          .catch(function (err) { O.toast(err.message, 'error'); btn.disabled = false; });
      });
    });
  });

  /* Panel AC di ruangan */
  var roomAcPanel = document.getElementById('room-ac-panel');
  if (roomAcPanel) {
    var acDeviceId = roomAcPanel.getAttribute('data-device-id');
    var rTemp = parseInt(roomAcPanel.getAttribute('data-temp'), 10) || 24;
    var rMode = roomAcPanel.getAttribute('data-ac-mode-val') || 'cool';
    var rBoost = roomAcPanel.getAttribute('data-boost') === '1';
    var rTempValue = document.getElementById('room-temp-value');
    var rBoostBtn = document.getElementById('room-boost-toggle');
    var rBoostState = document.getElementById('room-boost-state');
    var rModeSeg = document.getElementById('room-ac-mode-seg');

    function postRoomAc(body) {
      O.fetch('/api/devices/' + acDeviceId + '/command', { method: 'POST', body: body })
        .then(function (res) { O.toast(res.message, 'success'); })
        .catch(function (err) { O.toast(err.message, 'error'); });
    }

    var rMinus = document.getElementById('room-temp-minus');
    var rPlus = document.getElementById('room-temp-plus');
    if (rMinus) {
      rMinus.addEventListener('click', function () {
        if (rTemp <= 16) { return; }
        rTemp--; rTempValue.textContent = rTemp;
        postRoomAc({ command: 'set_temperature', temperature: rTemp });
      });
    }
    if (rPlus) {
      rPlus.addEventListener('click', function () {
        if (rTemp >= 30) { return; }
        rTemp++; rTempValue.textContent = rTemp;
        postRoomAc({ command: 'set_temperature', temperature: rTemp });
      });
    }
    if (rModeSeg) {
      rModeSeg.querySelectorAll('[data-ac-mode]').forEach(function (b) {
        b.addEventListener('click', function () {
          var m = b.getAttribute('data-ac-mode');
          if (m === rMode) { return; }
          rModeSeg.querySelectorAll('[data-ac-mode]').forEach(function (x) { x.classList.remove('active'); });
          b.classList.add('active');
          rMode = m;
          postRoomAc({ command: 'set_mode', mode: m });
        });
      });
    }
    if (rBoostBtn) {
      rBoostBtn.addEventListener('click', function () {
        rBoost = !rBoost;
        rBoostState.textContent = rBoost ? 'ON' : 'OFF';
        rBoostBtn.classList.toggle('btn-soft', rBoost);
        rBoostBtn.classList.toggle('btn-outline', !rBoost);
        postRoomAc({ command: 'set_boost', boost: rBoost });
      });
    }
  }

  document.querySelectorAll('[data-mode]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var mode = btn.getAttribute('data-mode');
      btn.disabled = true;
      O.fetch('/api/rooms/' + roomId + '/mode', { method: 'POST', body: { mode: mode } })
        .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.reload(); }, 600); })
        .catch(function (err) { O.toast(err.message, 'error'); btn.disabled = false; });
    });
  });

  var occBtn = document.getElementById('btn-occupancy');
  if (occBtn) {
    occBtn.addEventListener('click', function () {
      var next = <?= json_encode($room['occupancy']) ?> === 'occupied' ? 'vacant' : 'occupied';
      occBtn.disabled = true;
      O.fetch('/api/rooms/' + roomId + '/occupancy', { method: 'POST', body: { occupancy: next } })
        .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.reload(); }, 600); })
        .catch(function (err) { O.toast(err.message, 'error'); occBtn.disabled = false; });
    });
  }

  document.querySelectorAll('[data-end-override]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      O.confirm({ title: 'Akhiri override?', message: 'Perlindungan override akan dihapus.', yesLabel: 'Akhiri' })
        .then(function (ok) {
          if (!ok) { return; }
          O.fetch('/api/overrides/' + btn.getAttribute('data-end-override') + '/end', { method: 'POST', body: {} })
            .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.reload(); }, 600); })
            .catch(function (err) { O.toast(err.message, 'error'); });
        });
    });
  });

  <?php if ($isAdmin): ?>
  var form = document.getElementById('room-form');
  window.__roomFormReset = function () { O.clearFieldErrors(form); };

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    O.clearFieldErrors(form);
    O.fetch('/api/rooms/' + roomId, {
      method: 'PUT',
      body: {
        name: form.elements.name.value.trim(),
        description: form.description.value.trim(),
        floor_id: parseInt(form.floor_id.value, 10),
        inventory_status: form.inventory_status.value
      }
    }).then(function (res) {
      O.toast(res.message, 'success');
      O.closeModal('room-modal');
      setTimeout(function () { window.location.reload(); }, 500);
    }).catch(function (err) {
      if (err.errors) { O.showFieldErrors(form, err.errors); }
      O.toast(err.message, 'error');
    });
  });

  var archiveBtn = document.getElementById('btn-archive');
  if (archiveBtn) {
    archiveBtn.addEventListener('click', function () {
      O.confirm({
        title: 'Arsipkan ruangan?',
        message: 'Ruangan disembunyikan dari daftar aktif beserta kendalinya.',
        yesLabel: 'Arsipkan'
      }).then(function (ok) {
        if (!ok) { return; }
        O.fetch('/api/rooms/' + roomId + '/archive', { method: 'POST', body: { archive: true } })
          .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.href = '/ruangan'; }, 600); })
          .catch(function (err) { O.toast(err.message, 'error'); });
      });
    });
  }
  <?php endif ?>
})();
</script>
<?php $this->endSection() ?>
