CREATE TABLE IF NOT EXISTS remember_tokens (
    selector CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
