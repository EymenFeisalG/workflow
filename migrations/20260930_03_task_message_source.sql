ALTER TABLE task_messages ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'discussion' AFTER body;
