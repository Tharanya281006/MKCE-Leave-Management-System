<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/course_faculty_helper.php';

$actor = null;
$classes = [];
$initialState = null;
$errorMessage = '';

try {

    $actor = cfa_actor($conn, ['staff']);

    $classes = cfa_rows(
        $conn,
        "
        SELECT
            year_name,
            batch,
            department,
            section
        FROM year_advisor
        WHERE advisor_staff_id = ?
          AND department = ?
                    AND department = 'IT'
                    AND year_name = '3rd Year'
                    AND batch = '2024-2028'
                    AND section IN ('A', 'B')
                ORDER BY FIELD(section, 'A', 'B')
        ",
        'ss',
        [
            $actor['staff_id'],
            $actor['department']
        ]
    );

    if (!$classes) {

        throw new CfaException(
            'Only an authorized Year Advisor can assign course faculty.',
            403
        );

    }

    if (count($classes) !== 1) {
        throw new CfaException(
            'This advisor must have exactly one active third-year IT class allocation.',
            403
        );
    }

    $initialState =
        cfa_build_state(
            $conn,
            $actor,
            $classes[0]
        );

} catch (Throwable $e) {

    $errorMessage =
        $e instanceof CfaException
            ? $e->getMessage()
            : 'Unable to load Course Faculty Assignment.';

}


$years = [];
$batches = [];

foreach ($classes as $c) {

    $years[$c['year_name']] = true;
    $batches[$c['batch']] = true;

}

?>

