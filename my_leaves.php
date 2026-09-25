<?php
/*
 * Student "My Leave Requests" page.
 * Reuses the existing leave-request UI and places the student's
 * latest approved timetable below it.
 */
require_once __DIR__ . '/apply_leave.php';
?>

<div class="mt-4">
    <?php require __DIR__ . '/student_timetable.php'; ?>
</div>
