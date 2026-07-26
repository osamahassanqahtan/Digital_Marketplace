<?php
/**
 * seller/delete-product.php
 * حذف منتج من لوحة البائع (للبائع أو المدير)
 * يدعم GET و POST و AJAX
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول ودور البائع أو المدير
requireLogin('../auth/login.php');
requireRole(['seller', 'admin'], '../index.php');

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$productId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$redirect = $_GET['redirect'] ?? 'dashboard.php';
$ajax = isset($_GET['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($productId < 1) {
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'معرف المنتج غير صحيح.']);
        exit;
    }
    setFlashMessage('error', 'معرف المنتج غير صحيح.', 'danger');
    header('Location: ' . $redirect);
    exit;
}

try {
    $db = Database::getConnection();
    
    // جلب بيانات المنتج للتحقق من الصلاحية
    $stmt = $db->prepare('SELECT id, seller_id FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'المنتج غير موجود.']);
            exit;
        }
        setFlashMessage('error', 'المنتج غير موجود.', 'danger');
        header('Location: ' . $redirect);
        exit;
    }
    
    // التحقق من الصلاحية: البائع نفسه أو مدير
    if ($userRole !== 'admin' && $userId != $product['seller_id']) {
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'لا تملك صلاحية حذف هذا المنتج.']);
            exit;
        }
        setFlashMessage('error', 'لا تملك صلاحية حذف هذا المنتج.', 'danger');
        header('Location: ' . $redirect);
        exit;
    }
    
    // جلب أسماء الصور لحذفها من السيرفر
    $stmtImg = $db->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
    $stmtImg->execute([$productId]);
    $images = $stmtImg->fetchAll();
    
    // بدء المعاملة
    $db->beginTransaction();
    
    // حذف المنتج من قاعدة البيانات (سيتم حذف الصور تلقائياً بسبب ON DELETE CASCADE)
    $stmtDelete = $db->prepare('DELETE FROM products WHERE id = ?');
    $stmtDelete->execute([$productId]);
    
    // حذف ملفات الصور من السيرفر
    $deletedFiles = 0;
    foreach ($images as $img) {
        $filePath = '../uploads/products/' . $img['image_path'];
        if (file_exists($filePath) && is_file($filePath)) {
            if (unlink($filePath)) {
                $deletedFiles++;
            }
        }
    }
    
    $db->commit();
    
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف المنتج بنجاح.',
            'deleted_files' => $deletedFiles
        ]);
        exit;
    }
    
    setFlashMessage('success', 'تم حذف المنتج بنجاح.', 'success');
    header('Location: ' . $redirect);
    exit;
    
} catch (PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.']);
        exit;
    }
    setFlashMessage('error', 'حدث خطأ في حذف المنتج.', 'danger');
    header('Location: ' . $redirect);
    exit;
}