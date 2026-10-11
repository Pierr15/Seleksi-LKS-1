<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<?php
$powerBadge = ['on' => 'badge-success', 'off' => 'badge-danger', 'sleep' => 'badge-warn', 'unknown' => 'badge-muted'][$device['power_state']] ?? 'badge-muted';
$canCommand = $canControl
    && $device['control_capability'] === 'controllable'
    && (int) $device['is_active'] === 1
    && $device['archived_at'] === null;
$isAc       = in_array($device['device_type'], ['ac', 'ac_central'], true);
$isComputer = $device['device_type'] === 'computer';
$acTemp     = $device['target_temperature'] !== null ? (float) $device['target_temperature'] : 24.0;
$acMode     = $device['ac_mode'] ?? 'cool';
$acBoost    = (int) ($device['ac_boost'] ?? 0) === 1;
?>

<div class="page-head">
    <div>
        <h2><?= esc($device['name']) ?></h2>
        <p>
            <?= orbit_device_icon($device['device_type']) ?> <?= esc(orbit_device_type_label($device['device_type'])) ?>
            · <a href="<?= site_url('ruangan/' . $device['room_id']) ?>"><?= esc($device['room_name'] ?? '—') ?></a>
            · <?= esc($device['floor_name'] ?? '—') ?>
        </p>
    </div>
    <div class="actions">
        <?php if ($isAdmin && $device['archived_at'] === null): ?>
            <button class="btn btn-outline btn-sm" type="button" data-modal-open="edit-device-modal"
                    onclick="window.__devEditReset && window.__devEditReset()">
                <?= orbit_icon('edit', 15) ?> Ubah
            </button>
            <button class="btn btn-outline btn-sm" type="button" id="btn-archive">
                <?= orbit_icon('alert', 15) ?> Arsipkan
            </button>
        <?php endif; ?>
        <?php if ($override === null && $isAdmin && $device['archived_at'] === null): ?>
            <button class="btn btn-sm" type="button" data-modal-open="override-modal">
                <?= orbit_icon('lock', 15) ?> Buat Override
            </button>
        <?php endif; ?>
        <?php if ($override !== null && $isAdmin): ?>
            <button class="btn btn-sm btn-danger" type="button" id="btn-end-override">
                <?= orbit_icon('x', 15) ?> Akhiri Override
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($device['archived_at'] !== null): ?>
    <div class="alert alert-warn"><?= orbit_icon('alert') ?>
        <span>Perangkat ini sudah diarsipkan <strong><?= esc(orbit_format_datetime($device['archived_at'])) ?></strong>.</span>
    </div>
<?php endif; ?>

<?php if ($override !== null): ?>
    <div class="alert alert-danger"><?= orbit_icon('lock') ?>
        <span>Override <strong><?= esc($override['override_type'] === 'persistent' ? 'permanen' : 'terbatas waktu') ?></strong>
            aktif sejak <?= esc(orbit_format_datetime($override['starts_at'])) ?>
            <?= $override['expires_at'] !== null ? ' sampai ' . esc(orbit_format_datetime($override['expires_at'])) : '' ?>
            — <?= esc($override['reason']) ?>.</span>
    </div>
<?php endif; ?>

