<?php
/**
 * includes/header.php
 * شريط التنقل العلوي - نسخة نهائية مع شريط أبيض وروابط ديناميكية
 */

// بدء الجلسة إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين دوال المساعدة
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

// =============================================
// تحديد المسار الأساسي تلقائياً (يعمل من أي مجلد)
// =============================================
$currentDir = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = '';
if ($currentDir !== '/') {
    $levels = substr_count(trim($currentDir, '/'), '/');
    if ($levels > 0) {
        $baseUrl = str_repeat('../', $levels);
    }
}
if ($baseUrl !== '' && substr($baseUrl, -1) !== '/') {
    $baseUrl .= '/';
}

// جلب عدد الإشعارات غير المقروءة
$unreadCount = 0;
$unreadNotifications = 0;
if (isLoggedIn()) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = Database::getConnection();
        $userId = $_SESSION['user_id'];
        
        $stmt = $db->prepare('SELECT COUNT(*) as unread FROM chats WHERE receiver_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $unreadCount = (int)$stmt->fetch()['unread'];
        
        $stmt = $db->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $unreadNotifications = (int)$stmt->fetch()['unread'];
    } catch (Exception $e) {}
}
$totalUnread = $unreadCount + $unreadNotifications;

// تحديد الصفحة الحالية لتسليط الضوء على الروابط
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDirName = basename(dirname($_SERVER['PHP_SELF']));
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="منصة السوق - بيع وشراء المنتجات">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <!-- التنسيقات العامة -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>assets/css/style.css">
    
    <title><?php echo $pageTitle ?? 'منصة السوق'; ?></title>
    
    <style>
        /* ==========================================
           تنسيق شريط التنقل الأبيض
           ========================================== */
        .navbar-white {
            background: #ffffff !important;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }
        .navbar-white .navbar-brand {
            font-weight: 900;
            font-size: 1.5rem;
            color: #1E293B;
        }
        .navbar-white .navbar-brand i {
            color: #4F46E5;
        }
        .navbar-white .nav-link {
            color: #475569 !important;
            font-weight: 600;
            padding: 8px 18px !important;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        .navbar-white .nav-link:hover,
        .navbar-white .nav-link.active {
            background: #EEF2FF;
            color: #4F46E5 !important;
        }
        .navbar-white .nav-link i {
            margin-left: 6px;
        }
        .navbar-white .navbar-toggler {
            border: none;
            padding: 6px 10px;
            border-radius: 12px;
            background: #F1F5F9;
            transition: 0.3s;
        }
        .navbar-white .navbar-toggler:hover {
            background: #E2E8F0;
        }
        .navbar-white .navbar-toggler:focus {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }
        .navbar-white .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(30,41,59,0.8)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        .badge-notification {
            background: #EF4444;
            color: #fff;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-right: 4px;
        }
        .btn-login {
            background: #EEF2FF;
            color: #4F46E5;
            padding: 8px 20px;
            border-radius: 30px;
            transition: 0.3s;
            font-weight: 600;
            border: 1px solid #E0E7FF;
        }
        .btn-login:hover {
            background: #E0E7FF;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.1);
        }
        .btn-register {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: #fff;
            padding: 8px 24px;
            border-radius: 30px;
            transition: 0.3s;
            font-weight: 700;
            border: none;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.25);
            color: #fff;
        }
        .dropdown-menu {
            background: #ffffff;
            border: 1px solid #F1F5F9;
            border-radius: 16px;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.06);
            padding: 8px;
            min-width: 220px;
        }
        .dropdown-menu .dropdown-item {
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 500;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1E293B;
        }
        .dropdown-menu .dropdown-item:hover {
            background: #F1F5F9;
            color: #4F46E5;
        }
        .dropdown-menu .dropdown-item.text-danger:hover {
            background: #FEE2E2;
            color: #DC2626 !important;
        }
        .dropdown-menu .dropdown-divider {
            margin: 6px 0;
            border-color: #F1F5F9;
        }
        @media (max-width: 992px) {
            .navbar-white .navbar-nav .nav-link {
                padding: 12px 16px;
                justify-content: center;
            }
            .btn-login, .btn-register {
                width: 100%;
                text-align: center;
                margin-top: 6px;
            }
        }
        @media (max-width: 576px) {
            .navbar-white .navbar-brand {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>

<!-- ==========================================
     شريط التنقل (أبيض)
     ========================================== -->
<nav class="navbar navbar-expand-lg navbar-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $baseUrl; ?>index.php">
            <i class="bi bi-shop"></i> منصة السوق
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="تبديل القائمة">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'index.php' && $currentDirName == '.') ? 'active' : ''; ?>" 
                       href="<?php echo $baseUrl; ?>index.php">
                        <i class="bi bi-house-door"></i> الرئيسية
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentDirName == 'products' && $currentPage != 'add.php') ? 'active' : ''; ?>" 
                       href="<?php echo $baseUrl; ?>products/index.php">
                        <i class="bi bi-grid-3x3-gap-fill"></i> المنتجات
                    </a>
                </li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentDirName == 'favorites') ? 'active' : ''; ?>" 
                           href="<?php echo $baseUrl; ?>favorites/index.php">
                            <i class="bi bi-heart"></i> المفضلة
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentDirName == 'chat') ? 'active' : ''; ?>" 
                           href="<?php echo $baseUrl; ?>chat/index.php">
                            <i class="bi bi-chat-dots"></i> المحادثات
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if (hasRole('seller') || hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'add-product.php' || $currentPage == 'add.php') ? 'active' : ''; ?>" 
                               href="<?php echo $baseUrl; ?>products/add.php">
                                <i class="bi bi-plus-circle"></i> إضافة منتج
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (hasRole('seller')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentDirName == 'seller' && $currentPage != 'add-product.php') ? 'active' : ''; ?>" 
                               href="<?php echo $baseUrl; ?>seller/index.php">
                                <i class="bi bi-speedometer2"></i> لوحة البائع
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentDirName == 'admin') ? 'active' : ''; ?>" 
                               href="<?php echo $baseUrl; ?>admin/dashboard.php">
                                <i class="bi bi-shield-lock"></i> المدير
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): ?>
                    <!-- أيقونة الإشعارات -->
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell" style="font-size: 1.3rem; color: #1E293B;"></i>
                            <?php if ($totalUnread > 0): ?>
                                <span class="badge-notification"><?php echo $totalUnread; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="min-width:300px; max-height:400px; overflow-y:auto;">
                            <?php if ($totalUnread == 0): ?>
                                <li class="dropdown-item text-center text-muted">لا توجد إشعارات جديدة</li>
                            <?php else: ?>
                                <?php if ($unreadCount > 0): ?>
                                    <li class="dropdown-header text-primary">📩 رسائل جديدة (<?php echo $unreadCount; ?>)</li>
                                    <?php
                                    try {
                                        $stmt = $db->prepare('SELECT c.*, u.name as sender_name FROM chats c JOIN users u ON c.sender_id = u.id WHERE c.receiver_id = ? AND c.is_read = 0 ORDER BY c.created_at DESC LIMIT 5');
                                        $stmt->execute([$_SESSION['user_id']]);
                                        while ($msg = $stmt->fetch()) {
                                            echo '<li><a class="dropdown-item" href="' . $baseUrl . 'chat/conversation.php?user_id=' . $msg['sender_id'] . '">';
                                            echo '<i class="bi bi-chat-dots"></i> ' . htmlspecialchars($msg['sender_name']) . ' <br><small class="text-muted">' . htmlspecialchars(substr($msg['message'], 0, 30)) . '...</small>';
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
                                        while ($notif = $stmt->fetch()) {
                                            echo '<li><a class="dropdown-item" href="' . $baseUrl . 'notifications/index.php">';
                                            echo '<i class="bi bi-info-circle"></i> ' . htmlspecialchars(substr($notif['message'], 0, 40)) . '...';
                                            echo '</a></li>';
                                        }
                                    } catch (Exception $e) {}
                                    ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="<?php echo $baseUrl; ?>notifications/index.php">عرض جميع الإشعارات</a></li>
                        </ul>
                    </li>
                    <!-- قائمة المستخدم -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle" style="font-size: 1.4rem; color: #1E293B;"></i>
                            <span class="d-none d-sm-inline" style="color: #1E293B;"><?php echo htmlspecialchars(getCurrentUserName()); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>auth/profile.php"><i class="bi bi-person"></i> ملفي الشخصي</a></li>
                            <?php if (hasRole('seller')): ?>
                                <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>seller/index.php"><i class="bi bi-speedometer2"></i> لوحة البائع</a></li>
                            <?php endif; ?>
                            <?php if (hasRole('admin')): ?>
                                <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>admin/dashboard.php"><i class="bi bi-shield-lock"></i> لوحة المدير</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo $baseUrl; ?>auth/logout.php"><i class="bi bi-box-arrow-right"></i> تسجيل خروج</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-login me-2" href="<?php echo $baseUrl; ?>auth/login.php"><i class="bi bi-box-arrow-in-right"></i> دخول</a></li>
                    <li class="nav-item"><a class="btn btn-register" href="<?php echo $baseUrl; ?>auth/register.php"><i class="bi bi-person-plus"></i> تسجيل</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-shrink-0">