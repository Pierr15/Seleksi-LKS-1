<?php
/**
 * Paginasi sederhana mengikuti query string saat ini.
 * Variabel: $pager (CodeIgniter\Pager\Pager), opsional $uri.
 */
$uri = $uri ?? current_url();
$group = 'default';

try {
    $current = (int) $pager->currentPage($group);
    $pages   = (int) $pager->pageCount($group);
} catch (\Throwable $e) {
    return;
}

if ($pages <= 1) {
    return;
}

$qs = static function (int $page) use ($uri): string {
    $params = array_merge(service('request')->getGet() ?? [], ['page' => $page]);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

    return $uri . '?' . http_build_query($params);
};

$window = 2;
$start  = max(1, $current - $window);
$end    = min($pages, $current + $window);
?>
<nav class="pagination" aria-label="Paginasi">
    <?php if ($current > 1): ?>
        <a href="<?= esc($qs($current - 1)) ?>">‹ Sebelumnya</a>
    <?php endif; ?>

    <?php if ($start > 1): ?>
        <a href="<?= esc($qs(1)) ?>">1</a>
        <?php if ($start > 2): ?><span class="disabled">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $current): ?>
            <span class="active"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= esc($qs($i)) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($end < $pages): ?>
        <?php if ($end < $pages - 1): ?><span class="disabled">…</span><?php endif; ?>
        <a href="<?= esc($qs($pages)) ?>"><?= $pages ?></a>
    <?php endif; ?>

    <?php if ($current < $pages): ?>
        <a href="<?= esc($qs($current + 1)) ?>">Berikutnya ›</a>
    <?php endif; ?>

    <span class="muted small" style="margin-left:auto">Hal. <?= $current ?>/<?= $pages ?></span>
</nav>
