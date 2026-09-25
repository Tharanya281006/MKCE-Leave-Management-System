# College Leave Management System

## 1. Executive Summary

The project is a PHP and MySQL/MariaDB college leave and academic management system. It integrates student leave/OD applications, advisor routing, HOD decisions, course-faculty allocation, collaborative electives, timetables, faculty periods, attendance, and notifications.

The current engineering work preserved the existing application and added centralized department/section resolution, safer advisor/HOD authorization, database-backed student profile data, explicit timetable class selection, and final approved leave/OD protection during attendance.

## 2. Problem Statement

The system must reliably route students to the correct advisor and keep academic records independent by department, batch, year, section, semester, and timetable slot. Incorrect section derivation or weak authorization can expose leave requests, mix IT-A and IT-B, or allow approved leave to be overwritten in attendance.

## 3. Existing System

The application uses server-rendered PHP pages, AJAX endpoints, mysqli prepared statements, a shared database connection, Bootstrap UI, and existing normalized academic tables. Navigation is role-based through `index.php`, with separate student, staff/advisor, and HOD pages.

## 4. Proposed System

The maintained system uses trusted database profiles and shared helpers for business rules. It preserves the existing tables and workflow while tightening authorization at backend endpoints and aligning presentation behavior with the verified 2024-2028 IT third-year data.

## 5. Objectives

- Route leave to the correct advisor.
- Preserve department and section independence.
- Keep course faculty and timetable data database-driven.
- Protect final approved leave/OD during attendance.
- Prevent unauthorized cross-section and cross-department operations.
- Provide a repeatable demonstration and validation record.

## 6. Target Users

Students, assigned advisors/faculty, and department HODs.

## 7. Functional Requirements

The application supports authentication, profile display, leave/OD submission, proof uploads, advisor review, HOD review, notifications, course-faculty assignment, elective participation, timetable creation/review, faculty timetable viewing, and attendance.

## 8. Non-Functional Requirements

The system requires server-side authorization, prepared statements, escaped output, preserved historical data, responsive existing UI, clear statuses, and graceful error messages.

## 9. Technology Stack

PHP, mysqli, MySQL/MariaDB, Apache/XAMPP, Bootstrap 5, Font Awesome, jQuery/AJAX, SweetAlert2, and PHPMailer.

## 10. System Architecture

```text
Browser
  -> index.php role router and shared UI
  -> PHP page modules
  -> authenticated AJAX endpoints
  -> db/connection.php
  -> mkce_leave tables
```

Shared business logic is in `includes/academic_helper.php` and `includes/course_faculty_helper.php`.

## 11. Module Description

### 11.1 Student Module

Student identity is loaded from `stu_login`. The dashboard displays register number, name, email, department, batch, section, advisor, and advisor year when available. Courses are resolved from course and assignment tables.

### 11.2 Advisor Module

Advisor permissions are based on active staff accounts and `year_advisor`. Leave requests are filtered against each advisor's assigned class. Course faculty and timetable operations are class-scoped.

### 11.3 HOD Module

The HOD reviews forwarded leave requests within the HOD's department, manages advisor allocation through `year_advisor`, manages faculty assignments, and reviews timetables.

### 11.4 Faculty Module

Faculty timetable periods are read from approved `timetable_slots` where `faculty_id` matches the authenticated staff member. Attendance is only available for assigned approved slots.

### 11.5 Course Faculty Assignment

Theory subjects come from `course_master`; faculty comes from active `staff_login`; class authorization comes from `year_advisor`. Section is part of the assignment context, so IT-A and IT-B can use different faculty for the same course.

### 11.6 Timetable

`timetable_sets` stores the class/semester status and `timetable_slots` stores day/hour/course/faculty. Draft, pending, rejected, and approved states are supported. Duplicate day/hour slots are prevented by the existing unique key when present.

### 11.7 Attendance

Attendance is associated with student, date, period, subject/course, faculty, and timetable slot. Final HOD-approved Leave/OD is resolved server-side and cannot be changed to ordinary attendance.

### 11.8 Elective Management

Collaborative elective faculty assignment uses internal section `ALL`. Student selection is database-driven and each `elective_students` row retains the student's real department and section.

### 11.9 Notifications

Leave decision endpoints create notifications through the existing notification helper. Email support is present through the existing mail configuration and PHPMailer integration.

## 12. Database Design

