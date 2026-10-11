<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<div class="page-head">
    <div>
        <h2>Perangkat</h2>
        <p><?= count($devices) ?> perangkat ditampilkan · filter aktif memengaruhi hasil</p>
    </div>
    <?php if ($isAdmin): ?>
        <div class="actions">
            <button class="btn btn-sm" type="button" data-modal-open="device-modal"
                    onclick="window.__deviceFormReset && window.__deviceFormReset()">
                <?= orbit_icon('plus', 16) ?> Tambah Perangkat
            </button>
        </div>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= site_url('perangkat') ?>">
    <div class="field wide">
        <label class="label" for="q">Cari</label>
        <input class="input" type="search" id="q" name="q" placeholder="Nama perangkat…"
               value="<?= esc($filters['q']) ?>">
    </div>
    <div class="field">
        <label class="label" for="ruangan">Ruangan</label>
        <select class="select" id="ruangan" name="ruangan">
            <option value="">Semua</option>
            <?php foreach ($roomOptions as $room): ?>
                <option value="<?= (int) $room['id'] ?>" <?= $filters['ruangan'] === (int) $room['id'] ? 'selected' : '' ?>>
                    <?= esc($room['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="tipe">Tipe</label>
        <select class="select" id="tipe" name="tipe">
            <option value="">Semua</option>
            <?php foreach ($typeOptions as $key => $label): ?>
                <option value="<?= esc($key) ?>" <?= $filters['tipe'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="daya">Daya</label>
        <select class="select" id="daya" name="daya">
            <option value="">Semua</option>
            <option value="on" <?= $filters['daya'] === 'on' ? 'selected' : '' ?>>Menyala</option>
            <option value="off" <?= $filters['daya'] === 'off' ? 'selected' : '' ?>>Mati</option>
            <option value="unknown" <?= $filters['daya'] === 'unknown' ? 'selected' : '' ?>>Tidak diketahui</option>
        </select>
    </div>
    <div class="field">
        <label class="label" for="posisi">Posisi denah</label>
        <select class="select" id="posisi" name="posisi">
            <option value="">Semua</option>
            <option value="placed" <?= $filters['posisi'] === 'placed' ? 'selected' : '' ?>>Sudah ditempatkan</option>
            <option value="unplaced" <?= $filters['posisi'] === 'unplaced' ? 'selected' : '' ?>>Belum ditempatkan</option>
        </select>
    </div>
    <div class="field">
        <label class="label" for="verifikasi">Verifikasi</label>
        <select class="select" id="verifikasi" name="verifikasi">
            <option value="">Semua</option>
            <option value="needs_verification" <?= $filters['verifikasi'] === 'needs_verification' ? 'selected' : '' ?>>Perlu verifikasi</option>
            <option value="verified" <?= $filters['verifikasi'] === 'verified' ? 'selected' : '' ?>>Terverifikasi</option>
        </select>
    </div>
    <button class="btn" type="submit"><?= orbit_icon('filter', 16) ?> Terapkan</button>
    <a class="btn btn-outline" href="<?= site_url('perangkat') ?>">Reset</a>
</form>

<div class="card">
    <div class="card-body tight">
        <?php if ($devices === []): ?>
            <div class="empty"><?= orbit_icon('cpu', 34) ?><div>Tidak ada perangkat yang cocok dengan filter.</div></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Perangkat</th>
                        <th>Ruangan</th>
                        <th>Tipe</th>
                        <th>Daya</th>
                        <th>Koneksi</th>
                        <th>Denah</th>
                        <th>Inventaris</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($devices as $device): ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('perangkat/' . $device['id']) ?>">
                                    <strong><?= esc($device['name']) ?></strong>
                                </a>
                                <div class="muted small"><?= orbit_device_icon($device['device_type']) ?>
                                    <?= esc(orbit_device_type_label($device['device_type'])) ?>
                                    <?= $device['control_capability'] === 'monitor_only' ? '· pantau saja' : '' ?>
                                </div>
                            </td>
                            <td><?= esc($device['room_name'] ?? '—') ?></td>
                            <td><?= esc(orbit_device_type_label($device['device_type'])) ?></td>
                            <td>
                                <span class="badge <?= $device['power_state'] === 'on' ? 'badge-success' : ($device['power_state'] === 'off' ? 'badge-danger' : 'badge-muted') ?> plain">
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
                            <td>
                                <?php if ($device['position_x'] !== null): ?>
                                    <span class="badge badge-info plain">x<?= esc($device['position_x']) ?> y<?= esc($device['position_y']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-warn">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($device['inventory_status'] === 'needs_verification'): ?>
                                    <span class="badge badge-warn">Perlu verifikasi</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Terverifikasi</span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap">
                                <a class="btn btn-outline btn-sm" href="<?= site_url('perangkat/' . $device['id']) ?>">Detail</a>
                                <?php if ($isAdmin): ?>
                                    <button class="btn btn-ghost btn-sm" type="button"
                                            data-edit-device='<?= json_encode([
                                                'id' => (int) $device['id'],
                                                'name' => $device['name'],
                                                'room_id' => (int) $device['room_id'],
                                                'device_type' => $device['device_type'],
                                                'control_capability' => $device['control_capability'],
                                                'inventory_status' => $device['inventory_status'],
                                            ], JSON_UNESCAPED_UNICODE) ?>'
                                            data-modal-open="device-modal">
                                        <?= orbit_icon('edit', 15) ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= view('partials/pagination', ['pager' => $pager]) ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
    <div class="modal-root" id="device-modal">
        <div class="modal-card">
            <div class="card-header" id="device-modal-title">Tambah Perangkat</div>
            <div class="card-body">
                <form id="device-form">
                    <input type="hidden" name="id">
                    <div class="field">
                        <label class="label" for="device-name">Nama <span class="req">*</span></label>
                        <input class="input" type="text" id="device-name" name="name" maxlength="100" required>
                        <div class="error-text"></div>
                    </div>
                    <div class="field">
                        <label class="label" for="device-room">Ruangan <span class="req">*</span></label>
                        <select class="select" id="device-room" name="room_id" required>
                            <?php foreach ($roomOptions as $room): ?>
                                <option value="<?= (int) $room['id'] ?>"><?= esc($room['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-text"></div>
                    </div>
                    <div class="field">
                        <label class="label" for="device-type">Tipe <span class="req">*</span></label>
                        <select class="select" id="device-type" name="device_type" required>
                            <?php foreach ($typeOptions as $key => $label): ?>
                                <option value="<?= esc($key) ?>"><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-text"></div>
                    </div>
                    <div class="field">
                        <label class="label" for="device-cap">Kemampuan kontrol</label>
                        <select class="select" id="device-cap" name="control_capability">
                            <option value="controllable">Dapat dikontrol</option>
                            <option value="monitor_only">Hanya pantau</option>
                        </select>
                        <div class="hint">Perangkat pantau tidak boleh menerima perintah nyala/mati.</div>
                    </div>
                    <div class="field">
                        <label class="label" for="device-verif">Status inventaris</label>
                        <select class="select" id="device-verif" name="inventory_status">
                            <option value="needs_verification">Perlu verifikasi (dugaan)</option>
                            <option value="verified">Terverifikasi</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" type="button" data-modal-close="device-modal">Batal</button>
                <button class="btn" type="submit" form="device-form" id="device-submit">Simpan</button>
            </div>
        </div>
    </div>
<?php endif ?>

<?php $this->endSection() ?>

<?php if ($isAdmin): ?>
<?php $this->section('scripts') ?>
<script>
(function () {
  var O = window.orbit;
  var form = document.getElementById('device-form');
  var title = document.getElementById('device-modal-title');
  var editingId = null;

  window.__deviceFormReset = function () {
    editingId = null;
    form.reset();
    form.querySelector('[name=id]').value = '';
    title.textContent = 'Tambah Perangkat';
    O.clearFieldErrors(form);
  };

  document.querySelectorAll('[data-edit-device]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var data = JSON.parse(btn.getAttribute('data-edit-device'));
      window.__deviceFormReset();
      editingId = data.id;
      title.textContent = 'Edit Perangkat';
      form.querySelector('[name=name]').value = data.name;
      form.querySelector('[name=room_id]').value = data.room_id;
      form.querySelector('[name=device_type]').value = data.device_type;
      form.querySelector('[name=control_capability]').value = data.control_capability;
      form.querySelector('[name=inventory_status]').value = data.inventory_status;
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    O.clearFieldErrors(form);
    var payload = {
      name: form.elements.name.value.trim(),
      room_id: parseInt(form.room_id.value, 10),
      device_type: form.device_type.value,
      control_capability: form.control_capability.value,
      inventory_status: form.inventory_status.value
    };

    var url = editingId ? '/api/devices/' + editingId : '/api/devices';
    var method = editingId ? 'PUT' : 'POST';

    O.fetch(url, { method: method, body: payload }).then(function (res) {
      O.toast(res.message || 'Perangkat disimpan.', 'success');
      O.closeModal('device-modal');
      setTimeout(function () { window.location.reload(); }, 500);
    }).catch(function (err) {
      if (err.errors && Object.keys(err.errors).length) {
        O.showFieldErrors(form, err.errors);
      }
      O.toast(err.message, 'error');
    });
  });
})();
</script>
<?php $this->endSection() ?>
<?php endif ?>
