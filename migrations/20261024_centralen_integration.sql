-- Required for one atomic ordinary profile, default right and identity link.
-- Root applies only after a full backup and isolated preservation/rollback drill.
ALTER TABLE users ENGINE=InnoDB;
ALTER TABLE privileges ENGINE=InnoDB;

CREATE TABLE workflow_user_links (
 centralen_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 workflow_user_id INT NOT NULL UNIQUE,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE workflow_integration_nonces (
 nonce_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 expires_at DATETIME NOT NULL,
 INDEX nonce_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE workflow_task_operations (
 request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 actor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 intent_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 owner_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 result JSON NULL,
 created_at DATETIME NOT NULL,
 INDEX actor_operations(actor_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE workflow_link_attempts (
 centralen_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL,
 window_started DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