<div class="container-fluid p-4">

    <!-- HEADER -->

    <div class="mb-4">

        <h4 class="fw-bold mb-1">

            <i class="fas fa-users me-2"></i>

            Course Faculty Assignment

        </h4>

        <p class="text-muted mb-0">

            Assign and update faculty for theory subjects.

        </p>

    </div>


    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger">

            <i class="fas fa-lock me-2"></i>

            <?= htmlspecialchars(
                $errorMessage,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php else: ?>


        <!-- ADVISOR DETAILS -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white py-3">

                <h5 class="mb-0 fw-bold">

                    <i class="fas fa-user-tie me-2 text-primary"></i>

                    Advisor Details

                </h5>

            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-4">

                        <strong>Advisor ID</strong>

                        <p class="mb-0">

                            <?= htmlspecialchars($actor['staff_id']) ?>

                        </p>

                    </div>


                    <div class="col-md-4">

                        <strong>Department</strong>

                        <p class="mb-0">

                            <?= htmlspecialchars($actor['department']) ?>

                        </p>

                    </div>


                    <div class="col-md-4">

                        <strong>Role</strong>

                        <p class="mb-0">

                            Authorized Year Advisor

                        </p>

                    </div>

                </div>

            </div>

        </div>


        <!-- ASSIGNMENT FORM -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white py-3">

                <div class="d-flex justify-content-between align-items-center">

                    <h5 class="mb-0 fw-bold">

                        <i class="fas fa-book me-2 text-primary"></i>

                        <span id="courseFormTitle">

                            Assign Course Faculty

                        </span>

                    </h5>


                    <span
                        id="courseEditBadge"
                        class="badge bg-warning text-dark d-none"
                    >
                        EDIT MODE
                    </span>

                </div>

            </div>


            <div class="card-body">

                <form id="courseFacultyForm">

                    <div class="row g-3">


                        <!-- YEAR -->

                        <div class="col-md-3">

                            <label class="form-label fw-bold">
                                Year
                            </label>

                            <input
                                id="courseFacultyYear"
                                name="year_name"
                                class="form-control"
                                value="<?= htmlspecialchars($classes[0]['year_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                readonly
                                required
                            >

                        </div>


                        <!-- BATCH -->

                        <div class="col-md-3">

                            <label class="form-label fw-bold">
                                Batch
                            </label>

                            <input
                                id="courseFacultyBatch"
                                name="batch"
                                class="form-control"
                                value="<?= htmlspecialchars($classes[0]['batch'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                readonly
                                required
                            >

                        </div>


                        <!-- SECTION -->

                        <div class="col-md-3">

                            <label class="form-label fw-bold">
                                Section
                            </label>

                            <input
                                id="courseFacultySection"
                                name="section"
                                class="form-control"
                                value="<?= htmlspecialchars($classes[0]['section'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                readonly
                                required
                            >

                        </div>
                        <!-- DEPARTMENT -->

<div class="col-md-3">

    <label class="form-label fw-bold">
        Faculty Department
    </label>

    <select
        id="courseFacultyDepartment"
        name="faculty_department"
        class="form-select"
        required
    >
        <option value="IT">IT</option>
        <option value="CSE">CSE</option>
        <option value="ECE">ECE</option>
    </select>

</div>


                        <!-- SUBJECT -->

                        <div class="col-md-3">

                            <label class="form-label fw-bold">
                                Subject
                            </label>

                            <select
                                id="courseFacultyCourse"
                                name="course_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Subject
                                </option>

                                <?php foreach (($initialState['courses'] ?? []) as $courseOption): ?>
                                    <?php if (($courseOption['type'] ?? '') === 'Theory'): ?>
                                        <option value="<?= (int) $courseOption['course_id'] ?>">
                                            <?= htmlspecialchars($courseOption['course_code'], ENT_QUOTES, 'UTF-8') ?> -
                                            <?= htmlspecialchars($courseOption['course_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- FACULTY -->

                        <div class="col-md-6">

                            <label class="form-label fw-bold">
                                Course Faculty
                            </label>

                            <select
                                id="courseFacultyStaff"
                                name="faculty_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Course Faculty
                                </option>

                                <?php foreach (($initialState['faculty'] ?? []) as $facultyOption): ?>
                                    <option value="<?= htmlspecialchars($facultyOption['staff_id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($facultyOption['staff_name'], ENT_QUOTES, 'UTF-8') ?> -
                                        <?= htmlspecialchars($facultyOption['staff_id'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- BUTTONS -->

                        <div class="col-md-6 d-flex align-items-end gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                                id="courseSaveBtn"
                            >

                                <i class="fas fa-save me-2"></i>

                                Save Assignment

                            </button>


                            <button
                                type="button"
                                id="cancelCourseEdit"
                                class="btn btn-outline-secondary d-none"
                            >

                                <i class="fas fa-times me-2"></i>

                                Cancel Edit

                            </button>


                            <button
                                type="button"
                                id="removeCourseFaculty"
                                class="btn btn-outline-danger"
                            >

                                <i class="fas fa-trash me-2"></i>

                                Remove

                            </button>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- CURRENT ASSIGNMENTS -->

        <div class="card shadow-sm border-0">

            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">

                <h5 class="mb-0 fw-bold">

                    <i class="fas fa-list me-2 text-primary"></i>

                    Current Course Faculty

                </h5>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="editAllCourseFaculty">
                        <i class="fas fa-pen-to-square me-1"></i> Edit All Assignments
                    </button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="saveAllCourseFaculty">
                        <i class="fas fa-save me-1"></i> Save All Changes
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="cancelAllCourseFaculty">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                </div>

            </div>


            <div class="table-responsive">

                <table
                    class="table table-hover align-middle mb-0"
                    id="courseFacultyTable"
                >

                    <thead class="table-light">

                        <tr>

                            <th>Year</th>

                            <th>Batch</th>

                            <th>Section</th>

                            <th>Course</th>

                            <th>Type</th>

                            <th>Credits</th>

                            <th>Faculty Department</th>

                            <th>Faculty</th>

                            <th>Assigned By</th>

                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody id="courseFacultyTableBody"></tbody>

                </table>

            </div>

        </div>


    <?php endif; ?>

</div>


<?php if ($errorMessage === ''): ?>

<script>

(function () {

    const classes =
        <?= json_encode(
            $classes,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;


    const initialState =
        <?= json_encode(
            $initialState,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;


    const year =
        document.getElementById('courseFacultyYear');

    const batch =
        document.getElementById('courseFacultyBatch');

    const section =
        document.getElementById('courseFacultySection');
    
    const department =
    document.getElementById('courseFacultyDepartment');

    const course =
        document.getElementById('courseFacultyCourse');

    const faculty =
        document.getElementById('courseFacultyStaff');

    const form =
        document.getElementById('courseFacultyForm');

    const removeBtn =
        document.getElementById('removeCourseFaculty');

    const cancelBtn =
        document.getElementById('cancelCourseEdit');

    const saveBtn =
        document.getElementById('courseSaveBtn');

    const formTitle =
        document.getElementById('courseFormTitle');

    const editBadge =
        document.getElementById('courseEditBadge');

    const body =
        document.getElementById('courseFacultyTableBody');

    const editAllBtn =
        document.getElementById('editAllCourseFaculty');

    const saveAllBtn =
        document.getElementById('saveAllCourseFaculty');

    const cancelAllBtn =
        document.getElementById('cancelAllCourseFaculty');


    let state = initialState;
    let bulkEditMode = false;


    function esc(value) {

        const d =
            document.createElement('div');

        d.textContent =
            value ?? '';

        return d.innerHTML;

    }


    function fillState(s) {

        if (!s || !Array.isArray(s.courses) || !Array.isArray(s.faculty) || !Array.isArray(s.all_faculty) || !Array.isArray(s.assigned_faculty_ids)) {
            Swal.fire({
                icon: 'error',
                text: 'Unable to load courses and faculty for the authorized class.'
            });
            return;
        }

        state = s;

        if (bulkEditMode) {
            bulkEditMode = false;
            editAllBtn.classList.remove('d-none');
            saveAllBtn.classList.add('d-none');
            cancelAllBtn.classList.add('d-none');
        }

        course.innerHTML =
            '<option value="">Select Subject</option>';


        (s.courses || [])
            .filter(function (c) {

                return c.type === 'Theory';

            })
            .forEach(function (c) {

                course.insertAdjacentHTML(
                    'beforeend',
                    `<option value="${c.course_id}">
                        ${esc(c.course_code)}
                        -
                        ${esc(c.course_name)}
                    </option>`
                );

            });


        faculty.innerHTML =
            '<option value="">Select Course Faculty</option>';

        renderTable();

        updateFacultyOptions();

    }


    function renderTable() {

        body.innerHTML = '';

        const scope = state.scope || {};
        const assignedIds = new Set(
            (state.assigned_faculty_ids || []).map(function (id) {
                return String(id);
            })
        );

        const allFaculty = Array.isArray(state.all_faculty)
            ? state.all_faculty
            : [];

        (state.courses || []).forEach(function (c) {

            const assigned = !!c.faculty_id;
            const status = assigned ? 'Assigned' : 'Not Assigned';
            let facultyCell = '<span class="text-muted">Not assigned</span>';

            if (bulkEditMode && c.type === 'Theory') {
                let options = '<option value="">Not Assigned</option>';
                allFaculty.forEach(function (f) {
                    const id = String(f.staff_id);
                    const currentId = c.faculty_id ? String(c.faculty_id) : '';
                    // In common edit mode all active faculty are available so assignments can be changed or swapped.
                    // The server validates that the final saved state contains no duplicate faculty in the class.
                    const selected = id === currentId ? ' selected' : '';
                    options += `<option value="${esc(id)}"${selected}>${esc(f.department)} - ${esc(f.staff_name)} - ${esc(id)}</option>`;
                });
                facultyCell = `<select class="form-select form-select-sm bulk-faculty-select" data-course-id="${c.course_id}">${options}</select>`;
            } else if (c.faculty_name) {
                facultyCell = `${esc(c.faculty_name)} <small class="text-muted">(${esc(c.faculty_department || '')})</small>`;
            }

            body.insertAdjacentHTML(
                'beforeend',
                `
                <tr>
                    <td>${esc(scope.year_name || '')}</td>
                    <td>${esc(scope.batch || '')}</td>
                    <td><span class="badge bg-info text-dark">${esc(scope.section || '')}</span></td>
                    <td><strong>${esc(c.course_code)}</strong><br><span class="text-muted">${esc(c.course_name)}</span></td>
                    <td>${esc(c.type)}</td>
                    <td>${c.credits}</td>
                    <td>${c.faculty_department ? esc(c.faculty_department) : '-'}</td>
                    <td>${facultyCell}</td>
                    <td>${c.assigned_by ? esc(c.assigned_by) : '-'}</td>
                    <td><span class="badge ${assigned ? 'bg-success' : 'bg-secondary'}">${status}</span></td>
                </tr>
                `
            );
        });
    }


    function updateFacultyOptions() {

        const selectedCourse = String(course.value || '');

        const current =
            (state.courses || []).find(function (c) {
                return String(c.course_id) === selectedCourse;
            });

        const currentFacultyId =
            current && current.faculty_id
                ? String(current.faculty_id)
                : '';

        const assignedIds = new Set(
            (state.assigned_faculty_ids || []).map(function (id) {
                return String(id);
            })
        );

        faculty.innerHTML =
            '<option value="">Select Course Faculty</option>';

        (state.faculty || []).forEach(function (f) {
            const facultyId = String(f.staff_id);

            /* Keep the current course's faculty visible while editing it.
             * Hide every other faculty already used by another subject in this class. */
            if (assignedIds.has(facultyId) && facultyId !== currentFacultyId) {
                return;
            }

            faculty.insertAdjacentHTML(
                'beforeend',
                `<option value="${esc(facultyId)}">
                    ${esc(f.staff_name)} - ${esc(facultyId)}
                </option>`
            );
        });

        faculty.value = currentFacultyId;
    }


    async function loadState() {

        if (
            !year.value ||
            !batch.value ||
            !section.value
        ) {

            return;

        }


        const fd =
            new FormData();

        fd.append(
            'year_name',
            year.value
        );

        fd.append(
            'batch',
            batch.value
        );

        fd.append(
            'section',
            section.value
        );

        fd.append(
            'faculty_department',
            department.value
        );

        const response =
            await fetch(
                'ajax/get_faculty_assignments.php',
                {
                    method: 'POST',
                    body: fd
                }
            );


        const result =
            await response.json();


        if (!result.status) {

            Swal.fire({
                icon: 'error',
                text: result.message
            });

            return;

        }


        fillState(result.state);

    }


    function enterBulkEditMode() {
        bulkEditMode = true;
        editAllBtn.classList.add('d-none');
        saveAllBtn.classList.remove('d-none');
        cancelAllBtn.classList.remove('d-none');
        renderTable();
    }

    function exitBulkEditMode() {
        bulkEditMode = false;
        editAllBtn.classList.remove('d-none');
        saveAllBtn.classList.add('d-none');
        cancelAllBtn.classList.add('d-none');
        renderTable();
    }

    async function saveAllAssignments() {
        const assignments = [];
        document.querySelectorAll('.bulk-faculty-select').forEach(function (select) {
            assignments.push({
                course_id: Number(select.dataset.courseId),
                faculty_id: String(select.value || '')
            });
        });

        saveAllBtn.disabled = true;
        cancelAllBtn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('year_name', year.value);
            fd.append('batch', batch.value);
            fd.append('section', section.value);
            fd.append('assignments', JSON.stringify(assignments));

            const response = await fetch('ajax/save_course_faculty_bulk.php', {
                method: 'POST',
                body: fd
            });
            const result = await response.json();

            await Swal.fire({
                icon: result.status ? 'success' : 'error',
                title: result.status ? 'Success' : 'Update Failed',
                text: result.message
            });

            if (result.status) {
                fillState(result.state);
                exitBulkEditMode();
            }
        } catch (error) {
            await Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: 'Unable to save all course-faculty assignments.'
            });
        } finally {
            saveAllBtn.disabled = false;
            cancelAllBtn.disabled = false;
        }
    }


    function resetEditMode() {

        formTitle.textContent =
            'Assign Course Faculty';

        saveBtn.innerHTML =
            '<i class="fas fa-save me-2"></i> Save Assignment';

        editBadge.classList.add('d-none');

        cancelBtn.classList.add('d-none');

        course.value = '';

        faculty.value = '';

    }
    editAllBtn.addEventListener('click', enterBulkEditMode);
    saveAllBtn.addEventListener('click', saveAllAssignments);
    cancelAllBtn.addEventListener('click', exitBulkEditMode);

    department.addEventListener(
    'change',
    loadState
);

    course.addEventListener(
        'change',
        updateFacultyOptions
    );


    form.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            const fd =
                new FormData(form);

            fd.append(
                'action',
                'save'
            );


            const response =
                await fetch(
                    'ajax/save_course_faculty.php',
                    {
                        method: 'POST',
                        body: fd
                    }
                );


            const result =
                await response.json();


            Swal.fire({

                icon:
                    result.status
                        ? 'success'
                        : 'error',

                title:
                    result.status
                        ? 'Success'
                        : 'Update Failed',

                text:
                    result.message

            }).then(function () {

                if (result.status) {

                    fillState(result.state);

                    resetEditMode();

                }

            });

        }
    );


    cancelBtn.addEventListener(
        'click',
        resetEditMode
    );


    removeBtn.addEventListener(
        'click',
        async function () {

            if (!course.value) {

                Swal.fire({

                    icon: 'warning',

                    text:
                        'Select a subject first.'

                });

                return;

            }


            const confirm =
                await Swal.fire({

                    icon: 'warning',

                    title: 'Remove assignment?',

                    text:
                        'This will deactivate the current course-faculty assignment.',

                    showCancelButton: true,

                    confirmButtonText:
                        'Yes, remove it',

                    cancelButtonText:
                        'Cancel'

                });


            if (!confirm.isConfirmed) {

                return;

            }


            const fd =
                new FormData(form);

            fd.append(
                'action',
                'remove'
            );


            const response =
                await fetch(
                    'ajax/save_course_faculty.php',
                    {
                        method: 'POST',
                        body: fd
                    }
                );


            const result =
                await response.json();


            Swal.fire({

                icon:
                    result.status
                        ? 'success'
                        : 'error',

                text:
                    result.message

            }).then(function () {

                if (result.status) {

                    fillState(result.state);

                    resetEditMode();

                }

            });

        }
    );


    loadState();

})();

</script>

<?php endif; ?>