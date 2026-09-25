<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db/connection.php";
require_once __DIR__ . "/includes/academic_helper.php";

/* =========================================
   STAFF LOGIN CHECK
========================================= */

if (
    !isset($_SESSION["role"]) ||
    strtolower($_SESSION["role"]) !== "staff"
) {

    header("Location: index.php");
    exit;
}

/* Only an assigned advisor can view leave requests. */
$advisor_id = trim($_SESSION['staff_id'] ?? '');
$advisor_department = trim($_SESSION['department'] ?? '');
$advisor_access = false;
if ($advisor_id !== '' && $advisor_department !== '') {
    $access_stmt = $conn->prepare("SELECT 1 FROM year_advisor WHERE advisor_staff_id = ? AND department = ? LIMIT 1");
    if ($access_stmt) {
        $access_stmt->bind_param('ss', $advisor_id, $advisor_department);
        $access_stmt->execute();
        $access_stmt->store_result();
        $advisor_access = $access_stmt->num_rows > 0;
        $access_stmt->close();
    }
}
if (!$advisor_access) {
    http_response_code(403);
    echo '<div class="container-fluid p-4"><div class="alert alert-danger">Access denied. Only an authorized advisor can view leave requests.</div></div>';
    exit;
}


/* =========================================
   CHECK DEPARTMENT SESSION
========================================= */

if (!isset($_SESSION["department"])) {

    echo "Department session not found.";
    exit;
}


/* =========================================
   FETCH PENDING LEAVE REQUESTS
========================================= */

$department = $_SESSION["department"];


$sql = "SELECT lr.*
        FROM leave_requests lr
        WHERE UPPER(TRIM(lr.department)) = UPPER(TRIM(?))
          AND lr.batch = ?
          AND lr.advisor_status IN (0, 1, 4)
        ORDER BY lr.created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Failed to prepare leave request query: " . $conn->error);
}

$batch = '2024-2028';

$stmt->bind_param("ss", $department, $batch);
$stmt->execute();

$raw_result = $stmt->get_result();

$requests = [];

while ($request = $raw_result->fetch_assoc()) {

    /*
     * Route the request using:
     * Student Register Number
     *        ↓
     * Department
     *        ↓
     * IT Section A/B
     *        ↓
     * year_advisor
     *        ↓
     * Correct Staff Advisor
     */

    if (isAdvisorForStudent(
        $conn,
        $advisor_id,
        $request['reg_no'],
        $request['department'],
        $request['batch'],
        $request['section'],
        '3rd Year'
    )) {
        $requests[] = $request;
    }
}

$stmt->close();

?>


<div class="container-fluid p-4">


    <!-- PAGE TITLE -->

    <div class="mb-4">

        <h3 class="page-title">

            <i class="fas fa-user-tie"></i>

            Leave Requests

        </h3>


        <p class="text-muted">

            Review pending student leave requests.

        </p>

    </div>



    <!-- TABLE CARD -->

    <div class="card shadow-sm">

        <div class="card-body">


            <div class="d-flex justify-content-between align-items-center mb-4">

                <h5 class="mb-0">

                    <i class="fas fa-clock text-warning"></i>

                     Leave Requests

                </h5>


                <div class="d-flex gap-2">
                    <a href="ajax/download_leave_history.php" class="btn btn-success btn-sm"><i class="fas fa-file-excel me-1"></i>Download Excel</a>
                    <a href="ajax/download_leave_history.php?format=pdf" class="btn btn-danger btn-sm" target="_blank"><i class="fas fa-file-pdf me-1"></i>Download PDF</a>
                </div>

            </div>


            <div class="table-responsive">


                    <table id="staffLeaveTable" class="table table-bordered table-hover align-middle">


                    <thead class="table-dark">

                        <tr>

                            <th>S.No</th>

                            <th>Register No</th>

                            <th>From Date</th>

                            <th>To Date</th>
                            <th>Type</th>

                            <th>Batch</th>

                            <th>Semester</th>

                            <th>Academic Year</th>

                            <th>Reason</th>

                            <th>Proof</th>

                            <th>Advisor Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    $serial = 1;

                    if (
                        $requests
                    ) {

                        foreach ($requests as $row) {

                    ?>

                        <tr>


                            <!-- S.NO -->

                            <td>

                                <?php echo $serial++; ?>

                            </td>


                            <!-- REGISTER NUMBER -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row["reg_no"]
                                );

                                ?>

                            </td>


                            <!-- FROM DATE -->

                            <td>

                                <?php

                                echo date(
                                    "d-m-Y",
                                    strtotime(
                                        $row["from_date"]
                                    )
                                );

                                ?>

                            </td>


                            <!-- TO DATE -->

                            <td>

                                <?php

                                echo date(
                                    "d-m-Y",
                                    strtotime(
                                        $row["to_date"]
                                    )
                                );

                                ?>

                            </td>
                                    <td>
    <span class="badge bg-info">
        <?= htmlspecialchars($row["request_type"]) ?>
    </span>
</td>

                            <!-- BATCH -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row["batch"]
                                );

                                ?>

                            </td>


                            <!-- SEMESTER -->

                            <td>

                                Semester
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


                            <!-- REASON -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row["reason"]
                                );

                                ?>

                            </td>


                            <!-- PROOF -->

                            <td>

                                <?php

                                if (
                                    !empty($row["proof"])
                                ) {

                                ?>

                                    <button
                                        type="button"
                                        class="btn btn-info btn-sm viewProof"
                                        data-proof="<?php
                                            echo htmlspecialchars(
                                                $row["proof"]
                                            );
                                        ?>"
                                    >

                                        <i class="fas fa-eye"></i>

                                        View

                                    </button>

                                <?php

                                } else {

                                ?>

                                    -

                                <?php

                                }

                                ?>

                            </td>
                            <!-- ADVISOR STATUS -->
