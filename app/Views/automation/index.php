<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<div class="page-head">
    <div>
        <h2>Otomatisasi</h2>
        <p><?= count($rules) ?> aturan · eksekusi ditangguhkan sementara untuk demo LKS</p>
    </div>
    <?php if ($isAdmin): ?>
        <div class="actions">
            <button class="btn btn-sm" type="button" data-modal-open="rule-modal"
                    onclick="window.__ruleFormReset && window.__ruleFormReset()">
                <?= orbit_icon('plus', 16) ?> Buat Aturan
            </button>
        </div>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-header"><?= orbit_icon('bolt', 18) ?> Daftar Aturan</div>
    <div class="card-body tight">
        <?php if ($rules === []): ?>
            <div class="empty" style="padding:26px"><?= orbit_icon('bolt', 32) ?><div>Belum ada aturan otomatisasi.</div></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Aturan</th>
                        <th>Pemicu</th>
                        <th>Durasi</th>
                        <th>Tindakan</th>
                        <th>Status</th>
                        <th>Pembuat</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rules as $rule): ?>
                        <?php
                        $actions  = json_decode($rule['actions_json'] ?? '[]', true) ?: [];
                        $target   = json_decode($rule['target_json'] ?? 'null', true);
                        $roomIds  = is_array($target) ? ($target['room_ids'] ?? []) : [];
                        $actionLabels = [];
                        foreach ($actions as $action) {
                            if (($action['type'] ?? '') === 'set_room_mode') {
                                $actionLabels[] = 'Mode ' . orbit_room_mode_label($action['mode'] ?? 'active');
                            } elseif (($action['type'] ?? '') === 'turn_off') {
                                $actionLabels[] = 'Matikan perangkat';
                            } else {
                                $actionLabels[] = $action['type'] ?? '-';
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('otomatisasi/' . $rule['id']) ?>">
                                    <strong><?= esc($rule['name']) ?></strong>
                                </a>
                                <?php if ($rule['description']): ?>
                                    <div class="muted small"><?= esc(mb_strimwidth((string) $rule['description'], 0, 70, '…')) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= $rule['trigger_type'] === 'vacant_duration' ? 'Ruangan kosong' : 'Manual' ?></td>
                            <td class="num"><?= $rule['trigger_type'] === 'vacant_duration' ? (int) $rule['duration_seconds'] . ' dtk' : '—' ?></td>
                            <td>
                                <?= esc(implode(', ', $actionLabels) ?: '—') ?>
                                <div class="muted small">
                                    <?= $roomIds === [] ? 'seluruh ruangan' : count($roomIds) . ' ruangan dituju' ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($isAdmin): ?>
                                    <button class="badge <?= (int) $rule['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"
                                            type="button" data-toggle-rule="<?= (int) $rule['id'] ?>"
                                            data-active="<?= (int) $rule['is_active'] ?>"
                                            style="border:0;cursor:pointer">
                                        <?= (int) $rule['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                                    </button>
                                <?php else: ?>
                                    <span class="badge <?= (int) $rule['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>">
                                        <?= (int) $rule['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= esc($rule['creator_name'] ?? 'sistem') ?>
                                <div class="muted small">
                                    terakhir <?= $rule['last_executed_at'] !== null ? esc(orbit_format_datetime($rule['last_executed_at'])) : 'belum' ?>
                                </div>
                            </td>
                            <td class="nowrap">
                                <a class="btn btn-outline btn-sm" href="<?= site_url('otomatisasi/' . $rule['id']) ?>">Detail</a>
                                <?php if ($isAdmin): ?>
                                    <button class="btn btn-ghost btn-sm" type="button"
                                            data-edit-rule="<?= (int) $rule['id'] ?>"
                                            data-modal-open="rule-modal">
                                        <?= orbit_icon('edit', 15) ?>
                                    </button>
                                    <button class="btn btn-ghost btn-sm" type="button" data-delete-rule="<?= (int) $rule['id'] ?>">
                                        <?= orbit_icon('trash', 15) ?>
                                    </button>
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

<div class="card">
    <div class="card-header"><?= orbit_icon('list', 18) ?> Riwayat Eksekusi
        <span class="right muted small">10 terakhir per halaman</span>
    </div>
    <div class="card-body tight">
        <?php if ($executions === []): ?>
            <div class="empty" style="padding:24px"><?= orbit_icon('clock', 30) ?><div>Belum ada eksekusi aturan.</div></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr><th>Waktu</th><th>Aturan</th><th>Status</th><th>Alasan</th><th>Tik</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($executions as $ex): ?>
                        <tr>
                            <td class="mono small"><?= esc(orbit_format_datetime($ex['executed_at'] ?? $ex['created_at'], 'd M Y, H:i:s')) ?></td>
                            <td><a href="<?= site_url('otomatisasi/' . $ex['rule_id']) ?>"><?= esc($ex['rule_name'] ?? '—') ?></a></td>
                            <td>
                                <?php
                                $st = ['executed' => 'badge-success', 'skipped' => 'badge-muted',
                                    'failed' => 'badge-danger', 'conflict' => 'badge-warn'][$ex['status']] ?? 'badge-muted';
                                ?>
                                <span class="badge <?= $st ?>"><?= esc($ex['status']) ?></span>
                            </td>
                            <td class="muted small"><?= esc($ex['reason'] ?? '—') ?></td>
                            <td class="mono small"><?= esc($ex['tick_key']) ?></td>
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
    <div class="modal-root" id="rule-modal">
        <div class="modal-card">
            <div class="card-header" id="rule-modal-title">Buat Aturan</div>
            <div class="card-body">
                <form id="rule-form">
                    <input type="hidden" name="id">
                    <div class="field">
                        <label class="label" for="rule-name">Nama <span class="req">*</span></label>
                        <input class="input" type="text" id="rule-name" name="name" maxlength="150" required
                               placeholder="Mis. Matikan AC saat ruangan kosong">
                        <div class="error-text"></div>
                    </div>
                    <div class="field">
                        <label class="label" for="rule-desc">Deskripsi</label>
                        <textarea class="textarea" id="rule-desc" name="description" maxlength="500"></textarea>
                    </div>
                    <div class="field">
                        <label class="label" for="rule-trigger">Pemicu</label>
                        <select class="select" id="rule-trigger" name="trigger_type">
                            <option value="vacant_duration">Ruangan kosong selama durasi</option>
                            <option value="manual">Manual (tanpa pemicu otomatis)</option>
                        </select>
                    </div>
                    <div class="field" id="rule-duration-wrap">
                        <label class="label" for="rule-duration">Durasi kosong (detik)</label>
                        <input class="input" type="number" id="rule-duration" name="duration_seconds" min="0" step="1" value="300">
                        <div class="hint">0 berarti langsung dieksekusi begitu memenuhi syarat.</div>
                        <div class="error-text"></div>
                    </div>

                    <div class="field">
                        <label class="label" for="rule-action-type">Tindakan</label>
                        <select class="select" id="rule-action-type" name="action_type">
                            <option value="turn_off">Matikan perangkat terpilih</option>
                            <option value="set_room_mode">Ubah mode ruangan</option>
                        </select>
                    </div>
                    <div class="field" id="rule-mode-wrap" style="display:none">
                        <label class="label" for="rule-mode">Mode tujuan</label>
                        <select class="select" id="rule-mode" name="action_mode">
                            <option value="active">Aktif</option>
                            <option value="standby" selected>Standby</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label">Ruangan tujuan</label>
                        <div class="hint" style="margin-bottom:6px">Kosongkan untuk menerapkan ke seluruh ruangan.</div>
                        <div class="flex gap-3 wrap">
                            <?php foreach ($rooms as $room): ?>
                                <label class="checkbox">
                                    <input type="checkbox" name="target_rooms[]" value="<?= (int) $room['id'] ?>">
                                    <?= esc($room['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="field">
                        <label class="checkbox">
                            <input type="checkbox" name="is_active" value="1" checked>
                            Aturan aktif
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" type="button" data-modal-close="rule-modal">Batal</button>
                <button class="btn" type="submit" form="rule-form">Simpan</button>
            </div>
        </div>
    </div>
<?php endif ?>

<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
(function () {
  var O = window.orbit;
  var rulesData = <?= json_encode(
      array_map(static fn ($r) => [
          'id' => (int) $r['id'],
          'name' => $r['name'],
          'description' => $r['description'],
          'trigger_type' => $r['trigger_type'],
          'duration_seconds' => (int) $r['duration_seconds'],
          'actions_json' => $r['actions_json'],
          'target_json' => $r['target_json'],
          'is_active' => (int) $r['is_active'],
      ], array_values($rules)),
      JSON_UNESCAPED_UNICODE
  ) ?>;

  document.querySelectorAll('[data-toggle-rule]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-toggle-rule');
      var next = btn.getAttribute('data-active') === '1' ? 0 : 1;
      btn.disabled = true;
      O.fetch('/api/automation/rules/' + id, { method: 'PUT', body: { is_active: next } })
        .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.reload(); }, 500); })
        .catch(function (err) { O.toast(err.message, 'error'); btn.disabled = false; });
    });
  });

  <?php if ($isAdmin): ?>
  var form = document.getElementById('rule-form');
  var title = document.getElementById('rule-modal-title');
  var editingId = null;
  var triggerSel = document.getElementById('rule-trigger');
  var durationWrap = document.getElementById('rule-duration-wrap');
  var actionType = document.getElementById('rule-action-type');
  var modeWrap = document.getElementById('rule-mode-wrap');

  function syncTrigger() {
    durationWrap.style.display = triggerSel.value === 'vacant_duration' ? '' : 'none';
  }
  function syncAction() {
    modeWrap.style.display = actionType.value === 'set_room_mode' ? '' : 'none';
  }
  triggerSel.addEventListener('change', syncTrigger);
  actionType.addEventListener('change', syncAction);

  window.__ruleFormReset = function () {
    editingId = null;
    form.reset();
    form.querySelector('[name=id]').value = '';
    title.textContent = 'Buat Aturan';
    O.clearFieldErrors(form);
    syncTrigger();
    syncAction();
  };

  document.querySelectorAll('[data-edit-rule]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = parseInt(btn.getAttribute('data-edit-rule'), 10);
      var rule = rulesData.filter(function (r) { return r.id === id; })[0];
      if (!rule) { return; }
      window.__ruleFormReset();
      editingId = id;
      title.textContent = 'Ubah Aturan';
      form.querySelector('[name=name]').value = rule.name;
      form.querySelector('[name=description]').value = rule.description || '';
      form.querySelector('[name=trigger_type]').value = rule.trigger_type;
      form.querySelector('[name=duration_seconds]').value = rule.duration_seconds;
      form.is_active.checked = rule.is_active === 1;

      var actions = [];
      try { actions = JSON.parse(rule.actions_json || '[]'); } catch (e) { actions = []; }
      var first = actions[0] || { type: 'turn_off' };
      actionType.value = first.type === 'set_room_mode' ? 'set_room_mode' : 'turn_off';
      if (first.type === 'set_room_mode') { document.getElementById('rule-mode').value = first.mode || 'standby'; }

      var target = null;
      try { target = JSON.parse(rule.target_json || 'null'); } catch (e) { target = null; }
      var roomIds = (target && target.room_ids) ? target.room_ids.map(String) : [];
      form.querySelectorAll('[name="target_rooms[]"]').forEach(function (cb) {
        cb.checked = roomIds.indexOf(cb.value) !== -1;
      });

      syncTrigger();
      syncAction();
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    O.clearFieldErrors(form);

    var action = { type: actionType.value };
    if (action.type === 'set_room_mode') { action.mode = document.getElementById('rule-mode').value; }

    var roomIds = [];
    form.querySelectorAll('[name="target_rooms[]"]:checked').forEach(function (cb) {
      roomIds.push(parseInt(cb.value, 10));
    });

    var payload = {
      name: form.querySelector('[name=name]').value.trim(),
      description: form.querySelector('[name=description]').value.trim(),
      trigger_type: triggerSel.value,
      duration_seconds: parseInt(form.querySelector('[name=duration_seconds]').value, 10) || 0,
      actions_json: JSON.stringify([action]),
      target_json: JSON.stringify({ room_ids: roomIds }),
      is_active: form.is_active.checked ? 1 : 0
    };

    var url = editingId ? '/api/automation/rules/' + editingId : '/api/automation/rules';
    var method = editingId ? 'PUT' : 'POST';

    O.fetch(url, { method: method, body: payload }).then(function (res) {
      O.toast(res.message, 'success');
      O.closeModal('rule-modal');
      setTimeout(function () { window.location.reload(); }, 500);
    }).catch(function (err) {
      if (err.errors && Object.keys(err.errors).length) { O.showFieldErrors(form, err.errors); }
      O.toast(err.message, 'error');
    });
  });

  document.querySelectorAll('[data-delete-rule]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-delete-rule');
      O.confirm({
        title: 'Hapus aturan?',
        message: 'Aturan dan riwayat eksekusinya akan dihapus permanen.',
        yesLabel: 'Hapus'
      }).then(function (ok) {
        if (!ok) { return; }
        O.fetch('/api/automation/rules/' + id, { method: 'DELETE', body: {} })
          .then(function (res) { O.toast(res.message, 'success'); setTimeout(function () { window.location.reload(); }, 500); })
          .catch(function (err) { O.toast(err.message, 'error'); });
      });
    });
  });
  <?php endif ?>
})();
</script>
<?php $this->endSection() ?>
