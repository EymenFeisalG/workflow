<?php
// Kör bara uttryckligen mot en isolerad testdatabas.
if (getenv('WORKFLOW_ALLOW_INTEGRATION_TEST') !== '1') {
    fwrite(STDERR, "Set WORKFLOW_ALLOW_INTEGRATION_TEST=1 to run this isolated database test.\n");
    exit(2);
}

require __DIR__ . '/../settings.php';
require __DIR__ . '/../php/classes/database.class.php';
require __DIR__ . '/../php/classes/taskNotifications.class.php';
require __DIR__ . '/../php/classes/main.class.php';

function verifyStep(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = $mysql_settings['mysql_database'];
verifyStep((bool)preg_match('/^[A-Za-z0-9_]+$/', $source), 'Invalid source database name');
$name = 'workflow2_steps_test_' . bin2hex(random_bytes(4));
$admin = new mysqli($mysql_settings['host'], $mysql_settings['mysql_user'], $mysql_settings['mysql_password'], $source);
$testDb = null;
$created = false;

try {
    $admin->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
    $created = true;
    foreach (['query', 'steps', 'users', 'task_notifications'] as $table) {
        $admin->query("CREATE TABLE `$name`.`$table` LIKE `$source`.`$table`");
    }
    $testDb = new mysqli($mysql_settings['host'], $mysql_settings['mysql_user'], $mysql_settings['mysql_password'], $name);
    database::$mysql = $testDb;
    $testDb->query("INSERT INTO users (id, username, email, password, user_role) VALUES
        (101, 'Creator', 'creator@example.test', 'x', 1),
        (102, 'Worker', 'worker@example.test', 'x', 1),
        (103, 'Other', 'other@example.test', 'x', 1)");
    $testDb->query("INSERT INTO `query` (id, Name, Hostname, Info, status, admin, password, Prio, worker_name_id, date, Path, creator) VALUES
        (201, 'Mine', '', '', 'ongoing', '', '', 'normal', 101, '2026-09-29', 'NONE', 101),
        (202, 'Delegated', '', '', 'ongoing', '', '', 'normal', 102, '2026-09-29', 'NONE', 101)");
    $testDb->query("INSERT INTO steps (id, step, `desc`, orderId, creator, completed) VALUES
        (301, 1, 'Own <step>', 201, 101, 0),
        (302, 1, 'Delegated step', 202, 101, 0)");

    $main = new main();
    $_SESSION['user']['userid'] = 101;
    verifyStep($main->setStepCompletion(301, true)['success'], 'Owner cannot complete own step');
    verifyStep((int)$testDb->query('SELECT completed+0 FROM steps WHERE id=301')->fetch_row()[0] === 1, 'Own step did not persist');
    verifyStep($main->setStepCompletion(301, true)['success'], 'Repeated completion failed');
    verifyStep($main->setStepCompletion(301, false)['success'], 'Owner cannot reopen own step');
    verifyStep($main->setStepCompletion(302, true)['success'], 'Creator cannot complete delegated step');
    verifyStep((int)$testDb->query("SELECT COUNT(*) FROM task_notifications WHERE recipient_id=102 AND event_type='step_completed'")->fetch_row()[0] === 1, 'Wrong notification count');

    $_SESSION['user']['userid'] = 102;
    verifyStep($main->setStepCompletion(302, false)['success'], 'Worker cannot reopen delegated step');
    verifyStep($main->setStepCompletion(302, true)['success'], 'Worker cannot complete delegated step');

    $_SESSION['user']['userid'] = 103;
    verifyStep(!$main->setStepCompletion(302, false)['success'], 'Unrelated user changed step');
    $_SESSION['user']['userid'] = 101;
    $testDb->query("UPDATE `query` SET status='pending' WHERE id=201");
    verifyStep(!$main->setStepCompletion(301, true)['success'], 'Pending task step changed');
    ob_start();
    $main->getSteps(201);
    $html = ob_get_clean();
    verifyStep(str_contains($html, 'Own &lt;step&gt;'), 'Step text was not escaped');
    verifyStep(str_contains($html, 'disabled'), 'Pending task checkbox is editable');
    echo "Task steps integration: passed\n";
} finally {
    if ($testDb) $testDb->close();
    database::$mysql = null;
    if ($created) $admin->query("DROP DATABASE `$name`");
    $admin->close();
}
