<?php
/**
 * notifications/index.php
 * عرض إشعارات المستخدم - تصميم عصري وجذاب
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
requireLogin('../auth/login.php');

$userId = getCurrentUserId();
$message = '';
$messageType = '';

// معالجة تحديث حالة الإشعار (قراءة الكل أو فردي)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = Database::getConnection();
        
        if ($action === 'mark_all_read') {
            $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            $stmt->execute([$userId]);
            $message = 'تم تحديث جميع الإشعارات كمقروءة.';
            $messageType = 'success';
        } elseif ($action === 'mark_read') {
            $notificationId = filter_var($_POST['notification_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($notificationId > 0) {
                $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
                $stmt->execute([$notificationId, $userId]);
                $message = 'تم تحديث الإشعار.';
                $messageType = 'success';
            }
        } elseif ($action === 'delete_all') {
            $stmt = $db->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1');
            $stmt->execute([$userId]);
            $message = 'تم حذف جميع الإشعارات المقروءة.';
            $messageType = 'success';
        } elseif ($action === 'delete_one') {
            $notificationId = filter_var($_POST['notification_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($notificationId > 0) {
                $stmt = $db->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
                $stmt->execute([$notificationId, $userId]);
                $message = 'تم حذف الإشعار.';
                $messageType = 'success';
            }
        }
    } catch (PDOException $e) {
        $message = 'حدث خطأ في تحديث الإشعارات.';
        $messageType = 'danger';
    }
}

// جلب الإشعارات
try {
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
    
    // عدد الإشعارات غير المقروءة
    $stmtUnread = $db->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmtUnread->execute([$userId]);
    $unreadCount = $stmtUnread->fetch()['unread'];
    
    $totalCount = count($notifications);
    
} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل الإشعارات.';
    $notifications = [];
    $unreadCount = 0;
    $totalCount = 0;
}

// تضمين الهيدر الجديد
require_once '../includes/header.php';
?>

<style>
/* ==========================================
   تصميم صفحة الإشعارات - عصري وجذاب
   ========================================== */

/* رأس الصفحة */
.notifications-header {
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 50%, #6D28D9 100%);
    padding: 40px 0 50px;
    border-radius: 0 0 40px 40px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}
.notifications-header::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.notifications-header::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
}
.notifications-header .page-title {
    color: #fff;
    font-weight: 900;
    font-size: 2.2rem;
    margin-bottom: 6px;
}
.notifications-header .page-title i {
    color: #FCD34D;
}
.notifications-header .page-subtitle {
    color: rgba(255,255,255,0.8);
    font-size: 1.05rem;
}
.notifications-header .notif-stats {
    display: flex;
    gap: 30px;
    margin-top: 15px;
}
.notifications-header .notif-stats .stat-item {
    color: rgba(255,255,255,0.9);
}
.notifications-header .notif-stats .stat-item .stat-number {
    font-size: 1.6rem;
    font-weight: 800;
    display: block;
}
.notifications-header .notif-stats .stat-item .stat-label {
    font-size: 0.85rem;
    opacity: 0.8;
}

/* أزرار الإجراءات */
.notif-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}
.notif-actions .btn-action {
    border-radius: 30px;
    padding: 8px 20px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: 0.3s;
    border: none;
}
.notif-actions .btn-action i {
    margin-left: 6px;
}
.btn-action-mark-read {
    background: #EEF2FF;
    color: #4F46E5;
}
.btn-action-mark-read:hover {
    background: #4F46E5;
    color: #fff;
}
.btn-action-delete-read {
    background: #FEE2E2;
    color: #DC2626;
}
.btn-action-delete-read:hover {
    background: #DC2626;
    color: #fff;
}

