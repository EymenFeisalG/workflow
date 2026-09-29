-- Task events are displayed inside the application.
CREATE TABLE IF NOT EXISTS task_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    actor_id INT NOT NULL,
    order_id INT NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    detail VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    INDEX idx_recipient_created (recipient_id, created_at, id),
    INDEX idx_recipient_unread (recipient_id, read_at),
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
