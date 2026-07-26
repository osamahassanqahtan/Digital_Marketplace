<?php
/**
 * chats/view.php
 * عرض المحادثة بين مستخدمين مع إمكانية إرسال رسائل جديدة
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// التحقق من تسجيل الدخول
requireLogin('../auth/login.php');

$userId = getCurrentUserId();
$otherUserId = filter_var($_GET['user_id'] ?? 0, FILTER_VALIDATE_INT);

if ($otherUserId < 1 || $otherUserId == $userId) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// التحقق من وجود المستخدم الآخر
try {
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT id, name, email FROM users WHERE id = ? AND status = "active"');
    $stmt->execute([$otherUserId]);
    $otherUser = $stmt->fetch();
    if (!$otherUser) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: index.php');
    exit;
}

// معالجة إرسال رسالة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = sanitizeInput($_POST['message']);
    $productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!empty($message)) {
        try {
            $stmt = $db->prepare('INSERT INTO chats (sender_id, receiver_id, product_id, message) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $otherUserId, $productId > 0 ? $productId : null, $message]);
            $success = 'تم إرسال الرسالة.';
        } catch (PDOException $e) {
            $error = 'حدث خطأ في إرسال الرسالة.';
        }
    } else {
        $error = 'الرجاء كتابة رسالة.';
    }
}

// جلب الرسائل بين المستخدمين
try {
    $db = Database::getConnection();
    
    // تحديث حالة القراءة للرسائل الواردة (غير المقروءة)
    $stmt = $db->prepare('UPDATE chats SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
    $stmt->execute([$otherUserId, $userId]);
    
    // جلب جميع الرسائل بين المستخدمين
    $sql = "SELECT c.*, 
            u_sender.name as sender_name,
            u_receiver.name as receiver_name,
            p.name as product_name
            FROM chats c
            JOIN users u_sender ON c.sender_id = u_sender.id
            JOIN users u_receiver ON c.receiver_id = u_receiver.id
            LEFT JOIN products p ON c.product_id = p.id
            WHERE (c.sender_id = ? AND c.receiver_id = ?) 
               OR (c.sender_id = ? AND c.receiver_id = ?)
            ORDER BY c.created_at ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
    $messages = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل الرسائل.';
    $messages = [];
}

// جلب المنتجات الخاصة بالمستخدم الآخر (للإشارة إليها في الرسائل)
try {
    $stmt = $db->prepare('SELECT id, name FROM products WHERE seller_id = ? AND status = "available" ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$otherUserId]);
    $sellerProducts = $stmt->fetchAll();
} catch (PDOException $e) {
    $sellerProducts = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المحادثة مع <?php echo htmlspecialchars($otherUser['name']); ?> - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .chat-container {
            max-height: 500px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .message {
            max-width: 75%;
            padding: 10px 15px;
            border-radius: 15px;
            margin-bottom: 10px;
            word-wrap: break-word;
        }
        .message-sent {
            background-color: #0d6efd;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }
        .message-received {
            background-color: #e9ecef;
            color: #212529;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }
        .message-time {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 3px;
        }
        .message-sent .message-time {
            color: rgba(255,255,255,0.8);
        }
        .message-received .message-time {
            color: #6c757d;
        }
        .product-ref {
            font-size: 0.8rem;
            background: rgba(0,0,0,0.05);
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 3px;
        }
        .message-sent .product-ref {
            background: rgba(255,255,255,0.2);
        }
        #messageInput:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.25);
        }
    </style>
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">🏪 منصة السوق</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="../products/index.php">المنتجات</a></li>
                    <li class="nav-item"><a class="nav-link" href="../favorites/index.php">المفضلة</a></li>
                    <li class="nav-item"><a class="nav-link" href="../notifications/index.php">الإشعارات</a></li>
                    <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-chat"></i> المحادثات</a></li>
                    <li class="nav-item"><a class="nav-link" href="../auth/logout.php">تسجيل خروج</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="bi bi-person-circle"></i> المحادثة مع <?php echo htmlspecialchars($otherUser['name']); ?></h4>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> العودة</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- منطقة عرض الرسائل -->
        <div class="card shadow-sm">
            <div class="card-body chat-container" id="chatMessages">
                <?php if (empty($messages)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-chat" style="font-size: 2rem;"></i>
                        <p class="mt-2">لا توجد رسائل بعد. ابدأ المحادثة!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php 
                        $isSent = ($msg['sender_id'] == $userId);
                        $msgClass = $isSent ? 'message-sent' : 'message-received';
                        ?>
                        <div class="message <?php echo $msgClass; ?>">
                            <?php if ($msg['product_name']): ?>
                                <div class="product-ref">
                                    <i class="bi bi-box"></i> <?php echo htmlspecialchars($msg['product_name']); ?>
                                </div>
                            <?php endif; ?>
                            <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                            <div class="message-time">
                                <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                <?php if ($isSent): ?>
                                    <span class="ms-1">
                                        <?php if ($msg['is_read']): ?>
                                            <i class="bi bi-check-all" style="color: #87CEEB;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-check"></i>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- نموذج إرسال رسالة -->
        <div class="mt-3">
            <form method="POST" action="" id="chatForm">
                <div class="row g-2">
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="messageInput" name="message" placeholder="اكتب رسالتك..." required autofocus>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send"></i> إرسال</button>
                    </div>
                </div>
                <div class="mt-2">
                    <select class="form-select form-select-sm" name="product_id" style="width: auto; display: inline-block;">
                        <option value="0">بدون منتج</option>
                        <?php foreach ($sellerProducts as $prod): ?>
                            <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted ms-2">اختر منتجاً للإشارة إليه (اختياري)</small>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تمرير الشات إلى الأسفل لعرض أحدث الرسائل
        const chatContainer = document.getElementById('chatMessages');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // إعادة تحميل الصفحة بعد إرسال الرسالة (سيحدث تلقائياً مع POST)
        // يمكن إضافة AJAX لاحقاً للتحديث الديناميكي
    </script>
</body>
</html>