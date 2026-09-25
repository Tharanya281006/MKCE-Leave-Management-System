<?php

require_once __DIR__ . "/db/connection.php";

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: login.php");
    exit;
}

$reg_no = $_SESSION["reg_no"];

/* STUDENT NOTIFICATIONS */
require_once __DIR__ . "/notification_helper.php";
ensureNotificationTable($conn);

$studentNotifications = [];
$notificationSql = "
    SELECT
        id,
        title,
        message,
        notification_type,
        created_at
    FROM notifications
    WHERE reg_no = ?
      AND is_read = 0
    ORDER BY id DESC
    LIMIT 10
";

$notificationStmt = $conn->prepare($notificationSql);

if ($notificationStmt) {

    $notificationStmt->bind_param("s", $reg_no);

    $notificationStmt->execute();

    $notificationResult = $notificationStmt->get_result();

    while ($notificationRow = $notificationResult->fetch_assoc()) {

        $studentNotifications[] = $notificationRow;

    }

    $notificationStmt->close();
}
/* 
==========================================
FETCH STUDENT LEAVE REQUESTS
==========================================
*/

$sql = "
    SELECT *
    FROM leave_requests
    WHERE reg_no = ?
    ORDER BY id DESC
";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $reg_no);

$stmt->execute();

$result = $stmt->get_result();

