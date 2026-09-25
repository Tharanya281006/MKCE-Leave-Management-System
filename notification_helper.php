<?php
/**
 * Notification helper for Leave Management System.
 * Creates the notification table automatically if it does not exist.
 */

function ensureNotificationTable($conn)
{
    $sql = "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reg_no VARCHAR(100) NOT NULL,
            leave_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifications_reg_no (reg_no),
            INDEX idx_notifications_leave_id (leave_id),
            INDEX idx_notifications_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    return $conn->query($sql);
}

function createLeaveNotification($conn, $regNo, $leaveId, $title, $message, $type)
{
    if (!ensureNotificationTable($conn)) {
        return false;
    }

    $sql = "
        INSERT INTO notifications
        (reg_no, leave_id, title, message, notification_type)
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Notification prepare error: " . $conn->error);
        return false;
    }

    $stmt->bind_param(
        "sisss",
        $regNo,
        $leaveId,
        $title,
        $message,
        $type
    );

    $ok = $stmt->execute();

    if (!$ok) {
        error_log("Notification insert error: " . $stmt->error);
    }

    $stmt->close();
    return $ok;
}
?>
