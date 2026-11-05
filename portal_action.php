<?php
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/security.php';
require_once __DIR__.'/includes/functions.php';
$user = requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
enforceCsrfOnPost();
$role = $user['role'];
$action = (string)($_POST['action'] ?? '');
$back = (string)($_POST['back'] ?? 'index.php');
$allowedBack = ['dashboard.php','scholars.php','programs.php','grades.php','documents.php','notifications.php','users.php'];
if (!in_array($back,$allowedBack,true)) $back='index.php';
$folder = $role === 'User' ? 'user' : ($role === 'Admin' ? 'admin' : 'super_admin');
$go = function(string $message) use ($folder,$back) { $_SESSION['portal_flash']=$message; redirect($folder.'/'.$back); };
try {
 if ($action==='mark_notification' && $role==='User') {
  $id=(int)($_POST['id']??0); $q=$pdo->prepare('UPDATE notifications SET read_at=NOW() WHERE id=? AND user_id=?'); $q->execute([$id,$user['id']]); $go('Notification marked as read.');
 }
 if ($action==='submit_document' && $role==='User') {
  $sch=$pdo->prepare('SELECT id,program_id FROM scholars WHERE user_id=?');$sch->execute([$user['id']]);$s=$sch->fetch();
  $req=(int)($_POST['requirement_id']??0);$file=$_FILES['document']??null;
  if(!$s || !$file || $file['error']!==UPLOAD_ERR_OK) $go('Choose a valid document to upload.');
  $q=$pdo->prepare('SELECT id FROM document_requirements WHERE id=? AND program_id=?');$q->execute([$req,$s['program_id']]);
  if(!$q->fetch()) $go('That requirement is not available for your scholarship.');
  if($file['size']>5*1024*1024) $go('Files must be 5 MB or smaller.');
  $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);
  $types=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
  if(!isset($types[$mime])) $go('Only PDF, JPG and PNG files are accepted.');
  $stored=bin2hex(random_bytes(20)).'.'.$types[$mime];$dir=__DIR__.'/uploads/documents';
  if(!is_dir($dir) && !mkdir($dir,0750,true)) $go('Upload folder is unavailable.');
  if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$stored)) $go('Could not save the uploaded file.');
  $q=$pdo->prepare("INSERT INTO scholar_documents (scholar_id,requirement_id,stored_name,original_name,status) VALUES (?,?,?,?,'Submitted') ON DUPLICATE KEY UPDATE stored_name=VALUES(stored_name),original_name=VALUES(original_name),status='Submitted',note=NULL,reviewed_by=NULL,reviewed_at=NULL,submitted_at=NOW()");
  $q->execute([$s['id'],$req,$stored,mb_substr(basename($file['name']),0,255)]);
  auditLog((int)$user['id'],'document_submitted',"requirement $req");$go('Document submitted successfully.');
 }
 if($action==='create_program' && in_array($role,['Admin','SuperAdmin'],true)){
  $name=trim((string)($_POST['name']??''));$min=(float)($_POST['min_grade']??0);$margin=(float)($_POST['warning_margin']??3);
  if($name===''||mb_strlen($name)>150||$min<0||$min>100||$margin<0||$margin>100)$go('Enter a valid program name and grade rules.');
  $q=$pdo->prepare('INSERT INTO scholarship_programs(name,description,min_grade,warning_margin) VALUES(?,?,?,?)');$q->execute([$name,trim((string)($_POST['description']??'')),$min,$margin]);
  auditLog((int)$user['id'],'program_created',$name);$go('Scholarship program created.');
 }
 if($action==='create_requirement' && in_array($role,['Admin','SuperAdmin'],true)){
  $pid=(int)($_POST['program_id']??0);$name=trim((string)($_POST['name']??''));$description=trim((string)($_POST['description']??''));$due=trim((string)($_POST['due_date']??''));
  if($pid<1||$name===''||mb_strlen($name)>150||($due!==''&&!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$due)))$go('Enter a valid program, requirement name and due date.');
  $q=$pdo->prepare('INSERT INTO document_requirements(program_id,name,description,due_date) VALUES(?,?,?,?)');$q->execute([$pid,$name,$description?:null,$due?:null]);
  auditLog((int)$user['id'],'requirement_created',$name);$go('Document requirement created.');
 }
 if($action==='assign_scholar' && $role==='Admin'){
  $uid=(int)($_POST['user_id']??0);$pid=(int)($_POST['program_id']??0);$number=trim((string)($_POST['student_number']??''));
  if($uid<1||$pid<1||$number===''||mb_strlen($number)>30)$go('Complete all scholar details.');
  $q=$pdo->prepare("INSERT INTO scholars(user_id,program_id,student_number,status,verified_by,verified_at) VALUES(?,?,?,'Active',?,NOW())");$q->execute([$uid,$pid,$number,$user['id']]);
  auditLog((int)$user['id'],'scholar_assigned',"user $uid, program $pid");$go('Scholar account assigned.');
 }
 if($action==='record_grade' && $role==='Admin'){
  $sid=(int)($_POST['scholar_id']??0);$term=trim((string)($_POST['term']??''));$avg=(float)($_POST['average']??-1);
  if($sid<1||$term===''||mb_strlen($term)>30||$avg<0||$avg>100)$go('Enter a valid term and grade from 0 to 100.');
  $q=$pdo->prepare('INSERT INTO grade_records(scholar_id,term,average,recorded_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE average=VALUES(average),recorded_by=VALUES(recorded_by),created_at=NOW()');$q->execute([$sid,$term,$avg,$user['id']]);
  require_once __DIR__.'/includes/scholar_status.php';refreshScholarStatus($sid,(int)$user['id']);
  $q=$pdo->prepare('SELECT user_id FROM scholars WHERE id=?');$q->execute([$sid]);$student=$q->fetchColumn();
  if($student) $pdo->prepare('INSERT INTO notifications(user_id,message) VALUES(?,?)')->execute([$student,"Your academic record for $term has been updated."]);
  auditLog((int)$user['id'],'grade_recorded',"scholar $sid, $term");$go('Academic record saved and scholarship status recalculated.');
 }
 if($action==='review_document' && $role==='Admin'){
  $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');$note=trim((string)($_POST['note']??''));
  if(!in_array($status,['Approved','Rejected'],true))$go('Choose Approved or Rejected.');
  $q=$pdo->prepare('UPDATE scholar_documents SET status=?,note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?');$q->execute([$status,mb_substr($note,0,500),$user['id'],$id]);
  $q=$pdo->prepare('SELECT s.user_id FROM scholar_documents d JOIN scholars s ON s.id=d.scholar_id WHERE d.id=?');$q->execute([$id]);$uid=$q->fetchColumn();
  if($uid)$pdo->prepare('INSERT INTO notifications(user_id,message) VALUES(?,?)')->execute([$uid,"A submitted scholarship document was $status.".($note?" Note: $note":'')]);
  auditLog((int)$user['id'],'document_reviewed',"document $id: $status");$go('Document review saved.');
 }
 if($action==='create_admin' && $role==='SuperAdmin'){
  $username=trim((string)($_POST['username']??''));$email=trim((string)($_POST['email']??''));$name=trim((string)($_POST['full_name']??''));$pass=(string)($_POST['password']??'');
  if(!isValidUsername($username)||!filter_var($email,FILTER_VALIDATE_EMAIL)||$name===''||($problem=passwordProblem($pass)))$go('Check the username, email, full name and password (10–72 characters).');
  $q=$pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,role) VALUES(?,?,?,?, 'Admin')");$q->execute([$username,$email,password_hash($pass,PASSWORD_DEFAULT),$name]);
  auditLog((int)$user['id'],'admin_created',$username);$go('Administrator account created.');
 }
 if($action==='toggle_user' && $role==='SuperAdmin'){
  $id=(int)($_POST['id']??0);if($id===(int)$user['id'])$go('You cannot deactivate your own account.');
  $q=$pdo->prepare("UPDATE users SET is_active=1-is_active WHERE id=? AND role='Admin'");$q->execute([$id]);
  auditLog((int)$user['id'],'admin_status_toggled',"user $id");$go('Administrator account status updated.');
 }
} catch(PDOException $e) {
 error_log('GranTrack action failed: '.$e->getMessage());
 $go($e->getCode()==='23000'?'A record with those details already exists.':'The operation could not be completed. Check the submitted information.');
}
http_response_code(403);exit('Action not allowed.');
