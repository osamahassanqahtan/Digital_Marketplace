<?php
/**
 * chats/send.php
 * ملف مخصص لإرسال الرسائل عبر AJAX (بدون إعادة تحميل الصفحة)
 * يستقبل طلبات POST ويعيد استجابة JSON
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

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة غير مسموحة.']);
    exit;
}

// جلب البيانات
$receiverId = filter_var($_POST['receiver_id'] ?? 0, FILTER_VALIDATE_INT);
$message = sanitizeInput($_POST['message'] ?? '');
$productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);

// التحقق من صحة البيانات
if ($receiverId < 1 || $receiverId == $userId) {
    echo json_encode(['success' => false, 'message' => 'معرف المستلم غير صحيح.']);
    exit;
}

if (empty($message) && empty($_FILES['image']['name'])) {
    echo json_encode(['success' => false, 'message' => 'يرجى كتابة رسالة أو رفع صورة.']);
    exit;
}

// التحقق من وجود المستلم
try {
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT id, name FROM users WHERE id = ? AND status = "active"');
    $stmt->execute([$receiverId]);
    $receiver = $stmt->fetch();
    
    if (!$receiver) {
        echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات.']);
    exit;
}

// معالجة الصورة المرفقة
$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5 MB
    
    $fileType = $_FILES['image']['type'];
    $fileTmp = $_FILES['image']['tmp_name'];
    $fileSize = $_FILES['image']['size'];
    $fileExt = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    
    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'نوع الملف غير مدعوم.']);
        exit;
    }
    
    if ($fileSize > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'حجم الصورة يتجاوز 5 ميجابايت.']);
        exit;
    }
    
    $newName = uniqid() . '.' . $fileExt;
    $uploadPath = '../uploads/chat/' . $newName;
    
    // إنشاء مجلد chat في uploads إن لم يكن موجوداً
    if (!is_dir('../uploads/chat')) {
        mkdir('../uploads/chat', 0777, true);
    }
    
    if (move_uploaded_file($fileTmp, $uploadPath)) {
        $imagePath = $newName;
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل رفع الصورة.']);
        exit;
    }
}

// حفظ الرسالة في قاعدة البيانات
try {
    $db->beginTransaction();
    
    $stmt = $db->prepare('INSERT INTO chats (sender_id, receiver_id, product_id, message, image) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $receiverId, $productId > 0 ? $productId : null, $message, $imagePath]);
    
    $chatId = $db->lastInsertId();
    
    // تحديث حالة القراءة للرسائل المرسلة من الطرف الآخر (تحديث فوري)
    $stmt = $db->prepare('UPDATE chats SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
    $stmt->execute([$receiverId, $userId]);
    
    // إنشاء إشعار للمستلم
    $senderName = getCurrentUserName();
    $notifMessage = "📩 رسالة جديدة من $senderName";
    if (!empty($message)) {
        $notifMessage .= ": " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '');
    } elseif ($imagePath) {
        $notifMessage .= " (صورة)";
    }
    
    $stmt = $db->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $stmt->execute([$receiverId, $notifMessage]);
    
    $db->commit();
    
    // جلب بيانات الرسالة المرسلة لإعادتها في الاستجابة
    $stmt = $db->prepare('SELECT c.*, u.name as sender_name FROM chats c JOIN users u ON c.sender_id = u.id WHERE c.id = ?');
    $stmt->execute([$chatId]);
    $newMessage = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إرسال الرسالة بنجاح.',
        'data' => [
            'id' => $newMessage['id'],
            'sender_id' => $newMessage['sender_id'],
            'sender_name' => $newMessage['sender_name'],
            'message' => $newMessage['message'],
            'image' => $newMessage['image'],
            'product_id' => $newMessage['product_id'],
            'created_at' => $newMessage['created_at'],
            'is_read' => $newMessage['is_read']
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في إرسال الرسالة: ' . $e->getMessage()]);
}