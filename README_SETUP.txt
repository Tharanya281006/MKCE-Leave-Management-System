LEAVE TIH 1 - COURSE FACULTY ASSIGNING

1. Extract this folder into XAMPP htdocs as:
   htdocs/leave_tih_1

2. Create/select database:
   mkce_leave

3. Import this SQL file in phpMyAdmin:
   course_faculty_assignments.sql

4. Existing tables such as staff_login, year_advisor and leave_requests must already exist.

5. Access control:
   - Only a staff member present in year_advisor can open Course Faculty Assigning.
   - Advisor can assign a course, section and faculty.
   - Course faculty login uses the existing staff_login credentials.
   - Course faculty dashboard shows only courses assigned to their staff_id.
   - Only an authorized advisor can view staff leave requests.
   - logout.php and existing CSS are preserved.

6. Important:
   The presentation target uses these academic values:
   Year: 3rd Year
   Batch: 2024-2028
   Sections: A and B
   Semesters: 5 and 6

7. If your department value is not exactly IT, the assignment page seeds the course rows using the logged-in advisor department automatically.