<td class="text-center">
    <?php if ((int)$row["advisor_status"] === 1 || (int)$row["advisor_locked"] === 1) { ?>
        <span class="badge bg-success">Approved</span>
    <?php } elseif ((int)$row["advisor_status"] === 4) { ?>
        <span class="badge bg-danger">Rejected</span>
    <?php } else { ?>
        <span class="badge bg-warning text-dark">Pending</span>
    <?php } ?>
</td>


                           
<!-- ACTION -->

<td class="text-center">

    <?php if (
        isset($row["advisor_locked"]) &&
        (int)$row["advisor_locked"] === 1
    ) { ?>

        <!-- LOCKED -->

        <span class="badge bg-dark">
            <i class="fas fa-lock"></i>
            Locked
        </span>

        <div class="small text-muted mt-1">
            Forwarded to HOD
        </div>

    <?php } elseif (
        (int)$row["advisor_status"] === 0
    ) { ?>

        <!-- PENDING -->

        <button
            type="button"
            class="btn btn-success btn-sm approveLeave"
            data-id="<?= $row["id"] ?>"
        >
            <i class="fas fa-check"></i>
            Approve
        </button>

        <button
            type="button"
            class="btn btn-danger btn-sm rejectLeave"
            data-id="<?= $row["id"] ?>"
        >
            <i class="fas fa-times"></i>
            Reject
        </button>

    <?php } elseif (
        (int)$row["advisor_status"] === 1
    ) { ?>

        <!-- ALREADY APPROVED -->

        <span class="badge bg-success">
            <i class="fas fa-check-circle"></i>
            Approved
        </span>

        <div class="small text-muted mt-1">
            Forwarded to HOD
        </div>

    <?php } elseif (
        (int)$row["advisor_status"] === 4
    ) { ?>

        <!-- ALREADY REJECTED -->

        <span class="badge bg-danger">
            <i class="fas fa-times-circle"></i>
            Rejected
        </span>

    <?php } ?>

</td>


                        </tr>


                    <?php

                        }

                    } else {

                    ?>

                        

                    <?php

                    }

                    ?>


                    </tbody>


                </table>


            </div>


        </div>

    </div>


</div>

<script>
$(function () {
    $("#staffLeaveTable").DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[2, "desc"]]
    });
});
</script>



<!-- =====================================
     PROOF MODAL
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


        </div>


    </div>


</div>



<script>


/* =====================================
   VIEW PROOF
===================================== */

