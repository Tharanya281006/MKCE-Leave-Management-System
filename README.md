# College Leave Management System

## Overview

This is an existing PHP and MySQL/MariaDB college leave and academic management system. It coordinates student leave applications, advisor review, HOD decisions, course-faculty assignment, collaborative electives, timetable approval, faculty periods, attendance, and notifications.

The implementation preserves the existing PHP structure, Bootstrap/Font Awesome UI, database tables, and historical records.

## Problem Statement

Manual leave and academic coordination makes it difficult to route requests to the correct advisor, maintain section-specific academic assignments, review timetables, and protect approved leave during attendance marking.

## Roles

- **Student:** view profile and courses, submit/update leave or OD requests, view status and notifications.
- **Advisor/Faculty:** review assigned-section leave requests, assign theory faculty where authorized, design timetables, view teaching periods, and record attendance.
- **HOD:** review forwarded leave, manage advisor allocation, manage course/elective faculty assignments, and approve or reject timetables.

## Main Workflow

```text
Student -> Advisor -> HOD -> Final leave decision
Advisor -> Timetable draft -> HOD review -> Approved or Rejected -> Resubmit
HOD -> Course/elective faculty assignment -> Advisor timetable -> Faculty period -> Attendance
```

## Technology Stack

- PHP with mysqli prepared statements
- MySQL/MariaDB database `mkce_leave`
- Bootstrap 5 and Font Awesome
- jQuery/AJAX and SweetAlert2
- PHPMailer for configured notifications
- XAMPP/Apache-compatible deployment

## Department and Section Rules

Registration prefixes are mapped centrally in `includes/academic_helper.php`:

- `BIT` -> `IT`
- `BCS` -> `CSE`
- `BEC` -> `ECE`

Only BIT registrations use the IT numeric section rule:

- `BIT001` through `BIT063` -> Section A
- `BIT064` through `BIT126` -> Section B
- There is no IT-C.

BCS and BEC retain their database section values.

## Academic Target

The presentation target is:

- Department: IT
- Batch: 2024-2028
- Year: 3rd Year
- Sections: A and B
- Semesters: 5 and 6
- Academic year: 2026-2027

Verified advisor rows:

- IT / 3rd Year / 2024-2028 / A -> `ITSTAFF001`
- IT / 3rd Year / 2024-2028 / B -> `ITSTAFF002`

Existing second-year and CSE advisor rows must remain preserved.

## Database Tables

- `stu_login`: student identity, credentials, department, batch, section, status, and contact data.
- `staff_login`: staff/HOD accounts, department, status, and contact data.
- `year_advisor`: advisor allocation by department, year, batch, and section.
- `leave_requests`: student leave/OD applications and advisor/HOD decisions.
- `course_master`: normalized subject catalog.
- `course_faculty_assignments`: section-specific theory faculty assignments.
- `elective_faculty_assignments`: collaborative elective faculty assignments using internal section `ALL`.
- `elective_students`: dynamically selected elective participants with their real department and section.
- `timetable_sets` and `timetable_slots`: class timetable headers and relational slots.
- `attendance`: attendance linked to student, date, course, faculty, and timetable slot.
- `notifications`: user notifications.

No `advisor_allocations` table is used.

## Module Details

### Leave

Student requests are validated against the active database profile. Department and IT section are derived server-side. Advisors are authorized through `year_advisor`; HOD actions are department-scoped. Only advisor-approved and HOD-approved requests are final approved leave/OD.

### Course Faculty

Subjects are loaded from `course_master`, faculty from active `staff_login` rows, and assignment authorization from `year_advisor`. IT-A and IT-B are independent assignment contexts. Updates reuse the existing logical assignment instead of creating duplicate rows.

### Timetable

Advisors save draft or submit a timetable for HOD review. Timetable slots are relational and unique within a timetable set/day/hour. Faculty periods are read from approved timetable slots. Current editing behavior preserves status transitions but approved-set revision history remains a known limitation documented in the validation report.

### Attendance

Faculty can mark attendance only for approved timetable slots assigned to their staff ID. Student rosters are database-driven and canonical IT section filtering is applied. HOD-approved leave and OD are rechecked at the save endpoint and cannot be overwritten by ordinary attendance values.

### Electives

Collaborative electives do not use a user-entered section. The assignment uses internal `ALL`; selected students retain their actual section in `elective_students` and are loaded dynamically from `stu_login`.

## Security Measures

- Session and role checks on protected pages and AJAX endpoints.
- Prepared statements for application-controlled SQL values.
- Server-side class and department authorization.
- HTML escaping for displayed database values.
- Status checks to prevent repeated leave/timetable decisions.
- Approved leave/OD protection at the attendance write boundary.

The legacy login schema currently compares the existing password field directly. Password hashing should be introduced through a separate controlled migration after the current credential format is confirmed.

## Installation

1. Install XAMPP with Apache, PHP, and MySQL/MariaDB.
2. Copy the project into `htdocs`, for example `C:/xampp/htdocs/leave_tih_1`.
3. Create/select database `mkce_leave`.
4. Import the existing project schema/data SQL in the order required by the deployment.
5. Review and run `database/migrations/third_year_it_academic_management.sql` once.
6. Confirm the live `staff_login`, `stu_login`, and `year_advisor` rows before demonstration.
7. Configure `db/connection.php` for the local database.
8. Configure mail settings in `mail_config.php` only if email notifications are required.
9. Open `http://localhost/leave_tih_1/login.php`.

Do not use credentials from documentation as real credentials. Use the credentials already configured in the database or local demonstration seed data.

## Project Structure

- Root PHP pages: dashboards and workflow screens.
- `ajax/`: authenticated JSON actions.
- `includes/`: shared academic and course-faculty helpers.
- `db/`: database connection.
- `hod/`: HOD allocation pages.
- `database/migrations/`: safe schema/index and optional advisor seed migration.
- `uploads/proofs/`: uploaded leave/OD documents.

## Validation

Static PHP lint passed for the project files during the current validation session. Canonical mapping checks passed for BIT001, BIT063, BIT064, BIT113, BIT126, BCS001, and BEC001.

Live MySQL integration, browser workflows, real login, mail delivery, and database migration execution were not run in this environment because the MySQL CLI/server connection was unavailable. See `FINAL_VALIDATION_REPORT.md` for the complete status.

## Troubleshooting

- If the dashboard is blank, confirm the session role and database connection.
- If an advisor sees no class, inspect the active `year_advisor` row and exact `staff_id`.
- If subjects are missing, inspect active `course_master` and section-specific assignments.
- If attendance shows no students, verify the approved timetable slot, faculty ID, batch, section, and active student rows.
- If mail fails, verify `mail_config.php` and SMTP credentials outside source control.

## Known Limitations and Future Enhancements

- Live database and browser integration testing is still required before a production demonstration.
- Approved timetable editing currently moves the set back to Draft; a separate immutable revision table/version model would provide stronger historical preservation.
- Password hashing and CSRF tokens should be added through controlled migrations and endpoint updates.
- A dedicated automated PHP integration test suite would improve regression coverage.