?>

   


    <!-- PAGE CONTENT -->

    <div class="container-fluid p-4">


        <!-- TITLE + ADD BUTTON -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h3 class="mb-1">

                    Apply Leave

                </h3>

                <p class="text-muted mb-0">

                    Manage your leave requests.

                </p>

            </div>


            <div class="d-flex gap-2">


                <!-- ADD LEAVE -->
                <button
                    type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#leaveModal"
                    id="addLeaveBtn"
                >

                <i class="fas fa-plus"></i>

                Add Leave

                </button>
            </div>

        </div>


        <!-- LEAVE TABLE -->

        <div class="card">

            <div class="card-body">

                <div class="table-responsive">

                    <table id="studentLeaveTable" class="table table-bordered table-hover align-middle">

                        <thead>

                            <tr>

                                <th>S.No</th>

                                <th>From Date</th>

                                <th>To Date</th>

                                <th>Type</th>

                                <th>Batch</th>

                               

                                <th>Semester</th>

                                <th>Academic Year</th>

                                <th>Reason</th>

                                <th>Proof</th>

                                <th>Status</th>
                                <th>Advisor Remarks</th>

                                <th>HOD Remarks</th>
                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php

                        $serial = 1;


                        if ($result->num_rows > 0) {

                            while ($row = $result->fetch_assoc()) {
/*
==========================================
STATUS LOGIC
==========================================
*/

/*
------------------------------------------
HOD APPROVED
------------------------------------------
*/

if (
    $row["hod_status"] == 3 &&
    (
        $row["advisor_status"] == 1 ||
        (int)$row["advisor_locked"] === 1
    )
) {

    $statusText = "Approved by HOD";
    $statusClass = "bg-success";

}


/*
------------------------------------------
HOD REJECTED
------------------------------------------
*/

elseif (
    $row["hod_status"] == 5 &&
    (
        $row["advisor_status"] == 1 ||
        (int)$row["advisor_locked"] === 1
    )
) {

    $statusText = "Rejected by HOD";
    $statusClass = "bg-danger";

}


/*
------------------------------------------
ADVISOR REJECTED
------------------------------------------
*/

elseif ($row["advisor_status"] == 4) {

    $statusText = "Rejected by Advisor";
    $statusClass = "bg-danger";

}


/*
------------------------------------------
ADVISOR LOCKED → HOD PENDING
------------------------------------------
*/

elseif (
    $row["advisor_status"] == 0 &&
    isset($row["advisor_locked"]) &&
    (int)$row["advisor_locked"] === 1 &&
    $row["hod_status"] == 0
) {

    $statusText = "Advisor Locked - Pending HOD";
    $statusClass = "bg-dark";

}


/*
------------------------------------------
WAITING FOR ADVISOR
------------------------------------------
*/

elseif (
    $row["advisor_status"] == 0 &&
    (int)$row["advisor_locked"] === 0
) {

    $statusText = "Pending - Waiting for Advisor";
    $statusClass = "bg-warning text-dark";

}


/*
------------------------------------------
ADVISOR APPROVED → HOD PENDING
------------------------------------------
*/

elseif (
    $row["advisor_status"] == 1 &&
    $row["forwarded_to_hod"] == 1 &&
    $row["hod_status"] == 0
) {

    $statusText = "Approved by Advisor - Pending HOD";
    $statusClass = "bg-info text-dark";

}


/*
------------------------------------------
ADVISOR APPROVED
------------------------------------------
*/

elseif ($row["advisor_status"] == 1) {

    $statusText = "Approved by Advisor";
    $statusClass = "bg-success";

}


/*
------------------------------------------
DEFAULT
------------------------------------------
*/

else {

    $statusText = "Pending";
    $statusClass = "bg-warning text-dark";

}
                        ?>

                            <tr id="leaveRow<?php echo $row["id"]; ?>">

                                <td>

                                    <?php echo $serial++; ?>

                                </td>


                                <td>

                                    <?php
                                    echo date(
                                        "d-m-Y",
                                        strtotime($row["from_date"])
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo date(
                                        "d-m-Y",
                                        strtotime($row["to_date"])
                                    );
                                    ?>

                                </td>
                                    <td>
    <span class="badge bg-info">
        <?= htmlspecialchars($row['request_type']) ?>
    </span>
</td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["batch"]
                                    );
                                    ?>

                                </td>


                                <!-- YEAR -->




<!-- SEMESTER -->

<td>
    <?php
    echo htmlspecialchars(
        $row["semester"]
    );
    ?>
</td>


<!-- ACADEMIC YEAR -->

<td>
    <?php
    echo htmlspecialchars(
        $row["academic_year"]
    );
    ?>
</td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["reason"]
                                    );
                                    ?>

                                </td>


                                <!-- PROOF -->

                                <td class="text-center">

                                    <?php

                                    if (!empty($row["proof"])) {

                                    ?>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-info viewProof"
                                            data-proof="<?php echo htmlspecialchars($row["proof"]); ?>"
                                        >

                                            <i class="fas fa-eye"></i>

                                        </button>

                                    <?php

                                    } else {

                                        echo "-";

                                    }

                                    ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="badge <?php echo $statusClass; ?>"
                                    >

                                        <?php echo $statusText; ?>

                                    </span>

                                </td>
                       <td>

                               <?php

                                  if (!empty($row["advisor_remarks"])) {

                                         echo htmlspecialchars($row["advisor_remarks"]);

                                       } else {

                                            echo "-";

                                                 }

                                                    ?>

       </td>
      <!-- HOD REMARKS -->

       <td>

      <?php

     if (!empty($row["hod_remarks"])) {

        echo htmlspecialchars(
            $row["hod_remarks"]
        );

     } else {

        echo "-";

     }

     ?>

        </td>
                                <!-- ACTION -->

                                <td>

                                    <?php

                                    /*
                                    Edit and Delete
                                    Only while Advisor Pending
                                    */

                                    if (
    $row["advisor_status"] == 0 &&
    (int)$row["advisor_locked"] === 0 &&
    (int)$row["hod_status"] === 0
) {

                                    ?>

                                        <!-- EDIT -->

<button
    type="button"
    class="btn btn-warning editLeave"

    data-id="<?= $row['id'] ?>"
    data-from="<?= htmlspecialchars($row['from_date']) ?>"
    data-to="<?= htmlspecialchars($row['to_date']) ?>"
    data-type="<?= htmlspecialchars($row['request_type']) ?>"
    data-batch="<?= htmlspecialchars($row['batch']) ?>"
    data-semester="<?= htmlspecialchars($row['semester']) ?>"
    data-reason="<?= htmlspecialchars($row['reason']) ?>"
    data-duration="<?= htmlspecialchars($row['duration_type']) ?>"
    data-period="<?= htmlspecialchars($row['selected_period']) ?>"
    data-proof="<?= htmlspecialchars($row['proof'] ?? '') ?>"
>
    <i class="fas fa-edit"></i>
</button>
                                        <!-- DELETE -->

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger deleteLeave"

                                            data-id="<?php echo $row["id"]; ?>"
                                        >

                                            <i class="fas fa-trash"></i>

                                        </button>

                                    <?php

                                    } else {

                                        echo "-";

                                    }

                                    ?>

                                </td>

                            </tr>


                        <?php

                            }

                        } else {

                        ?>

                            <tr>

                                <td
                                    colspan="13"
                                    class="text-center text-muted"
                                >

                                    No leave requests found.

                                </td>

                            </tr>

                        <?php

                        }

                        ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>


    </div>

<!-- =====================================
     ADD / EDIT LEAVE MODAL
