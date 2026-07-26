<?php
/**
 * chat/conversation.php
 * صفحة المحادثة - تصميم مستوحى من واتساب
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';
require_once '../includes/upload.php';

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
    $stmt = $db->prepare('SELECT id, name, email, phone, location FROM users WHERE id = ? AND status = "active"');
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
    
    // معالجة الصورة المرفقة
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $result = uploadImage($_FILES['image'], '../uploads/chat/', 800, 800);
        if ($result['success']) {
            $imagePath = $result['filename'];
        }
    }
    
    if (!empty($message) || $imagePath) {
        try {
            $stmtCheck = $db->query("SHOW COLUMNS FROM chats LIKE 'image'");
            $hasImageColumn = $stmtCheck->rowCount() > 0;
            
            if ($hasImageColumn) {
                $stmt = $db->prepare('INSERT INTO chats (sender_id, receiver_id, product_id, message, image) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $otherUserId, $productId > 0 ? $productId : null, $message, $imagePath]);
            } else {
                $stmt = $db->prepare('INSERT INTO chats (sender_id, receiver_id, product_id, message) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $otherUserId, $productId > 0 ? $productId : null, $message]);
            }
            
            // تحديث حالة القراءة
            $stmt = $db->prepare('UPDATE chats SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
            $stmt->execute([$otherUserId, $userId]);
            
            // إنشاء إشعار للمستقبل
            $senderName = getCurrentUserName();
            $notifMessage = "📩 رسالة جديدة من $senderName";
            if (!empty($message)) {
                $notifMessage .= ": " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '');
            } elseif ($imagePath) {
                $notifMessage .= " (صورة)";
            }
            $stmt = $db->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->execute([$otherUserId, $notifMessage]);
            
            header('Location: conversation.php?user_id=' . $otherUserId . '&sent=1');
            exit;
        } catch (PDOException $e) {
            $error = 'حدث خطأ في إرسال الرسالة.';
        }
    } else {
        $error = 'الرجاء كتابة رسالة أو رفع صورة.';
    }
}

// جلب الرسائل بين المستخدمين
try {
    $db = Database::getConnection();
    
    // تحديث حالة القراءة للرسائل الواردة
    $stmt = $db->prepare('UPDATE chats SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
    $stmt->execute([$otherUserId, $userId]);
    
    $sql = "SELECT c.*, 
            u_sender.name as sender_name,
            u_receiver.name as receiver_name,
            p.name as product_name,
            p.id as product_id
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

// جلب المنتجات الخاصة بالمستخدم الآخر
try {
    $stmt = $db->prepare('SELECT id, name, price FROM products WHERE seller_id = ? AND status = "available" ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$otherUserId]);
    $sellerProducts = $stmt->fetchAll();
} catch (PDOException $e) {
    $sellerProducts = [];
}

// حساب عدد الرسائل غير المقروءة
$unreadCount = 0;
foreach ($messages as $msg) {
    if ($msg['sender_id'] == $otherUserId && $msg['is_read'] == 0) {
        $unreadCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>المحادثة مع <?php echo htmlspecialchars($otherUser['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* ==========================================
           تنسيقات واتساب - شاملة ومتطورة
           ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
            font-family: 'Tajawal', 'Segoe UI', sans-serif;
            background: #ECE5DD;
        }
        
        /* خلفية واتساب */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"%3E%3Cpath d="M10 10 L90 10 L90 90 L10 90 Z" fill="none" stroke="rgba(0,0,0,0.02)" stroke-width="1"/%3E%3C/svg%3E');
            background-size: 60px 60px;
            z-index: 0;
        }
        
        /* ==========================================
           الهيدر - شريط علوي (واتساب)
           ========================================== */
        .chat-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #075E54;
            padding: 8px 16px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .chat-header .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-header .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #128C7E;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .chat-header .user-info {
            color: #fff;
        }
        .chat-header .user-info .name {
            font-weight: 700;
            font-size: 1rem;
            line-height: 1.2;
        }
        .chat-header .user-info .status {
            font-size: 0.7rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .chat-header .user-info .status .online-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4CAF50;
            display: inline-block;
        }
        .chat-header .header-actions {
            display: flex;
            gap: 12px;
            color: #fff;
        }
        .chat-header .header-actions a {
            color: #fff;
            text-decoration: none;
            font-size: 1.3rem;
            transition: 0.2s;
            padding: 4px;
        }
        .chat-header .header-actions a:hover {
            opacity: 0.7;
        }
        
        /* ==========================================
           منطقة الرسائل
           ========================================== */
        .chat-messages {
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 70px;
            overflow-y: auto;
            padding: 16px 20px 20px;
            z-index: 1;
            display: flex;
            flex-direction: column;
        }
        .chat-messages .message-wrapper {
            display: flex;
            flex-direction: column;
            margin-bottom: 4px;
            max-width: 85%;
        }
        .chat-messages .message-wrapper.sent {
            align-self: flex-end;
            align-items: flex-end;
        }
        .chat-messages .message-wrapper.received {
            align-self: flex-start;
            align-items: flex-start;
        }
        
        .chat-messages .bubble {
            padding: 8px 14px;
            border-radius: 12px;
            word-wrap: break-word;
            position: relative;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            max-width: 100%;
        }
        .chat-messages .bubble.sent {
            background: #DCF8C6;
            color: #1A1A1A;
            border-bottom-left-radius: 4px;
        }
        .chat-messages .bubble.received {
            background: #FFFFFF;
            color: #1A1A1A;
            border-bottom-right-radius: 4px;
        }
        .chat-messages .bubble .product-ref {
            font-size: 0.7rem;
            background: rgba(0,0,0,0.05);
            padding: 2px 10px;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 4px;
        }
        .chat-messages .bubble .product-ref a {
            color: #075E54;
            text-decoration: none;
            font-weight: 600;
        }
        .chat-messages .bubble .message-text {
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .chat-messages .bubble .message-image {
            max-width: 220px;
            border-radius: 10px;
            margin-top: 4px;
            cursor: pointer;
            border: 1px solid rgba(0,0,0,0.04);
        }
        .chat-messages .bubble .message-time {
            font-size: 0.6rem;
            color: rgba(0,0,0,0.45);
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 4px;
            justify-content: flex-end;
        }
        .chat-messages .bubble .message-time .read-status {
            font-size: 0.65rem;
        }
        
        /* ==========================================
           تذييل الإدخال (واتساب)
           ========================================== */
        .chat-input {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #F0F0F0;
            padding: 6px 12px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-top: 1px solid rgba(0,0,0,0.04);
        }
        .chat-input .input-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            background: #FFFFFF;
            border-radius: 25px;
            padding: 2px 6px;
            border: 1px solid #E0E0E0;
            transition: 0.3s;
        }
        .chat-input .input-wrapper:focus-within {
            border-color: #075E54;
            box-shadow: 0 0 0 2px rgba(7, 94, 84, 0.15);
        }
        .chat-input .input-wrapper .emoji-btn {
            background: none;
            border: none;
            color: #7C7C7C;
            font-size: 1.3rem;
            padding: 4px 8px;
            cursor: pointer;
            transition: 0.2s;
        }
        .chat-input .input-wrapper .emoji-btn:hover {
            color: #075E54;
        }
        .chat-input .input-wrapper .form-control {
            border: none;
            background: transparent;
            padding: 10px 4px;
            font-size: 0.95rem;
            outline: none;
            box-shadow: none;
            height: auto;
            resize: none;
            color: #1A1A1A;
        }
        .chat-input .input-wrapper .form-control::placeholder {
            color: #B0B0B0;
        }
        
        .chat-input .btn-attach {
            background: none;
            border: none;
            color: #7C7C7C;
            font-size: 1.4rem;
            padding: 6px;
            cursor: pointer;
            transition: 0.2s;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chat-input .btn-attach:hover {
            background: rgba(0,0,0,0.04);
            color: #075E54;
        }
        
        .chat-input .btn-send {
            background: #075E54;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.3rem;
            transition: 0.3s;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(7, 94, 84, 0.25);
        }
        .chat-input .btn-send:hover {
            background: #054740;
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(7, 94, 84, 0.35);
        }
        .chat-input .btn-send:active {
            transform: scale(0.95);
        }
        .chat-input .btn-send i {
            font-size: 1.2rem;
        }
        
        /* ==========================================
           شريط المنتجات (أسفل الإدخال)
           ========================================== */
        .product-selector {
            position: fixed;
            bottom: 62px;
            left: 12px;
            right: 12px;
            z-index: 999;
            background: #FFFFFF;
            border-radius: 16px;
            padding: 8px 12px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.04);
            border: 1px solid #E8E8E8;
            display: none;
            max-height: 150px;
            overflow-y: auto;
        }
        .product-selector.active {
            display: block;
        }
        .product-selector .product-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            font-size: 0.85rem;
        }
        .product-selector .product-item:hover {
            background: #F0F0F0;
        }
        .product-selector .product-item .product-name {
            font-weight: 500;
        }
        .product-selector .product-item .product-price {
            color: #075E54;
            font-weight: 700;
            font-size: 0.8rem;
        }
        .product-selector .product-item .badge-selected {
            background: #075E54;
            color: #fff;
            border-radius: 30px;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 700;
        }
        
        /* ==========================================
           حالة عدم وجود رسائل
           ========================================== */
        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #8C8C8C;
            text-align: center;
        }
        .empty-chat i {
            font-size: 4rem;
            color: #D0D0D0;
            margin-bottom: 16px;
        }
        .empty-chat h5 {
            font-weight: 700;
            color: #4A4A4A;
        }
        .empty-chat p {
            font-size: 0.9rem;
        }
        
        /* ==========================================
           تنسيق شريط التمرير
           ========================================== */
        .chat-messages::-webkit-scrollbar {
            width: 4px;
        }
        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }
        .chat-messages::-webkit-scrollbar-thumb {
            background: #D0D0D0;
            border-radius: 4px;
        }
        
        /* ==========================================
           التوافق مع الشاشات الصغيرة
           ========================================== */
        @media (max-width: 576px) {
            .chat-header {
                padding: 6px 12px;
                height: 54px;
            }
            .chat-header .avatar {
                width: 34px;
                height: 34px;
                font-size: 1rem;
            }
            .chat-header .user-info .name {
                font-size: 0.9rem;
            }
            .chat-messages {
                top: 54px;
                bottom: 62px;
                padding: 12px 12px 16px;
            }
            .chat-input {
                padding: 4px 8px 8px;
            }
            .chat-input .btn-send {
                width: 42px;
                height: 42px;
                font-size: 1.1rem;
            }
            .chat-input .input-wrapper .form-control {
                font-size: 0.9rem;
                padding: 8px 4px;
            }
            .chat-messages .bubble .message-text {
                font-size: 0.9rem;
            }
            .chat-messages .bubble .message-image {
                max-width: 160px;
            }
            .product-selector {
                bottom: 56px;
                left: 8px;
                right: 8px;
            }
        }
        
        @media (max-width: 400px) {
            .chat-header .user-info .status {
                font-size: 0.6rem;
            }
            .chat-messages .message-wrapper {
                max-width: 92%;
            }
            .chat-input .btn-attach {
                width: 34px;
                height: 34px;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>

<!-- ==========================================
     الهيدر - شريط علوي (واتساب)
     ========================================== -->
<header class="chat-header">
    <div class="header-left">
        <a href="index.php" class="text-white text-decoration-none me-1" style="font-size: 1.3rem;">
            <i class="bi bi-arrow-right"></i>
        </a>
        <div class="avatar">
            <?php echo mb_substr($otherUser['name'], 0, 1); ?>
        </div>
        <div class="user-info">
            <div class="name"><?php echo htmlspecialchars($otherUser['name']); ?></div>
            <div class="status">
                <span class="online-dot"></span>
                متصل الآن
            </div>
        </div>
    </div>
    <div class="header-actions">
        <a href="../products/index.php" title="تصفح المنتجات"><i class="bi bi-search"></i></a>
        <a href="#" title="خيارات" id="moreOptions"><i class="bi bi-three-dots-vertical"></i></a>
    </div>
</header>

<!-- ==========================================
     منطقة الرسائل
     ========================================== -->
<div class="chat-messages" id="chatMessages">
    <?php if (empty($messages)): ?>
        <div class="empty-chat">
            <i class="bi bi-chat"></i>
            <h5>لا توجد رسائل</h5>
            <p>ابدأ المحادثة مع <?php echo htmlspecialchars($otherUser['name']); ?></p>
        </div>
    <?php else: ?>
        <?php 
        $lastDate = '';
        foreach ($messages as $msg): 
            $isSent = ($msg['sender_id'] == $userId);
            $msgDate = date('Y-m-d', strtotime($msg['created_at']));
            if ($msgDate != $lastDate) {
                $lastDate = $msgDate;
                echo '<div class="text-center my-2"><span class="badge bg-light text-muted px-3 py-1 rounded-pill" style="font-size:0.7rem;">' . date('d/m/Y', strtotime($msg['created_at'])) . '</span></div>';
            }
        ?>
            <div class="message-wrapper <?php echo $isSent ? 'sent' : 'received'; ?>">
                <div class="bubble <?php echo $isSent ? 'sent' : 'received'; ?>">
                    <?php if ($msg['product_name']): ?>
                        <div class="product-ref">
                            <i class="bi bi-box"></i>
                            <a href="../products/details.php?id=<?php echo $msg['product_id']; ?>" target="_blank">
                                <?php echo htmlspecialchars($msg['product_name']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if ($msg['message']): ?>
                        <div class="message-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                    <?php endif; ?>
                    <?php if (isset($msg['image']) && $msg['image']): ?>
                        <img src="../uploads/chat/<?php echo htmlspecialchars($msg['image']); ?>" 
                             class="message-image" 
                             alt="صورة مرفقة"
                             onclick="window.open(this.src, '_blank')">
                    <?php endif; ?>
                    <div class="message-time">
                        <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                        <?php if ($isSent): ?>
                            <span class="read-status">
                                <?php if ($msg['is_read']): ?>
                                    <i class="bi bi-check2-all" style="color: #4FC3F7;"></i>
                                <?php else: ?>
                                    <i class="bi bi-check2" style="color: #9E9E9E;"></i>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ==========================================
     منتجات للإشارة (منسدلة)
     ========================================== -->
<div class="product-selector" id="productSelector">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <small class="text-muted fw-semibold">📦 اختر منتجاً للإشارة</small>
        <button type="button" class="btn-close btn-sm" id="closeProductSelector"></button>
    </div>
    <?php if (empty($sellerProducts)): ?>
        <div class="text-muted small py-2">لا توجد منتجات متاحة لهذا البائع</div>
    <?php else: ?>
        <?php foreach ($sellerProducts as $prod): ?>
            <div class="product-item" data-product-id="<?php echo $prod['id']; ?>" data-product-name="<?php echo htmlspecialchars($prod['name']); ?>">
                <span class="product-name"><?php echo htmlspecialchars($prod['name']); ?></span>
                <span class="product-price"><?php echo formatPrice($prod['price']); ?></span>
                <span class="badge-selected ms-auto">اختيار</span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ==========================================
     شريط الإدخال (واتساب)
     ========================================== -->
<div class="chat-input" id="chatInput">
    <button class="btn-attach" id="attachBtn" title="إرفاق صورة">
        <i class="bi bi-paperclip"></i>
    </button>
    <button class="btn-attach" id="productBtn" title="إشارة إلى منتج">
        <i class="bi bi-box"></i>
    </button>
    <input type="file" id="imageInput" accept="image/*" style="display:none;">
    
    <div class="input-wrapper">
        <button class="emoji-btn" id="emojiBtn" title="إيموجي">
            <i class="bi bi-emoji-smile"></i>
        </button>
        <input type="text" class="form-control" id="messageInput" placeholder="اكتب رسالة..." autofocus>
    </div>
    
    <button class="btn-send" id="sendBtn" title="إرسال">
        <i class="bi bi-send"></i>
    </button>
</div>

<!-- ==========================================
     نموذج مخفي للإرسال
     ========================================== -->
<form id="messageForm" method="POST" action="" enctype="multipart/form-data" style="display:none;">
    <input type="hidden" name="message" id="formMessage">
    <input type="hidden" name="product_id" id="formProductId" value="0">
    <input type="file" name="image" id="formImage">
</form>

<!-- ==========================================
     Bootstrap JS
     ========================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ============================================
    // كود واتساب - JavaScript كامل
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        
        const chatMessages = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const messageForm = document.getElementById('messageForm');
        const formMessage = document.getElementById('formMessage');
        const formProductId = document.getElementById('formProductId');
        const formImage = document.getElementById('formImage');
        const attachBtn = document.getElementById('attachBtn');
        const imageInput = document.getElementById('imageInput');
        const productBtn = document.getElementById('productBtn');
        const productSelector = document.getElementById('productSelector');
        const closeProductSelector = document.getElementById('closeProductSelector');
        const emojiBtn = document.getElementById('emojiBtn');
        
        let selectedProductId = 0;
        let selectedProductName = '';
        
        // ============================================
        // تمرير إلى أسفل الرسائل
        // ============================================
        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        scrollToBottom();
        
        // ============================================
        // إرسال الرسالة
        // ============================================
        function sendMessage() {
            const message = messageInput.value.trim();
            if (!message && !imageInput.files.length) return;
            
            formMessage.value = message;
            formProductId.value = selectedProductId;
            
            if (imageInput.files.length) {
                formImage.files = imageInput.files;
            }
            
            messageForm.submit();
        }
        
        // ============================================
        // أحداث الإرسال
        // ============================================
        sendBtn.addEventListener('click', sendMessage);
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // ============================================
        // إرفاق صورة
        // ============================================
        attachBtn.addEventListener('click', function() {
            imageInput.click();
        });
        imageInput.addEventListener('change', function() {
            if (this.files.length) {
                // يمكن عرض اسم الملف أو معاينة
                const fileName = this.files[0].name;
                messageInput.placeholder = '📷 ' + fileName + ' (ستُرفع مع الإرسال)';
                sendBtn.click();
            }
        });
        
        // ============================================
        // اختيار منتج
        // ============================================
        productBtn.addEventListener('click', function() {
            productSelector.classList.toggle('active');
        });
        
        closeProductSelector.addEventListener('click', function() {
            productSelector.classList.remove('active');
        });
        
        document.querySelectorAll('.product-item').forEach(item => {
            item.addEventListener('click', function() {
                const id = this.dataset.productId;
                const name = this.dataset.productName;
                selectedProductId = id;
                selectedProductName = name;
                productSelector.classList.remove('active');
                messageInput.placeholder = '📦 ' + name + ' (سيتم الإشارة إليه)';
                // تحديد العنصر المختار
                document.querySelectorAll('.product-item').forEach(el => {
                    el.style.background = 'transparent';
                    el.querySelector('.badge-selected').textContent = 'اختيار';
                });
                this.style.background = '#E8F5E9';
                this.querySelector('.badge-selected').textContent = '✓ مختار';
                
                // إلغاء الاختيار بعد 5 ثواني من عدم النشاط (اختياري)
                setTimeout(() => {
                    if (!messageInput.value.trim()) {
                        // لا نعيد الضبط تلقائياً
                    }
                }, 30000);
            });
        });
        
        // ============================================
        // إيموجي (إظهار لوحة الإيموجي بسيطة)
        // ============================================
        emojiBtn.addEventListener('click', function() {
            const emojis = ['😊', '😂', '❤️', '👍', '👋', '✨', '🔥', '💯', '🙏', '🥳'];
            const randomEmoji = emojis[Math.floor(Math.random() * emojis.length)];
            messageInput.value += randomEmoji;
            messageInput.focus();
        });
        
        // ============================================
        // إعادة تعيين المنتج عند البدء بالكتابة
        // ============================================
        messageInput.addEventListener('focus', function() {
            productSelector.classList.remove('active');
        });
        
        // ============================================
        // تحميل الرسائل الجديدة (polling)
        // ============================================
        let lastMessageCount = <?php echo count($messages); ?>;
        
        function checkNewMessages() {
            const otherUserId = <?php echo $otherUserId; ?>;
            fetch('get_messages.php?user_id=' + otherUserId + '&last_id=' + lastMessageCount, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.messages && data.messages.length > 0) {
                    // إعادة تحميل الصفحة لعرض الرسائل الجديدة (حل بسيط)
                    // أو يمكن إضافتها ديناميكياً
                    if (data.messages.length > 0) {
                        location.reload();
                    }
                }
            })
            .catch(err => console.log('خطأ في تحديث الرسائل:', err));
        }
        
        // تحديث كل 5 ثواني
        setInterval(checkNewMessages, 5000);
        
        // ============================================
        // منع إعادة تحميل الصفحة عند الإرسال الفارغ
        // ============================================
        messageForm.addEventListener('submit', function(e) {
            const msg = formMessage.value.trim();
            if (!msg && !imageInput.files.length) {
                e.preventDefault();
                return false;
            }
        });
        
        console.log('✅ تم تحميل واجهة واتساب بنجاح');
    });
</script>

</body>
</html>