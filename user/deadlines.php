<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/security.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/portal.php';
$q=$pdo->prepare('SELECT id,program_id FROM scholars WHERE user_id=?');$q->execute([$portalUser['id']]);$s=$q->fetch();$rows=[];if($s){$q=$pdo->prepare('SELECT r.name,r.due_date,d.status FROM document_requirements r LEFT JOIN scholar_documents d ON d.requirement_id=r.id AND d.scholar_id=? WHERE r.program_id=? AND r.due_date IS NOT NULL ORDER BY r.due_date');$q->execute([$s['id'],$s['program_id']]);foreach($q->fetchAll() as $r)$rows[]=[h($r['name']),h($r['due_date']),h($r['status']??'Not submitted')];}portalStart('Deadlines','deadlines.php');portalTable(['Requirement','Due date','Status'],$rows);portalEnd();