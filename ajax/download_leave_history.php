<?php

session_start();
require_once __DIR__ . "/../db/connection.php";

$role = strtolower(trim($_SESSION["role"] ?? ""));
if (!in_array($role, ["student", "staff", "hod"], true)) {
    http_response_code(403);
    exit("Unauthorized access.");
}

$format = strtolower(trim((string)($_GET["format"] ?? "xls")));
if (!in_array($format, ["xls", "pdf"], true)) $format = "xls";

$where = [];
$types = "";
$params = [];
$add = static function (string $condition, string $type, $value) use (&$where, &$types, &$params): void {
    $where[] = $condition;
    if ($type !== "") {
        $types .= $type;
        $params[] = $value;
    }
};

if ($role === "student") {
    $add("lr.reg_no = ?", "s", trim($_SESSION["reg_no"] ?? ""));
    $filename = "Leave_History." . $format;
} elseif ($role === "staff") {
    $staff_id = trim($_SESSION["staff_id"] ?? "");
    $department = trim($_SESSION["department"] ?? "");
    if ($staff_id === "" || $department === "") {
        http_response_code(403);
        exit("Staff session not found.");
    }
    $add(
    "EXISTS (
        SELECT 1
        FROM year_advisor ya
        WHERE ya.advisor_staff_id = ?
          AND ya.department = lr.department
          AND ya.batch = lr.batch
          AND UPPER(RIGHT(TRIM(ya.section),1)) =
              CASE
                  WHEN UPPER(TRIM(lr.department)) IN ('IT', 'INFORMATION TECHNOLOGY')
                       AND CAST(RIGHT(TRIM(lr.reg_no), 3) AS UNSIGNED) BETWEEN 1 AND 63
                      THEN 'A'

                  WHEN UPPER(TRIM(lr.department)) IN ('IT', 'INFORMATION TECHNOLOGY')
                       AND CAST(RIGHT(TRIM(lr.reg_no), 3) AS UNSIGNED) BETWEEN 64 AND 126
                      THEN 'B'

                  ELSE UPPER(RIGHT(TRIM(lr.section),1))
              END
    )",
    "s",
    $staff_id
);
    $add("lr.department = ?", "s", $department);
    $filename = "Staff_Leave_History." . $format;
} else {
    $department = trim($_SESSION["department"] ?? "");
    if ($department === "") {
        http_response_code(403);
        exit("HOD session not found.");
    }
    $add("lr.department = ?", "s", $department);
    $add("lr.forwarded_to_hod = 1", "", null);
    $add("(lr.advisor_status = 1 OR lr.advisor_locked = 1)", "", null);
    $filename = "HOD_Leave_History." . $format;
}

if ($role !== "student") {
    $filters = [
        "request_type" => ["lr.request_type = ?", "s"],
        "batch" => ["lr.batch = ?", "s"],
        "department" => ["lr.department = ?", "s"],
        "section" => ["lr.section = ?", "s"],
        "semester" => ["lr.semester = ?", "i"],
        "academic_year" => ["lr.academic_year = ?", "s"]
    ];
    foreach ($filters as $key => [$condition, $type]) {
        $value = trim((string)($_GET[$key] ?? ""));
        if ($value !== "") {
            $add($condition, $type, $type === "i" ? (int)$value : $value);
        }
    }

    $status = trim((string)($_GET["status"] ?? ""));
    if ($status === "approved") {
        $where[] = "lr.hod_status = 3 AND (lr.advisor_status = 1 OR lr.advisor_locked = 1)";
    } elseif ($status === "rejected") {
        $where[] = "(lr.advisor_status = 4 OR lr.hod_status = 5)";
    } elseif ($status === "pending") {
        $where[] = "NOT (lr.advisor_status = 4 OR lr.hod_status = 5 OR (lr.hod_status = 3 AND (lr.advisor_status = 1 OR lr.advisor_locked = 1)))";
    }

    $from_date = trim((string)($_GET["from_date"] ?? ""));
    $to_date = trim((string)($_GET["to_date"] ?? ""));
    if ($from_date !== "") {
        $add("lr.to_date >= ?", "s", $from_date);
    }
    if ($to_date !== "") {
        $add("lr.from_date <= ?", "s", $to_date);
    }
}

$sql = "SELECT lr.reg_no, lr.department, lr.section, lr.request_type, lr.from_date, lr.to_date, lr.batch, lr.semester, lr.academic_year, lr.reason, lr.proof, lr.advisor_status, lr.advisor_locked, lr.advisor_remarks, lr.hod_status, lr.hod_remarks, lr.forwarded_to_hod, lr.created_at, lr.updated_at FROM leave_requests lr WHERE " . implode(" AND ", $where) . " ORDER BY lr.id DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit("Unable to prepare export.");
}
if ($types !== "") {
    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[] = &$params[$index];
    }
    call_user_func_array([$stmt, "bind_param"], $bind);
}
$stmt->execute();
$result = $stmt->get_result();

function exportStatus(array $row): string
{
    if ((int)$row["advisor_status"] === 4) return "Rejected by Advisor";
    if ((int)$row["hod_status"] === 5) return "Rejected by HOD";
    if ((int)$row["hod_status"] === 3 && ((int)$row["advisor_status"] === 1 || (int)$row["advisor_locked"] === 1)) return "Approved by HOD";
    if ((int)$row["forwarded_to_hod"] === 1) return "Pending HOD";
    return "Pending Advisor";
}


