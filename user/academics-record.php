<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/security.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/portal.php';
$q=$pdo->prepare('SELECT id FROM scholars WHERE user_id=?');$q->execute([$portalUser['id']]);$sid=$q->fetchColumn();$rows=[];if($sid){$q=$pdo->prepare('SELECT term,average,created_at FROM grade_records WHERE scholar_id=? ORDER BY created_at DESC,id DESC');$q->execute([$sid]);foreach($q->fetchAll() as $r)$rows[]=[h($r['term']),h($r['average']),h($r['created_at'])];}portalStart('Academic Records','academics-record.php');portalTable(['Term','Average','Recorded'],$rows);portalEnd();