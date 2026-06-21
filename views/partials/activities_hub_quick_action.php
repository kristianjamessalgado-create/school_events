<?php
/** @var array<int, array<string, mixed>> $activities_hub_events */
$activities_hub_events = $activities_hub_events ?? [];
$activities_hub_count = count($activities_hub_events);
$activities_hub_btn_class = $activities_hub_btn_class ?? 'w-100 text-start border-0 bg-transparent';
$activities_hub_show_chevron = !empty($activities_hub_show_chevron);
$activities_hub_sa_style = !empty($activities_hub_sa_style);
$activities_hub_btn_classes = $activities_hub_sa_style
    ? trim($activities_hub_btn_class)
    : trim('action-btn ' . $activities_hub_btn_class);
$activities_hub_url = BASE_URL . '/activities_hub.php';
?>
<a href="<?= htmlspecialchars($activities_hub_url) ?>" class="<?= htmlspecialchars($activities_hub_btn_classes) ?> text-decoration-none">
    <?php if ($activities_hub_sa_style): ?>
        <span><i class="fas fa-th-large me-2"></i>Activities hub<?php if ($activities_hub_count > 0): ?> <span class="badge bg-primary ms-1"><?= $activities_hub_count > 99 ? '99+' : $activities_hub_count ?></span><?php endif; ?></span>
        <?php if ($activities_hub_show_chevron): ?><i class="fas fa-chevron-right"></i><?php endif; ?>
    <?php else: ?>
        <i class="fas fa-th-large"></i>
        <span>Activities hub</span>
        <?php if ($activities_hub_count > 0): ?>
            <span class="badge bg-primary ms-1"><?= $activities_hub_count > 99 ? '99+' : $activities_hub_count ?></span>
        <?php endif; ?>
        <?php if ($activities_hub_show_chevron): ?>
            <i class="fas fa-chevron-right ms-auto"></i>
        <?php endif; ?>
    <?php endif; ?>
</a>
