<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim($_SESSION["role"] ?? ""));
$staff_id = $_SESSION["username"]
    ?? $_SESSION["staff_id"]
    ?? $_SESSION["user_id"]
    ?? "";

    if (strtolower(trim($_SESSION["role"] ?? "")) !== "staff") {
    header("Location: index.php?page=dashboard");
    exit;
}

    require_once __DIR__ . "/db/connection.php";

    $staff_id = trim($_SESSION["staff_id"] ?? "");
    $department = trim($_SESSION["department"] ?? "");
    $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
$todayDate = (new DateTimeImmutable("now", new DateTimeZone("Asia/Kolkata")))->format("Y-m-d");
$todayDay = (new DateTimeImmutable("now", new DateTimeZone("Asia/Kolkata")))->format("l");
    $hours = range(1, 7);
    $slots = [];

    $sql = "
        SELECT
            ts.id AS slot_id,
            ts.day_name,
            ts.hour_no,
            ts.class_type,
            c.course_code,
            c.course_name,
            t.batch,
            t.section,
            ya.year_name,
            t.semester,
            t.academic_year,
            t.department
        FROM timetable_slots ts
        INNER JOIN timetable_sets t ON t.id = ts.timetable_id
        INNER JOIN course_master c ON c.id = ts.course_id
        INNER JOIN year_advisor ya
            ON ya.department = t.department
           AND ya.batch = t.batch
           AND ya.section = t.section
           AND ya.year_name = '3rd Year'
        WHERE ts.faculty_id = ?
          AND t.status = 'Approved'
          AND t.id = (
              SELECT MAX(current_set.id)
              FROM timetable_sets current_set
              WHERE current_set.department = t.department
                AND current_set.batch = t.batch
                AND current_set.section = t.section
                AND current_set.semester = t.semester
                AND current_set.academic_year = t.academic_year
                AND current_set.status = 'Approved'
          )
        ORDER BY t.batch, t.section, t.semester, ts.day_name, ts.hour_no
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $slots[$row["day_name"]][$row["hour_no"]][] = $row;
        }
        $stmt->close();
    }

    ?>

    <style>
    .staff-timetable-wrapper { padding: 10px; }
    .staff-timetable-header { background: linear-gradient(135deg, #4e73df, #1cc88a); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
    .staff-timetable-header h3 { margin: 0; font-weight: 700; }
    .staff-timetable-header p { margin: 6px 0 0; opacity: 0.9; }
    .timetable-card { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); overflow-x: auto; }
    .staff-timetable { width: 100%; min-width: 950px; border-collapse: collapse; margin: 0; }
    .staff-timetable th { background: #1a1c23; color: white; padding: 14px; text-align: center; white-space: nowrap; }
    .staff-timetable td { border: 1px solid #e5e7eb; padding: 10px; text-align: center; vertical-align: middle; min-width: 125px; }
    .day-name { background: #f1f5f9; font-weight: 700; color: #1f2937; }
    .free-period { color: #9ca3af; font-size: 13px; }
    .current-day-cell { background: #f0f8ff; }
    .subject-period { display: block; background: linear-gradient(135deg, #4e73df, #1cc88a); color: white; padding: 10px 6px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; margin: 3px 0; }
    .subject-period:hover { color: white; }
    .subject-period small { display: block; margin-top: 4px; font-size: 11px; opacity: 0.9; }
    </style>

    <div class="staff-timetable-wrapper">
        <div class="staff-timetable-header">
            <h3><i class="fas fa-calendar-days me-2"></i>My Timetable</h3>
            <p>Approved classes assigned to <?= htmlspecialchars($staff_id, ENT_QUOTES, "UTF-8") ?></p>
        </div>

        <div class="timetable-card">
            <table class="staff-timetable">
                <thead>
                    <tr>
                        <th>Day</th>
                        <?php foreach ($hours as $hour): ?>
                            <th>Hour <?= $hour ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($days as $day): ?>
                        <tr>
                            <td class="day-name"><?= htmlspecialchars($day, ENT_QUOTES, "UTF-8") ?></td>
                            <?php foreach ($hours as $hour): ?>
                                <td>
                                    <?php if (empty($slots[$day][$hour])): ?>
                                        <span class="free-period">Free</span>
                                    <?php else: ?>
                                        <?php foreach ($slots[$day][$hour] as $slot): ?>
                                            <?php if ($day === $todayDay): ?>
                                                <a class="subject-period" href="index.php?page=staff_period&amp;slot_id=<?= (int)$slot["slot_id"] ?>&amp;date=<?= htmlspecialchars($todayDate, ENT_QUOTES, "UTF-8") ?>">
                                                    <?= htmlspecialchars($slot["course_code"], ENT_QUOTES, "UTF-8") ?>
                                                    <small><?= htmlspecialchars($slot["year_name"] . " / " . $slot["batch"] . " / " . $slot["section"] . " / Sem " . $slot["semester"], ENT_QUOTES, "UTF-8") ?></small>
                                                    <small><i class="fas fa-clipboard-check me-1"></i>Open Attendance</small>
                                                </a>
                                            <?php else: ?>
                                                <div class="subject-period" style="opacity:.45;cursor:not-allowed;" title="Attendance opens only on the scheduled day">
                                                    <?= htmlspecialchars($slot["course_code"], ENT_QUOTES, "UTF-8") ?>
                                                    <small><?= htmlspecialchars($slot["year_name"] . " / " . $slot["batch"] . " / " . $slot["section"] . " / Sem " . $slot["semester"], ENT_QUOTES, "UTF-8") ?></small>
                                                    <small>Attendance opens on <?= htmlspecialchars($day, ENT_QUOTES, "UTF-8") ?></small>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
