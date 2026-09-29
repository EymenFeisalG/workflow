<?php

class TaskThread
{
    private mysqli $db;
    private TaskNotifications $notifications;
    private const PAGE_SIZE = 50;

    public function __construct(mysqli $db, TaskNotifications $notifications)
    {
        $this->db = $db;
        $this->notifications = $notifications;
    }

    private function accessibleOrder(int $orderId, int $userId, bool $showAll, bool $forUpdate = false): array
    {
        $order = $this->notifications->order($orderId, $forUpdate);
        if (!$order || $userId < 1 || !$this->notifications->canAccess($order, $userId, $showAll)) {
            throw new DomainException('Uppgiften är inte tillgänglig.');
        }
        return $order;
    }

    public function page(int $orderId, int $userId, bool $showAll, int $beforeId = 0, int $afterId = 0): array
    {
        $order = $this->accessibleOrder($orderId, $userId, $showAll);
        if ($beforeId > 0 && $afterId > 0) throw new InvalidArgumentException('Välj ett sätt att bläddra.');
        if ($afterId > 0) {
            $stmt = $this->db->prepare('SELECT m.id, m.author_id, u.username AS author_name, m.body, m.created_at FROM task_messages m JOIN users u ON u.id = m.author_id WHERE m.order_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 51');
            $stmt->bind_param('ii', $orderId, $afterId);
        } elseif ($beforeId > 0) {
            $stmt = $this->db->prepare('SELECT m.id, m.author_id, u.username AS author_name, m.body, m.created_at FROM task_messages m JOIN users u ON u.id = m.author_id WHERE m.order_id = ? AND m.id < ? ORDER BY m.id DESC LIMIT 51');
            $stmt->bind_param('ii', $orderId, $beforeId);
        } else {
            $stmt = $this->db->prepare('SELECT m.id, m.author_id, u.username AS author_name, m.body, m.created_at FROM task_messages m JOIN users u ON u.id = m.author_id WHERE m.order_id = ? ORDER BY m.id DESC LIMIT 51');
            $stmt->bind_param('i', $orderId);
        }
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $hasMore = count($messages) > self::PAGE_SIZE;
        if ($hasMore) array_pop($messages);
        if ($afterId === 0) $messages = array_reverse($messages);
        foreach ($messages as &$message) {
            $message['id'] = (int)$message['id'];
            $message['author_id'] = (int)$message['author_id'];
        }
        unset($message);
        return ['messages' => $messages, 'hasMore' => $hasMore,
            'canWrite' => in_array($order['status'], ['ongoing', 'rework', 'pending'], true)];
    }

    public function send(int $orderId, int $userId, bool $showAll, string $body): int
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 4000) throw new InvalidArgumentException('Skriv ett meddelande på högst 4000 tecken.');
        $this->db->begin_transaction();
        try {
            $order = $this->accessibleOrder($orderId, $userId, $showAll, true);
            if (!in_array($order['status'], ['ongoing', 'rework', 'pending'], true)) {
                throw new DomainException('Diskussionen är låst för nya inlägg.');
            }
            $stmt = $this->db->prepare('INSERT INTO task_messages (order_id, author_id, body) VALUES (?, ?, ?)');
            $stmt->bind_param('iis', $orderId, $userId, $body);
            $stmt->execute();
            $messageId = (int)$this->db->insert_id;
            $this->notifications->emitTo($orderId, $userId, 'thread_message', mb_substr($body, 0, 255),
                [(int)$order['creator'], (int)$order['worker_name_id']], $messageId);
            $this->db->commit();
            return $messageId;
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    public function markViewed(int $orderId, int $userId, bool $showAll, array $messageIds): void
    {
        $this->accessibleOrder($orderId, $userId, $showAll);
        $ids = array_values(array_unique(array_filter(array_map('intval', $messageIds), static fn($id) => $id > 0)));
        if (!$ids) return;
        if (count($ids) > self::PAGE_SIZE) throw new InvalidArgumentException('För många meddelanden.');
        $stmt = $this->db->prepare("UPDATE task_notifications SET read_at = NOW() WHERE recipient_id = ? AND order_id = ? AND event_type = 'thread_message' AND message_id = ? AND read_at IS NULL");
        foreach ($ids as $id) {
            $stmt->bind_param('iii', $userId, $orderId, $id);
            $stmt->execute();
        }
    }
}
