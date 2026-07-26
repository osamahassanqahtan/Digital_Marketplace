<?php
/**
 * favorites/toggle.php
 * إضافة أو إزالة منتج من المفضلة (يُستخدم عبر AJAX)
 * يعيد استجابة JSON
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً.']);
    exit;
}

$userId = getCurrentUserId();
$productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? 'toggle'; // 'add', 'remove', أو 'toggle'

if ($productId < 1) {
    echo json_encode(['success' => false, 'message' => 'معرف المنتج غير صحيح.']);
    exit;
}

try {
    $db = Database::getConnection();
    
    // التحقق من وجود المنتج
    $stmt = $db->prepare('SELECT id FROM products WHERE id = ? AND status != "deleted"');
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'المنتج غير موجود.']);
        exit;
    }
    
    // التحقق من المفضلة حالياً
    $stmt = $db->prepare('SELECT id FROM favorites WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    $exists = $stmt->fetch();
    
    $isFavorite = false;
    
    if ($action === 'add') {
        // إضافة إذا لم تكن موجودة
        if (!$exists) {
            $stmt = $db->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)');
            $stmt->execute([$userId, $productId]);
            $isFavorite = true;
            $message = 'تمت الإضافة إلى المفضلة.';
        } else {
            $isFavorite = true;
            $message = 'المنتج موجود بالفعل في المفضلة.';
        }
    } elseif ($action === 'remove') {
        // إزالة إذا كانت موجودة
        if ($exists) {
            $stmt = $db->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$userId, $productId]);
            $isFavorite = false;
            $message = 'تمت الإزالة من المفضلة.';
        } else {
            $isFavorite = false;
            $message = 'المنتج غير موجود في المفضلة.';
        }
    } else { // toggle
        if ($exists) {
            // إزالة
            $stmt = $db->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$userId, $productId]);
            $isFavorite = false;
            $message = 'تمت الإزالة من المفضلة.';
        } else {
            // إضافة
            $stmt = $db->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)');
            $stmt->execute([$userId, $productId]);
            $isFavorite = true;
            $message = 'تمت الإضافة إلى المفضلة.';
        }
    }
    
    echo json_encode([
        'success' => true,
        'is_favorite' => $isFavorite,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.']);
}