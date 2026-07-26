<?php
/**
 * auth/logout.php
 * تسجيل الخروج وتدمير الجلسة
 */

require_once '../includes/session.php';

// تسجيل الخروج
logoutUser();

// توجيه المستخدم إلى صفحة تسجيل الدخول مع رسالة
header('Location: login.php?logged_out=1');
exit;