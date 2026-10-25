<?php if ($centralenShellEnabled): ?>
<div class="centralen-shared-header">
<?php if ($centralenShellData): ?>
<?= $centralenShellData['header_html'] ?>
<?php else: ?>
<header class="site-header centralen-shell-fallback"><a href="<?= centralenShellEscape($centralenShellBase.'/') ?>">Centralen</a><h1 class="shell-app-title">Workflow</h1><p role="status">Den gemensamma headern och chatten kunde inte hämtas. Försök att ladda om sidan.</p></header>
<?php endif; ?>
</div>
<?php endif; ?>
