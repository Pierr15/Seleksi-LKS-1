<?php
/** Halaman login (tanpa sidebar). */
$flashSuccess = session('success');
$flashError = session('error');
$flashWarn = session('login_required');
?>
<!doctype html>
<html lang="id" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <title>Masuk · ORBIT Building</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body data-theme="system" data-csrf="<?= esc(csrf_hash()) ?>" data-role="" data-page="login">

<div class="login-page">
    <section class="login-hero">
        <div>
            <div class="brand" style="padding:0 0 26px;color:#fff">
                <span class="brand-mark">OB</span>
                <span>ORBIT Building<small style="color:rgba(255,255,255,.75)">Smart Building Management</small></span>
            </div>
            <h1>Kelola gedung sekolah dalam satu papan kendali.</h1>
            <p>Pantau okupansi ruangan, kontrol perangkat, dan jalankan aturan otomatis hemat energi
                untuk SMK Negeri 1 Adiwerna.</p>
        </div>
        <div class="feat">
            <div><?= orbit_icon('map', 18) ?> Denah 2 lantai dengan posisi perangkat</div>
            <div><?= orbit_icon('bolt', 18) ?> Otomatisasi & override manual</div>
            <div><?= orbit_icon('list', 18) ?> Log aktivitas lengkap & dapat difilter</div>
            <div><?= orbit_icon('lock', 18) ?> Peran Admin, Operator, dan Viewer</div>
        </div>
    </section>

    <section class="login-form-wrap">
        <div class="login-card">
            <h2>Selamat datang kembali</h2>
            <p class="sub">Masuk dengan akun ORBIT Building Anda.</p>

            <?php if ($flashError): ?>
                <div class="alert alert-danger"><?= orbit_icon('alert') ?><span><?= esc($flashError) ?></span></div>
            <?php elseif ($flashWarn): ?>
                <div class="alert alert-warn"><?= orbit_icon('info') ?><span><?= esc($flashWarn) ?></span></div>
            <?php elseif ($flashSuccess): ?>
                <div class="alert alert-success"><?= orbit_icon('check') ?><span><?= esc($flashSuccess) ?></span></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" action="<?= site_url('login') ?>" id="login-form" novalidate>
                        <input type="hidden" name="<?= csrf_token() ?>" value="<?= esc(csrf_hash()) ?>">

                        <div class="field">
                            <label class="label" for="email">Email <span class="req">*</span></label>
                            <input class="input" type="email" id="email" name="email"
                                   value="<?= esc(old('email')) ?>" placeholder="admin@orbit.local"
                                   autocomplete="username" required autofocus>
                            <div class="error-text"></div>
                        </div>

                        <div class="field">
                            <label class="label" for="password">Password <span class="req">*</span></label>
                            <input class="input" type="password" id="password" name="password"
                                   autocomplete="current-password" placeholder="••••••••" required>
                            <div class="error-text"></div>
                        </div>

                        <button class="btn btn-block" type="submit">
                            <?= orbit_icon('logout') ?> Masuk
                        </button>
                    </form>

                    <p class="hint mt-3 mb-0">
                        Akun awal tercantum di <span class="mono">.env</span> (SEED_ADMIN_EMAIL).
                        Akun terkunci 15 menit setelah 5 kali gagal login.
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="toast-wrap" aria-live="polite"></div>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
