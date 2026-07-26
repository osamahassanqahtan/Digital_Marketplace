<?php
/**
 * seller/index.php
 * لوحة تحكم البائع - تصميم مستوحى من أكاديمية الحلول
 * تعرض إحصائيات، منتجات، ومحادثات البائع
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
$message = '';
$messageType = '';

// معالجة تغيير حالة المنتج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
    $newStatus = $_POST['status'] ?? '';
    $validStatuses = ['available', 'sold', 'pending'];
    
    if ($productId > 0 && in_array($newStatus, $validStatuses)) {
        try {
            $db = Database::getConnection();
            
            $checkSql = "SELECT seller_id FROM products WHERE id = ?";
            $stmt = $db->prepare($checkSql);
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if ($product && ($userRole === 'admin' || $product['seller_id'] == $userId)) {
                $updateSql = "UPDATE products SET status = ? WHERE id = ?";
                $stmt = $db->prepare($updateSql);
                $stmt->execute([$newStatus, $productId]);
                $message = 'تم تحديث حالة المنتج بنجاح.';
                $messageType = 'success';
            } else {
                $message = 'لا تملك صلاحية تعديل هذا المنتج.';
                $messageType = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'حدث خطأ في تحديث الحالة.';
            $messageType = 'danger';
        }
    }
}

// جلب منتجات البائع
try {
    $db = Database::getConnection();
    
    if ($userRole === 'admin') {
        $sql = "SELECT p.*, u.name as seller_name, 
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM products p
                JOIN users u ON p.seller_id = u.id
                ORDER BY p.created_at DESC";
        $stmt = $db->query($sql);
    } else {
        $sql = "SELECT p.*, 
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM products p
                WHERE p.seller_id = ?
                ORDER BY p.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
    }
    $products = $stmt->fetchAll();

    $totalProducts = count($products);
    $availableCount = 0;
    $soldCount = 0;
    $pendingCount = 0;
    foreach ($products as $p) {
        if ($p['status'] == 'available') $availableCount++;
        elseif ($p['status'] == 'sold') $soldCount++;
        elseif ($p['status'] == 'pending') $pendingCount++;
    }

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل المنتجات.';
    $products = [];
    $totalProducts = 0;
    $availableCount = 0;
    $soldCount = 0;
    $pendingCount = 0;
}

// جلب المحادثات الخاصة بالبائع
$conversations = [];
$unreadCount = 0;
try {
    if ($userRole === 'admin') {
        $productIdsSql = "SELECT id FROM products";
        $productIdsStmt = $db->query($productIdsSql);
    } else {
        $productIdsSql = "SELECT id FROM products WHERE seller_id = ?";
        $productIdsStmt = $db->prepare($productIdsSql);
        $productIdsStmt->execute([$userId]);
    }
    $productIds = $productIdsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        
        $sql = "SELECT c.*, 
                u_sender.id as sender_id, u_sender.name as sender_name,
                u_receiver.id as receiver_id, u_receiver.name as receiver_name,
                p.name as product_name, p.id as product_id
                FROM chats c
                JOIN users u_sender ON c.sender_id = u_sender.id
                JOIN users u_receiver ON c.receiver_id = u_receiver.id
                LEFT JOIN products p ON c.product_id = p.id
                WHERE c.product_id IN ($placeholders)
                ORDER BY c.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($productIds);
        $allMessages = $stmt->fetchAll();
        
        $grouped = [];
        foreach ($allMessages as $msg) {
            $otherUserId = ($msg['sender_id'] == $userId) ? $msg['receiver_id'] : $msg['sender_id'];
            $otherUserName = ($msg['sender_id'] == $userId) ? $msg['receiver_name'] : $msg['sender_name'];
            
            if ($otherUserId == $userId) continue;
            
            if (!isset($grouped[$otherUserId])) {
                $grouped[$otherUserId] = [
                    'user_id' => $otherUserId,
                    'user_name' => $otherUserName,
                    'last_message' => $msg['message'],
                    'last_message_time' => $msg['created_at'],
                    'unread_count' => 0,
                    'product_name' => $msg['product_name'],
                    'product_id' => $msg['product_id']
                ];
            }
            
            if ($msg['sender_id'] != $userId && $msg['is_read'] == 0) {
                $grouped[$otherUserId]['unread_count']++;
                $unreadCount++;
            }
            
            if (strtotime($msg['created_at']) > strtotime($grouped[$otherUserId]['last_message_time'])) {
                $grouped[$otherUserId]['last_message'] = $msg['message'];
                $grouped[$otherUserId]['last_message_time'] = $msg['created_at'];
            }
        }
        
        usort($grouped, function($a, $b) {
            return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
        });
        
        $conversations = $grouped;
    }
} catch (PDOException $e) {
    // تجاهل
}

// جلب عدد الإشعارات غير المقروءة
$unreadNotifications = 0;
if (isLoggedIn()) {
    try {
        $stmt = $db->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $unreadNotifications = $stmt->fetch()['unread'];
    } catch (PDOException $e) {}
}
$totalUnread = $unreadCount + $unreadNotifications;

// تعريف ألوان الحالات
$statusColors = [
    'available' => 'success',
    'sold' => 'danger',
    'pending' => 'warning'
];
$statusLabels = [
    'available' => 'متاح',
    'sold' => 'تم البيع',
    'pending' => 'قيد الانتظار'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة البائع - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ==========================================
           تصميم لوحة البائع - أكاديمية الحلول
           ========================================== */
        
        /* تنسيق البطاقات الإحصائية */
        .stat-card {
            border: none;
            border-radius: 20px;
            padding: 5px 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
        }
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        .stat-card .stat-icon {
            font-size: 2.8rem;
            opacity: 0.25;
            position: absolute;
            left: 15px;
            bottom: 10px;
        }
        .stat-card .stat-number {
            font-size: 2.2rem;
            font-weight: 900;
            margin-bottom: 2px;
        }
        .stat-card .stat-label {
            font-size: 0.85rem;
            opacity: 0.8;
            font-weight: 500;
        }
        
        /* بطاقات بألوان متدرجة */
        .stat-card-purple {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: #fff;
        }
        .stat-card-green {
            background: linear-gradient(135deg, #059669 0%, #10B981 100%);
            color: #fff;
        }
        .stat-card-orange {
            background: linear-gradient(135deg, #D97706 0%, #F59E0B 100%);
            color: #fff;
        }
        .stat-card-red {
            background: linear-gradient(135deg, #DC2626 0%, #EF4444 100%);
            color: #fff;
        }
        
        /* تنسيق الجدول */
        .table-container {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }
        .table-container .table-header {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            padding: 18px 24px;
            color: #fff;
        }
        .table-container .table-header h5 {
            font-weight: 800;
            margin: 0;
        }
        .table-container .table-header .btn-add {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            padding: 6px 18px;
            font-weight: 600;
            transition: 0.3s;
        }
        .table-container .table-header .btn-add:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        .table-container table th {
            background: #F8FAFC;
            border-bottom: 2px solid #E2E8F0;
            font-weight: 700;
            font-size: 0.85rem;
            color: #475569;
            padding: 12px 16px;
        }
        .table-container table td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 0.95rem;
        }
        .table-container table tbody tr {
            transition: 0.2s;
        }
        .table-container table tbody tr:hover {
            background: #F8FAFC;
        }
        
        /* تنسيق شارة الحالة */
        .status-badge {
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        
        /* تنسيق المحادثات الجانبية */
        .chat-sidebar {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid #F1F5F9;
        }
        .chat-sidebar .chat-header {
            background: #F8FAFC;
            padding: 16px 20px;
            border-bottom: 1px solid #E2E8F0;
        }
        .chat-sidebar .chat-header h5 {
            font-weight: 700;
            margin: 0;
        }
        .chat-sidebar .chat-header .badge-new {
            background: #EF4444;
            color: #fff;
            border-radius: 30px;
            padding: 2px 12px;
            font-size: 0.7rem;
        }
        
        .chat-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #F1F5F9;
            transition: 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        .chat-item:hover {
            background: #F8FAFC;
        }
        .chat-item .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #E2E8F0, #CBD5E1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #64748B;
            flex-shrink: 0;
            margin-left: 12px;
            font-weight: 700;
        }
        .chat-item .chat-info {
            flex: 1;
            min-width: 0;
        }
        .chat-item .chat-info .chat-name {
            font-weight: 600;
            color: #1E293B;
            font-size: 0.95rem;
        }
        .chat-item .chat-info .chat-product {
            font-size: 0.7rem;
            color: #64748B;
            background: #F1F5F9;
            padding: 0 8px;
            border-radius: 10px;
            display: inline-block;
        }
        .chat-item .chat-info .chat-last-msg {
            font-size: 0.8rem;
            color: #94A3B8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chat-item .chat-meta {
            text-align: left;
            flex-shrink: 0;
            margin-right: 8px;
        }
        .chat-item .chat-meta .chat-time {
            font-size: 0.65rem;
            color: #94A3B8;
        }
        .chat-item .chat-meta .badge-unread {
            background: #4F46E5;
            color: #fff;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 0.65rem;
            font-weight: 700;
            display: inline-block;
            margin-top: 4px;
        }
        .chat-sidebar .chat-footer {
            padding: 12px 16px;
            text-align: center;
            border-top: 1px solid #F1F5F9;
        }
        .chat-sidebar .chat-footer a {
            font-weight: 600;
            color: #4F46E5;
        }
        .chat-sidebar .chat-footer a:hover {
            color: #7C3AED;
        }
        
        /* رسالة عدم وجود محادثات */
        .no-conversations {
            padding: 40px 20px;
            text-align: center;
            color: #94A3B8;
        }
        .no-conversations i {
            font-size: 3rem;
            display: block;
            margin-bottom: 12px;
            color: #CBD5E1;
        }
        .no-conversations p {
            font-size: 0.95rem;
            margin-bottom: 4px;
        }
        .no-conversations small {
            font-size: 0.8rem;
        }
        
        /* زر الإجراءات في الجدول */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
            font-size: 0.85rem;
        }
        .action-btn-edit {
            background: #FEF3C7;
            color: #D97706;
        }
        .action-btn-edit:hover {
            background: #F59E0B;
            color: #fff;
        }
        .action-btn-delete {
            background: #FEE2E2;
            color: #DC2626;
        }
        .action-btn-delete:hover {
            background: #EF4444;
            color: #fff;
        }
        .action-btn-status {
            background: #E0E7FF;
            color: #4F46E5;
        }
        .action-btn-status:hover {
            background: #4F46E5;
            color: #fff;
        }
        
        /* تنسيق المودال */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
        .modal-header {
            border-bottom: none;
            padding: 20px 24px 0;
        }
        .modal-body {
            padding: 16px 24px;
        }
        .modal-footer {
            border-top: none;
            padding: 0 24px 20px;
        }
        
        /* تنسيق ترحيب المستخدم */
        .user-greeting {
            background: linear-gradient(135deg, #EEF2FF, #E0E7FF);
            border-radius: 16px;
            padding: 12px 20px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }
        .user-greeting .greeting-name {
            font-weight: 700;
            color: #1E293B;
        }
        .user-greeting .greeting-role {
            background: #4F46E5;
            color: #fff;
            padding: 2px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* تحسينات للأجهزة الصغيرة */
        @media (max-width: 768px) {
            .stat-card .stat-number {
                font-size: 1.6rem;
            }
            .stat-card .stat-icon {
                font-size: 2rem;
            }
            .table-container .table-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            .chat-sidebar {
                margin-top: 20px;
            }
            .user-greeting {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

<!-- ==========================================
     شريط التنقل (مستخدم من الهيدر)
     ========================================== -->
<?php require_once '../includes/header.php'; ?>

<!-- ==========================================
     محتوى لوحة التحكم
     ========================================== -->
<div class="container py-4">
    
    <!-- رأس الصفحة مع ترحيب -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0" style="color: #1E293B;">
                <i class="bi bi-speedometer2" style="color: #4F46E5;"></i> لوحة التحكم
            </h2>
            <p class="text-muted mb-0">مرحباً بك في لوحة التحكم الخاصة بك، يمكنك إدارة منتجاتك ومتابعة محادثاتك هنا.</p>
        </div>
        <div class="user-greeting">
            <i class="bi bi-person-circle" style="font-size: 1.5rem; color: #4F46E5;"></i>
            <span class="greeting-name"><?php echo htmlspecialchars(getCurrentUserName()); ?></span>
            <span class="greeting-role"><?php echo $userRole == 'admin' ? 'مدير' : 'بائع'; ?></span>
        </div>
    </div>

    <!-- عرض الرسائل -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show rounded-4 mb-3">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger rounded-4 mb-3"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php $flash = getFlashMessage('success'); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show rounded-4 mb-3">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ==========================================
         بطاقات الإحصائيات
         ========================================== -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <div class="card stat-card stat-card-purple">
                <div class="card-body">
                    <i class="bi bi-box-seam stat-icon"></i>
                    <p class="stat-number"><?php echo $totalProducts; ?></p>
                    <p class="stat-label">إجمالي المنتجات</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card stat-card-green">
                <div class="card-body">
                    <i class="bi bi-check-circle stat-icon"></i>
                    <p class="stat-number"><?php echo $availableCount; ?></p>
                    <p class="stat-label">متاحة للبيع</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card stat-card-red">
                <div class="card-body">
                    <i class="bi bi-cart-check stat-icon"></i>
                    <p class="stat-number"><?php echo $soldCount; ?></p>
                    <p class="stat-label">تم البيع</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card stat-card-orange">
                <div class="card-body">
                    <i class="bi bi-clock-history stat-icon"></i>
                    <p class="stat-number"><?php echo $pendingCount; ?></p>
                    <p class="stat-label">قيد الانتظار</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         جدول المنتجات + المحادثات
         ========================================== -->
    <div class="row g-4">
        
        <!-- العمود الأيسر: جدول المنتجات -->
        <div class="col-lg-8">
            <div class="table-container">
                <div class="table-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-list-ul me-2"></i> قائمة المنتجات</h5>
                    <a href="add-product.php" class="btn-add">
                        <i class="bi bi-plus-circle"></i> إضافة جديد
                    </a>
                </div>
                <div class="table-responsive">
                    <?php if (empty($products)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #CBD5E1;"></i>
                            <p class="mt-3 fw-semibold">لا توجد منتجات مضافة بعد</p>
                            <p class="text-muted small">ابدأ بإضافة أول منتج لك الآن</p>
                            <a href="add-product.php" class="btn btn-primary btn-sm rounded-pill">أضف منتجاً</a>
                        </div>
                    <?php else: ?>
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الصورة</th>
                                    <th>الاسم</th>
                                    <th>السعر</th>
                                    <th>الحالة</th>
                                    <?php if ($userRole === 'admin'): ?>
                                        <th>البائع</th>
                                    <?php endif; ?>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $index => $product): ?>
                                    <tr>
                                        <td class="fw-semibold text-muted"><?php echo $index + 1; ?></td>
                                        <td>
                                            <?php if ($product['primary_image']): ?>
                                                <img src="../uploads/products/<?php echo htmlspecialchars($product['primary_image']); ?>" 
                                                     style="width: 44px; height: 44px; object-fit: cover; border-radius: 12px;">
                                            <?php else: ?>
                                                <div style="width:44px;height:44px;border-radius:12px;background:#F1F5F9;display:flex;align-items:center;justify-content:center;">
                                                    <i class="bi bi-image" style="color:#94A3B8;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="../products/details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none fw-semibold" style="color:#1E293B;">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </td>
                                        <td class="fw-bold" style="color:#4F46E5;"><?php echo formatPrice($product['price']); ?></td>
                                        <td>
                                            <span class="status-badge bg-<?php echo $statusColors[$product['status']] ?? 'secondary'; ?> text-white">
                                                <?php echo $statusLabels[$product['status']] ?? $product['status']; ?>
                                            </span>
                                        </td>
                                        <?php if ($userRole === 'admin'): ?>
                                            <td><?php echo htmlspecialchars($product['seller_name'] ?? 'غير معروف'); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="action-btn action-btn-edit" title="تعديل">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="delete-product.php?id=<?php echo $product['id']; ?>" 
                                                   class="action-btn action-btn-delete delete-confirm" 
                                                   data-confirm-message="هل أنت متأكد من حذف المنتج '<?php echo htmlspecialchars($product['name']); ?>'؟"
                                                   title="حذف">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                                <button type="button" class="action-btn action-btn-status" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#statusModal<?php echo $product['id']; ?>" 
                                                        title="تغيير الحالة">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- Modal تغيير الحالة -->
                                            <div class="modal fade" id="statusModal<?php echo $product['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-sm modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h6 class="modal-title fw-bold">تغيير حالة المنتج</h6>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                                <input type="hidden" name="action" value="change_status">
                                                                <select name="status" class="form-select">
                                                                    <option value="available" <?php echo $product['status'] == 'available' ? 'selected' : ''; ?>>متاح</option>
                                                                    <option value="sold" <?php echo $product['status'] == 'sold' ? 'selected' : ''; ?>>تم البيع</option>
                                                                    <option value="pending" <?php echo $product['status'] == 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                                                                </select>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">إلغاء</button>
                                                                <button type="submit" class="btn btn-primary btn-sm rounded-pill">تحديث</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- العمود الأيمن: المحادثات -->
        <div class="col-lg-4">
            <div class="chat-sidebar">
                <div class="chat-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-chat-dots" style="color:#4F46E5;"></i> المحادثات</h5>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge-new"><?php echo $unreadCount; ?> جديدة</span>
                    <?php endif; ?>
                </div>
                <div style="max-height: 440px; overflow-y: auto;">
                    <?php if (empty($conversations)): ?>
                        <div class="no-conversations">
                            <i class="bi bi-chat"></i>
                            <p>لا توجد محادثات حالياً</p>
                            <small>عندما يرسل المشترون رسائل، ستظهر هنا</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <a href="../chat/conversation.php?user_id=<?php echo $conv['user_id']; ?>" class="chat-item">
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
                                        <?php echo htmlspecialchars(substr($conv['last_message'], 0, 35)); ?>
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
                <div class="chat-footer">
                    <a href="../chat/index.php">عرض جميع المحادثات <i class="bi bi-arrow-left"></i></a>
                </div>
            </div>
        </div>
        
    </div><!-- /.row -->
    
</div><!-- /.container -->

<!-- ==========================================
     التذييل
     ========================================== -->
<?php require_once '../includes/footer.php'; ?>

<!-- ==========================================
     كود JavaScript إضافي
     ========================================== -->


</body>
</html>