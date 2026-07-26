
<?php
/**
 * chat/index.php
 * عرض قائمة المحادثات الخاصة بالمستخدم (شبيه بـ WhatsApp)
 * تعرض جميع المستخدمين الذين تواصلت معهم، مع آخر رسالة ووقتها
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
requireLogin('../auth/login.php');

$userId = getCurrentUserId();
$error = '';

try {
    $db = Database::getConnection();

    // جلب جميع المحادثات التي شارك فيها المستخدم
    $sql = "SELECT 
                CASE 
                    WHEN c.sender_id = ? THEN c.receiver_id 
                    ELSE c.sender_id 
                END as other_user_id,
                u.name as other_user_name,
                u.email as other_user_email,
                u.phone as other_user_phone,
                u.location as other_user_location,
                c.message as last_message,
                c.created_at as last_message_time,
                c.is_read,
                c.sender_id as last_sender_id,
                c.id as last_chat_id,
                p.name as product_name,
                p.id as product_id
            FROM chats c
            JOIN users u ON (CASE WHEN c.sender_id = ? THEN c.receiver_id ELSE c.sender_id END) = u.id
            LEFT JOIN products p ON c.product_id = p.id
            WHERE c.sender_id = ? OR c.receiver_id = ?
            ORDER BY c.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $allChats = $stmt->fetchAll();

    // تجميع المحادثات حسب المستخدم الآخر (اخر رسالة فقط)
    $conversations = [];
    foreach ($allChats as $chat) {
        $otherId = $chat['other_user_id'];
        if (!isset($conversations[$otherId])) {
            $conversations[$otherId] = [
                'user_id' => $otherId,
                'user_name' => $chat['other_user_name'],
                'user_email' => $chat['other_user_email'],
                'user_phone' => $chat['other_user_phone'],
                'user_location' => $chat['other_user_location'],
                'last_message' => $chat['last_message'],
                'last_message_time' => $chat['last_message_time'],
                'is_read' => $chat['is_read'],
                'last_sender_id' => $chat['last_sender_id'],
                'product_name' => $chat['product_name'],
                'product_id' => $chat['product_id']
            ];
        }
    }

    // ترتيب المحادثات حسب آخر رسالة (الأحدث أولاً)
    usort($conversations, function($a, $b) {
        return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
    });

    // حساب عدد الرسائل غير المقروءة لكل محادثة
    foreach ($conversations as &$conv) {
        $stmt = $db->prepare('SELECT COUNT(*) as unread FROM chats WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
        $stmt->execute([$conv['user_id'], $userId]);
        $conv['unread_count'] = (int)$stmt->fetch()['unread'];
    }

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل المحادثات.';
    $conversations = [];
}

// تضمين الهيدر الجديد
require_once '../includes/header.php';
?>
<style>
/* ==========================================
   تصميم صفحة المحادثات - شبيه بـ WhatsApp
   ========================================== */
.chat-list-container {
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    border: 1px solid #F1F5F9;
}

.chat-list-header {
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    padding: 20px 24px;
    color: #fff;
}
.chat-list-header h4 {
    font-weight: 800;
    margin: 0;
}
.chat-list-header i {
    font-size: 1.5rem;
}

.chat-item {
    display: flex;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid #F1F5F9;
    transition: background 0.2s;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}
.chat-item:hover {
    background: #F8FAFC;
}
.chat-item:last-child {
    border-bottom: none;
}

.chat-item .avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #E2E8F0, #CBD5E1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    font-weight: 700;
    color: #475569;
    flex-shrink: 0;
    margin-left: 16px;
}
.chat-item .avatar.online {
    border: 3px solid #10B981;
}

.chat-item .chat-info {
    flex: 1;
    min-width: 0;
}
.chat-item .chat-info .chat-name {
    font-weight: 700;
    color: #1E293B;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.chat-item .chat-info .chat-product {
    font-size: 0.7rem;
    background: #F1F5F9;
    padding: 0 10px;
    border-radius: 30px;
    color: #64748B;
}
.chat-item .chat-info .chat-last-msg {
    font-size: 0.9rem;
    color: #64748B;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}
.chat-item .chat-info .chat-last-msg .sent-icon {
    color: #94A3B8;
    margin-left: 4px;
}

.chat-item .chat-meta {
    text-align: left;
    flex-shrink: 0;
    margin-right: 12px;
}
.chat-item .chat-meta .chat-time {
    font-size: 0.7rem;
    color: #94A3B8;
}
.chat-item .chat-meta .badge-unread {
    background: #4F46E5;
    color: #fff;
    border-radius: 50%;
    padding: 2px 10px;
    font-size: 0.7rem;
    font-weight: 700;
    display: inline-block;
    margin-top: 6px;
    min-width: 24px;
    text-align: center;
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: #94A3B8;
}
.empty-state i {
    font-size: 4rem;
    color: #CBD5E1;
    display: block;
    margin-bottom: 16px;
}
.empty-state h5 {
    color: #475569;
    font-weight: 700;
}
.empty-state p {
    font-size: 0.95rem;
}

/* تحسين للشاشات الصغيرة */
@media (max-width: 576px) {
    .chat-item {
        padding: 12px 16px;
    }
    .chat-item .avatar {
        width: 44px;
        height: 44px;
        font-size: 1.1rem;
        margin-left: 12px;
    }
    .chat-item .chat-info .chat-name {
        font-size: 0.95rem;
    }
    .chat-item .chat-info .chat-last-msg {
        font-size: 0.8rem;
    }
    .chat-list-header {
        padding: 16px;
    }
}
</style>

<div class="container py-4">
    <div class="chat-list-container">
        <!-- رأس الصفحة -->
        <div class="chat-list-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-chat-dots"></i>
                <h4>المحادثات</h4>
                <?php 
                $totalUnread = array_sum(array_column($conversations, 'unread_count'));
                if ($totalUnread > 0): ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo $totalUnread; ?> جديدة</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- قائمة المحادثات -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger m-3"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($conversations)): ?>
            <div class="empty-state">
                <i class="bi bi-chat"></i>
                <h5>لا توجد محادثات حالياً</h5>
                <p>عندما ترسل أو تستقبل رسائل، ستظهر هنا.</p>
                <a href="../products/index.php" class="btn btn-primary btn-sm rounded-pill mt-2">تصفح المنتجات</a>
            </div>
        <?php else: ?>
            <?php foreach ($conversations as $conv): ?>
                <a href="conversation.php?user_id=<?php echo $conv['user_id']; ?>" class="chat-item">
                    <div class="avatar">
                        <?php echo mb_substr($conv['user_name'], 0, 1); ?>
                    </div>
                    <div class="chat-info">
                        <div class="chat-name">
                            <?php echo htmlspecialchars($conv['user_name']); ?>
                            <?php if ($conv['product_name']): ?>
                                <span class="chat-product"><?php echo htmlspecialchars($conv['product_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="chat-last-msg">
                            <?php if ($conv['last_sender_id'] == $userId): ?>
                                <span class="sent-icon"><i class="bi bi-check-all"></i></span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)); ?>
                        </div>
                    </div>
                    <div class="chat-meta">
                        <div class="chat-time"><?php echo date('H:i', strtotime($conv['last_message_time'])); ?></div>
                        <?php if ($conv['unread_count'] > 0): ?>
                            <span class="badge-unread"><?php echo $conv['unread_count']; ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// تضمين الفوتر الجديد
require_once '../includes/footer.php';
?>



</body>
</html>