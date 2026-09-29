<?php

/**
 * Läs in miljövariabler från .env om filen existerar
 */
(function () {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Hoppa över kommentarer och tomma rader
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Dela upp vid första '='
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // Ta bort omgivande enkla eller dubbla citattecken
        if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
            $value = $matches[2];
        }

        // Sätt i getenv, $_ENV och $_SERVER om de inte redan är satta i systemmiljön
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }
    }
})();

if (!function_exists('env')) {
    /**
     * Hämta konfigurationsvärde från miljövariabler med fallback.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        return $default;
    }
}

/* Host & database settings */
$mysql_settings = [
    'host'           => env('DB_HOST', 'localhost'),
    'mysql_user'     => env('DB_USER', 'root'),
    'mysql_database' => env('DB_NAME', 'workflow'),
    'mysql_password' => env('DB_PASS', '')
];

/* Email settings */
$email_settings = [
    'emailServerHost'       => env('SMTP_HOST', 'mailcluster.loopia.se'),
    'emailServerPort'       => (int) env('SMTP_PORT', 587),
    'website_mail'          => env('SMTP_USER', 'noreply@workflow.digitalmaklarna.se'),
    'website_mail_password' => env('SMTP_PASS', '')
];

return [
    'mysql' => $mysql_settings,
    'email' => $email_settings
];