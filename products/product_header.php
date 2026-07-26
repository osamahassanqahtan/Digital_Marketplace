<?php
/**
 * includes/header.php
 * شريط التنقل العلوي المشترك - تصميم مستوحى من أكاديمية الحلول
 * يجب تضمينه في بداية كل صفحة بعد استدعاء session.php
 */

// استخدام الجلسة إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين دوال المساعدة إذا لم تكن مضمنة
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/session.php';
}
if (!function_exists('getCurrentUserName')) {
    require_once __DIR__ . '/session.php';
}
if (!function_exists('getCurrentUserRole')) {
    require_once __DIR__ . '/session.php';
}
if (!function_exists('hasRole')) {
    require_once __DIR__ . '/session.php';
}

// جلب عدد الإشعارات غير المقروءة (إن كان المستخدم مسجلاً)
$unreadCount = 0;
$unreadNotifications = 0;
if (isLoggedIn()) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = Database::getConnection();
        
        // إشعارات المحادثات (الرسائل غير المقروءة)
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare('SELECT COUNT(*) as unread FROM chats WHERE receiver_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $unreadCount = (int)$stmt->fetch()['unread'];
        
        // إشعارات النظام العامة
        $stmt = $db->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $unreadNotifications = (int)$stmt->fetch()['unread'];
        
    } catch (Exception $e) {
        // تجاهل الخطأ
    }
}
$totalUnread = $unreadCount + $unreadNotifications;

// تحديد الصفحة الحالية لتسليط الضوء على الروابط
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// تحديد المسار الأساسي للروابط
$basePath = (strpos($_SERVER['PHP_SELF'], 'includes/') === false) ? '' : '../';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="منصة السوق - بيع وشراء المنتجات الجديدة والمستعملة">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- الخطوط -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <!-- التنسيقات العامة -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    
    <title><?php echo $pageTitle ?? 'منصة السوق'; ?></title>
</head>
<body>

