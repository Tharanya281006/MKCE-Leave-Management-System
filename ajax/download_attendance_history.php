<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Kolkata');
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!in_array($role, ['staff','hod'], true)) { http_response_code(403); exit('Access denied.'); }
require_once __DIR__ . '/../db/connection.php';

$staffId = trim($_SESSION['staff_id'] ?? '');
$department = trim($_SESSION['department'] ?? '');
$format = strtolower(trim($_GET['format'] ?? 'xls'));
if (!in_array($format, ['xls','pdf'], true)) $format = 'xls';
$rows=[];

if ($role === 'staff') {
    $sql="SELECT a.attendance_date,a.period,a.year_name,a.batch,a.section,a.subject
          FROM attendance a
          WHERE a.marked_by=?
          GROUP BY a.attendance_date,a.period,a.year_name,a.batch,a.section,a.subject
          ORDER BY a.attendance_date DESC, CAST(REPLACE(a.period,'Hour ','') AS UNSIGNED) DESC";
    $stmt=$conn->prepare($sql);
    if($stmt){$stmt->bind_param('s',$staffId);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$rows[]=$row;$stmt->close();}
} else {
    $sql="SELECT a.attendance_date,a.period,a.year_name,a.batch,a.section,a.subject
          FROM attendance a
          LEFT JOIN timetable_slots ts ON ts.id=a.timetable_slot_id
          LEFT JOIN timetable_sets t ON t.id=ts.timetable_id
          WHERE COALESCE(t.department,'')=? OR (COALESCE(t.department,'')='' AND EXISTS(
              SELECT 1 FROM year_advisor ya WHERE ya.department=? AND ya.year_name=a.year_name AND ya.batch=a.batch AND ya.section=a.section
          ))
          GROUP BY a.attendance_date,a.period,a.year_name,a.batch,a.section,a.subject
          ORDER BY a.attendance_date DESC, CAST(REPLACE(a.period,'Hour ','') AS UNSIGNED) DESC";
    $stmt=$conn->prepare($sql);
    if($stmt){$stmt->bind_param('ss',$department,$department);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$rows[]=$row;$stmt->close();}
}

$headers=['S.No','Date','Hour','Class','Batch','Section','Subject','Attendance'];
if($format==='xls'){
    $filename=($role==='hod'?'HOD':'Staff').'_Attendance_History_'.date('Ymd_His').'.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Pragma: no-cache'); header('Expires: 0'); echo "\xEF\xBB\xBF";
    echo '<table border="1"><thead><tr>'; foreach($headers as $h) echo '<th>'.htmlspecialchars($h,ENT_QUOTES,'UTF-8').'</th>'; echo '</tr></thead><tbody>';
    foreach($rows as $i=>$row){$vals=[$i+1,$row['attendance_date'],$row['period'],$row['year_name'],$row['batch'],$row['section'],$row['subject'],'Marked'];echo '<tr>';foreach($vals as $v)echo '<td>'.htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8').'</td>';echo '</tr>';}
    echo '</tbody></table>'; exit;
}

function pdfEscape($text){$text=preg_replace('/[^\x20-\x7E]/',' ',(string)$text);return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$text);}
function makeAttendancePdf(array $rows,array $headers,string $title,string $subtitle){
    $W=842;$H=595;$left=28;$top=555;$bottom=30;$rowH=18;$headerH=24;
    $widths=[30,75,55,80,85,55,190,80];$sum=array_sum($widths);$scale=($W-2*$left)/$sum;foreach($widths as &$w)$w=round($w*$scale,2);unset($w);
    $pages=[];$content='';$y=$top;$font1=0;$font2=0;
    $addText=function(&$c,$x,$y,$text,$size=8,$bold=false){$f=$bold?'/F2':'/F1';$c.="BT {$f} {$size} Tf 1 0 0 1 {$x} {$y} Tm (".pdfEscape($text).") Tj ET\n";};
    $cell=function(&$c,$x,$y,$w,$h,$text,$bold=false)use($addText){$c.=sprintf("0.82 0.82 0.82 RG 0.4 w %.2f %.2f %.2f %.2f re S\n",$x,$y,$w,$h);$display=(string)$text;$max=max(3,(int)floor($w/4.6));if(strlen($display)>$max)$display=substr($display,0,$max-1).'...';$c.='0 0 0 rg\n';$addText($c,$x+4,$y+6,$display,7,$bold);};
    $start=function()use(&$content,&$y,$addText,$cell,$headers,$widths,$left,$top,$headerH,$title,$subtitle){$content="q\n1 1 1 rg\n0 0 842 595 re f\nQ\n";$addText($content,$left,$top,$title,16,true);$addText($content,$left,$top-20,$subtitle,8,false);$y=$top-42;$x=$left;foreach($headers as $i=>$h){$content.=sprintf("0.90 0.94 0.98 rg %.2f %.2f %.2f %.2f re f\n",$x,$y-$headerH+2,$widths[$i],$headerH);$cell($content,$x,$y-$headerH+2,$widths[$i],$headerH,$h,true);$x+=$widths[$i];}$y-=$headerH;};
    $start();
    foreach($rows as $i=>$row){if($y-$rowH<$bottom){$pages[]=$content;$start();}$vals=[$i+1,$row['attendance_date'],$row['period'],$row['year_name'],$row['batch'],$row['section'],$row['subject'],'Marked'];$x=$left;foreach($vals as $j=>$v){$cell($content,$x,$y-$rowH+2,$widths[$j],$rowH,$v,false);$x+=$widths[$j];}$y-=$rowH;}
    $pages[]=$content;
    $objects=[];$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$next=3;$pageObjs=[];$contentObjs=[];$kids=[];
    foreach($pages as $pg){$po=$next++;$co=$next++;$pageObjs[]=$po;$contentObjs[]=$co;$kids[]=$po.' 0 R';$objects[$co]="<< /Length ".strlen($pg)." >>\nstream\n$pg\nendstream";}
    $font1=$next++;$font2=$next++;$objects[$font1]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';$objects[$font2]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
    foreach($pageObjs as $i=>$po)$objects[$po]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 '.$font1.' 0 R /F2 '.$font2.' 0 R >> >> /Contents '.$contentObjs[$i].' 0 R >>';
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($pages).' >>';
    $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];$count=$next-1;for($i=1;$i<=$count;$i++){$offsets[$i]=strlen($pdf);$pdf.=$i." 0 obj\n".($objects[$i]??'')."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".($count+1)."\n0000000000 65535 f \n";for($i=1;$i<=$count;$i++)$pdf.=sprintf('%010d 00000 n \n',$offsets[$i]);$pdf.="trailer\n<< /Size ".($count+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";return $pdf;
}

$subtitle=($role==='hod'?'Department: '.$department:'Faculty: '.$staffId).' | Generated: '.date('Y-m-d H:i:s');
$pdf=makeAttendancePdf($rows,$headers,'Attendance History',$subtitle);
$filename=($role==='hod'?'HOD':'Staff').'_Attendance_History_'.date('Ymd_His').'.pdf';
header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.strlen($pdf));header('Cache-Control: private, max-age=0, must-revalidate');header('Pragma: public');echo $pdf;