/* بطاقات الإشعارات */
.notification-card {
    background: #fff;
    border-radius: 16px;
    padding: 18px 22px;
    margin-bottom: 14px;
    border: 1px solid #F1F5F9;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.notification-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.04);
    border-color: #E0E7FF;
    transform: translateY(-2px);
}
.notification-card.unread {
    border-left: 5px solid #4F46E5;
    background: #F8FAFC;
}
.notification-card.read {
    border-left: 5px solid #CBD5E1;
    background: #fff;
}
.notification-card .notif-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.notification-card .notif-icon.primary {
    background: #EEF2FF;
    color: #4F46E5;
}
.notification-card .notif-icon.success {
    background: #D1FAE5;
    color: #10B981;
}
.notification-card .notif-icon.warning {
    background: #FEF3C7;
    color: #F59E0B;
}
.notification-card .notif-icon.danger {
    background: #FEE2E2;
    color: #EF4444;
}
.notification-card .notif-content {
    flex: 1;
    min-width: 0;
}
.notification-card .notif-content .notif-message {
    font-size: 0.95rem;
    color: #1E293B;
    margin-bottom: 4px;
    font-weight: 500;
}
.notification-card .notif-content .notif-message i {
    margin-left: 6px;
}
.notification-card .notif-content .notif-time {
    font-size: 0.75rem;
    color: #94A3B8;
}
.notification-card .notif-actions-btn {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}
.notification-card .notif-actions-btn .btn-sm {
    border-radius: 30px;
    padding: 4px 14px;
    font-size: 0.75rem;
    font-weight: 600;
}
.notification-card .notif-actions-btn .btn-mark-read {
    background: #EEF2FF;
    color: #4F46E5;
    border: none;
    transition: 0.3s;
}
.notification-card .notif-actions-btn .btn-mark-read:hover {
    background: #4F46E5;
    color: #fff;
}
.notification-card .notif-actions-btn .btn-delete-one {
    background: #FEE2E2;
    color: #DC2626;
    border: none;
    transition: 0.3s;
}
.notification-card .notif-actions-btn .btn-delete-one:hover {
    background: #DC2626;
    color: #fff;
}
.notification-card .notif-actions-btn .badge-read {
    background: #E2E8F0;
    color: #64748B;
    padding: 4px 14px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
.notification-card .notif-actions-btn .badge-read i {
    margin-left: 4px;
}

/* حالة عدم وجود إشعارات */
.empty-state {
    text-align: center;
    padding: 80px 20px;
}
.empty-state i {
    font-size: 5rem;
    color: #CBD5E1;
    display: block;
    margin-bottom: 16px;
}
.empty-state h4 {
    color: #1E293B;
    font-weight: 700;
    font-size: 1.5rem;
}
.empty-state p {
    color: #94A3B8;
    font-size: 1rem;
    max-width: 400px;
    margin: 0 auto 20px;
}
.empty-state .btn-explore {
    border-radius: 30px;
    padding: 12px 40px;
    font-weight: 700;
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border: none;
    color: #fff;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(79,70,229,0.2);
}
.empty-state .btn-explore:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(79,70,229,0.35);
}

/* رسائل التنبيه */
.alert-custom {
    border: none;
    border-radius: 16px;
    padding: 16px 24px;
    margin-bottom: 20px;
}
.alert-custom-success {
    background: #D1FAE5;
    color: #065F46;
}
.alert-custom-error {
    background: #FEE2E2;
    color: #991B1B;
}

/* التوافق مع الشاشات الصغيرة */
@media (max-width: 768px) {
    .notifications-header {
        padding: 25px 0 30px;
        border-radius: 0 0 25px 25px;
    }
    .notifications-header .page-title {
        font-size: 1.6rem;
    }
    .notifications-header .notif-stats {
        gap: 15px;
        flex-wrap: wrap;
    }
    .notifications-header .notif-stats .stat-item .stat-number {
        font-size: 1.2rem;
    }
    .notification-card {
        padding: 14px 16px;
        flex-wrap: wrap;
    }
    .notification-card .notif-actions-btn {
        margin-top: 10px;
        width: 100%;
        justify-content: flex-start;
    }
}
@media (max-width: 576px) {
    .notification-card {
        flex-direction: column;
        align-items: flex-start !important;
    }
    .notification-card .notif-icon {
        margin-bottom: 10px;
    }
    .notif-actions {
        flex-direction: column;
    }
    .notif-actions .btn-action {
        width: 100%;
        text-align: center;
    }
}
</style>

<!-- ==========================================
     رأس الصفحة
     ========================================== -->
