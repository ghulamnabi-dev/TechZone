<?php
/**
 * "In progress" empty state for pages whose rebuild lands in a later phase.
 * Set $wip = ['icon' => 'fa-…', 'title' => …, 'text' => …, 'items' => [[icon, text], …], 'link' => [href, label]].
 */
$wip = $wip ?? [];
$we = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<div class="panel">
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fas <?= $we($wip['icon'] ?? 'fa-person-digging') ?>" aria-hidden="true"></i></div>
        <h2 class="empty-state-title"><?= $we($wip['title'] ?? 'In progress') ?></h2>
        <p class="empty-state-text"><?= $we($wip['text'] ?? 'This page is being rebuilt.') ?></p>
        <?php if (!empty($wip['items'])): ?>
            <ul class="wip-list">
                <?php foreach ($wip['items'] as [$icon, $text]): ?>
                    <li><i class="fas <?= $we($icon) ?>" aria-hidden="true"></i><span><?= $we($text) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($wip['link'])): ?>
            <a class="btn btn-secondary" href="<?= $we($wip['link'][0]) ?>"><?= $we($wip['link'][1]) ?></a>
        <?php endif; ?>
    </div>
</div>
