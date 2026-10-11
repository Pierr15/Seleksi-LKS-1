<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<div class="page-head">
    <div>
        <h2>Manajemen Pengguna</h2>
        <p><?= count($users) ?> pengguna ditampilkan</p>
    </div>
    <div class="actions">
        <button class="btn btn-sm" type="button" data-modal-open="user-modal"
                onclick="window.__userFormReset && window.__userFormReset()">
            <?= orbit_icon('plus', 16) ?> Tambah Pengguna
        </button>
    </div>
</div>

<form class="filters" method="get" action="<?= site_url('pengguna') ?>">
    <div class="field wide">
        <label class="label" for="q">Cari</label>
        <input class="input" type="search" id="q" name="q" placeholder="Nama / email…"
               value="<?= esc($filters['q']) ?>">
    </div>
    <div class="field">
        <label class="label" for="peran">Peran</label>
        <select class="select" id="peran" name="peran">
            <option value="">Semua</option>
            <option value="admin" <?= $filters['peran'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="operator" <?= $filters['peran'] === 'operator' ? 'selected' : '' ?>>Operator</option>
            <option value="viewer" <?= $filters['peran'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
        </select>
    </div>
    <button class="btn" type="submit"><?= orbit_icon('filter', 16) ?> Terapkan</button>
    <a class="btn btn-outline" href="<?= site_url('pengguna') ?>">Reset</a>
</form>

<div class="card">
    <div class="card-body tight">
        <?php if ($users === []): ?>
            <div class="empty" style="padding:26px"><?= orbit_icon('users', 34) ?><div>Tidak ada pengguna yang cocok.</div></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Login terakhir</th>
                        <th>Terdaftar</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?= esc($user['name']) ?></strong>
                                <div class="muted small"><?= esc($user['email']) ?></div>
                            </td>
                            <td>
                                <?php
                                $roleBadge = ['admin' => 'badge-primary', 'operator' => 'badge-info', 'viewer' => 'badge-muted'][$user['role']] ?? 'badge-muted';
                                ?>
                                <span class="badge <?= $roleBadge ?> plain"><?= esc(ucfirst($user['role'])) ?></span>
                            </td>
                            <td>
                                <?php if ((int) $user['is_active'] === 1): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="small nowrap"><?= $user['last_login_at'] !== null ? esc(orbit_format_datetime($user['last_login_at'])) : '—' ?></td>
                            <td class="small nowrap"><?= esc(orbit_format_datetime($user['created_at'])) ?></td>
                            <td class="nowrap">
                                <button class="btn btn-outline btn-sm" type="button"
                                        data-edit-user='<?= json_encode([
                                            'id' => (int) $user['id'],
                                            'name' => $user['name'],
                                            'email' => $user['email'],
                                            'role' => $user['role'],
                                            'is_active' => (int) $user['is_active'],
                                        ], JSON_UNESCAPED_UNICODE) ?>'
                                        data-modal-open="user-modal">
                                    <?= orbit_icon('edit', 15) ?> Ubah
                                </button>
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

<div class="modal-root" id="user-modal">
    <div class="modal-card">
        <div class="card-header" id="user-modal-title">Tambah Pengguna</div>
        <div class="card-body">
            <form id="user-form">
                <input type="hidden" name="id">
                <div class="field">
                    <label class="label" for="user-name">Nama <span class="req">*</span></label>
                    <input class="input" type="text" id="user-name" name="name" maxlength="100" required>
                    <div class="error-text"></div>
                </div>
                <div class="field">
                    <label class="label" for="user-email">Email <span class="req">*</span></label>
                    <input class="input" type="email" id="user-email" name="email" maxlength="150" required>
                    <div class="error-text"></div>
                </div>
                <div class="field">
                    <label class="label" for="user-role">Peran <span class="req">*</span></label>
                    <select class="select" id="user-role" name="role">
                        <option value="viewer">Viewer — hanya melihat</option>
                        <option value="operator">Operator — kontrol perangkat</option>
                        <option value="admin">Admin — penuh</option>
                    </select>
                    <div class="hint">Pengguna dengan peran viewer tidak dapat mengirim perintah.</div>
                </div>
                <div class="field">
                    <label class="label" for="user-password">Kata sandi</label>
                    <input class="input" type="password" id="user-password" name="password" minlength="8"
                           autocomplete="new-password" placeholder="Minimal 8 karakter">
                    <div class="hint" id="user-password-hint">Wajib saat membuat pengguna; kosongkan bila tidak diubah.</div>
                    <div class="error-text"></div>
                </div>
                <div class="field" id="user-active-wrap" style="display:none">
                    <label class="checkbox">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Akun aktif
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" type="button" data-modal-close="user-modal">Batal</button>
            <button class="btn" type="submit" form="user-form">Simpan</button>
        </div>
    </div>
</div>

<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
(function () {
  var O = window.orbit;
  var form = document.getElementById('user-form');
  var title = document.getElementById('user-modal-title');
  var editingId = null;

  window.__userFormReset = function () {
    editingId = null;
    form.reset();
    form.querySelector('[name=id]').value = '';
    title.textContent = 'Tambah Pengguna';
    document.getElementById('user-active-wrap').style.display = 'none';
    document.getElementById('user-password-hint').textContent = 'Wajib saat membuat pengguna; kosongkan bila tidak diubah.';
    form.querySelector('[name=password]').required = true;
    O.clearFieldErrors(form);
  };

  document.querySelectorAll('[data-edit-user]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var data = JSON.parse(btn.getAttribute('data-edit-user'));
      window.__userFormReset();
      editingId = data.id;
      title.textContent = 'Ubah Pengguna';
      form.querySelector('[name=name]').value = data.name;
      form.querySelector('[name=email]').value = data.email;
      form.querySelector('[name=role]').value = data.role;
      document.getElementById('user-active-wrap').style.display = '';
      form.querySelector('[name=is_active]').checked = data.is_active === 1;
      document.getElementById('user-password-hint').textContent = 'Kosongkan bila kata sandi tidak diubah.';
      form.querySelector('[name=password]').required = false;
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    O.clearFieldErrors(form);

    var password = form.elements.password.value;
    var payload;
    if (editingId) {
      payload = {
        name: form.elements.name.value.trim(),
        email: form.elements.email.value.trim(),
        role: form.elements.role.value,
        is_active: form.querySelector('[name=is_active]').checked ? 1 : 0
      };
      if (password) { payload.password = password; }
    } else {
      payload = {
        name: form.elements.name.value.trim(),
        email: form.elements.email.value.trim(),
        role: form.elements.role.value,
        password: password
      };
    }

    var url = editingId ? '/api/users/' + editingId : '/api/users';
    var method = editingId ? 'PUT' : 'POST';

    O.fetch(url, { method: method, body: payload }).then(function (res) {
      O.toast(res.message || 'Pengguna disimpan.', 'success');
      O.closeModal('user-modal');
      setTimeout(function () { window.location.reload(); }, 500);
    }).catch(function (err) {
      if (err.errors && Object.keys(err.errors).length) { O.showFieldErrors(form, err.errors); }
      O.toast(err.message, 'error');
    });
  });
})();
</script>
<?php $this->endSection() ?>