<!-- ==========================================
     شريط التنقل - تصميم أكاديمية الحلول
     ========================================== -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
        <!-- الشعار -->
        <a class="navbar-brand" href="<?php echo $basePath; ?>index.php">
            <i class="bi bi-shop"></i> منصة السوق
        </a>
        
        <!-- زر الهمبرغر -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" 
                aria-controls="navbarMain" aria-expanded="false" aria-label="تبديل القائمة">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- القائمة -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- الرئيسية -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'index.php' && $currentDir == '.') ? 'active' : ''; ?>" 
                       href="<?php echo $basePath; ?>index.php">
                        <i class="bi bi-house-door"></i> الرئيسية
                    </a>
                </li>
                
                <!-- المنتجات -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentDir == 'products' && $currentPage != 'add.php') ? 'active' : ''; ?>" 
                       href="<?php echo $basePath; ?>products/index.php">
                        <i class="bi bi-grid-3x3-gap-fill"></i> المنتجات
                    </a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <!-- المفضلة -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentDir == 'favorites') ? 'active' : ''; ?>" 
                           href="<?php echo $basePath; ?>favorites/index.php">
                            <i class="bi bi-heart"></i> المفضلة
                        </a>
                    </li>
                    
                    <!-- المحادثات -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentDir == 'chat') ? 'active' : ''; ?>" 
                           href="<?php echo $basePath; ?>../chat/index.php">
                            <i class="bi bi-chat-dots"></i> المحادثات
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- إضافة منتج (للبائع والمدير) -->
                    <?php if (hasRole('seller') || hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'add-product.php' || $currentPage == 'add.php') ? 'active' : ''; ?>" 
                               href="<?php echo $basePath; ?>products/add.php">
                                <i class="bi bi-plus-circle"></i> إضافة منتج
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- لوحة البائع -->
                    <?php if (hasRole('seller')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentDir == 'seller' && $currentPage != 'add-product.php') ? 'active' : ''; ?>" 
                               href="<?php echo $basePath; ?>seller/index.php">
                                <i class="bi bi-speedometer2"></i> لوحة البائع
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- لوحة المدير -->
                    <?php if (hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentDir == 'admin') ? 'active' : ''; ?>" 
                               href="<?php echo $basePath; ?>admin/dashboard.php">
                                <i class="bi bi-shield-lock"></i> المدير
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <!-- القائمة اليمنى (المستخدم / تسجيل الدخول) -->
            <ul class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): ?>
                    <!-- أيقونة الإشعارات -->
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell" style="font-size: 1.3rem;"></i>
                            <?php if ($totalUnread > 0): ?>
                                <span class="badge-notification"><?php echo $totalUnread; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" style="min-width: 300px; max-height: 400px; overflow-y: auto;">
                            <?php if ($totalUnread == 0): ?>
                                <li class="dropdown-item text-center text-muted">لا توجد إشعارات جديدة</li>
                            <?php else: ?>
                                <?php if ($unreadCount > 0): ?>
                                    <li class="dropdown-header text-primary">📩 رسائل جديدة (<?php echo $unreadCount; ?>)</li>
                                    <?php
                                    // جلب آخر 5 رسائل غير مقروءة
                                    try {
                                        $stmt = $db->prepare('SELECT c.*, u.name as sender_name 
                                                               FROM chats c 
                                                               JOIN users u ON c.sender_id = u.id 
                                                               WHERE c.receiver_id = ? AND c.is_read = 0 
                                                               ORDER BY c.created_at DESC LIMIT 5');
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $unreadMessages = $stmt->fetchAll();
                                        foreach ($unreadMessages as $msg) {
                                            echo '<li><a class="dropdown-item" href="' . $basePath . 'chat/conversation.php?user_id=' . $msg['sender_id'] . '">';
                                            echo '<i class="bi bi-chat-dots"></i> ' . htmlspecialchars($msg['sender_name']);
                                            echo '<br><small class="text-muted">' . htmlspecialchars(substr($msg['message'], 0, 30)) . '...</small>';
                                            echo '</a></li>';
                                        }
                                    } catch (Exception $e) {}
                                    ?>
                                <?php endif; ?>
                                
                                <?php if ($unreadNotifications > 0): ?>
                                    <li class="dropdown-header text-success">🔔 إشعارات عامة (<?php echo $unreadNotifications; ?>)</li>
                                    <?php
                                    try {
                                        $stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5');
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $notifs = $stmt->fetchAll();
                                        foreach ($notifs as $notif) {
                                            echo '<li><a class="dropdown-item" href="' . $basePath . 'notifications/index.php">';
                                            echo '<i class="bi bi-info-circle"></i> ' . htmlspecialchars(substr($notif['message'], 0, 40)) . '...';
                                            echo '</a></li>';
                                        }
                                    } catch (Exception $e) {}
                                    ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="<?php echo $basePath; ?>notifications/index.php">عرض جميع الإشعارات</a></li>
                        </ul>
                    </li>
                    
                    <!-- القائمة المنسدلة للمستخدم -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle" style="font-size: 1.4rem;"></i>
                            <span class="d-none d-sm-inline"><?php echo htmlspecialchars(getCurrentUserName()); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>auth/profile.php">
                                <i class="bi bi-person"></i> ملفي الشخصي
                            </a></li>
                            <?php if (hasRole('seller')): ?>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>seller/index.php">
                                    <i class="bi bi-speedometer2"></i> لوحة البائع
                                </a></li>
                            <?php endif; ?>
                            <?php if (hasRole('admin')): ?>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>admin/dashboard.php">
                                    <i class="bi bi-shield-lock"></i> لوحة المدير
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo $basePath; ?>auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> تسجيل خروج
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- أزرار تسجيل الدخول والتسجيل للزوار -->
                    <li class="nav-item">
                        <a class="btn btn-login me-2" href="<?php echo $basePath; ?>auth/login.php">
                            <i class="bi bi-box-arrow-in-right"></i> دخول
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-register" href="<?php echo $basePath; ?>auth/register.php">
                            <i class="bi bi-person-plus"></i> تسجيل
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- ==========================================
     بداية المحتوى
     ========================================== -->
<main class="flex-shrink-0">

<!-- تضمين Bootstrap JS (ضروري لعمل الهمبرغر والقوائم المنسدلة) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 