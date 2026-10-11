<?= $this->extend('layouts/app') ?>

<?php $this->section('content') ?>

<div class="page-head">
    <div>
        <h2>Log Aktivitas</h2>
        <p><?= count($logs) ?> entri ditampilkan · waktu disimpan UTC, ditampilkan Asia/Jakarta</p>
    </div>
    <div class="actions">
        <a class="btn btn-outline btn-sm" href="<?= site_url('log') ?>">Reset</a>
    </div>
</div>

<form class="filters" method="get" action="<?= site_url('log') ?>">
    <div class="field">
        <label class="label" for="mulai">Dari tanggal</label>
        <input class="input" type="date" id="mulai" name="mulai" value="<?= esc($filters['mulai']) ?>">
    </div>
    <div class="field">
        <label class="label" for="akhir">Sampai tanggal</label>
        <input class="input" type="date" id="akhir" name="akhir" value="<?= esc($filters['akhir']) ?>">
    </div>
    <div class="field">
        <label class="label" for="pengguna">Pengguna</label>
        <select class="select" id="pengguna" name="pengguna">
            <option value="">Semua</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= (int) $user['id'] ?>" <?= $filters['pengguna'] === (int) $user['id'] ? 'selected' : '' ?>>
                    <?= esc($user['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="lantai">Lantai</label>
        <select class="select" id="lantai" name="lantai">
            <option value="">Semua</option>
            <?php foreach ($floors as $floor): ?>
                <option value="<?= (int) $floor['id'] ?>" <?= $filters['lantai'] === (int) $floor['id'] ? 'selected' : '' ?>>
                    <?= esc($floor['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="ruangan">Ruangan</label>
        <select class="select" id="ruangan" name="ruangan">
            <option value="">Semua</option>
            <?php foreach ($rooms as $room): ?>
                <option value="<?= (int) $room['id'] ?>" <?= $filters['ruangan'] === (int) $room['id'] ? 'selected' : '' ?>>
                    <?= esc($room['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="perangkat">ID perangkat</label>
        <input class="input" type="number" id="perangkat" name="perangkat" min="1"
               value="<?= esc((string) $filters['perangkat']) ?>" placeholder="contoh: 12">
    </div>
    <div class="field">
        <label class="label" for="aksi">Aksi</label>
        <select class="select" id="aksi" name="aksi">
            <option value="">Semua</option>
            <?php foreach ($actionOptions as $key => $label): ?>
                <option value="<?= esc($key) ?>" <?= $filters['aksi'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="hasil">Hasil</label>
        <select class="select" id="hasil" name="hasil">
            <option value="">Semua</option>
            <option value="success" <?= $filters['hasil'] === 'success' ? 'selected' : '' ?>>Sukses</option>
            <option value="failure" <?= $filters['hasil'] === 'failure' ? 'selected' : '' ?>>Gagal</option>
        </select>
    </div>
    <div class="field">
        <label class="label" for="sumber">Sumber</label>
        <select class="select" id="sumber" name="sumber">
            <option value="">Semua</option>
            <option value="manual" <?= $filters['sumber'] === 'manual' ? 'selected' : '' ?>>Manual</option>
            <option value="automation" <?= $filters['sumber'] === 'automation' ? 'selected' : '' ?>>Otomatisasi</option>
            <option value="scheduler" <?= $filters['sumber'] === 'scheduler' ? 'selected' : '' ?>>Scheduler</option>
        </select>
    </div>
    <button class="btn" type="submit"><?= orbit_icon('filter', 16) ?> Terapkan</button>
</form>

<div class="card">
    <div class="card-body tight">
        <?php if ($logs === []): ?>
            <div class="empty" style="padding:28px"><?= orbit_icon('list', 34) ?><div>Tidak ada log yang cocok dengan filter.</div></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Waktu (WIB)</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>Target</th>
                        <th>Hasil</th>
                        <th>Sumber</th>
                        <th>Detail</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="mono small nowrap"><?= esc(orbit_format_datetime($log['created_at'], 'd M Y, H:i:s')) ?></td>
                            <td><?= esc($log['user_name'] ?? 'sistem') ?></td>
                            <td>
                                <strong><?= esc($actionOptions[$log['action']] ?? $log['action']) ?></strong>
                                <div class="muted small mono"><?= esc($log['action']) ?></div>
                            </td>
                            <td>
                                <?php if ($log['device_name'] !== null): ?>
                                    <a href="<?= site_url('perangkat/' . $log['device_id']) ?>"><?= esc($log['device_name']) ?></a>
                                <?php elseif ($log['room_name'] !== null): ?>
                                    <a href="<?= site_url('ruangan/' . $log['room_id']) ?>"><?= esc($log['room_name']) ?></a>
                                <?php elseif ($log['floor_name'] !== null): ?>
                                    <?= esc($log['floor_name']) ?>
                                <?php else: ?>
                                    <span class="muted"><?= esc($log['target_type']) ?> #<?= (int) $log['target_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $log['result'] === 'success' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $log['result'] === 'success' ? 'Sukses' : 'Gagal' ?>
                                </span>
                            </td>
                            <td><?= esc($log['source']) ?></td>
                            <td class="small" style="max-width:260px">
                                <?php if ($log['details_json'] !== null): ?>
                                    <details>
                                        <summary class="muted">Lihat</summary>
                                        <code class="mono small" style="display:block;margin-top:4px;white-space:pre-wrap"><?= esc(
                                            (string) json_encode(json_decode($log['details_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                        ) ?></code>
                                    </details>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                                <?php if ($log['ip_address'] !== null): ?>
                                    <div class="muted small mono"><?= esc($log['ip_address']) ?></div>
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

<?php $this->endSection() ?>
