<?php
/**
 * favorites/remove.php
 * إزالة منتج من المفضلة (طريقة GET/POST بسيطة بدون AJAX)
 * يعيد التوجيه إلى الصفحة السابقة مع رسالة نجاح أو خطأ
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// التحقق من تسجيل الدخول
requireLogin('../auth/login.php');

$userId = getCurrentUserId();
$productId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$redirect = $_GET['redirect'] ?? '../products/view.php?id=' . $productId;

if ($productId < 1) {
    header('Location: ../products/index.php');
    exit;
}

// التحقق من وجود المنتج
try {
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT id FROM products WHERE id = ? AND status != "deleted"');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: ../products/index.php');
        exit;
    }
    
    // التحقق مما إذا كان المنتج موجوداً في المفضلة
    $stmt = $db->prepare('SELECT id FROM favorites WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // المنتج غير موجود في المفضلة
        header('Location: ' . $redirect . '&not_favorite=1');
        exit;
    }
    
    // حذف المنتج من المفضلة
    $stmt = $db->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    
    header('Location: ' . $redirect . '&favorite_removed=1');
    exit;
    
} catch (PDOException $e) {
    header('Location: ' . $redirect . '&favorite_error=1');
    exit;
}