<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<div class="page-head">
    <div>
        <h2>Ruangan & Perangkat</h2>
        <p><?= count($rooms) ?> ruangan ditampilkan · filter aktif memengaruhi hasil</p>
    </div>
    <?php if ($isAdmin): ?>
        <div class="actions">
            <button class="btn btn-sm" type="button" data-modal-open="room-modal"
                    onclick="window.__roomFormReset && window.__roomFormReset()">
                <?= orbit_icon('plus', 16) ?> Tambah Ruangan
            </button>
        </div>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= site_url('ruangan') ?>">
    <div class="field wide">
        <label class="label" for="q">Cari</label>
        <input class="input" type="search" id="q" name="q" placeholder="Nama / deskripsi ruangan…"
               value="<?= esc($filters['q']) ?>">
    </div>
    <div class="field">
        <label class="label" for="lantai">Lantai</label>
        <select class="select" id="lantai" name="lantai">
            <option value="">Semua</option>
            <?php foreach ($floorOptions as $floor): ?>
                <option value="<?= (int) $floor['id'] ?>" <?= $filters['lantai'] === (int) $floor['id'] ? 'selected' : '' ?>>
                    <?= esc($floor['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="status">Okupansi</label>
        <select class="select" id="status" name="status">
            <option value="">Semua</option>
            <option value="occupied" <?= $filters['status'] === 'occupied' ? 'selected' : '' ?>>Terisi</option>
            <option value="vacant" <?= $filters['status'] === 'vacant' ? 'selected' : '' ?>>Kosong</option>
        </select>
    </div>
    <div class="field">
        <label class="label" for="mode">Mode</label>
        <select class="select" id="mode" name="mode">
            <option value="">Semua</option>
            <option value="active" <?= $filters['mode'] === 'active' ? 'selected' : '' ?>>Aktif</option>
            <option value="standby" <?= $filters['mode'] === 'standby' ? 'selected' : '' ?>>Standby</option>
            <option value="inactive" <?= $filters['mode'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
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
    <a class="btn btn-outline" href="<?= site_url('ruangan') ?>">Reset</a>
</form>

<div class="card">
    <div class="card-body tight">
        <?php if ($rooms === []): ?>
            <div class="empty"><?= orbit_icon('door', 34) ?><div>Tidak ada ruangan yang cocok dengan filter.</div></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Ruangan</th>
                        <th>Lantai</th>
                        <th>Okupansi</th>
                        <th>Mode</th>
                        <th>Perangkat</th>
                        <th>Inventaris</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <?php
                        $counts = $deviceCounts[(int) $room['id']] ?? ['total' => 0, 'placed' => 0];
                        ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('ruangan/' . $room['id']) ?>">
                                    <strong><?= esc($room['name']) ?></strong>
                                </a>
                                <?php if ($room['description']): ?>
                                    <div class="muted small"><?= esc(mb_strimwidth((string) $room['description'], 0, 70, '…')) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($room['floor_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge <?= $room['occupancy'] === 'occupied' ? 'badge-success' : 'badge-info' ?> plain">
                                    <?= esc(orbit_occupancy_label($room['occupancy'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $modeBadge = ['active' => 'badge-success', 'standby' => 'badge-warn', 'inactive' => 'badge-muted'][$room['mode']] ?? '';
                                ?>
                                <span class="badge <?= $modeBadge ?> plain"><?= esc(orbit_room_mode_label($room['mode'])) ?></span>
                            </td>
                            <td class="num"><?= (int) $counts['total'] ?>
                                <span class="muted small">(<?= (int) $counts['placed'] ?> di denah)</span></td>
                            <td>
                                <?php if ($room['inventory_status'] === 'needs_verification'): ?>
                                    <span class="badge badge-warn">Perlu verifikasi</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Terverifikasi</span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap">
                                <a class="btn btn-outline btn-sm" href="<?= site_url('ruangan/' . $room['id']) ?>">Detail</a>
                                <?php if ($isAdmin): ?>
                                    <button class="btn btn-ghost btn-sm" type="button"
                                            data-edit-room='<?= json_encode([
                                                'id' => (int) $room['id'],
                                                'name' => $room['name'],
                                                'description' => $room['description'],
                                                'floor_id' => (int) $room['floor_id'],
                                                'inventory_status' => $room['inventory_status'],
                                            ], JSON_UNESCAPED_UNICODE) ?>'
                                            data-modal-open="room-modal">
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
    <div class="modal-root" id="room-modal">
        <div class="modal-card">
            <div class="card-header" id="room-modal-title">Tambah Ruangan</div>
            <div class="card-body">
                <form id="room-form">
                    <input type="hidden" name="id">
                    <div class="field">
                        <label class="label" for="room-name">Nama <span class="req">*</span></label>
                        <input class="input" type="text" id="room-name" name="name" maxlength="100" required>
                        <div class="error-text"></div>
                    </div>
                    <div class="field">
                        <label class="label" for="room-desc">Deskripsi</label>
                        <textarea class="textarea" id="room-desc" name="description" maxlength="500"></textarea>
                    </div>
                    <div class="field">
                        <label class="label" for="room-floor">Lantai <span class="req">*</span></label>
                        <select class="select" id="room-floor" name="floor_id" required>
                            <?php foreach ($floorOptions as $floor): ?>
                                <option value="<?= (int) $floor['id'] ?>"><?= esc($floor['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="room-verif">Status inventaris</label>
                        <select class="select" id="room-verif" name="inventory_status">
                            <option value="needs_verification">Perlu verifikasi (dugaan)</option>
                            <option value="verified">Terverifikasi</option>
                        </select>
                        <div class="hint">Status lantai dugaan awal tidak pernah ditebak sistem.</div>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" type="button" data-modal-close="room-modal">Batal</button>
                <button class="btn" type="submit" form="room-form" id="room-submit">Simpan</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php $this->endSection() ?>

<?php if ($isAdmin): ?>
<?php $this->section('scripts') ?>
<script>
(function () {
  var O = window.orbit;
  var form = document.getElementById('room-form');
  var title = document.getElementById('room-modal-title');
  var editingId = null;

  window.__roomFormReset = function () {
    editingId = null;
    form.reset();
    form.querySelector('[name=id]').value = '';
    title.textContent = 'Tambah Ruangan';
    O.clearFieldErrors(form);
  };

  document.querySelectorAll('[data-edit-room]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var data = JSON.parse(btn.getAttribute('data-edit-room'));
      window.__roomFormReset();
      editingId = data.id;
      title.textContent = 'Edit Ruangan';
      form.querySelector('[name=name]').value = data.name;
      form.querySelector('[name=description]').value = data.description || '';
      form.querySelector('[name=floor_id]').value = data.floor_id;
      form.querySelector('[name=inventory_status]').value = data.inventory_status;
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    O.clearFieldErrors(form);
    var payload = {
      name: form.elements.name.value.trim(),
      description: form.description.value.trim(),
      floor_id: parseInt(form.floor_id.value, 10),
      inventory_status: form.inventory_status.value
    };

    var url = editingId ? '/api/rooms/' + editingId : '/api/rooms';
    var method = editingId ? 'PUT' : 'POST';

    O.fetch(url, { method: method, body: payload }).then(function (res) {
      O.toast(res.message || 'Ruangan disimpan.', 'success');
      O.closeModal('room-modal');
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
<?php endif; ?>
