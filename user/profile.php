<?php
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../includes/security.php';require_once __DIR__.'/../includes/functions.php';require_once __DIR__.'/../includes/portal.php';
$q=$pdo->prepare('SELECT username,email,full_name,created_at FROM users WHERE id=?');$q->execute([$portalUser['id']]);$profile=$q->fetch();
portalStart('My Profile','profile.php');
echo '<div class="portal-panel"><h2>Account information</h2><form class="portal-form" method="post" action="../portal_action.php">'.csrf_field().'<input type="hidden" name="action" value="update_profile"><input type="hidden" name="back" value="profile.php"><label>Full name<input name="full_name" maxlength="150" value="'.h($profile['full_name']).'" required></label><label>Username<input value="'.h($profile['username']).'" disabled></label><label>Email<input type="email" value="'.h($profile['email']).'" disabled></label><label>Member since<input value="'.h($profile['created_at']).'" disabled></label><button class="portal-button">Save profile</button></form><p>For username or email changes, contact your system administrator.</p></div>';
portalEnd();
