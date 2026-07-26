<?php
/**
 * reports/status.php
 * تحديث حالة التقرير (من قبل المدير فقط)
 * يدعم GET و POST و AJAX
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول وصلاحية المدير
requireLogin('../auth/login.php');
requireRole('admin', '../index.php');

$reportId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$newStatus = $_GET['status'] ?? $_POST['status'] ?? '';
$redirect = $_GET['redirect'] ?? '../admin/dashboard.php';
$ajax = isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// التحقق من صحة المدخلات
if ($reportId < 1) {
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'معرف التقرير غير صحيح.']);
        exit;
    }
    setFlashMessage('error', 'معرف التقرير غير صحيح.', 'danger');
    header('Location: ' . $redirect);
    exit;
}

$validStatuses = ['pending', 'resolved', 'dismissed'];
if (!in_array($newStatus, $validStatuses)) {
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'حالة غير صالحة.']);
        exit;
    }
    setFlashMessage('error', 'حالة غير صالحة.', 'danger');
    header('Location: ' . $redirect);
    exit;
}

try {
    $db = Database::getConnection();
    
    // التحقق من وجود التقرير
    $stmt = $db->prepare('SELECT id, status, product_id FROM reports WHERE id = ?');
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    
    if (!$report) {
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'التقرير غير موجود.']);
            exit;
        }
        setFlashMessage('error', 'التقرير غير موجود.', 'danger');
        header('Location: ' . $redirect);
        exit;
    }
    
    // إذا كانت الحالة نفسها، نعيد بدون تغيير
    if ($report['status'] === $newStatus) {
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'الحالة لم تتغير.', 'status' => $newStatus]);
            exit;
        }
        setFlashMessage('info', 'الحالة لم تتغير.', 'info');
        header('Location: ' . $redirect);
        exit;
    }
    
    // تحديث حالة التقرير
    $stmt = $db->prepare('UPDATE reports SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $reportId]);
    
    // إنشاء إشعار للمستخدم الذي أبلغ (حسب الحالة الجديدة)
    $statusMessages = [
        'resolved' => 'تم حل البلاغ الخاص بالمنتج #' . $report['product_id'] . '، شكراً لك.',
        'dismissed' => 'تم رفض البلاغ الخاص بالمنتج #' . $report['product_id'] . '، لم يتم العثور على مخالفة.',
        'pending' => 'تم إعادة فتح البلاغ الخاص بالمنتج #' . $report['product_id'] . ' للمراجعة.'
    ];
    
    if (isset($statusMessages[$newStatus])) {
        // جلب معرف المستخدم الذي أبلغ
        $stmtUser = $db->prepare('SELECT user_id FROM reports WHERE id = ?');
        $stmtUser->execute([$reportId]);
        $reporter = $stmtUser->fetch();
        if ($reporter) {
            $stmtNotif = $db->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmtNotif->execute([$reporter['user_id'], $statusMessages[$newStatus]]);
        }
    }
    
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث حالة التقرير بنجاح.',
            'status' => $newStatus
        ]);
        exit;
    }
    
    setFlashMessage('success', 'تم تحديث حالة التقرير بنجاح.', 'success');
    header('Location: ' . $redirect);
    exit;
    
} catch (PDOException $e) {
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.']);
        exit;
    }
    setFlashMessage('error', 'حدث خطأ في تحديث حالة التقرير.', 'danger');
    header('Location: ' . $redirect);
    exit;
}