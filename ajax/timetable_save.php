<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors','0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || strtolower(trim($_SESSION['role'] ?? '')) !== 'staff') {
    http_response_code(403);
    echo json_encode(['status'=>false,'message'=>'Unauthorized access.']);
    exit;
}
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/academic_helper.php';

$staff_id = trim($_SESSION['staff_id'] ?? '');
$department = trim($_SESSION['department'] ?? '');
$batch = trim($_POST['batch'] ?? '');
$year_name = trim($_POST['year_name'] ?? '');
$section = trim($_POST['section'] ?? ''); // internal canonical value; not shown in UI
$semester = (int)($_POST['semester'] ?? 0);
$academic_year = trim($_POST['academic_year'] ?? '');
$action = strtolower(trim($_POST['action'] ?? 'save'));
$posted_slots = $_POST['slots'] ?? [];

if ($staff_id==='' || $department==='' || $year_name==='' || $batch==='' || $section==='' || $semester !== 5 || $academic_year==='' || !is_array($posted_slots)) {
    echo json_encode(['status'=>false,'message'=>'Invalid timetable details.']); exit;
}
$expectedAcademicYear = getAcademicYear($batch, $semester);
if ($expectedAcademicYear !== '' && $academic_year !== $expectedAcademicYear) {
    echo json_encode(['status'=>false,'message'=>'The academic year does not match the selected class and semester.']); exit;
}
if (!in_array($action,['save','submit','edit'],true)) {
    echo json_encode(['status'=>false,'message'=>'Invalid timetable action.']); exit;
}

/* The advisor is authorised only for his/her own class. */
$class_stmt=$conn->prepare("SELECT 1 FROM year_advisor WHERE advisor_staff_id=? AND department=? AND year_name=? AND batch=? AND section=? LIMIT 1");
$class_stmt->bind_param('sssss',$staff_id,$department,$year_name,$batch,$section);
$class_stmt->execute();
$class_stmt->store_result();
$allowed=$class_stmt->num_rows>0;
$class_stmt->close();
if(!$allowed){echo json_encode(['status'=>false,'message'=>'You are not assigned as advisor for this class.']);exit;}

$set_stmt=$conn->prepare("SELECT id,status FROM timetable_sets WHERE department=? AND batch=? AND section=? AND semester=? AND academic_year=? LIMIT 1");
$set_stmt->bind_param('sssis',$department,$batch,$section,$semester,$academic_year);
$set_stmt->execute();
$set=$set_stmt->get_result()->fetch_assoc();
$set_stmt->close();

/* Approved timetables can be explicitly moved back to Draft by Edit. */
if($action==='edit'){
    if(!$set || $set['status']!=='Approved'){
        echo json_encode(['status'=>false,'message'=>'Only an approved timetable can be edited.']); exit;
    }
    $upd=$conn->prepare("UPDATE timetable_sets SET status='Draft', hod_remarks=NULL, reviewed_at=NULL, reviewed_by=NULL, submitted_at=NULL WHERE id=? AND advisor_staff_id=? AND status='Approved'");
    $id=(int)$set['id'];
    $upd->bind_param('is',$id,$staff_id);
    $ok=$upd->execute(); $upd->close();
    echo json_encode(['status'=>$ok,'message'=>$ok?'Timetable moved back to Draft. You can edit and submit it to the HOD again.':'Unable to edit the approved timetable.']);
    exit;
}

/* A pending/approved set may not be overwritten accidentally. Draft/Rejected are editable. */
if($set && in_array($set['status'],['Pending HOD Approval','Submitted'],true)){
    echo json_encode(['status'=>false,'message'=>'This timetable is already waiting for HOD review.']); exit;
}
if($set && $set['status']==='Approved'){
    echo json_encode(['status'=>false,'message'=>'Click Edit Timetable before changing an approved timetable.']); exit;
}

