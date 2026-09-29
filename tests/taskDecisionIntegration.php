<?php
// Run only against a temporary database created by this test.
if (getenv('WORKFLOW_ALLOW_INTEGRATION_TEST') !== '1') {
    fwrite(STDERR, "Set WORKFLOW_ALLOW_INTEGRATION_TEST=1 to run this isolated database test.\n");
    exit(2);
}

require __DIR__ . '/../settings.php';
require __DIR__ . '/../php/classes/database.class.php';
require __DIR__ . '/../php/classes/taskNotifications.class.php';
require __DIR__ . '/../php/classes/main.class.php';

function verifyDecision(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = $mysql_settings['mysql_database'];
verifyDecision((bool)preg_match('/^[A-Za-z0-9_]+$/', $source), 'Invalid source database name');
$name = 'workflow2_decision_test_' . bin2hex(random_bytes(4));
$admin = new mysqli($mysql_settings['host'], $mysql_settings['mysql_user'], $mysql_settings['mysql_password'], $source);
$testDb = null;
$created = false;

try {
    $admin->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
    $created = true;
    foreach (['query', 'users', 'task_notifications', 'task_messages'] as $table) {
        $admin->query("CREATE TABLE `$name`.`$table` LIKE `$source`.`$table`");
    }
    $testDb = new mysqli($mysql_settings['host'], $mysql_settings['mysql_user'], $mysql_settings['mysql_password'], $name);
    database::$mysql = $testDb;
    $testDb->query("INSERT INTO users (id, username, email, password, user_role) VALUES
        (101, 'Creator', 'creator@example.test', 'x', 1),
        (102, 'Worker', 'worker@example.test', 'x', 1)");
    $testDb->query("INSERT INTO `query` (id, Name, Hostname, Info, status, admin, password, Prio, worker_name_id, date, Path, creator) VALUES
        (201, 'Decision', '', '', 'ongoing', '', '', 'normal', 102, '2026-09-30', 'NONE', 101),
        (202, 'Rollback', '', '', 'ongoing', '', '', 'normal', 102, '2026-09-30', 'NONE', 101)");

    $main = new main();
    $_SESSION = ['user' => ['userid' => 102], 'rights' => []];
    $main->submitOrderDecision(201, 'Redo för granskning', 'attest');
    $order = $testDb->query('SELECT status FROM `query` WHERE id = 201')->fetch_assoc();
    verifyDecision($order['status'] === 'pending', 'Attest did not set pending status');
    $first = $testDb->query('SELECT author_id, body, source FROM task_messages WHERE order_id = 201')->fetch_assoc();
    verifyDecision((int)$first['author_id'] === 102 && $first['body'] === 'Redo för granskning' && $first['source'] === 'attest', 'Attest comment was not stamped');

    $_SESSION['user']['userid'] = 101;
    $main->submitOrderDecision(201, 'Komplettera underlaget', 'deny');
    $order = $testDb->query('SELECT status, messageToDev FROM `query` WHERE id = 201')->fetch_assoc();
    verifyDecision($order['status'] === 'rework' && $order['messageToDev'] === 'Komplettera underlaget', 'Rework did not save status and comment');
    $second = $testDb->query('SELECT author_id, body, source FROM task_messages WHERE order_id = 201 ORDER BY id DESC LIMIT 1')->fetch_assoc();
    verifyDecision((int)$second['author_id'] === 101 && $second['body'] === 'Komplettera underlaget' && $second['source'] === 'rework', 'Rework comment was not stamped');

    $_SESSION['user']['userid'] = 102;
    $main->submitOrderDecision(201, '', 'attest');
    verifyDecision((int)$testDb->query('SELECT COUNT(*) FROM task_messages WHERE order_id = 201')->fetch_row()[0] === 2, 'Empty comment created a message');
    try { $main->submitOrderDecision(201, 'Duplicate', 'attest'); throw new RuntimeException('Duplicate attest was accepted'); }
    catch (DomainException $expected) {}

    $testDb->query("ALTER TABLE task_messages ADD CONSTRAINT reject_stamped CHECK (order_id <> 202 OR source = 'discussion')");
    try { $main->submitOrderDecision(202, 'Must roll back', 'attest'); throw new RuntimeException('Failed message insert was accepted'); }
    catch (mysqli_sql_exception $expected) {}
    verifyDecision($testDb->query('SELECT status FROM `query` WHERE id = 202')->fetch_assoc()['status'] === 'ongoing', 'Status did not roll back with message');
    verifyDecision((int)$testDb->query('SELECT COUNT(*) FROM task_notifications WHERE order_id = 202')->fetch_row()[0] === 0, 'Notifications did not roll back with message');
    echo "Task decision integration: passed\n";
} finally {
    if ($testDb) $testDb->close();
    if ($created) $admin->query("DROP DATABASE `$name`");
    $admin->close();
}