<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header"><?= orbit_icon('cpu', 18) ?> Status Perangkat
            <span class="right">
                <span class="badge <?= $powerBadge ?> plain"><?= esc(orbit_power_label((string) $device['power_state'])) ?></span>
                <?php if ($device['connection_state'] === 'offline'): ?>
                    <span class="badge badge-danger">Offline</span>
                <?php elseif ($device['connection_state'] === 'online'): ?>
                    <span class="badge badge-success">Online</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="card-body">
            <div class="kv">
                <span>Kemampuan kontrol</span>
                <strong><?= $device['control_capability'] === 'controllable' ? 'Dapat dikontrol' : 'Hanya pantau' ?></strong>
                <span>Terakhir terlihat</span>
                <strong><?= $device['last_seen_at'] !== null ? esc(orbit_format_datetime($device['last_seen_at'])) : '—' ?></strong>
                <span>Sumber status</span>
                <strong><?= esc($device['state_source'] ?? '—') ?></strong>
                <span>Mode ruangan</span>
                <strong><?= esc(orbit_room_mode_label((string) $device['room_mode'])) ?></strong>
                <span>Okupansi ruangan</span>
                <strong><?= esc(orbit_occupancy_label((string) $device['occupancy'])) ?></strong>
                <span>Posisi denah</span>
                <strong>
                    <?php if ($device['position_x'] !== null): ?>
                        x <?= esc($device['position_x']) ?>% · y <?= esc($device['position_y']) ?>%
                    <?php else: ?>
                        belum ditempatkan
                    <?php endif; ?>
                </strong>
                <span>Inventaris</span>
                <strong><?= esc(orbit_inventory_label((string) $device['inventory_status'])) ?></strong>
                <?php if ($isAc): ?>
                    <span>Suhu target</span>
                    <strong id="kv-target-temp"><?= esc(number_format($acTemp, 1)) ?> °C</strong>
                    <span>Mode AC</span>
                    <strong id="kv-ac-mode"><?= esc($acMode === 'dry' ? 'Dry' : 'Cool') ?><?= $acBoost ? ' + Powerful' : '' ?></strong>
                <?php endif ?>
            </div>

            <div class="control-row mt-3" id="control-row">
                <?php if ($canCommand): ?>
                    <button class="btn btn-soft" type="button" data-cmd="on"><?= orbit_icon('power', 16) ?> Nyalakan</button>
                    <button class="btn btn-outline" type="button" data-cmd="off"><?= orbit_icon('x', 16) ?> Matikan</button>
                    <?php if ($isComputer): ?>
                        <button class="btn btn-outline" type="button" data-cmd="sleep" title="Mode hemat daya"><?= orbit_icon('moon', 16) ?> Sleep</button>
                        <button class="btn btn-outline" type="button" data-cmd="shutdown" title="Matikan total"><?= orbit_icon('x', 16) ?> Shutdown</button>
                    <?php endif ?>
                <?php elseif ($device['control_capability'] !== 'controllable'): ?>
                    <div class="hint">Perangkat ini hanya dapat dipantau.</div>
                <?php elseif (! $canControl): ?>
                    <div class="hint">Peran Anda tidak memiliki izin mengontrol perangkat.</div>
                <?php endif ?>
            </div>

            <?php if ($isAc && $canCommand): ?>
            <div class="ac-panel mt-3" id="ac-panel">
                <div class="ac-panel-title">Panel Kendali AC</div>
                <div class="temp-control">
                    <span class="temp-label">Suhu target</span>
                    <div class="temp-stepper">
                        <button type="button" class="temp-btn" id="temp-minus" aria-label="Kurangi suhu">&minus;</button>
                        <span class="temp-value" id="temp-value"><?= esc(number_format($acTemp, 0)) ?></span>
                        <span class="temp-unit">°C</span>
                        <button type="button" class="temp-btn" id="temp-plus" aria-label="Tambah suhu">+</button>
                    </div>
                </div>
                <div class="ac-mode-row">
                    <span class="temp-label">Mode</span>
                    <div class="seg" id="ac-mode-seg">
                        <button type="button" data-mode="cool" class="<?= $acMode !== 'dry' ? 'active' : '' ?>">Cool</button>
                        <button type="button" data-mode="dry" class="<?= $acMode === 'dry' ? 'active' : '' ?>">Dry</button>
                    </div>
                </div>
                <div class="ac-boost-row">
                    <button type="button" class="btn btn-sm <?= $acBoost ? 'btn-soft' : 'btn-outline' ?>" id="boost-toggle">
                        <?= orbit_icon('zap', 15) ?> Powerful
                        <span class="boost-state" id="boost-state"><?= $acBoost ? 'ON' : 'OFF' ?></span>
                    </button>
                </div>
            </div>
            <?php endif ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><?= orbit_icon('wrench', 18) ?> Riwayat Perintah
            <span class="right muted small"><?= count($commands) ?> terakhir</span>
        </div>
        <div class="card-body tight">
            <?php if ($commands === []): ?>
                <div class="empty" style="padding:22px"><?= orbit_icon('clock', 30) ?><div>Belum ada perintah.</div></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr><th>Waktu</th><th>Perintah</th><th>Sumber</th><th>Pemesan</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($commands as $cmd): ?>
                            <tr>
                                <td class="mono small"><?= esc(orbit_format_datetime($cmd['created_at'], 'd M Y, H:i:s')) ?></td>
                                <td><span class="badge <?= in_array($cmd['command'], ['on', 'set_temperature', 'set_mode', 'set_boost'], true) ? 'badge-success' : 'badge-danger' ?> plain"><?= esc(str_replace('_', ' ', strtoupper($cmd['command']))) ?></span></td>
                                <td><?= esc($cmd['source']) ?></td>
                                <td><?= esc($cmd['requester_name'] ?? 'sistem') ?></td>
                                <td>
                                    <?php
                                    $st = ['success' => 'badge-success', 'failed' => 'badge-danger', 'pending' => 'badge-warn'][$cmd['status']] ?? 'badge-muted';
                                    ?>
                                    <span class="badge <?= $st ?>"><?= esc($cmd['status']) ?></span>
                                    <?php if ($cmd['result_message'] !== null && $cmd['result_message'] !== ''): ?>
                                        <div class="muted small"><?= esc($cmd['result_message']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isAdmin && $device['archived_at'] === null): ?>
    <div class="modal-root" id="edit-device-modal">
        <div class="modal-card">
            <div class="card-header">Ubah Perangkat</div>
            <div class="card-body">
                <form id="edit-device-form">
                    <div class="field">
                        <label class="label" for="ed-name">Nama <span class="req">*</span></label>
                        <input class="input" type="text" id="ed-name" name="name" maxlength="100" required
                               value="<?= esc($device['name']) ?>">
                        <div class="error-text"></div>
                    </div>
                    <div class="field">
                        <label class="label" for="ed-cap">Kemampuan kontrol</label>
                        <select class="select" id="ed-cap" name="control_capability">
                            <option value="controllable" <?= $device['control_capability'] === 'controllable' ? 'selected' : '' ?>>Dapat dikontrol</option>
                            <option value="monitor_only" <?= $device['control_capability'] === 'monitor_only' ? 'selected' : '' ?>>Hanya pantau</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="ed-verif">Status inventaris</label>
                        <select class="select" id="ed-verif" name="inventory_status">
                            <option value="needs_verification" <?= $device['inventory_status'] === 'needs_verification' ? 'selected' : '' ?>>Perlu verifikasi</option>
                            <option value="verified" <?= $device['inventory_status'] === 'verified' ? 'selected' : '' ?>>Terverifikasi</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" type="button" data-modal-close="edit-device-modal">Batal</button>
                <button class="btn" type="submit" form="edit-device-form">Simpan</button>
            </div>
        </div>
    </div>

    <div class="modal-root" id="override-modal">
        <div class="modal-card">
            <div class="card-header">Buat Override</div>
            <div class="card-body">
                <form id="override-form">
                    <div class="field">
                        <label class="label" for="ov-type">Tipe override</label>
                        <select class="select" id="ov-type" name="override_type">
                            <option value="persistent">Permanen (sampai diakhiri manual)</option>
                            <option value="time_limited">Terbatas waktu</option>
                        </select>
                    </div>
                    <div class="field" id="ov-expires-wrap" style="display:none">
                        <label class="label" for="ov-expires">Berakhir pada (waktu Indonesia)</label>
                        <input class="input" type="datetime-local" id="ov-expires" name="expires_at">
                        <div class="error-text"></div>
                    </div>
                    <div class="field">
                        <label class="label" for="ov-reason">Alasan <span class="req">*</span></label>
                        <textarea class="textarea" id="ov-reason" name="reason" maxlength="255" required
                                  placeholder="Mis. perbaikan panel listrik"></textarea>
                        <div class="error-text"></div>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" type="button" data-modal-close="override-modal">Batal</button>
                <button class="btn btn-danger" type="submit" form="override-form">Buat Override</button>
            </div>
        </div>
    </div>
