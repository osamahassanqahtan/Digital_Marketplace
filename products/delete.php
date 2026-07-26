<?php
/**
 * products/delete.php
 * حذف منتج (للبائع أو المدير) مع حذف الصور المرتبطة
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

requireLogin('../auth/login.php');
requireRole(['seller', 'admin'], '../index.php');

$productId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($productId < 1) {
    header('Location: index.php');
    exit;
}

try {
    $db = Database::getConnection();
    
    // جلب بيانات المنتج للتحقق من الصلاحية
    $stmt = $db->prepare('SELECT seller_id FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: index.php');
        exit;
    }
    
    // التحقق من الصلاحية: البائع نفسه أو مدير
    $currentUserId = getCurrentUserId();
    $currentRole = getCurrentUserRole();
    if ($currentRole !== 'admin' && $currentUserId != $product['seller_id']) {
        header('Location: index.php');
        exit;
    }
    
    // جلب أسماء الصور لحذفها من السيرفر
    $stmtImg = $db->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
    $stmtImg->execute([$productId]);
    $images = $stmtImg->fetchAll();
    
    // بدء المعاملة
    $db->beginTransaction();
    
    // حذف الصور من قاعدة البيانات (سيتم تلقائياً بسبب ON DELETE CASCADE)
    // لكننا نحتاج لحذف الملفات فعلياً
    $stmtDelete = $db->prepare('DELETE FROM products WHERE id = ?');
    $stmtDelete->execute([$productId]);
    
    // حذف ملفات الصور من السيرفر
    foreach ($images as $img) {
        $filePath = '../uploads/products/' . $img['image_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    $db->commit();
    
    // توجيه المستخدم مع رسالة نجاح
    header('Location: index.php?deleted=1');
    exit;
    
} catch (PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    // توجيه مع رسالة خطأ
    header('Location: index.php?error=1');
    exit;
}
?>