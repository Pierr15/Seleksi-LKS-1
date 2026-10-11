<?php
/**
 * Layout utama halaman terautentikasi.
 * Variabel: $title, $active, $user, $csrfHash, $theme, $isAdmin, $canControl
 */
$user = $user ?? null;
$active = $active ?? '';
$isAdmin = $isAdmin ?? (($user['role'] ?? '') === 'admin');
$canControl = $canControl ?? in_array($user['role'] ?? '', ['admin', 'operator'], true);

$navMain = [
    ['key' => 'dashboard', 'href' => '/dashboard', 'icon' => 'home', 'label' => 'Dashboard'],
    ['key' => 'denah', 'href' => '/denah', 'icon' => 'map', 'label' => 'Denah Gedung'],
    ['key' => 'ruangan', 'href' => '/ruangan', 'icon' => 'door', 'label' => 'Ruangan'],
    ['key' => 'perangkat', 'href' => '/perangkat', 'icon' => 'cpu', 'label' => 'Perangkat'],
    ['key' => 'otomatisasi', 'href' => '/otomatisasi', 'icon' => 'bolt', 'label' => 'Otomatisasi'],
    ['key' => 'log', 'href' => '/log', 'icon' => 'list', 'label' => 'Log Aktivitas'],
];

$navAdmin = [];
if ($isAdmin) {
    $navAdmin = [
        ['key' => 'pengguna', 'href' => '/pengguna', 'icon' => 'users', 'label' => 'Pengguna'],
        ['key' => 'pengaturan', 'href' => '/pengaturan', 'icon' => 'gear', 'label' => 'Pengaturan'],
    ];
}

$navItem = static function (array $item) use ($active): string {
    $isActive = $active === $item['key'];
    $ic = orbit_icon($item['icon'], 19);

    return '<a class="nav-item' . ($isActive ? ' active' : '') . '" href="' . $item['href'] . '" title="' . esc($item['label']) . '">'
        . $ic . '<span>' . esc($item['label']) . '</span></a>';
};
?>
<!doctype html>
<html lang="id" data-theme="<?= esc($theme ?? 'system') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= esc($csrfHash ?? '') ?>">
    <title><?= esc($title ?? 'ORBIT Building') ?> · ORBIT Building</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body data-theme="<?= esc($theme ?? 'system') ?>"
      data-csrf="<?= esc($csrfHash ?? '') ?>"
      data-role="<?= esc($user['role'] ?? '') ?>"
      data-page="<?= esc($active) ?>">

<?php if (session('success')): ?>
    <span data-flash="success" hidden><?= esc(session('success')) ?></span>
<?php endif; ?>
<?php if (session('error')): ?>
    <span data-flash="error" hidden><?= esc(session('error')) ?></span>
<?php endif; ?>

<div class="shell">

    <aside class="sidebar">
        <div class="brand" title="ORBIT Building">
            <span class="brand-mark">OB</span>
            <span>ORBIT Building<small>Smart Building · SMKN 1 Adiwerna</small></span>
        </div>

        <nav class="nav">
            <div class="nav-label">Menu Utama</div>
            <?php foreach ($navMain as $item): ?>
                <?= $navItem($item) ?>
            <?php endforeach; ?>

            <?php if ($navAdmin !== []): ?>
                <div class="nav-label">Administrasi</div>
                <?php foreach ($navAdmin as $item): ?>
                    <?= $navItem($item) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>

        <div class="sidebar-foot">
            Zona waktu tampilan <strong>Asia/Jakarta</strong><br>
            Penyimpanan data <strong>UTC</strong>
        </div>
    </aside>

    <div class="overlay" data-drawer-toggle></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" data-drawer-toggle aria-label="Buka menu">
                <?= orbit_icon('menu') ?>
            </button>
            <h1><?= esc($title ?? '') ?></h1>
            <div class="spacer"></div>

            <button class="icon-btn" data-theme-toggle aria-label="Ganti tema">
                <?= orbit_icon('system') ?>
            </button>

            <div class="user-chip">
                <span class="avatar"><?= esc(strtoupper(substr($user['name'] ?? '?', 0, 1))) ?></span>
                <span class="who">
                    <?= esc($user['name'] ?? '') ?><br>
                    <span class="role"><?= esc(strtoupper($user['role'] ?? '')) ?></span>
                </span>
            </div>

            <form method="post" action="<?= site_url('logout') ?>" style="display:inline">
                <input type="hidden" name="<?= csrf_token() ?>" value="<?= esc($csrfHash ?? '') ?>">
                <button class="icon-btn" type="submit" title="Keluar" aria-label="Keluar">
                    <?= orbit_icon('logout') ?>
                </button>
            </form>
        </header>

        <main class="content">
            <?= $this->renderSection('content') ?>
        </main>
    </div>
</div>

<nav class="bottomnav" aria-label="Navigasi utama">
    <div class="inner">
        <?php
        $bottom = [
            ['key' => 'dashboard', 'href' => '/dashboard', 'icon' => 'home', 'label' => 'Beranda'],
            ['key' => 'denah', 'href' => '/denah', 'icon' => 'map', 'label' => 'Denah'],
            ['key' => 'ruangan', 'href' => '/ruangan', 'icon' => 'door', 'label' => 'Ruangan'],
            ['key' => 'perangkat', 'href' => '/perangkat', 'icon' => 'cpu', 'label' => 'Perangkat'],
        ];
        foreach ($bottom as $item):
            $isActive = $active === $item['key'];
            ?>
            <a href="<?= $item['href'] ?>" class="<?= $isActive ? 'active' : '' ?>">
                <?= orbit_icon($item['icon'], 21) ?><span><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
        <button data-drawer-toggle type="button">
            <?= orbit_icon('menu', 21) ?><span>Lainnya</span>
        </button>
    </div>
</nav>

<div class="toast-wrap" aria-live="polite"></div>

<script src="<?= base_url('assets/js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