===================================== -->

<div
    class="modal fade"
    id="leaveModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">


            <form
                id="leaveForm"
                enctype="multipart/form-data"
            >


                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="leaveModalTitle"
                    >

                        Add Leave

                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">


                    <!-- EDIT ID -->

                    <input
                        type="hidden"
                        name="leave_id"
                        id="leave_id"
                    >


                    <div class="row">


                        <!-- FROM DATE -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                From Date

                            </label>


                            <input
                                type="date"
                                class="form-control"
                                name="from_date"
                                id="from_date"
                                required
                            >

                        </div>


                        <!-- TO DATE -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                To Date

                            </label>


                            <input
                                type="date"
                                class="form-control"
                                name="to_date"
                                id="to_date"
                                required
                            >

                        </div>
                        <!-- REQUEST TYPE -->

<div class="col-md-6 mb-3">

    <label class="form-label">
        Type
    </label>

<select
    name="request_type"
    id="request_type"
    class="form-select"
    required
>
        <option value="">
            -- Select Type --
        </option>

        <option value="OD">
            OD
        </option>

        <option value="LEAVE">
            LEAVE
        </option>

    </select>

</div>
<!-- DURATION TYPE -->

<div class="col-md-6 mb-3">

    <label for="duration_type" class="form-label">
        Select Duration
    </label>

    <select
        name="duration_type"
        id="duration_type"
        class="form-select"
        required
    >

        <option value="">
            -- Select Duration --
        </option>

        <option value="Single Day">
            Single Day
        </option>

        <option value="Half Day">
            Half Day
        </option>

        <option value="Specific Hours">
            Specific Hours
        </option>

    </select>

</div>


<!-- HALF DAY / SPECIFIC HOURS SELECTION -->

<div
    class="col-md-6 mb-3"
    id="period_container"
    style="display: none;"
>

    <label
        for="selected_period"
        class="form-label"
        id="period_label"
    >
        Select Period
    </label>

    <select
        name="selected_period"
        id="selected_period"
        class="form-select"
    >

        <option value="">
            -- Select Period --
        </option>

    </select>

</div>


                        <!-- BATCH -->

                        
<div class="col-md-6 mb-3">

    <label class="form-label">Batch</label>

    <select id="batch" name="batch" class="form-control" required>

    <option value="">-- Select Batch --</option>

    <option value="2022-2026">2022-2026</option>
    <option value="2023-2027">2023-2027</option>
    <option value="2024-2028">2024-2028</option>
    <option value="2025-2029">2025-2029</option>
    <option value="2026-2030">2026-2030</option>

</select>

</div>
                        <!-- SEMESTER -->

                        <div class="col-md-6 mb-3">

    <label class="form-label">
        Semester
    </label>

    <select
        class="form-select"
        name="semester"
        id="semester"
        required
    >

        <option value="">
            -- Select Semester --
        </option>

        <option value="1">
            Semester 1
        </option>

        <option value="2">
            Semester 2
        </option>

        <option value="3">
            Semester 3
        </option>

        <option value="4">
            Semester 4
        </option>

        <option value="5">
            Semester 5
        </option>

        <option value="6">
            Semester 6
        </option>

        <option value="7">
            Semester 7
        </option>

        <option value="8">
            Semester 8
        </option>

    </select>

</div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Year</label>
                            <select class="form-select" name="academic_year" id="academic_year" required>
                                <option value="">Choose Academic Year</option>
                                <option value="2025-2026">2025-2026</option>
                                <option value="2026-2027">2026-2027</option>
                            </select>
                            <small class="text-muted">Choose the academic year manually.</small>
                        </div>

                        <!-- PROOF -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Proof / Certificate <span id="proofRequiredMark" class="text-danger">*</span>

                            </label>


                            <input
                                type="file"
                                class="form-control"
                                name="proof"
                                id="proof"
                                accept=".jpg,.jpeg,.png,.pdf"
                            >
                            <div id="existingProof" class="mt-2"></div>

<input
    type="hidden"
    name="existing_proof"
    id="existing_proof"
>

                            <small class="text-muted">

                                JPG, PNG or PDF

                            </small>

                        </div>


                        <!-- REASON -->

                        <div class="col-md-12 mb-3">

                            <label class="form-label">

                                Reason

                            </label>


                            <textarea
                                class="form-control"
                                name="reason"
                                id="reason"
                                rows="4"
                                required
                            ></textarea>

                        </div>


                    </div>


                </div>


                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >

                        Close

                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="saveLeaveBtn"
                    >

                        Save Leave

                    </button>


                </div>


            </form>


        </div>

    </div>

