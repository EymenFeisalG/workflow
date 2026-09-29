-- Store at most one focused task per account.
CREATE TABLE IF NOT EXISTS user_order_focus (
    user_id INT NOT NULL PRIMARY KEY,
    order_id INT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