<section class="notifications-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="page-title">
                    <i class="bi bi-bell-fill"></i> الإشعارات
                </h1>
                <p class="page-subtitle">تابع آخر التحديثات والرسائل</p>
                <div class="notif-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $totalCount; ?></span>
                        <span class="stat-label">إجمالي الإشعارات</span>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <div class="stat-item">
                            <span class="stat-number" style="color: #FCD34D;"><?php echo $unreadCount; ?></span>
                            <span class="stat-label">غير مقروءة</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <i class="bi bi-bell" style="font-size: 6rem; opacity: 0.2; color: #fff;"></i>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     الإشعارات
     ========================================== -->
<div class="container pb-4">
    
    <!-- عرض الرسائل -->
    <?php if ($message): ?>
        <div class="alert alert-custom alert-custom-<?php echo $messageType == 'success' ? 'success' : 'error'; ?> alert-dismissible fade show rounded-4">
            <i class="bi bi-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-custom alert-custom-error rounded-4">
            <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
        <!-- حالة عدم وجود إشعارات -->
        <div class="empty-state">
            <i class="bi bi-bell-slash"></i>
            <h4>لا توجد إشعارات</h4>
            <p>ليس لديك أي إشعارات حالياً. سنخبرك عندما يحدث شيء جديد.</p>
            <a href="../index.php" class="btn btn-explore">
                <i class="bi bi-house"></i> العودة للرئيسية
            </a>
        </div>
    <?php else: ?>
        
        <!-- أزرار الإجراءات -->
        <div class="notif-actions">
            <?php if ($unreadCount > 0): ?>
                <form method="POST" action="" class="d-inline">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn-action btn-action-mark-read">
                        <i class="bi bi-check2-all"></i> تحديد الكل كمقروء
                    </button>
                </form>
            <?php endif; ?>
            <?php 
            $hasRead = false;
            foreach ($notifications as $n) { if ($n['is_read']) { $hasRead = true; break; } }
            if ($hasRead): ?>
                <form method="POST" action="" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف جميع الإشعارات المقروءة؟')">
                    <input type="hidden" name="action" value="delete_all">
                    <button type="submit" class="btn-action btn-action-delete-read">
                        <i class="bi bi-trash"></i> حذف المقروءة
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- قائمة الإشعارات -->
        <?php foreach ($notifications as $notif): 
            $isRead = $notif['is_read'];
            $iconClass = $isRead ? 'read' : 'unread';
            // تحديد أيقونة حسب محتوى الرسالة
            $icon = 'bi-info-circle';
            $iconBg = 'primary';
            if (strpos($notif['message'], 'رسالة') !== false) {
                $icon = 'bi-chat-dots';
                $iconBg = 'primary';
            } elseif (strpos($notif['message'], 'تم حل البلاغ') !== false || strpos($notif['message'], 'تم رفض البلاغ') !== false) {
                $icon = 'bi-flag';
                $iconBg = 'warning';
            } elseif (strpos($notif['message'], 'تم البيع') !== false) {
                $icon = 'bi-cart-check';
                $iconBg = 'success';
            } elseif (strpos($notif['message'], 'حذف') !== false) {
                $icon = 'bi-trash';
                $iconBg = 'danger';
            }
        ?>
            <div class="notification-card <?php echo $iconClass; ?> d-flex align-items-center gap-3">
                <div class="notif-icon <?php echo $iconBg; ?>">
                    <i class="bi <?php echo $icon; ?>"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-message">
                        <?php echo nl2br(htmlspecialchars($notif['message'])); ?>
                    </div>
                    <div class="notif-time">
                        <i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                    </div>
                </div>
                <div class="notif-actions-btn">
                    <?php if (!$isRead): ?>
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-mark-read">
                                <i class="bi bi-check2"></i> قراءة
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="badge-read">
                            <i class="bi bi-check-circle"></i> مقروء
                        </span>
                    <?php endif; ?>
                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الإشعار؟')">
                        <input type="hidden" name="action" value="delete_one">
                        <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-delete-one">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        
    <?php endif; ?>
</div>

<?php
// تضمين الفوتر الجديد
require_once '../includes/footer.php';
?>
</body>
</html>