</div>
<!-- =====================================
     PROOF PREVIEW MODAL
===================================== -->

<div
    class="modal fade"
    id="proofModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">

                    Proof Preview

                </h5>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body text-center">

                <div id="proofPreview"></div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >

                    Close

                </button>

            </div>


        </div>

    </div>

</div>



<script>

/*
==========================================
ADD LEAVE BUTTON
==========================================
*/

$("#addLeaveBtn").on("click", function () {

    $("#leaveForm")[0].reset();

    $("#leave_id").val("");

    $("#existing_proof").val("");

    $("#existingProof").html("");

    $("#leaveModalTitle").text("Add Leave");

    $("#saveLeaveBtn").text("Save Leave");

    $("#period_container").hide();
    $("#selected_period")
    .empty()
    .append(
        '<option value="">-- Select Option --</option>'
    )
    .prop("required", false);
loadDurationOptions($("#duration_type").val());
});



/*
==========================================
EDIT LEAVE
==========================================
*/

$(document).on("click", ".editLeave", function () {

    const id = $(this).data("id");

    const fromDate = $(this).data("from");

    const toDate = $(this).data("to");

    const batch = $(this).data("batch");

    const semester = $(this).data("semester");

    const reason = $(this).data("reason");

    const requestType = $(this).data("type");

    const durationType = $(this).data("duration");

    const selectedPeriod = $(this).data("period");

    const proof = $(this).data("proof") || "";


    console.log("EDIT DATA:", {

        id: id,

        fromDate: fromDate,

        toDate: toDate,

        batch: batch,

        semester: semester,

        reason: reason,

        requestType: requestType,

        durationType: durationType,

        selectedPeriod: selectedPeriod,

        proof: proof

    });


    // Set leave ID

    $("#leave_id").val(id);


    // Set dates

    $("#from_date").val(fromDate);

    $("#to_date").val(toDate);


    // Set request type

    $("#request_type").val(requestType);


    // Set batch

    $("#batch").val(batch);


    // Set semester

    $("#semester").val(semester);


    // Set reason

    $("#reason").val(reason);


    // Set duration
$("#duration_type").val(durationType);

loadDurationOptions(
    durationType,
    selectedPeriod
);


    // Set existing proof

    $("#existing_proof").val(proof);


    // Display existing proof

    if (proof !== "") {

        const proofPath = "uploads/proofs/" + proof;


        $("#existingProof").html(

            '<a href="' + proofPath + '" ' +
            'target="_blank" ' +
            'class="btn btn-sm btn-info">' +

            '<i class="fas fa-eye"></i> ' +

            'View Existing Proof' +

            '</a>'

        );

    } else {

        $("#existingProof").html(

            '<span class="text-muted">' +

            'No proof uploaded' +

            '</span>'

        );

    }


    // Change modal title

    $("#leaveModalTitle").text("Edit Leave");


    // Change save button text

    $("#saveLeaveBtn").text("Save Changes");


    // Show modal

    const leaveModalElement =

        document.getElementById("leaveModal");


    const leaveModal =

        bootstrap.Modal.getOrCreateInstance(

            leaveModalElement

        );


    leaveModal.show();

});


/*
==========================================
SAVE / UPDATE LEAVE
==========================================
*/

$("#leaveForm").on("submit", function (e) {

    e.preventDefault();
    const selectedType = $("#request_type")
    .val()
    .trim()
    .toUpperCase();

const proofFile = $("#proof")[0].files.length;
const existingProof = $("#existing_proof").val().trim();

if (selectedType === "OD" && proofFile === 0 && existingProof === "") {

    e.preventDefault();

    Swal.fire({
        icon: "warning",
        title: "Proof Required",
        text: "Please upload proof for OD."
    });

    $("#proof").focus();

    return;
}

    const leaveId = $("#leave_id").val();


    let url;


    if (leaveId === "") {

        url = "ajax/leave_save.php";

    } else {

        url = "ajax/leave_update.php";

    }


    const formData = new FormData(this);


    $("#saveLeaveBtn")

        .prop("disabled", true)

        .text("Saving...");


    $.ajax({

        url: url,

        type: "POST",

        data: formData,

        processData: false,

        contentType: false,

        dataType: "json",


        success: function (response) {


            if (response.status === true) {


                Swal.fire({

                    icon: "success",

                    title: "Success",

                    text: response.message,

                    timer: 1500,

                    showConfirmButton: false

                });


                setTimeout(function () {

                    location.reload();

                }, 1500);


            } else {


                Swal.fire({

                    icon: "error",

                    title: "Error",

                    text: response.message

                });

            }

        },


        error: function (xhr, status, error) {


            console.log("STATUS:", status);

            console.log("ERROR:", error);

            console.log("RESPONSE:", xhr.responseText);


            Swal.fire({

                icon: "error",

                title: "Server Error",

                html:

                    "<pre style='text-align:left;white-space:pre-wrap;'>" +

                    "HTTP Status: " + xhr.status +

                    "\n\n" +

                    "Error: " + error +

                    "\n\n" +

                    "Response:\n" +

                    (xhr.responseText || "No response from PHP") +

                    "</pre>"

            });

        },


        complete: function () {


            $("#saveLeaveBtn")

                .prop("disabled", false)

                .text(

                    leaveId === ""

                        ? "Save Leave"

                        : "Save Changes"

                );

        }

    });

});


