<?php


require_once "db/connection.php";


/* =====================================
   HOD LOGIN CHECK
===================================== */

if (

    !isset($_SESSION["role"]) ||

    $_SESSION["role"] !== "hod"

) {

    header("Location: login.php");

    exit;

}


/* =====================================
   FETCH HOD LEAVE REQUESTS
===================================== */
$department = $_SESSION["department"];
$sql = "

    SELECT *

    FROM leave_requests

    WHERE
        department = ?
        AND forwarded_to_hod = 1
        AND (
            advisor_status = 1
            OR advisor_locked = 1
        )

    ORDER BY id DESC

";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $department
);

$stmt->execute();

$result = $stmt->get_result();

?>


<div class="container-fluid p-4">


    <!-- PAGE TITLE -->

    <div class="mb-4">


        <h3>

            <i class="fas fa-user-tie"></i>

            Leave Requests

        </h3>


        <p class="text-muted">

            Review leave requests approved by the Advisor.

        </p>


    </div>



    <!-- TABLE -->

    <div class="card shadow-sm">


        <div class="card-body">


            <div class="d-flex justify-content-end gap-2 mb-3">
                <a href="ajax/download_leave_history.php" class="btn btn-success"><i class="fas fa-file-excel me-1"></i>Download Excel</a>
                <a href="ajax/download_leave_history.php?format=pdf" class="btn btn-danger" target="_blank"><i class="fas fa-file-pdf me-1"></i>Download PDF</a>
            </div>

            <div class="table-responsive">


                <table id="hodLeaveTable"
                    class="
                        table
                        table-bordered
                        table-hover
                        align-middle
                    "
                >


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

                            <th>HOD Action</th>

                            <th>HOD Status</th>

                        </tr>


                    </thead>



                    <tbody>


                    <?php


                    $serial = 1;


                    if (

                        $result &&
                        $result->num_rows > 0

                    ) {


                        while (

                            $row =
                            $result->fetch_assoc()

                        ) {


                    ?>


                        <tr>


                            <!-- SERIAL -->

                            <td>

                                <?php
                                echo $serial++;
                                ?>

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
    <?php if ($row["request_type"] === "OD"): ?>
        <span class="badge bg-warning text-dark">OD</span>
    <?php else: ?>
        <span class="badge bg-primary">LEAVE</span>
    <?php endif; ?>
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

                                    !empty(

                                        $row["proof"]

                                    )

                                ) {

                                ?>


                                    <button

                                        type="button"

                                        class="
                                            btn
                                            btn-info
                                            btn-sm
                                            viewProof
                                        "

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

                            <!-- ADVISOR STATUS -->

<td>

<?php

if (
    isset($row["advisor_locked"]) &&
    (int)$row["advisor_locked"] === 1
) {

?>

    <span class="badge bg-dark">
        Locked
    </span>

    <br>

    <small class="text-muted">
        Forwarded to HOD
    </small>

<?php

}
elseif ($row["advisor_status"] == 1) {

?>

    <span class="badge bg-success">
        Approved
    </span>

<?php

}
else {

?>

    <span class="badge bg-warning text-dark">
        Pending
    </span>

<?php

}

?>

</td>

                            <!-- HOD ACTION -->

                            <td>


                            <?php


                            /*
                            HOD PENDING
                            */


                            if (

                                $row["hod_status"] == 0

                            ) {

                            ?>


                                <!-- APPROVE -->

                                <button

                                    class="
                                        btn
                                        btn-success
                                        btn-sm
                                        hodApprove
                                    "

                                    data-id="<?php

                                    echo $row["id"];

                                    ?>"

                                >

                                    Approve

                                </button>



                                <!-- REJECT -->

                                <button

                                    class="
                                        btn
                                        btn-danger
                                        btn-sm
                                        hodReject
                                    "

                                    data-id="<?php

                                    echo $row["id"];

                                    ?>"

                                >

                                    Reject

                                </button>


                            <?php

                            } else {

                                echo "-";

                            }

                            ?>


                            </td>


                            <!-- HOD STATUS -->

                            <td>


                            <?php


                            if (

                                $row["hod_status"] == 3

                            ) {

                            ?>


                                <span class="badge bg-success">

                                    Approved

                                </span>


                            <?php

                            }


                            elseif (

                                $row["hod_status"] == 5

                            ) {

                            ?>


                                <span class="badge bg-danger">

                                    Rejected

                                </span>


                            <?php

                            }


                            else {

                            ?>


                                <span class="badge bg-warning text-dark">

                                    Pending

                                </span>


                            <?php

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

                                colspan="12"

                                class="
                                    text-center
                                    text-muted
                                "

                            >

                                No leave requests available.

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


            <div
                class="
                    modal-body
                    text-center
                "
            >

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


        let proof =

            $(this).data("proof");


        let extension =

            proof
            .split(".")
            .pop()
            .toLowerCase();


        let filePath =

            "view_proof.php?file=" + encodeURIComponent(proof);


        let preview =

            $("#proofPreview");


        preview.html("");


        if (

            extension === "pdf"

        ) {


            preview.html(

                '<iframe src="' +

                filePath +

                '" width="100%" ' +

                'height="560px" style="border:0;"></iframe>'

            );


        }

        else {


            preview.html(

                '<img src="' +

                filePath +

                '" class="img-fluid" ' +

                'style="max-height:500px;">'

            );


        }


        let modal =

            new bootstrap.Modal(

                document.getElementById(

                    "proofModal"

                )

            );


        modal.show();


    }

);


/* =====================================
   HOD APPROVE
===================================== */

$(document).on(

    "click",

    ".hodApprove",

    function () {


        let leaveId =

            $(this).data("id");


        Swal.fire({

            title:

                "Approve Leave?",

            text:

                "Remarks are optional.",

            icon:

                "question",

            input:

                "textarea",

            inputPlaceholder:

                "Enter remarks (Optional)",

            showCancelButton:

                true,

            confirmButtonText:

                "Approve"

        })


        .then(function (result) {


            if (

                result.isConfirmed

            ) {


                $.ajax({

                    url:

                        "ajax/hod_approve.php",

                    type:

                        "POST",

                    data: {

                        leave_id:

                            leaveId,

                        remarks:

                            result.value || ""

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

                                        "Approved",

                                    text:

                                        response.message,

                                    timer:

                                        1500,

                                    showConfirmButton:

                                        false

                                });


                                setTimeout(

                                    function () {

                                        location.reload();

                                    },

                                    1500

                                );


                            } else {


                                Swal.fire(

                                    "Error",

                                    response.message,

                                    "error"

                                );


                            }


                        }


                });


            }


        });


    }

);


/* =====================================
   HOD REJECT
===================================== */

$(document).on(

    "click",

    ".hodReject",

    function () {


        let leaveId =

            $(this).data("id");


        Swal.fire({

            title:

                "Reject Leave?",

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


            preConfirm:

                function (remarks) {


                    if (

                        !remarks ||

                        !remarks.trim()

                    ) {


                        Swal.showValidationMessage(

                            "Remarks are required."

                        );


                        return false;


                    }


                    return remarks.trim();


                }


        })


        .then(function (result) {


            if (

                result.isConfirmed

            ) {


                $.ajax({

                    url:

                        "ajax/hod_reject.php",

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

                                        1500,

                                    showConfirmButton:

                                        false

                                });


                                setTimeout(

                                    function () {

                                        location.reload();

                                    },

                                    1500

                                );


                            } else {


                                Swal.fire(

                                    "Error",

                                    response.message,

                                    "error"

                                );


                            }


                        }


                });


            }


        });


    }

);


</script>

<script>
$(function () {
    $("#hodLeaveTable").DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[2, "desc"]]
    });
});
</script>