$valid_days=['Monday','Tuesday','Wednesday','Thursday','Friday'];
$valid_slots=[];
foreach($valid_days as $day){
    for($hour=1;$hour<=7;$hour++){
        $course_id=(int)($posted_slots[$day][$hour] ?? 0);
        if($course_id<=0) continue;

        /* Theory/common assignment */
        $course_stmt=$conn->prepare("SELECT a.faculty_id,'Common' AS class_type
            FROM course_faculty_assignments a
            INNER JOIN course_master c ON c.id=a.course_id
            WHERE a.course_id=? AND a.year_name=? AND a.batch=? AND a.department=? AND a.section=?
              AND a.status IN ('Active','Assigned') AND c.status='Active' LIMIT 1");
        $course_stmt->bind_param('issss',$course_id,$year_name,$batch,$department,$section);
        $course_stmt->execute();
        $course=$course_stmt->get_result()->fetch_assoc();
        $course_stmt->close();

        /* Elective/HOD assignment */
        if(!$course){
            $course_stmt=$conn->prepare("SELECT faculty_id,'Elective' AS class_type
                FROM elective_faculty_assignments
                WHERE course_id=? AND batch=? AND section=? AND semester=? AND academic_year=? AND status='Active'
                ORDER BY updated_at DESC,id DESC LIMIT 1");
            $course_stmt->bind_param('issis',$course_id,$batch,$section,$semester,$academic_year);
            $course_stmt->execute();
            $course=$course_stmt->get_result()->fetch_assoc();
            $course_stmt->close();
        }
        if(!$course){echo json_encode(['status'=>false,'message'=>'One or more selected subjects are not assigned to this class.']);exit;}
        $valid_slots[]=[$day,$hour,$course_id,$course['faculty_id'],$course['class_type']];
    }
}

$conn->begin_transaction();
try{
    if($set){
        $timetable_id=(int)$set['id'];
        $status='Draft';
        if($action==='submit') $status='Pending HOD Approval';
        $update=$conn->prepare("UPDATE timetable_sets SET status=?, advisor_staff_id=?, submitted_at=CASE WHEN ?='Pending HOD Approval' THEN CURRENT_TIMESTAMP ELSE NULL END, hod_remarks=NULL, reviewed_at=NULL, reviewed_by=NULL WHERE id=?");
        $update->bind_param('sssi',$status,$staff_id,$status,$timetable_id);
        $update->execute(); $update->close();
        $delete=$conn->prepare('DELETE FROM timetable_slots WHERE timetable_id=?');
        $delete->bind_param('i',$timetable_id); $delete->execute(); $delete->close();
    }else{
        $status=$action==='submit'?'Pending HOD Approval':'Draft';
        $insert=$conn->prepare("INSERT INTO timetable_sets(advisor_staff_id,department,batch,section,semester,academic_year,status,submitted_at) VALUES(?,?,?,?,?,?,?,CASE WHEN ?='Pending HOD Approval' THEN CURRENT_TIMESTAMP ELSE NULL END)");
        $insert->bind_param('ssssisss',$staff_id,$department,$batch,$section,$semester,$academic_year,$status,$status);
        $insert->execute(); $timetable_id=$insert->insert_id; $insert->close();
    }

    $slot_insert=$conn->prepare("INSERT INTO timetable_slots(timetable_id,day_name,hour_no,course_id,faculty_id,class_type) VALUES(?,?,?,?,?,?)");
    foreach($valid_slots as $slot){$slot_insert->bind_param('isiiss',$timetable_id,$slot[0],$slot[1],$slot[2],$slot[3],$slot[4]);$slot_insert->execute();}
    $slot_insert->close();
    $conn->commit();
    echo json_encode(['status'=>true,'message'=>$action==='submit'?'Timetable sent to HOD for approval.':'Timetable draft saved.']);
}catch(Throwable $e){$conn->rollback();echo json_encode(['status'=>false,'message'=>'Unable to save timetable.']);}
