<?php
/**
 * notifications/mark-read.php
 * تحديد إشعار كمقروء (يدعم GET و POST و AJAX)
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
requireLogin('../auth/login.php');

$userId = getCurrentUserId();
$notificationId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$redirect = $_GET['redirect'] ?? 'index.php';
$ajax = isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($notificationId < 1) {
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'معرف الإشعار غير صحيح.']);
        exit;
    }
    setFlashMessage('error', 'معرف الإشعار غير صحيح.', 'danger');
    header('Location: ' . $redirect);
    exit;
}

try {
    $db = Database::getConnection();
    
    // التحقق من ملكية الإشعار
    $stmt = $db->prepare('SELECT id, is_read FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([$notificationId, $userId]);
    $notification = $stmt->fetch();
    
    if (!$notification) {
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'الإشعار غير موجود أو لا يخصك.']);
            exit;
        }
        setFlashMessage('error', 'الإشعار غير موجود أو لا يخصك.', 'danger');
        header('Location: ' . $redirect);
        exit;
    }
    
    // إذا كان مقروءاً بالفعل، نعيد توجيه بدون تغيير
    if ($notification['is_read'] == 1) {
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'الإشعار مقروء بالفعل.', 'already_read' => true]);
            exit;
        }
        setFlashMessage('info', 'الإشعار مقروء بالفعل.', 'info');
        header('Location: ' . $redirect);
        exit;
    }
    
    // تحديث الحالة إلى مقروء
    $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
    $stmt->execute([$notificationId]);
    
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'تم تحديد الإشعار كمقروء.']);
        exit;
    }
    
    setFlashMessage('success', 'تم تحديد الإشعار كمقروء.', 'success');
    header('Location: ' . $redirect);
    exit;
    
} catch (PDOException $e) {
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.']);
        exit;
    }
    setFlashMessage('error', 'حدث خطأ في تحديث الإشعار.', 'danger');
    header('Location: ' . $redirect);
    exit;
}