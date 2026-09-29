<?php

declare(strict_types=1);

// Run only from a terminal or GitHub Actions. Never expose database maintenance over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function usage(): never
{
    fwrite(STDERR, "Usage: php bin/migrate.php (--status|--up|--baseline=FILE.sql) --expected-db=NAME\n");
    exit(2);
}

$mode = null;
$baseline = null;
$expectedDb = null;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--status' || $argument === '--up') {
        if ($mode !== null) usage();
        $mode = substr($argument, 2);
    } elseif (str_starts_with($argument, '--baseline=')) {
        if ($mode !== null) usage();
        $mode = 'baseline';
        $baseline = substr($argument, strlen('--baseline='));
    } elseif (str_starts_with($argument, '--expected-db=')) {
        if ($expectedDb !== null) usage();
        $expectedDb = substr($argument, strlen('--expected-db='));
    } else {
        usage();
    }
}
if ($mode === null || $expectedDb === null || !preg_match('/^[a-zA-Z0-9_]+$/D', $expectedDb)) {
    usage();
}
if ($mode === 'baseline' && ($baseline === '' || basename($baseline) !== $baseline || !str_ends_with($baseline, '.sql'))) {
    usage();
}

$settings = require dirname(__DIR__) . '/settings.php';
$config = $settings['mysql'];
if ($config['mysql_database'] !== $expectedDb) {
    fwrite(STDERR, "Configured database does not match --expected-db. No changes made.\n");
    exit(1);
}

$connection = null;
$lockName = 'workflow-migrations-' . substr(hash('sha256', $expectedDb), 0, 32);
$hasLock = false;
$exitCode = 0;
try {
    $connection = mysqli_init();
    $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    $connection->real_connect(
        (string) $config['host'],
        (string) $config['mysql_user'],
        (string) $config['mysql_password'],
        (string) $config['mysql_database'],
        (int) env('DB_PORT', 3306)
    );
    $connection->set_charset('utf8mb4');
    $actualDb = $connection->query('SELECT DATABASE()')->fetch_row()[0];
    if ($actualDb !== $expectedDb) {
        throw new RuntimeException('Connected database does not match --expected-db. No changes made.');
    }

    $lockStatement = $connection->prepare('SELECT GET_LOCK(?, 10)');
    $lockStatement->bind_param('s', $lockName);
    $lockStatement->execute();
    $hasLock = (int) $lockStatement->get_result()->fetch_row()[0] === 1;
    $lockStatement->close();
    if (!$hasLock) {
        throw new RuntimeException('Could not acquire migration lock.');
    }

    $files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);
    $migrations = [];
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Empty or unreadable migration: ' . basename($file));
        }
        $migrations[basename($file)] = ['sql' => $sql, 'checksum' => hash('sha256', $sql)];
    }

    $tableExists = (int) $connection->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'"
    )->fetch_row()[0] === 1;
    if (!$tableExists && $mode !== 'status') {
        $connection->query(
            'CREATE TABLE schema_migrations ('
            . 'filename VARCHAR(255) NOT NULL PRIMARY KEY, '
            . 'checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
            . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $tableExists = true;
    }

    $applied = [];
    if ($tableExists) {
        $rows = $connection->query('SELECT filename, checksum FROM schema_migrations ORDER BY filename');
        while ($row = $rows->fetch_assoc()) {
            $applied[$row['filename']] = $row['checksum'];
        }
        $rows->free();
    }
    foreach ($applied as $filename => $checksum) {
        if (!isset($migrations[$filename])) {
            throw new RuntimeException("Applied migration is missing from Git: $filename");
        }
        if (!hash_equals($checksum, $migrations[$filename]['checksum'])) {
            throw new RuntimeException("Applied migration was changed: $filename");
        }
    }

    echo "Database: $actualDb\n";
    if ($mode === 'status') {
        foreach ($migrations as $filename => $_migration) {
            echo (isset($applied[$filename]) ? 'applied ' : 'pending ') . $filename . "\n";
        }
    } elseif ($mode === 'baseline') {
        if (!isset($migrations[$baseline])) {
            throw new RuntimeException("Unknown migration: $baseline");
        }
        if (isset($applied[$baseline])) {
            echo "Already recorded: $baseline\n";
        } else {
            $insert = $connection->prepare('INSERT INTO schema_migrations (filename, checksum) VALUES (?, ?)');
            $insert->bind_param('ss', $baseline, $migrations[$baseline]['checksum']);
            $insert->execute();
            $insert->close();
            echo "Recorded existing schema without running SQL: $baseline\n";
        }
    } else {
        $latestApplied = $applied === [] ? '' : max(array_keys($applied));
        foreach ($migrations as $filename => $migration) {
            if (isset($applied[$filename])) continue;
            if ($filename < $latestApplied) {
                throw new RuntimeException("Out-of-order migration: $filename");
            }
            // One SQL statement per file. DDL may auto-commit, so a failed migration needs inspection.
            $connection->query($migration['sql']);
            $insert = $connection->prepare('INSERT INTO schema_migrations (filename, checksum) VALUES (?, ?)');
            $insert->bind_param('ss', $filename, $migration['checksum']);
            $insert->execute();
            $insert->close();
            $latestApplied = $filename;
            echo "Applied: $filename\n";
        }
        echo "Migrations complete.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if ($hasLock && $connection instanceof mysqli) {
        $unlock = $connection->prepare('SELECT RELEASE_LOCK(?)');
        $unlock->bind_param('s', $lockName);
        $unlock->execute();
        $unlock->close();
    }
    if ($connection instanceof mysqli) $connection->close();
}
exit($exitCode);
