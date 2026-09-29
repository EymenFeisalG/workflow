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

    public function emitTo(int $orderId, int $actorId, string $type, string $detail, array $recipients, ?int $messageId = null): void
    {
        $recipients = array_unique(array_map('intval', $recipients));
        $stmt = $messageId === null
            ? $this->db->prepare('INSERT INTO task_notifications (recipient_id, actor_id, order_id, event_type, detail) VALUES (?, ?, ?, ?, ?)')
            : $this->db->prepare('INSERT INTO task_notifications (recipient_id, actor_id, order_id, event_type, detail, message_id) VALUES (?, ?, ?, ?, ?, ?)');
        $detail = mb_substr(trim($detail), 0, 255);
        foreach ($recipients as $recipientId) {
            $recipientId = (int)$recipientId;
            if ($recipientId < 1 || $recipientId === $actorId) continue;
            if ($messageId === null) $stmt->bind_param('iiiss', $recipientId, $actorId, $orderId, $type, $detail);
            else $stmt->bind_param('iiissi', $recipientId, $actorId, $orderId, $type, $detail, $messageId);
            $stmt->execute();
        }
    }

    public function listFor(int $userId, bool $showAll = false): array
    {
        $visible = "n.event_type <> 'thread_message' OR ? = 1 OR q.creator = ? OR q.worker_name_id = ?";
        $stmt = $this->db->prepare('SELECT n.id, n.order_id, n.event_type, n.detail, n.created_at, n.read_at, q.Name AS order_title, q.status, u.username AS actor_name FROM task_notifications n JOIN `query` q ON q.id = n.order_id JOIN users u ON u.id = n.actor_id WHERE n.recipient_id = ? AND (' . $visible . ') ORDER BY n.id DESC LIMIT 50');
        $all = $showAll ? 1 : 0;
        $stmt->bind_param('iiii', $userId, $all, $userId, $userId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $count = $this->db->prepare('SELECT COUNT(*) AS total FROM task_notifications n JOIN `query` q ON q.id = n.order_id WHERE n.recipient_id = ? AND n.read_at IS NULL AND (' . $visible . ')');
        $count->bind_param('iiii', $userId, $all, $userId, $userId);
        $count->execute();
        $unread = (int)$count->get_result()->fetch_assoc()['total'];
        $threadCount = $this->db->prepare("SELECT n.order_id, COUNT(*) AS total FROM task_notifications n JOIN `query` q ON q.id = n.order_id WHERE n.recipient_id = ? AND n.read_at IS NULL AND n.event_type = 'thread_message' AND (? = 1 OR q.creator = ? OR q.worker_name_id = ?) GROUP BY n.order_id");
        $threadCount->bind_param('iiii', $userId, $all, $userId, $userId);
        $threadCount->execute();
        $threadUnread = [];
        foreach ($threadCount->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $threadUnread[(string)$row['order_id']] = (int)$row['total'];
        $totalCount = $this->db->prepare('SELECT m.order_id, COUNT(*) AS total FROM task_messages m JOIN `query` q ON q.id = m.order_id WHERE ? = 1 OR q.creator = ? OR q.worker_name_id = ? GROUP BY m.order_id');
        $totalCount->bind_param('iii', $all, $userId, $userId);
        $totalCount->execute();
        $threadTotal = [];
        foreach ($totalCount->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $threadTotal[(string)$row['order_id']] = (int)$row['total'];
        return ['items' => $items, 'unread' => $unread, 'threadUnread' => $threadUnread, 'threadTotal' => $threadTotal];
    }

    public function markRead(int $userId, array $ids = [], bool $all = false): void
    {
        if ($all) {
            $stmt = $this->db->prepare("UPDATE task_notifications SET read_at = NOW() WHERE recipient_id = ? AND read_at IS NULL AND event_type <> 'thread_message'");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            return;
        }
        $stmt = $this->db->prepare("UPDATE task_notifications SET read_at = NOW() WHERE recipient_id = ? AND id = ? AND read_at IS NULL AND event_type <> 'thread_message'");
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