/*
==========================================
DELETE LEAVE
==========================================
*/

$(document).on(

    "click",

    ".deleteLeave",

    function () {


        const leaveId = $(this).data("id");


        Swal.fire({

            title: "Are you sure?",

            text: "This leave request will be deleted permanently.",

            icon: "warning",

            showCancelButton: true,

            confirmButtonText: "Yes, Delete",

            cancelButtonText: "Cancel"

        }).then(function (result) {


            if (result.isConfirmed) {


                $.ajax({

                    url: "ajax/leave_delete.php",

                    type: "POST",

                    data: {

                        leave_id: leaveId

                    },

                    dataType: "json",


                    success: function (response) {


                        if (response.status === true) {


                            Swal.fire({

                                icon: "success",

                                title: "Deleted",

                                text: response.message,

                                timer: 1500,

                                showConfirmButton: false

                            });


                            $("#leaveRow" + leaveId)

                                .fadeOut(

                                    500,

                                    function () {

                                        $(this).remove();

                                    }

                                );


                        } else {


                            Swal.fire({

                                icon: "error",

                                title: "Error",

                                text: response.message

                            });

                        }

                    },


                    error: function () {


                        Swal.fire({

                            icon: "error",

                            title: "Error",

                            text: "Unable to delete leave request."

                        });

                    }

                });

            }

        });

    }

);


/*
==========================================
PROOF PREVIEW
==========================================
*/

$(document).on(

    "click",

    ".viewProof",

    function () {


        const proof = $(this).data("proof");


        if (!proof) {

            return;

        }


        const extension = proof

            .split(".")

            .pop()

            .toLowerCase();


        const preview = $("#proofPreview");


        preview.html("");


        const filePath = "uploads/proofs/" + proof;


        // PDF preview

        if (extension === "pdf") {


            preview.html(

                '<iframe ' +

                'src="' + filePath + '" ' +

                'width="100%" ' +

                'height="500px">' +

                '</iframe>'

            );

        }


        // Image preview

        else {


            preview.html(

                '<img ' +

                'src="' + filePath + '" ' +

                'class="img-fluid" ' +

                'style="max-height:500px;">'

            );

        }


        const proofModalElement =

            document.getElementById("proofModal");


        const proofModal =

            bootstrap.Modal.getOrCreateInstance(

                proofModalElement

            );


        proofModal.show();

    }

);


/*
==========================================
NOTIFICATIONS
==========================================
*/

<?php if (!empty($studentNotifications)) { ?>

    const studentNotifications =

        <?php echo json_encode($studentNotifications); ?>;


    studentNotifications.forEach(function (notification) {


        const notificationType =

            notification.notification_type || "";


        const message =

            notification.title +

            ": " +

            notification.message;


        if (

            notificationType.indexOf("rejected") !== -1

        ) {

            alertify.error(message);

        }

        else if (

            notificationType.indexOf("approved") !== -1

        ) {

            alertify.success(message);

        }

        else {

            alertify.message(message);

        }


        // Mark notification as read

        $.ajax({

            url: "ajax/mark_notifications_read.php",

            type: "POST",

            data: {

                notification_id: notification.id

            },

            dataType: "json"

        });

    });

<?php } ?>



/*
==========================================
DURATION TYPE CHANGE
==========================================
*/

const durationTypeElement =
    document.getElementById("duration_type");

const periodContainer =
    document.getElementById("period_container");

const periodLabel =
    document.getElementById("period_label");

const selectedPeriod =
    document.getElementById("selected_period");