| Table | Purpose |
|---|---|
| `stu_login` | Student credentials and academic profile |
| `staff_login` | Faculty and HOD accounts |
| `year_advisor` | Department/year/batch/section advisor allocation |
| `leave_requests` | Leave/OD applications and decisions |
| `course_master` | Subject catalog |
| `course_faculty_assignments` | Section-specific theory assignment |
| `elective_faculty_assignments` | Collaborative elective faculty assignment |
| `elective_students` | Elective participant enrollment |
| `timetable_sets` | Timetable header and approval status |
| `timetable_slots` | Relational timetable periods |
| `attendance` | Student/date/slot attendance |
| `notifications` | In-app decision notifications |

The project does not use or create `advisor_allocations`.

## 13. Department and Section Logic

`BIT` maps to IT, `BCS` maps to CSE, and `BEC` maps to ECE. Only BIT uses numeric sections: 001-063 map to A and 064-126 map to B. There is no IT-C. The shared resolver is in `includes/academic_helper.php`.

## 14. Leave Workflow

```text
Student submits -> Advisor reviews -> Advisor forwards -> HOD reviews -> Final decision
```

Advisor approval alone is not final approval. Attendance only treats a request as final Leave/OD when advisor approval and `hod_status = 3` are both present.

## 15. Security

The implementation uses session/role checks, active-account checks in shared course authorization, prepared statements for user-controlled SQL values, escaped display output, class-scoped authorization, HOD department scoping, and write-time approved Leave/OD enforcement.

Remaining security improvements include CSRF protection and a controlled migration from legacy direct password comparison to password hashing.

## 16. User Interface

The existing Bootstrap and Font Awesome template is preserved. Student profile information was made more complete, timetable class selection was made explicit, and existing status badges, tables, forms, and SweetAlert messages remain in use.

## 17. Testing Strategy

Testing used static PHP lint, editor diagnostics, source inspection, canonical mapping smoke tests, and SQL/authorization review. Live database tests, browser interaction, mail delivery, and migration execution require a running MySQL/MariaDB environment and were not claimed as passed.

## 18. Test Cases

| Test ID | Module | Input | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| T01 | Resolver | BIT001 | IT / A | IT / A from PHP smoke test | PASS |
| T02 | Resolver | BIT063 | IT / A | IT / A from PHP smoke test | PASS |
| T03 | Resolver | BIT064 | IT / B | IT / B from PHP smoke test | PASS |
| T04 | Resolver | BIT113 | IT / B | IT / B from PHP smoke test | PASS |
| T05 | Resolver | BIT126 | IT / B | IT / B from PHP smoke test | PASS |
| T06 | Resolver | BCS001 | CSE | CSE from PHP smoke test | PASS |
| T07 | Resolver | BEC001 | ECE | ECE from PHP smoke test | PASS |
| T08 | Advisor auth | ITSTAFF001/002 | Separate A/B access | Source path verified; DB execution unavailable | NOT TESTED |
| T09 | Leave | Student -> Advisor -> HOD | Final status only after HOD | Source path reviewed; DB execution unavailable | NOT TESTED |
| T10 | Course assignment | Same course in A and B | Independent assignments | Source/schema reviewed; DB execution unavailable | NOT TESTED |
| T11 | Timetable | A and B same period | Independent timetable sets | Source/schema reviewed; DB execution unavailable | NOT TESTED |
| T12 | Attendance | HOD-approved Leave/OD | Locked LEAVE/OD | Write guard verified statically; DB execution unavailable | NOT TESTED |
| T13 | Elective | Cross-section student selection | Real section retained | Source path reviewed; DB execution unavailable | NOT TESTED |
| T14 | PHP syntax | Project PHP files | No parse errors | 48 files linted successfully | PASS |

## 19. Validation Results

The project PHP syntax check passed. The canonical department/section boundary checks passed. Advisor and HOD endpoint SQL parameter mismatches found during audit were corrected. Database-dependent results remain unexecuted.

## 20. Known Limitations

- No live MySQL/MariaDB server was available for this validation session.
- No browser/E2E runner was used.
- Approved timetable editing currently transitions the existing record back to Draft rather than creating an immutable revision row.
- Login uses the existing password storage/comparison contract; password hashing is a future controlled change.
- CSRF tokens are not yet implemented.

## 21. Future Enhancements

Add timetable revision/version tables, CSRF protection, password hashing migration, automated integration tests, database constraints after duplicate audits, and a production-grade secrets/configuration strategy.

## 22. Conclusion

The maintained application now has a centralized academic identity resolver, stricter advisor/HOD authorization, explicit class selection for timetable work, database-backed student profile display, and protected approved leave/OD attendance behavior. The next required sign-off step is running the documented test cases against the actual `mkce_leave` database and browser environment.