<?php endif ?>

<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
(function () {
  var O = window.orbit;
  var deviceId = <?= (int) $device['id'] ?>;

  var CMD_LABELS = { on: 'nyalakan', off: 'matikan', sleep: 'masukkan mode sleep', shutdown: 'shutdown (matikan total)' };

  document.querySelectorAll('[data-cmd]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var cmd = btn.getAttribute('data-cmd');
      var label = CMD_LABELS[cmd] || cmd;
      O.confirm({
        title: 'Kirim perintah?',
        message: 'Perangkat ini akan ' + label + '.',
        yesLabel: 'Kirim',
        danger: cmd === 'off' || cmd === 'shutdown'
      }).then(function (ok) {
        if (!ok) { return; }
        btn.disabled = true;
        O.fetch('/api/devices/' + deviceId + '/command', { method: 'POST', body: { command: cmd } })
          .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.reload(); }, 600); })
          .catch(function (err) { O.toast(err.message, 'error'); btn.disabled = false; });
      });
    });
  });

  /* ---------------- Panel AC ---------------- */
  var acPanel = document.getElementById('ac-panel');
  if (acPanel) {
    var tempValue = document.getElementById('temp-value');
    var tempMinus = document.getElementById('temp-minus');
    var tempPlus  = document.getElementById('temp-plus');
    var modeSeg   = document.getElementById('ac-mode-seg');
    var boostBtn  = document.getElementById('boost-toggle');
    var boostState = document.getElementById('boost-state');
    var kvTemp    = document.getElementById('kv-target-temp');
    var kvMode    = document.getElementById('kv-ac-mode');
    var currentTemp = <?= json_encode((int) $acTemp) ?>;
    var currentMode = <?= json_encode($acMode) ?>;
    var currentBoost = <?= json_encode($acBoost) ?>;

    function postAc(body, onOk) {
      O.fetch('/api/devices/' + deviceId + '/command', { method: 'POST', body: body })
        .then(function (res) { O.toast(res.message, 'success'); onOk && onOk(res); })
        .catch(function (err) { O.toast(err.message, 'error'); });
    }

    function updateKv() {
      if (kvTemp) { kvTemp.textContent = currentTemp + '.0 °C'; }
      if (kvMode) { kvMode.textContent = (currentMode === 'dry' ? 'Dry' : 'Cool') + (currentBoost ? ' + Powerful' : ''); }
    }

    if (tempMinus) {
      tempMinus.addEventListener('click', function () {
        if (currentTemp <= 16) { return; }
        currentTemp--;
        tempValue.textContent = currentTemp;
        postAc({ command: 'set_temperature', temperature: currentTemp }, updateKv);
      });
    }
    if (tempPlus) {
      tempPlus.addEventListener('click', function () {
        if (currentTemp >= 30) { return; }
        currentTemp++;
        tempValue.textContent = currentTemp;
        postAc({ command: 'set_temperature', temperature: currentTemp }, updateKv);
      });
    }
    if (modeSeg) {
      modeSeg.querySelectorAll('[data-mode]').forEach(function (b) {
        b.addEventListener('click', function () {
          var m = b.getAttribute('data-mode');
          if (m === currentMode) { return; }
          modeSeg.querySelectorAll('[data-mode]').forEach(function (x) { x.classList.remove('active'); });
          b.classList.add('active');
          currentMode = m;
          postAc({ command: 'set_mode', mode: m }, updateKv);
        });
      });
    }
    if (boostBtn) {
      boostBtn.addEventListener('click', function () {
        currentBoost = !currentBoost;
        boostState.textContent = currentBoost ? 'ON' : 'OFF';
        boostBtn.classList.toggle('btn-soft', currentBoost);
        boostBtn.classList.toggle('btn-outline', !currentBoost);
        postAc({ command: 'set_boost', boost: currentBoost }, updateKv);
      });
    }
  }

  <?php if ($isAdmin): ?>
  var editForm = document.getElementById('edit-device-form');
  window.__devEditReset = function () { O.clearFieldErrors(editForm); };
  editForm.addEventListener('submit', function (e) {
    e.preventDefault();
    O.clearFieldErrors(editForm);
    O.fetch('/api/devices/' + deviceId, {
      method: 'PUT',
      body: {
        name: editForm.elements.name.value.trim(),
        control_capability: editForm.control_capability.value,
        inventory_status: editForm.inventory_status.value
      }
    }).then(function (res) {
      O.toast(res.message, 'success');
      O.closeModal('edit-device-modal');
      setTimeout(function () { window.location.reload(); }, 500);
    }).catch(function (err) {
      if (err.errors) { O.showFieldErrors(editForm, err.errors); }
      O.toast(err.message, 'error');
    });
  });

  var archiveBtn = document.getElementById('btn-archive');
  if (archiveBtn) {
    archiveBtn.addEventListener('click', function () {
      O.confirm({
        title: 'Arsipkan perangkat?',
        message: 'Perangkat disembunyikan dari daftar aktif dan tidak dapat dikontrol.',
        yesLabel: 'Arsipkan'
      }).then(function (ok) {
        if (!ok) { return; }
        O.fetch('/api/devices/' + deviceId + '/archive', { method: 'POST', body: { archive: true } })
          .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.href = '/perangkat'; }, 600); })
          .catch(function (err) { O.toast(err.message, 'error'); });
      });
    });
  }

  var ovType = document.getElementById('ov-type');
  var ovWrap = document.getElementById('ov-expires-wrap');
  if (ovType) {
    ovType.addEventListener('change', function () {
      ovWrap.style.display = ovType.value === 'time_limited' ? '' : 'none';
    });
  }

  var ovForm = document.getElementById('override-form');
  if (ovForm) {
    // tombol buat override disisipkan di header bila override tidak aktif
    ovForm.addEventListener('submit', function (e) {
      e.preventDefault();
      O.clearFieldErrors(ovForm);
      var body = {
        device_id: deviceId,
        override_type: ovForm.override_type.value,
        reason: ovForm.reason.value.trim()
      };
      if (body.override_type === 'time_limited') {
        if (!ovForm.expires_at.value) { O.showFieldErrors(ovForm, { expires_at: 'Waktu berakhir wajib diisi.' }); return; }
        var d = new Date(ovForm.expires_at.value);
        if (isNaN(d.getTime())) { O.showFieldErrors(ovForm, { expires_at: 'Waktu tidak valid.' }); return; }
        // input lokal (Asia/Jakarta) dikonversi ke UTC format Y-m-d H:i:s
        body.expires_at = d.toISOString().slice(0, 19).replace('T', ' ');
      }
      O.fetch('/api/overrides', { method: 'POST', body: body }).then(function (res) {
        O.toast(res.message, 'success');
        O.closeModal('override-modal');
        setTimeout(function () { window.location.reload(); }, 600);
      }).catch(function (err) {
        if (err.errors) { O.showFieldErrors(ovForm, err.errors); }
        O.toast(err.message, 'error');
      });
    });
  }

  <?php if ($override !== null): ?>
  var endBtn = document.getElementById('btn-end-override');
  if (endBtn) {
    endBtn.addEventListener('click', function () {
      O.confirm({ title: 'Akhiri override?', message: 'Perangkat kembali dapat dikontrol normal.', yesLabel: 'Akhiri' })
        .then(function (ok) {
          if (!ok) { return; }
          O.fetch('/api/overrides/<?= (int) $override['id'] ?>/end', { method: 'POST', body: {} })
            .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.reload(); }, 600); })
            .catch(function (err) { O.toast(err.message, 'error'); });
        });
    });
  }
  <?php endif ?>
  <?php endif ?>
})();
</script>
<?php $this->endSection() ?>
