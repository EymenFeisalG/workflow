<?php
// Run only against a temporary database created by this test.
if (getenv('WORKFLOW_ALLOW_INTEGRATION_TEST') !== '1') {
    fwrite(STDERR, "Set WORKFLOW_ALLOW_INTEGRATION_TEST=1 to run this isolated database test.\n");
    exit(2);
}

require __DIR__ . '/../settings.php';
require __DIR__ . '/../php/classes/taskNotifications.class.php';
require __DIR__ . '/../php/classes/taskThread.class.php';

function checkThread(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = $mysql_settings['mysql_database'];
checkThread((bool)preg_match('/^[A-Za-z0-9_]+$/', $source), 'Invalid source database name');
$name = 'workflow2_thread_test_' . bin2hex(random_bytes(4));
$admin = new mysqli($mysql_settings['host'], $mysql_settings['mysql_user'], $mysql_settings['mysql_password'], $source);
$testDb = null;
$created = false;

try {
    $admin->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
    $created = true;
    foreach (['query', 'users', 'task_notifications'] as $table) {
        $admin->query("CREATE TABLE `$name`.`$table` LIKE `$source`.`$table`");
    }
    $testDb = new mysqli($mysql_settings['host'], $mysql_settings['mysql_user'], $mysql_settings['mysql_password'], $name);
    $testDb->query('ALTER TABLE `query` ENGINE = InnoDB');
    $testDb->query('ALTER TABLE task_notifications ENGINE = InnoDB');
    $testDb->query(file_get_contents(__DIR__ . '/../migrations/20260930_01_task_messages.sql'));
    $column = $testDb->query("SHOW COLUMNS FROM task_notifications LIKE 'message_id'");
    if ($column->num_rows === 0) $testDb->query(file_get_contents(__DIR__ . '/../migrations/20260930_02_task_notification_message_id.sql'));
    $testDb->query("INSERT INTO users (id, username, email, password, user_role) VALUES
        (101, 'Creator', 'creator@example.test', 'x', 1),
        (102, 'Worker', 'worker@example.test', 'x', 1),
        (103, 'Other', 'other@example.test', 'x', 1),
        (104, 'New worker', 'new@example.test', 'x', 1)");
    $testDb->query("INSERT INTO `query` (id, Name, Hostname, Info, status, admin, password, Prio, worker_name_id, date, Path, creator) VALUES
        (201, 'Delegated', '', '', 'ongoing', '', '', 'normal', 102, '2026-09-29', 'NONE', 101)");

    $notifications = new TaskNotifications($testDb);
    $thread = new TaskThread($testDb, $notifications);
    $first = $thread->send(201, 101, false, "Started work\nNext step");
    checkThread($first > 0, 'Message was not created');
    checkThread($notifications->listFor(102)['unread'] === 1, 'Worker did not get notification');
    checkThread($notifications->listFor(101)['unread'] === 0, 'Sender got own notification');
    $chatNotificationId = (int)$notifications->listFor(102)['items'][0]['id'];
    $notifications->markRead(102, [$chatNotificationId]);
    checkThread($notifications->listFor(102)['unread'] === 1, 'Notification click cleared thread message');
    $notifications->emitTo(201, 101, 'updated', '', [102]);
    $notifications->markRead(102, [], true);
    checkThread($notifications->listFor(102)['unread'] === 1, 'Read all did not preserve only the thread notification');
    $page = $thread->page(201, 102, false);
    checkThread(count($page['messages']) === 1 && $page['messages'][0]['id'] === $first, 'Worker cannot see message');
    $thread->markViewed(201, 102, false, [$first]);
    checkThread($notifications->listFor(102)['unread'] === 0, 'Viewing message did not clear notification');

    $reply = $thread->send(201, 102, false, 'Reply');
    checkThread($reply > $first && $notifications->listFor(101)['unread'] === 1, 'Reply notification failed');
    try { $thread->page(201, 103, false); throw new RuntimeException('Unrelated user read thread'); }
    catch (DomainException $expected) {}
    try { $thread->send(201, 103, false, 'No access'); throw new RuntimeException('Unrelated user wrote thread'); }
    catch (DomainException $expected) {}
    checkThread(count($thread->page(201, 103, true)['messages']) === 2, 'Show-all access failed');

    $testDb->query("UPDATE `query` SET worker_name_id = 104 WHERE id = 201");
    try { $thread->page(201, 102, false); throw new RuntimeException('Former worker retained access'); }
    catch (DomainException $expected) {}
    $newMessage = $thread->send(201, 101, false, 'For new worker');
    checkThread($notifications->listFor(104)['unread'] === 1, 'New worker did not get notification');
    checkThread($notifications->listFor(102)['unread'] === 0, 'Former worker has visible thread notification');
    checkThread($newMessage > $reply, 'Message ordering failed');

    $testDb->query("UPDATE `query` SET status = 'pending' WHERE id = 201");
    checkThread($thread->send(201, 104, false, 'Pending question') > $newMessage, 'Pending task is not writable');
    foreach (['completed', 'canceled'] as $status) {
        $testDb->query("UPDATE `query` SET status = '$status' WHERE id = 201");
        checkThread(!$thread->page(201, 101, false)['canWrite'], 'Closed task is writable');
        try { $thread->send(201, 101, false, 'Should fail'); throw new RuntimeException('Closed task accepted message'); }
        catch (DomainException $expected) {}
    }

    $testDb->query("UPDATE `query` SET status = 'ongoing' WHERE id = 201");
    for ($i = 0; $i < 55; $i++) $thread->send(201, 101, false, 'Page ' . $i);
    $latest = $thread->page(201, 104, false);
    checkThread(count($latest['messages']) === 50 && $latest['hasMore'], 'Latest page is incorrect');
    $older = $thread->page(201, 104, false, $latest['messages'][0]['id']);
    checkThread(count($older['messages']) === 9 && !$older['hasMore'], 'Older page is incorrect');
    $newer = $thread->page(201, 104, false, 0, $older['messages'][8]['id']);
    checkThread(count($newer['messages']) === 50, 'New message paging is incorrect');

    $beforeRollback = (int)$testDb->query('SELECT COUNT(*) FROM task_messages')->fetch_row()[0];
    $testDb->query('DROP TABLE task_notifications');
    try { $thread->send(201, 101, false, 'Rollback'); throw new RuntimeException('Send succeeded without notification table'); }
    catch (mysqli_sql_exception $expected) {}
    checkThread((int)$testDb->query('SELECT COUNT(*) FROM task_messages')->fetch_row()[0] === $beforeRollback, 'Message did not roll back with notification failure');
    echo "Task thread integration: passed\n";
} finally {
    if ($testDb) $testDb->close();
    if ($created) $admin->query("DROP DATABASE `$name`");
    $admin->close();
}
