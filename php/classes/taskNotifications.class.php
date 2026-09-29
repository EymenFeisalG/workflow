<?php

class TaskNotifications
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function order(int $orderId, bool $forUpdate = false): ?array
    {
        if ($orderId < 1) return null;
        $stmt = $this->db->prepare('SELECT id, Name, creator, worker_name_id, status FROM `query` WHERE id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function canAccess(array $order, int $userId, bool $showAll = false): bool
    {
        return $showAll || (int)$order['creator'] === $userId || (int)$order['worker_name_id'] === $userId;
    }

    public function emit(int $orderId, int $actorId, string $type, string $detail = '', array $extraRecipients = []): void
    {
        $order = $this->order($orderId);
        if (!$order || $actorId < 1) return;
        $this->emitTo($orderId, $actorId, $type, $detail, array_merge([(int)$order['creator'], (int)$order['worker_name_id']], $extraRecipients));
    }

    public function emitTo(int $orderId, int $actorId, string $type, string $detail, array $recipients): void
    {
        $recipients = array_unique(array_map('intval', $recipients));
        $stmt = $this->db->prepare('INSERT INTO task_notifications (recipient_id, actor_id, order_id, event_type, detail) VALUES (?, ?, ?, ?, ?)');
        $detail = mb_substr(trim($detail), 0, 255);
        foreach ($recipients as $recipientId) {
            $recipientId = (int)$recipientId;
            if ($recipientId < 1 || $recipientId === $actorId) continue;
            $stmt->bind_param('iiiss', $recipientId, $actorId, $orderId, $type, $detail);
            $stmt->execute();
        }
    }

    public function listFor(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT n.id, n.order_id, n.event_type, n.detail, n.created_at, n.read_at, q.Name AS order_title, q.status, u.username AS actor_name FROM task_notifications n JOIN `query` q ON q.id = n.order_id JOIN users u ON u.id = n.actor_id WHERE n.recipient_id = ? ORDER BY n.id DESC LIMIT 50');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $count = $this->db->prepare('SELECT COUNT(*) AS total FROM task_notifications WHERE recipient_id = ? AND read_at IS NULL');
        $count->bind_param('i', $userId);
        $count->execute();
        return ['items' => $items, 'unread' => (int)$count->get_result()->fetch_assoc()['total']];
    }

    public function markRead(int $userId, array $ids = [], bool $all = false): void
    {
        if ($all) {
            $stmt = $this->db->prepare('UPDATE task_notifications SET read_at = NOW() WHERE recipient_id = ? AND read_at IS NULL');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            return;
        }
        $stmt = $this->db->prepare('UPDATE task_notifications SET read_at = NOW() WHERE recipient_id = ? AND id = ? AND read_at IS NULL');
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id < 1) continue;
            $stmt->bind_param('ii', $userId, $id);
            $stmt->execute();
        }
    }

    public function getFocus(int $userId, bool $showAll = false): ?array
    {
        $stmt = $this->db->prepare('SELECT order_id FROM user_order_focus WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return null;
        $order = $this->order((int)$row['order_id']);
        if (!$order || in_array($order['status'], ['completed', 'canceled'], true) || !$this->canAccess($order, $userId, $showAll)) {
            $this->clearFocus($userId);
            return null;
        }
        return $order;
    }

    public function setFocus(int $userId, int $orderId, bool $showAll = false): ?array
    {
        $order = $this->order($orderId);
        if (!$order || in_array($order['status'], ['completed', 'canceled'], true) || !$this->canAccess($order, $userId, $showAll)) return null;
        $stmt = $this->db->prepare('INSERT INTO user_order_focus (user_id, order_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id)');
        $stmt->bind_param('ii', $userId, $orderId);
        $stmt->execute();
        return $order;
    }

    public function clearFocus(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM user_order_focus WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }

    public function clearFocusForOrder(int $orderId): void
    {
        $stmt = $this->db->prepare('DELETE FROM user_order_focus WHERE order_id = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
    }
}
