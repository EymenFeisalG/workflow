<?php
define('login_req', true);
require __DIR__ . '/../../global.php';
require __DIR__ . '/../classes/taskThread.class.php';
$auth->userLoginCheck();
header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user']['userid'] ?? 0);
$orderInput = $_REQUEST['orderId'] ?? null;
$orderId = is_scalar($orderInput) ? filter_var($orderInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if ($userId < 1) { http_response_code(401); echo json_encode(['error' => 'Ej inloggad']); exit; }
if (!$orderId) { http_response_code(400); echo json_encode(['error' => 'Ogiltig uppgift']); exit; }

$thread = new TaskThread(database::$mysql, $taskNotifications);
$showAll = $auth->hasRight('orders_show_all');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $beforeInput = $_GET['beforeId'] ?? 0;
        $afterInput = $_GET['afterId'] ?? 0;
        $before = is_scalar($beforeInput) ? filter_var($beforeInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : false;
        $after = is_scalar($afterInput) ? filter_var($afterInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : false;
        if ($before === false || $after === false) throw new InvalidArgumentException('Ogiltig meddelandegräns.');
        echo json_encode($thread->page((int)$orderId, $userId, $showAll, (int)$before, (int)$after), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Ogiltig metod']); exit; }
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals((string)$_SESSION['csrf_token'], $_POST['csrf'])) {
        http_response_code(403); echo json_encode(['error' => 'Ogiltig session']); exit;
    }
    if (($_POST['action'] ?? '') === 'send') {
        if (!is_string($_POST['body'] ?? null)) throw new InvalidArgumentException('Ogiltigt meddelande.');
        $messageId = $thread->send((int)$orderId, $userId, $showAll, $_POST['body']);
        echo json_encode(['success' => true, 'messageId' => $messageId]);
    } elseif (($_POST['action'] ?? '') === 'read') {
        if (!is_array($_POST['messageIds'] ?? null) || count($_POST['messageIds']) > 50) throw new InvalidArgumentException('Ogiltiga meddelanden.');
        foreach ($_POST['messageIds'] as $id) {
            if (!is_scalar($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new InvalidArgumentException('Ogiltiga meddelanden.');
            }
        }
        $thread->markViewed((int)$orderId, $userId, $showAll, $_POST['messageIds']);
        echo json_encode(['success' => true]);
    } else {
        throw new InvalidArgumentException('Ogiltig åtgärd.');
    }
} catch (DomainException $error) {
    http_response_code(403);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Task thread failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Diskussionen kunde inte hanteras. Försök igen.'], JSON_UNESCAPED_UNICODE);
}
