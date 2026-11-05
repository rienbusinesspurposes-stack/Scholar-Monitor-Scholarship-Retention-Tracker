<?php
require_once __DIR__.'/config/database.php';require_once __DIR__.'/includes/security.php';require_once __DIR__.'/includes/functions.php';
$user=requireLogin();$id=(int)($_GET['id']??0);
$sql='SELECT d.stored_name,d.original_name,s.user_id FROM scholar_documents d JOIN scholars s ON s.id=d.scholar_id WHERE d.id=?';
$q=$pdo->prepare($sql);$q->execute([$id]);$d=$q->fetch();
if(!$d || ($user['role']==='User' && (int)$d['user_id']!==(int)$user['id']) || !in_array($user['role'],['User','Admin','SuperAdmin'],true)){http_response_code(404);exit('File not found.');}
$path=__DIR__.'/uploads/documents/'.$d['stored_name'];
if(!is_file($path)){http_response_code(404);exit('File not found.');}
$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($path);if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true)){http_response_code(415);exit('Unsupported file.');}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="'.rawurlencode(basename($d['original_name'])).'"');readfile($path);exit;
