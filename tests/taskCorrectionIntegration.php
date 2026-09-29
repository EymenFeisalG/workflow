<?php
// Kör bara uttryckligen: $env:WORKFLOW_ALLOW_INTEGRATION_TEST='1'; php tests/taskCorrectionIntegration.php
if (getenv('WORKFLOW_ALLOW_INTEGRATION_TEST') !== '1') {
    fwrite(STDERR, "Set WORKFLOW_ALLOW_INTEGRATION_TEST=1 to run this isolated database test.\n");
    exit(2);
}

require __DIR__ . '/../settings.php';
require __DIR__ . '/../php/classes/taskNotifications.class.php';
require __DIR__ . '/../php/classes/taskCorrection.class.php';

function verify(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = $mysql_settings['mysql_database'];
verify((bool)preg_match('/^[A-Za-z0-9_]+$/', $source), 'Invalid source database name');
$name = 'workflow2_corr_test_' . bin2hex(random_bytes(4));
$admin = new mysqli($mysql_settings['host'], $mysql_settings['mysql_user'], $mysql_settings['mysql_password'], $source);
$db = null;
$created = false;

try {
    $admin->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
    $created = true;
    foreach (['query', 'steps', 'images', 'users', 'task_notifications'] as $table) {
        $admin->query("CREATE TABLE `$name`.`$table` LIKE `$source`.`$table`");
    }
    $db = new mysqli($mysql_settings['host'], $mysql_settings['mysql_user'], $mysql_settings['mysql_password'], $name);
    $db->query("INSERT INTO users (id, username, email, password, user_role) VALUES
        (101, 'Creator', 'creator@example.test', 'x', 1),
        (102, 'Recipient', 'recipient@example.test', 'x', 1),
        (103, 'Other', 'other@example.test', 'x', 1)");
    $db->query("INSERT INTO `query` (Name, Hostname, Info, status, admin, password, Prio, worker_name_id, date, Path, creator)
        VALUES ('Original', '', 'Original description', 'ongoing', 'login', 'secret', 'normal', 101, '2026-09-29', 'NONE', 101)");
    $orderId = (int)$db->insert_id;
    $path = 'media/taskcorr_' . bin2hex(random_bytes(8));
    $db->query("UPDATE `query` SET Path = '$path' WHERE id = $orderId");
    $db->query("INSERT INTO steps (step, `desc`, orderId, creator, completed) VALUES (1, 'Original step', $orderId, 101, 0)");
    $stepId = (int)$db->insert_id;
    $db->query("INSERT INTO images (imageUrl, `Path`) VALUES ('$path/placeholder.jpg', '$path')");
    $imageId = (int)$db->insert_id;

    $editor = new TaskCorrection($db);
    verify($editor->read($orderId, 101, false) !== null, 'Creator cannot read');
    verify($editor->read($orderId, 103, false) === null, 'Other user can read');
    verify($editor->read($orderId, 103, true) !== null, 'Permission cannot read');

    $data = [
        'order_title' => 'Corrected', 'company_name' => 'Contact', 'company_domain' => 'example.test',
        'org' => 'Org', 'contact' => 'contact@example.test', 'company_admin_username' => 'new-login',
        'company_admin_password' => '', 'worker' => '102', 'order_desc' => 'Updated description',
        'asap' => 'asap', 'steps' => json_encode([['id' => $stepId, 'text' => 'Updated step'], ['id' => 0, 'text' => 'New step']]),
        'remove_images' => json_encode([$imageId]), 'clear_password' => '0'
    ];
    verify($editor->save($orderId, 101, false, $data, null)['success'], 'Creator save failed');
    $order = $db->query("SELECT * FROM `query` WHERE id = $orderId")->fetch_assoc();
    verify($order['Name'] === 'Corrected' && $order['worker_name_id'] == 102 && $order['password'] === 'secret', 'Fields or password preservation failed');
    verify($order['contact_name'] === 'Contact' && $order['contact_org'] === 'Org' && $order['contact_details'] === 'contact@example.test', 'Contact fields failed');
    verify($order['Path'] === 'NONE', 'Last image did not clear task path');
    verify((int)$db->query("SELECT COUNT(*) AS n FROM steps WHERE orderId = $orderId")->fetch_assoc()['n'] === 2, 'Step update failed');
    verify((int)$db->query("SELECT COUNT(*) AS n FROM images")->fetch_assoc()['n'] === 0, 'Image removal failed');
    verify((int)$db->query("SELECT COUNT(*) AS n FROM task_notifications WHERE recipient_id = 102 AND event_type = 'assigned'")->fetch_assoc()['n'] === 1, 'Recipient notification failed');

    $data['order_title'] = 'Blocked';
    verify(!$editor->save($orderId, 103, false, $data, null)['success'], 'Unauthorized save succeeded');
    $data['worker'] = '999';
    verify(!$editor->save($orderId, 101, false, $data, null)['success'], 'Unknown recipient accepted');
    $data['worker'] = '102';
    $data['steps'] = json_encode([['id' => 999999, 'text' => 'Foreign step']]);
    verify(!$editor->save($orderId, 101, false, $data, null)['success'], 'Foreign step accepted');
    $data['order_title'] = 'Corrected';
    $currentSteps = $editor->read($orderId, 101, false)['steps'];
    $data['steps'] = json_encode([['id' => (int)$currentSteps[0]['id'], 'text' => $currentSteps[0]['text']]]);
    $data['remove_images'] = '[]';
    $data['clear_password'] = '1';
    verify($editor->save($orderId, 101, false, $data, null)['success'], 'Password clear failed');
    verify($db->query("SELECT password FROM `query` WHERE id = $orderId")->fetch_assoc()['password'] === '', 'Password was not cleared');
    verify((int)$db->query("SELECT COUNT(*) AS n FROM steps WHERE orderId = $orderId")->fetch_assoc()['n'] === 1, 'Step removal failed');
    $db->query("UPDATE `query` SET status = 'completed' WHERE id = $orderId");
    verify($editor->read($orderId, 101, false) === null, 'Completed task can be opened');
    verify(!$editor->save($orderId, 101, false, $data, null)['success'], 'Completed task can be saved');
    echo "Task correction integration: passed\n";
} finally {
    if ($db) $db->close();
    if ($created) $admin->query("DROP DATABASE `$name`");
    $admin->close();
}
