<?php
// Bootstrap before output: the bridge releases the Workflow session before HTTP.
if (!isset($centralenShellEnabled)) {
    $centralenShellEnabled = CentralenIdentity::enabled();
    $centralenShellData = null;
    $centralenShellBase = $centralenShellEnabled ? CentralenIdentity::url('CENTRALEN_URL') : '';
    if ($centralenShellEnabled) {
        try {
            require_once __DIR__.'/../classes/centralenShell.class.php';
            $centralenShellData = (new CentralenShell($centralenIdentity))->request('shell', []);
            if (!is_array($centralenShellData) || !is_string($centralenShellData['header_html'] ?? null) || !is_array($centralenShellData['messages'] ?? null)) {
                throw new RuntimeException('Invalid shared shell');
            }
        } catch (Throwable $error) {
            $centralenShellData = null;
            error_log('Centralen shell unavailable: '.get_class($error));
        }
    }
}

function centralenShellEscape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function centralenShellHead(): void {
    global $centralenShellEnabled, $centralenShellData, $centralenShellBase;
    if (!$centralenShellEnabled) return;
    $asset = static function (string $path) use ($centralenShellBase): string {
        $local = dirname(__DIR__, 3).'/centralen/'.$path;
        $version = is_file($local) ? substr(hash_file('sha256', $local), 0, 12) : '20261025-shell1';
        return centralenShellEscape($centralenShellBase.'/'.$path.'?v='.$version);
    };
    foreach (['tokens','shell','shell-theme','profile','chat','operations'] as $css) {
        echo '<link rel="stylesheet" href="'.$asset('resources/site/style_one/css/'.$css.'.css').'">';
    }
    $workflowBase = CentralenIdentity::url('WORKFLOW_URL');
    echo '<link rel="stylesheet" href="'.centralenShellEscape($workflowBase.'/ui/style/css/centralen-shell.css?v='.substr(hash_file('sha256', __DIR__.'/../../ui/style/css/centralen-shell.css'),0,12)).'">';
    echo '<meta name="csrf-token" content="'.centralenShellEscape((string)($_SESSION['csrf_token'] ?? '')).'">';
    if ($centralenShellData) {
        $data = ['locale'=>$centralenShellData['locale'], 'messages'=>$centralenShellData['messages'], 'options'=>['locales'=>[], 'endpoint'=>'']];
        echo '<script type="application/json" id="site-language-data">'.json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR).'</script>';
        echo '<script defer src="'.$asset('resources/js/language.js').'"></script>';
        echo '<script type="module" crossorigin="anonymous" src="'.$asset('resources/site/style_one/js/chat-dock.js').'"></script>';
    }
    echo '<script defer src="'.centralenShellEscape($workflowBase.'/ui/js/centralen-shell.js?v='.substr(hash_file('sha256',__DIR__.'/../../ui/js/centralen-shell.js'),0,12)).'"></script>';
}

function centralenShellBodyAttributes(): string {
    global $centralenShellEnabled, $centralenShellData;
    if (!$centralenShellEnabled) return '';
    $palette = $centralenShellData['palette'] ?? 'default';
    return ' data-shell-theme="'.centralenShellEscape(in_array($palette,['default','ocean','forest','plum'],true)?$palette:'default').'"';
}
