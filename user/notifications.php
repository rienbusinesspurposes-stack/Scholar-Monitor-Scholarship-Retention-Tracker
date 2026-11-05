<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/security.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/portal.php';
$q=$pdo->prepare('SELECT id,message,created_at,read_at FROM notifications WHERE user_id=? ORDER BY created_at DESC');$q->execute([$portalUser['id']]);$items=$q->fetchAll();portalStart('Notifications','notifications.php');foreach($items as $n){echo '<div class="portal-panel"><p>'.h($n['message']).'</p><small>'.h($n['created_at']).' · '.($n['read_at']?'Read':'Unread').'</small>';if(!$n['read_at'])echo '<form method="post" action="../portal_action.php">'.csrf_field().'<input type="hidden" name="action" value="mark_notification"><input type="hidden" name="back" value="notifications.php"><input type="hidden" name="id" value="'.h($n['id']).'"><button class="portal-button secondary">Mark as read</button></form>';echo '</div>';}if(!$items)portalNotice('No notifications yet.');portalEnd();