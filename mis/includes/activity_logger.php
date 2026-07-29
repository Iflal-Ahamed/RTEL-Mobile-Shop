<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('rtel_admin_log_event')) {
    function rtel_admin_log_event($conn, $eventType, $status = 'success', $note = '')
    {
        if (!$conn) {
            return;
        }
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbladmin_log (
            log_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_id VARCHAR(50) NULL,
            event_type VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            note VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $adminId = trim((string)($_SESSION['admin_id'] ?? 'SYSTEM'));
        $roleLabel = trim((string)($_SESSION['admin_role_label'] ?? $_SESSION['admin_type'] ?? 'admin'));
        $eventType = trim((string)$eventType);
        $status = strtolower(trim((string)$status)) === 'failed' ? 'failed' : 'success';
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $note = trim((string)$note);
        if ($roleLabel !== '') {
            $note = '[' . $roleLabel . '] ' . $note;
        }
        $note = substr($note, 0, 255);
        $createdAt = date('Y-m-d H:i:s');

        $hasEventType = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM tbladmin_log LIKE 'event_type'");
        if ($colCheck) {
            $hasEventType = ($colCheck->num_rows > 0);
            $colCheck->free();
        }

        if ($hasEventType) {
            $stmt = $conn->prepare("INSERT INTO tbladmin_log (admin_id, event_type, status, ip_address, user_agent, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('sssssss', $adminId, $eventType, $status, $ip, $ua, $note, $createdAt);
                $stmt->execute();
                $stmt->close();
            }
            return;
        }

        // Legacy fallback from original schema in Rtel.sql.
        $legacyId = 'L' . substr((string)time(), -5) . substr((string)random_int(1000, 9999), -4);
        $legacyAction = substr($eventType . ':' . $status . ' ' . $note, 0, 50);
        $entityType = 'system';
        $entityId = '-';
        $activityDate = date('Y-m-d');
        $legacyStmt = $conn->prepare("INSERT INTO tbladmin_log (adminlog_id, admin_id, action_type, entity_type, entity_id, activity_date) VALUES (?, ?, ?, ?, ?, ?)");
        if ($legacyStmt) {
            $legacyStmt->bind_param('ssssss', $legacyId, $adminId, $legacyAction, $entityType, $entityId, $activityDate);
            $legacyStmt->execute();
            $legacyStmt->close();
        }
    }
}

