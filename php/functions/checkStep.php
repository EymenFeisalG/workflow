<?php
define('login_req', true);
require __DIR__ . '/../../global.php';
$auth->userLoginCheck();

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Ogiltig metod.']);
    exit;
}
if (!hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Ladda om sidan.']);
    exit;
}

$stepId = filter_var($_POST['stepid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$completed = (string)($_POST['completed'] ?? '');
if (!$stepId || !in_array($completed, ['0', '1'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ogiltigt steg.']);
    exit;
}

try {
    $result = $main->setStepCompletion((int)$stepId, $completed === '1');
    if (!$result['success']) http_response_code(403);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Step update failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Steget kunde inte sparas. Försök igen.']);
}