$(document).on(
    "click",
    ".viewProof",
    function () {


        let proof = $(this).data("proof");


        let extension = proof
            .split(".")
            .pop()
            .toLowerCase();


        let filePath =
            "view_proof.php?file=" + encodeURIComponent(proof);


        let preview =
            $("#proofPreview");


        preview.html("");


        if (extension === "pdf") {


            preview.html(

                '<iframe ' +

                'src="' + filePath + '" ' +

                'width="100%" ' +

                'height="560px" style="border:0;">' +

                '</iframe>'

            );


        } else {


            preview.html(

                '<img ' +

                'src="' + filePath + '" ' +

                'class="img-fluid" ' +

                'style="max-height:500px;">'

            );


        }


        let modal = new bootstrap.Modal(

            document.getElementById(
                "proofModal"
            )

        );


        modal.show();


    }
);


/* =====================================
   APPROVE LEAVE
===================================== */

$(document).on("click", ".approveLeave", function () {

    const leaveId = $(this).data("id");

    if (!leaveId) {

        Swal.fire(
            "Error",
            "Leave ID not found.",
            "error"
        );

        return;
    }

    Swal.fire({

        title: "Approve Leave?",

        text: "This leave will be approved and forwarded to HOD.",

        icon: "question",

        showCancelButton: true,

        confirmButtonText: "Yes, Approve",

        cancelButtonText: "Cancel"

    }).then((result) => {

        if (!result.isConfirmed) {
            return;
        }

        $.ajax({

            url: "ajax/advisor_approve.php",

            type: "POST",

            dataType: "json",

            data: {

                leave_id: leaveId,

                remarks: ""

            },

            beforeSend: function () {

                Swal.fire({

                    title: "Processing...",

                    text: "Please wait.",

                    allowOutsideClick: false,

                    didOpen: () => {

                        Swal.showLoading();

                    }

                });

            },

            success: function (response) {

                console.log("APPROVE RESPONSE:", response);

                Swal.close();

                if (response.status === true) {

                    Swal.fire({

                        icon: "success",

                        title: "Approved!",

                        text: response.message,

                        confirmButtonText: "OK"

                    }).then(() => {

                        location.reload();

                    });

                } else {

                    Swal.fire({

                        icon: "error",

                        title: "Approval Failed",

                        text: response.message

                    });

                }

            },

            error: function (xhr, status, error) {

                Swal.close();

                console.log("AJAX ERROR:", xhr.responseText);

                Swal.fire({

                    icon: "error",

                    title: "Server Error",

                    html:
                        "<pre style='text-align:left;white-space:pre-wrap;'>" +
                        xhr.responseText +
                        "</pre>"

                });

            }

        });

    });

});

/* =====================================
   REJECT LEAVE
===================================== */

$(document).on(
    "click",
    ".rejectLeave",
    function () {


        let leaveId =
            $(this).data("id");


        Swal.fire({

            title:
                "Reject Leave?",

            text:
                "Remarks are required.",

            icon:
                "warning",

            input:
                "textarea",

            inputPlaceholder:
                "Enter rejection reason",

            showCancelButton:
                true,

            confirmButtonText:
                "Reject",

            cancelButtonText:
                "Cancel",


            preConfirm:
                function (remarks) {


                    if (
                        !remarks ||
                        !remarks.trim()
                    ) {


                        Swal.showValidationMessage(

                            "Remarks are required for rejection."

                        );


                        return false;


                    }


                    return remarks.trim();


                }


        }).then(function (result) {


            if (result.isConfirmed) {


                $.ajax({

                    url:
                        "ajax/advisor_reject.php",

                    type:
                        "POST",

                    data: {

                        leave_id:
                            leaveId,

                        remarks:
                            result.value

                    },

                    dataType:
                        "json",


                    success:
                        function (response) {


                            if (
                                response.status === true
                            ) {


                                Swal.fire({

                                    icon:
                                        "success",

                                    title:
                                        "Rejected",

                                    text:
                                        response.message,

                                    timer:
                                        1200,

                                    showConfirmButton:
                                        false

                                });


                                setTimeout(
                                    function () {

                                        location.reload();

                                    },
                                    1200
                                );


                            } else {


                                Swal.fire({

                                    icon:
                                        "error",

                                    title:
                                        "Error",

                                    text:
                                        response.message

                                });


                            }


                        }


                });


            }


        });


    }
);


</script>