/*
==========================================
DURATION TYPE CHANGE
==========================================
*/

function loadDurationOptions(durationType, savedValue = "") {

    const $container = $("#period_container");
    const $label = $("#period_label");
    const $select = $("#selected_period");

    if ($container.length === 0 || $select.length === 0) {
        return;
    }

    // Clear previous options
    $select.empty();

    $select.append(
        '<option value="">-- Select Option --</option>'
    );

    // SINGLE DAY
    if (durationType === "Single Day") {

        $container.hide();

        $select.prop("required", false);

        $select.val("");

        return;
    }

    // HALF DAY
    if (durationType === "Half Day") {

        $container.show();

        $label.text("Select Half Day");

        $select.prop("required", true);

        $select.append(
            '<option value="Morning">Morning</option>'
        );

        $select.append(
            '<option value="Evening">Evening</option>'
        );
    }

    // SPECIFIC HOURS
    else if (durationType === "Specific Hours") {

        $container.show();

        $label.text("Select Hour");

        $select.prop("required", true);

        for (let hour = 1; hour <= 7; hour++) {

            $select.append(
                '<option value="Hour ' +
                hour +
                '">Hour ' +
                hour +
                '</option>'
            );
        }
    }

    // Restore saved value while editing
    if (savedValue !== "") {

        $select.val(savedValue);

    }
}


/*
==========================================
DURATION DROPDOWN CHANGE
==========================================
*/

$(document).on(
    "change",
    "#duration_type",
    function () {

        const durationType = $(this).val();

        console.log(
            "Duration selected:",
            durationType
        );

        loadDurationOptions(durationType);
    }
);


/*
==========================================
INITIALIZE DURATION
==========================================
*/

$(document).ready(function () {

    const durationType = $("#duration_type").val();

    loadDurationOptions(durationType);

});


/*
==========================================
WHEN ADD LEAVE MODAL OPENS
==========================================
*/

$("#addLeaveBtn").on("click", function () {

    setTimeout(function () {

        loadDurationOptions(
            $("#duration_type").val()
        );

    }, 100);

});

/*
==========================================
WHEN DURATION CHANGES
==========================================
*/

if (durationTypeElement) {

    durationTypeElement.addEventListener(
        "change",
        function () {
            loadDurationOptions(this.value);
        }
    );

    // Load duration state when page/modal opens
    loadDurationOptions(durationTypeElement.value);
}
const requestType = document.getElementById("request_type");
const proofInput = document.getElementById("proof");
const proofRequiredMark = document.getElementById("proofRequiredMark");

function updateProofRequirement() {

    if (!requestType || !proofInput || !proofRequiredMark) {
        return;
    }

    const selectedType = requestType.value
        .trim()
        .toUpperCase();

    if (selectedType === "OD") {

        proofInput.required = true;

        proofRequiredMark.style.display = "inline";

    } else {

        proofInput.required = false;

        proofRequiredMark.style.display = "none";

    }
}



requestType.addEventListener("change", updateProofRequirement);

updateProofRequirement();

</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const durationType = document.getElementById("duration_type");
    const periodContainer = document.getElementById("period_container");
    const periodLabel = document.getElementById("period_label");
    const selectedPeriod = document.getElementById("selected_period");

    if (!durationType || !periodContainer || !periodLabel || !selectedPeriod) {
        return;
    }

    function updateDurationOptions(savedValue = "") {

        const type = durationType.value;

        selectedPeriod.innerHTML =
            '<option value="">-- Select Option --</option>';

        periodContainer.style.display = "none";
        selectedPeriod.required = false;

        if (type === "Half Day") {

            periodContainer.style.display = "block";
            periodLabel.textContent = "Select Half Day";
            selectedPeriod.required = true;

            selectedPeriod.innerHTML +=
                '<option value="Morning">Morning</option>';

            selectedPeriod.innerHTML +=
                '<option value="Evening">Evening</option>';

        }

        else if (type === "Specific Hours") {

            periodContainer.style.display = "block";
            periodLabel.textContent = "Select Hour";
            selectedPeriod.required = true;

            for (let hour = 1; hour <= 7; hour++) {

                selectedPeriod.innerHTML +=
                    '<option value="Hour ' + hour + '">Hour ' + hour + '</option>';

            }
        }

        if (savedValue !== "") {
            selectedPeriod.value = savedValue;
        }
    }

    durationType.addEventListener("change", function () {
        updateDurationOptions();

    });
updateDurationOptions();

});
</script>