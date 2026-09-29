<?php

class TaskCorrection
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    private function task(int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT id, Name, Hostname, Info, admin, password, contact_name, contact_org, contact_details, Prio, worker_name_id, creator, status, Path FROM `query` WHERE id = ? LIMIT 1';
        if ($lock) $sql .= ' FOR UPDATE';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private function canEdit(array $task, int $actorId, bool $hasRight): bool
    {
        return in_array($task['status'], ['ongoing', 'pending', 'rework'], true)
            && ($actorId === (int)$task['creator'] || $hasRight);
    }

    public function read(int $id, int $actorId, bool $hasRight): ?array
    {
        if ($id < 1 || $actorId < 1) return null;
        $task = $this->task($id);
        if (!$task || !$this->canEdit($task, $actorId, $hasRight)) return null;

        $stmt = $this->db->prepare('SELECT id, `desc` AS text, completed+0 AS completed FROM steps WHERE orderId = ? ORDER BY step, id');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $steps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $images = [];
        if ($task['Path'] !== 'NONE' && $task['Path'] !== '' && $task['Path'] !== null) {
            $stmt = $this->db->prepare('SELECT id, imageUrl AS url FROM images WHERE `Path` = ? ORDER BY id');
            $stmt->bind_param('s', $task['Path']);
            $stmt->execute();
            $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        return [
            'id' => (int)$task['id'], 'title' => $task['Name'], 'domain' => $task['Hostname'],
            'description' => $task['Info'], 'contact_name' => $task['contact_name'] ?? '',
            'contact_org' => $task['contact_org'] ?? '', 'contact_details' => $task['contact_details'] ?? '',
            'admin_username' => $task['admin'] === 'tomt' ? '' : $task['admin'],
            'has_password' => !in_array((string)($task['password'] ?? ''), ['', 'tomt'], true),
            'priority' => $task['Prio'], 'worker_id' => (int)$task['worker_name_id'],
            'status' => $task['status'], 'steps' => $steps, 'images' => $images
        ];
    }

    public function save(int $id, int $actorId, bool $hasRight, array $data, ?array $files): array
    {
        $title = trim((string)($data['order_title'] ?? ''));
        $worker = filter_var($data['worker'] ?? null, FILTER_VALIDATE_INT);
        $rawSteps = json_decode((string)($data['steps'] ?? '[]'), true);
        $removeImages = json_decode((string)($data['remove_images'] ?? '[]'), true);
        if ($id < 1 || $actorId < 1 || $title === '' || mb_strlen($title) > 225 ||
            !$worker || !is_array($rawSteps) || !array_is_list($rawSteps) ||
            !is_array($removeImages) || !array_is_list($removeImages)) {
            return ['success' => false, 'error' => 'Ogiltiga uppgifter.'];
        }
        $steps = [];
        $seen = [];
        foreach ($rawSteps as $step) {
            if (!is_array($step) || !isset($step['text']) || !is_string($step['text'])) return ['success' => false, 'error' => 'Ogiltigt delmoment.'];
            $stepId = (int)($step['id'] ?? 0);
            $label = trim($step['text']);
            if ($stepId < 0 || $label === '' || mb_strlen($label) > 1000 || ($stepId && isset($seen[$stepId]))) return ['success' => false, 'error' => 'Ogiltigt delmoment.'];
            if ($stepId) $seen[$stepId] = true;
            $steps[] = ['id' => $stepId, 'text' => $label];
        }
        if (count($steps) > 100) return ['success' => false, 'error' => 'För många delmoment.'];
        $removeIds = [];
        foreach ($removeImages as $imageId) {
            if (!filter_var($imageId, FILTER_VALIDATE_INT) || (int)$imageId < 1) return ['success' => false, 'error' => 'Ogiltig bilaga.'];
            $removeIds[(int)$imageId] = true;
        }
        $uploads = [];
        if ($files && isset($files['tmp_name']) && is_array($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $index => $tmp) {
                $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) continue;
                if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp) || (int)$files['size'][$index] > 10 * 1024 * 1024) return ['success' => false, 'error' => 'En bilaga kunde inte läsas eller är för stor.'];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
                if (!isset($extensions[$mime])) return ['success' => false, 'error' => 'Bilagan måste vara en bild eller PDF.'];
                $uploads[] = ['tmp' => $tmp, 'ext' => $extensions[$mime]];
            }
        }

        $moved = [];
        $deleteAfterCommit = [];
        $createdDirectory = null;
        $root = dirname(__DIR__, 2);
        try {
            $this->db->begin_transaction();
            $task = $this->task($id, true);
            if (!$task || !$this->canEdit($task, $actorId, $hasRight)) throw new DomainException('Uppgiften kan inte korrigeras.');
            $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $worker);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) throw new DomainException('Mottagaren finns inte.');

            $existingSteps = [];
            $stmt = $this->db->prepare('SELECT id FROM steps WHERE orderId = ? FOR UPDATE');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $existingSteps[(int)$row['id']] = true;
            foreach ($steps as $step) if ($step['id'] && !isset($existingSteps[$step['id']])) throw new DomainException('Delmomentet tillhör inte uppgiften.');

            $path = (string)($task['Path'] ?: 'NONE');
            if ($path !== 'NONE' && $path !== '' && !preg_match('~^media/[A-Za-z0-9_-]+$~', $path)) throw new DomainException('Bilagornas sökväg är ogiltig.');
            $existingImages = [];
            if ($path !== 'NONE' && $path !== '') {
                $stmt = $this->db->prepare('SELECT id, imageUrl FROM images WHERE `Path` = ? FOR UPDATE');
                $stmt->bind_param('s', $path);
                $stmt->execute();
                foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $existingImages[(int)$row['id']] = $row['imageUrl'];
            }
            foreach ($removeIds as $imageId => $_) if (!isset($existingImages[$imageId])) throw new DomainException('Bilagan tillhör inte uppgiften.');

            $password = !empty($data['clear_password']) ? '' : (string)($data['company_admin_password'] ?? '');
            if ($password === '' && empty($data['clear_password'])) $password = $task['password'];
            $domain = trim((string)($data['company_domain'] ?? ''));
            $description = trim((string)($data['order_desc'] ?? ''));
            $contactName = trim((string)($data['company_name'] ?? ''));
            $contactOrg = trim((string)($data['org'] ?? ''));
            $contactDetails = trim((string)($data['contact'] ?? ''));
            $admin = trim((string)($data['company_admin_username'] ?? ''));
            $priority = ($data['asap'] ?? '') === 'asap' ? 'asap' : 'normal';
            $stmt = $this->db->prepare('UPDATE `query` SET Name=?, Hostname=?, Info=?, admin=?, password=?, contact_name=?, contact_org=?, contact_details=?, Prio=?, worker_name_id=? WHERE id=?');
            $stmt->bind_param('sssssssssii', $title, $domain, $description, $admin, $password, $contactName, $contactOrg, $contactDetails, $priority, $worker, $id);
            $stmt->execute();

            $keep = array_fill_keys(array_column($steps, 'id'), true);
            $deleteStep = $this->db->prepare('DELETE FROM steps WHERE id = ? AND orderId = ?');
            foreach ($existingSteps as $stepId => $_) if (!isset($keep[$stepId])) {
                $deleteStep->bind_param('ii', $stepId, $id);
                $deleteStep->execute();
            }
            $updateStep = $this->db->prepare('UPDATE steps SET step=?, `desc`=? WHERE id=? AND orderId=?');
            $insertStep = $this->db->prepare('INSERT INTO steps (step, `desc`, orderId, creator, completed) VALUES (?, ?, ?, ?, 0)');
            foreach ($steps as $index => $step) {
                $number = $index + 1;
                $label = $step['text'];
                if ($step['id']) {
                    $stepId = $step['id'];
                    $updateStep->bind_param('isii', $number, $label, $stepId, $id);
                    $updateStep->execute();
                } else {
                    $insertStep->bind_param('isii', $number, $label, $id, $actorId);
                    $insertStep->execute();
                }
            }

            $deleteImage = $this->db->prepare('DELETE FROM images WHERE id = ? AND `Path` = ?');
            foreach ($removeIds as $imageId => $_) {
                $deleteImage->bind_param('is', $imageId, $path);
                $deleteImage->execute();
                $url = $existingImages[$imageId];
                if (str_starts_with($url, $path . '/') && basename($url) === substr($url, strlen($path) + 1)) $deleteAfterCommit[] = $root . '/' . $url;
            }
            if ($uploads) {
                if ($path === 'NONE' || $path === '') $path = 'media/order_' . $id;
                $directory = $root . '/' . $path;
                if (!is_dir($directory)) {
                    if (!mkdir($directory, 0777, true)) throw new RuntimeException('Kunde inte skapa bilagemapp.');
                    $createdDirectory = $directory;
                }
                $insertImage = $this->db->prepare('INSERT INTO images (imageUrl, `Path`) VALUES (?, ?)');
                foreach ($uploads as $upload) {
                    $url = $path . '/img_' . bin2hex(random_bytes(12)) . '.' . $upload['ext'];
                    $target = $root . '/' . $url;
                    if (!move_uploaded_file($upload['tmp'], $target)) throw new RuntimeException('Kunde inte spara bilagan.');
                    $moved[] = $target;
                    $insertImage->bind_param('ss', $url, $path);
                    $insertImage->execute();
                }
            }
            if ($path !== 'NONE' && $path !== '') {
                $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM images WHERE `Path` = ?');
                $stmt->bind_param('s', $path);
                $stmt->execute();
                if ((int)$stmt->get_result()->fetch_assoc()['total'] === 0) $path = 'NONE';
            }
            if ($path !== $task['Path']) {
                $stmt = $this->db->prepare('UPDATE `query` SET Path = ? WHERE id = ?');
                $stmt->bind_param('si', $path, $id);
                $stmt->execute();
            }
            $notifications = new TaskNotifications($this->db);
            if ((int)$task['worker_name_id'] !== $worker) {
                $notifications->emit($id, $actorId, 'assigned');
                $oldWorker = (int)$task['worker_name_id'];
                if ($oldWorker !== (int)$task['creator']) $notifications->emitTo($id, $actorId, 'unassigned', '', [$oldWorker]);
            } else {
                $notifications->emit($id, $actorId, 'updated');
            }
            $this->db->commit();
            foreach ($deleteAfterCommit as $file) if (is_file($file) && !unlink($file)) error_log('Could not remove task attachment: ' . $file);
            return ['success' => true, 'orderId' => $id];
        } catch (Throwable $error) {
            $this->db->rollback();
            foreach ($moved as $file) if (is_file($file)) unlink($file);
            if ($createdDirectory !== null && is_dir($createdDirectory)) rmdir($createdDirectory);
            if ($error instanceof DomainException) return ['success' => false, 'error' => $error->getMessage()];
            error_log('Task correction failed: ' . $error->getMessage());
            return ['success' => false, 'error' => 'Kunde inte spara korrigeringen.'];
        }
    }
}
