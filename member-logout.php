<?php
// member-logout.php
require_once 'includes/member-auth.php';
logoutMember();
header('Location: index.php');
exit;
