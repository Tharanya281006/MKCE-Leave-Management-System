<?php

/** Resolve the canonical department represented by a registration number. */
function getDepartmentFromRegNo($regNo, $storedDepartment = '')
{
    $prefix = strtoupper(substr(preg_replace('/[^A-Z]/i', '', trim((string) $regNo)), 0, 3));
    $departments = [
        'BIT' => 'IT',
        'BCS' => 'CSE',
        'BEC' => 'ECE',
    ];

    if (isset($departments[$prefix])) {
        return $departments[$prefix];
    }

    return strtoupper(trim((string) $storedDepartment));
}

/** Return the IT section for BIT001-BIT126, or an empty string outside that range. */
function getITSectionFromRegNo($regNo)
{
    $regNo = strtoupper(trim((string) $regNo));

    /*
     * Actual MKCE IT register numbers are like:
     * 927624BIT001
     * 927624BIT005
     * 927624BIT113
     *
     * Therefore, find "BIT" anywhere in the register number
     * and read the numeric part immediately after BIT.
     */

    $position = strpos($regNo, 'BIT');

    if ($position === false) {
        return '';
    }

    $numberPart = substr($regNo, $position + 3);

    // Take only the numeric part after BIT
    if (!preg_match('/^(\d+)/', $numberPart, $matches)) {
        return '';
    }

    $number = (int) $matches[1];

    if ($number >= 1 && $number <= 63) {
        return 'A';
    }

    if ($number >= 64 && $number <= 126) {
        return 'B';
    }

    return '';
}

function getSectionKey($section)
{
    $parts = preg_split('/[\s\-_\/]+/', strtoupper(trim((string) $section)), -1, PREG_SPLIT_NO_EMPTY);
    return $parts ? (string) end($parts) : '';
}

/** Resolve department and section without trusting a browser-provided section. */
function getCanonicalStudentClass($regNo, $storedDepartment, $storedSection)
{
    $department = getDepartmentFromRegNo($regNo, $storedDepartment);
    $section = getITSectionFromRegNo($regNo);

    if ($section === '') {
        $section = trim((string) $storedSection);
    }

    return [
        'department' => $department,
        'section' => $section,
    ];
}

/** Confirm that an advisor owns the student's canonical department/batch/section. */
function isAdvisorForStudent(mysqli $conn, $advisorStaffId, $regNo, $department, $batch, $storedSection, $yearName = '')
{
    $class = getCanonicalStudentClass($regNo, $department, $storedSection);
    $stmt = $conn->prepare(
        'SELECT department, batch, section, year_name FROM year_advisor
         WHERE advisor_staff_id = ? AND batch = ?'
    );
    if (!$stmt) {
        return false;
    }

    $advisorStaffId = trim((string) $advisorStaffId);
    $batch = trim((string) $batch);
    $stmt->bind_param('ss', $advisorStaffId, $batch);
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed = false;
    while ($row = $result->fetch_assoc()) {
        $assignedDepartment = getDepartmentFromRegNo($regNo, $row['department']);
        if (strcasecmp($assignedDepartment, $class['department']) === 0
            && ($yearName === '' || strcasecmp(trim((string) $row['year_name']), trim((string) $yearName)) === 0)
            && getSectionKey($row['section']) === getSectionKey($class['section'])) {
            $allowed = true;
            break;
        }
    }
    $stmt->close();
    return $allowed;
}

/** Calculate the academic year for the current 2024-2028 presentation batch. */
function getAcademicYear($batch, $semester = 0)
{
    $batch = trim((string) $batch);
    if ($batch === '2024-2028' && in_array((int) $semester, [5, 6], true)) {
        return '2026-2027';
    }

    return '';
}

/** Return LE, OD, or an empty string when no final HOD-approved request matches. */
function getApprovedLeaveOrOD(mysqli $conn, $regNo, $date, $period)
{
    $stmt = $conn->prepare(
        'SELECT request_type, duration_type, selected_period
         FROM leave_requests
         WHERE reg_no = ?
           AND from_date <= ?
           AND to_date >= ?
           AND advisor_status = 1
           AND hod_status = 3
         ORDER BY id DESC'
    );

    if (!$stmt) {
        return '';
    }

    $regNo = trim((string) $regNo);
    $date = trim((string) $date);
    $period = trim((string) $period);

    $stmt->bind_param('sss', $regNo, $date, $date);
    $stmt->execute();

    $result = $stmt->get_result();

    /*
     * Convert the attendance period into a number.
     *
     * Examples:
     * "4"      -> 4
     * "Hour 4" -> 4
     * "Period 4" -> 4
     */
    $periodNumber = (int) preg_replace('/[^0-9]/', '', $period);

    $approvedType = '';

    while ($leave = $result->fetch_assoc()) {

        $durationType = trim((string) $leave['duration_type']);
        $selectedPeriod = trim((string) $leave['selected_period']);

        /*
         * SINGLE DAY
         *
         * Entire day is covered.
         */
        if ($durationType === 'Single Day') {

            $matches = true;

        /*
         * SPECIFIC HOURS
         *
         * Example:
         * selected_period = "Hour 4"
         * attendance period = "4"
         *
         * Both become number 4.
         */
        } elseif ($durationType === 'Specific Hours') {

            $selectedPeriodNumber =
                (int) preg_replace('/[^0-9]/', '', $selectedPeriod);

            $matches =
                ($selectedPeriodNumber > 0 &&
                 $selectedPeriodNumber === $periodNumber);

        /*
         * HALF DAY
         *
         * Morning = Periods 1, 2, 3
         * Afternoon = Periods 4, 5, 6...
         */
        } elseif ($durationType === 'Half Day') {

            $isMorningPeriod =
                in_array($periodNumber, [1, 2, 3], true);

            $selectedIsMorning =
                strcasecmp($selectedPeriod, 'Morning') === 0;

            $selectedIsAfternoon =
                strcasecmp($selectedPeriod, 'Afternoon') === 0;

            if ($selectedIsMorning) {

                $matches = $isMorningPeriod;

            } elseif ($selectedIsAfternoon) {

                $matches =
                    ($periodNumber > 3);

            } else {

                $matches = false;
            }

        } else {

            $matches = false;
        }

        if ($matches) {

            $approvedType =
                strtoupper(trim((string) $leave['request_type'])) === 'OD'
                    ? 'OD'
                    : 'LE';

            break;
        }
    }

    $stmt->close();

    return $approvedType;
}