if ($format === 'pdf') {
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    function leavePdfEscape($text) {
        $text = preg_replace('/[^\x20-\x7E]/', ' ', (string)$text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
    function leavePdfWrap($text, $width = 92) {
        $text = trim((string)$text);
        if ($text === '') return [''];
        return preg_split('/\s+/', $text) ? wordwrap($text, $width, "\n", true) ? explode("\n", wordwrap($text, $width, "\n", true)) : [''] : [''];
    }

    $objects = [];
    $pages = [];
    $fontNormal = 0;
    $fontBold = 0;

    foreach ($records as $record) {
        $lines = [];
        $lines[] = 'LEAVE REQUEST';
        $lines[] = 'Register No: ' . ($record['reg_no'] ?? '');
        $lines[] = 'Department: ' . ($record['department'] ?? '') . '    Section: ' . ($record['section'] ?? '');
        $lines[] = 'Type: ' . ($record['request_type'] ?? '') . '    From: ' . ($record['from_date'] ?? '') . '    To: ' . ($record['to_date'] ?? '');
        $lines[] = 'Batch: ' . ($record['batch'] ?? '') . '    Semester: ' . ($record['semester'] ?? '') . '    Academic Year: ' . ($record['academic_year'] ?? '');
        $lines[] = 'Status: ' . exportStatus($record);
        $lines[] = 'Reason:';
        foreach (leavePdfWrap($record['reason'] ?? '', 92) as $line) $lines[] = '  ' . $line;
        $lines[] = 'Advisor Remarks: ' . ($record['advisor_remarks'] ?? '');
        $lines[] = 'HOD Remarks: ' . ($record['hod_remarks'] ?? '');
        $lines[] = 'Proof: ' . ($record['proof'] ?? '');
        $lines[] = 'Submitted: ' . ($record['created_at'] ?? '');
        $lines[] = 'Updated: ' . ($record['updated_at'] ?? '');

        $content = "BT /F2 18 Tf 40 790 Td (" . leavePdfEscape(array_shift($lines)) . ") Tj ET\n";
        $y = 755;
        foreach ($lines as $line) {
            $content .= "BT /F1 10 Tf 40 {$y} Td (" . leavePdfEscape($line) . ") Tj ET\n";
            $y -= 22;
        }
        $pages[] = $content;
    }

    if (!$pages) {
        $pages[] = "BT /F2 16 Tf 40 790 Td (No leave requests found) Tj ET\n";
    }

    // Build a valid multi-page PDF.
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $pageObjectIds = [];
    $nextId = 3;
    foreach ($pages as $content) {
        $pageId = $nextId++;
        $contentId = $nextId++;
        $pageObjectIds[] = $pageId;
        $objects[$pageId] = ['contentId' => $contentId];
        $objects[$contentId] = ['stream' => $content];
    }
    $fontNormal = $nextId++;
    $fontBold = $nextId++;
    $kids = implode(' ', array_map(fn($id) => $id . ' 0 R', $pageObjectIds));
    $objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageObjectIds) . ' >>';

    foreach ($pageObjectIds as $pageId) {
        $contentId = $objects[$pageId]['contentId'];
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontNormal . ' 0 R /F2 ' . $fontBold . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        $stream = $objects[$contentId]['stream'];
        $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
    }
    $objects[$fontNormal] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    $maxId = max(array_keys($objects));
    for ($i = 1; $i <= $maxId; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . ($objects[$i] ?? '') . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $maxId; $i++) $pdf .= sprintf('%010d 00000 n \n', $offsets[$i]);
    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $pdf;
    exit;
}

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF";
?>
<table border="1">
<tr><th>Register No</th><th>Department</th><th>Section</th><th>Type</th><th>From Date</th><th>To Date</th><th>Batch</th><th>Semester</th><th>Academic Year</th><th>Reason</th><th>Proof</th><th>Status</th><th>Advisor Remarks</th><th>HOD Remarks</th><th>Forwarded To HOD</th><th>Submitted At</th><th>Updated At</th></tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($row["reg_no"]) ?></td><td><?= htmlspecialchars($row["department"]) ?></td><td><?= htmlspecialchars($row["section"] ?? "") ?></td><td><?= htmlspecialchars($row["request_type"] ?? "") ?></td><td><?= htmlspecialchars($row["from_date"]) ?></td><td><?= htmlspecialchars($row["to_date"]) ?></td><td><?= htmlspecialchars($row["batch"]) ?></td><td><?= htmlspecialchars($row["semester"]) ?></td><td><?= htmlspecialchars($row["academic_year"]) ?></td><td><?= htmlspecialchars($row["reason"]) ?></td><td><?= htmlspecialchars($row["proof"] ?? "") ?></td><td><?= htmlspecialchars(exportStatus($row)) ?></td><td><?= htmlspecialchars($row["advisor_remarks"] ?? "") ?></td><td><?= htmlspecialchars($row["hod_remarks"] ?? "") ?></td><td><?= (int)$row["forwarded_to_hod"] === 1 ? "Yes" : "No" ?></td><td><?= htmlspecialchars($row["created_at"] ?? "") ?></td><td><?= htmlspecialchars($row["updated_at"] ?? "") ?></td>
</tr>
<?php endwhile; ?>
</table>
<?php $stmt->close(); $conn->close(); ?>
