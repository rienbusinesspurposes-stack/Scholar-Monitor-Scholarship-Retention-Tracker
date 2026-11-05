<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/security.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/portal.php';
requireRole(['SuperAdmin']);$q=$pdo->query('SELECT a.action,a.details,a.ip_address,a.created_at,u.username FROM security_audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 250');$rows=[];foreach($q->fetchAll() as $r)$rows[]=[h($r['created_at']),h($r['username']??'Deleted user'),h($r['action']),h($r['details']),h($r['ip_address'])];portalStart('Security Audit Logs','audit.php');portalTable(['Date','User','Action','Details','IP'],$rows);portalEnd();