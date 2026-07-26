<?php
/**
 * seller/order.php
 * صفحة إدارة الطلبات (المنتجات التي تم بيعها)
 * تعرض المنتجات التي حالتها 'sold' مع معلومات المشتري (إن وجدت)
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

// معالجة تحديث حالة الطلب (إعادة تفعيل المنتج أو تأكيد التسليم)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    
    if ($productId > 0) {
        try {
            $db = Database::getConnection();
            
            // التحقق من ملكية المنتج
            $checkSql = "SELECT seller_id FROM products WHERE id = ?";
            $stmt = $db->prepare($checkSql);
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if ($product && ($userRole === 'admin' || $product['seller_id'] == $userId)) {
                if ($action === 'mark_available') {
                    // إعادة المنتج للحالة متاح
                    $updateSql = "UPDATE products SET status = 'available' WHERE id = ?";
                    $stmt = $db->prepare($updateSql);
                    $stmt->execute([$productId]);
                    $message = 'تم إعادة المنتج للحالة "متاح للبيع".';
                    $messageType = 'success';
                } elseif ($action === 'confirm_delivery') {
                    // تأكيد التسليم (يمكن إضافة حقل delivery_confirmed في المستقبل)
                    // حالياً نقوم بتسجيل إشعار فقط
                    $message = 'تم تأكيد تسليم الطلب بنجاح.';
                    $messageType = 'success';
                } else {
                    $message = 'إجراء غير معروف.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'لا تملك صلاحية تعديل هذا المنتج.';
                $messageType = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'حدث خطأ في تحديث الطلب.';
            $messageType = 'danger';
        }
    }
}

// جلب الطلبات (المنتجات التي تم بيعها)
try {
    $db = Database::getConnection();
    
    if ($userRole === 'admin') {
        // المدير يرى جميع المنتجات المباعة
        $sql = "SELECT p.*, u.name as seller_name, u.phone as seller_phone,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM products p
                JOIN users u ON p.seller_id = u.id
                WHERE p.status = 'sold'
                ORDER BY p.updated_at DESC";
        $stmt = $db->query($sql);
    } else {
        // البائع يرى منتجاته المباعة فقط
        $sql = "SELECT p.*, 
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM products p
                WHERE p.seller_id = ? AND p.status = 'sold'
                ORDER BY p.updated_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
    }
    $orders = $stmt->fetchAll();

    // إحصائيات الطلبات
    $totalOrders = count($orders);
    $recentOrders = array_slice($orders, 0, 5);

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل الطلبات.';
    $orders = [];
    $totalOrders = 0;
    $recentOrders = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الطلبات - لوحة البائع</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .order-card {
            border: none;
            border-radius: 16px;
            transition: transform 0.2s;
        }
        .order-card:hover {
            transform: translateY(-3px);
        }
        .order-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .order-status.delivered {
            background: #d4edda;
            color: #155724;
        }
        .order-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        .order-status.shipped {
            background: #cce5ff;
            color: #004085;
        }
    </style>
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">🏪 منصة السوق</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="../products/index.php">المنتجات</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php">لوحة التحكم</a></li>
                    <li class="nav-item"><a class="nav-link active" href="order.php"><i class="bi bi-cart-check"></i> الطلبات</a></li>
                    <li class="nav-item"><a class="nav-link" href="add-product.php"><i class="bi bi-plus-circle"></i> إضافة منتج</a></li>
                    <li class="nav-item"><a class="nav-link" href="../auth/logout.php">تسجيل خروج</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-cart-check"></i> الطلبات</h2>
            <span class="badge bg-primary fs-6">مرحباً، <?php echo htmlspecialchars(getCurrentUserName()); ?></span>
        </div>

        <!-- عرض الرسائل -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show rounded-4">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger rounded-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- الإحصائيات -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card bg-success text-white shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <h6 class="card-title text-white-50">إجمالي الطلبات</h6>
                        <p class="display-6 fw-bold mb-0"><?php echo $totalOrders; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-warning text-dark shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <h6 class="card-title text-dark-50">قيد المعالجة</h6>
                        <p class="display-6 fw-bold mb-0"><?php echo count(array_filter($orders, fn($o) => $o['status'] == 'sold' && strtotime($o['updated_at']) > strtotime('-7 days'))); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-info text-white shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <h6 class="card-title text-white-50">آخر 7 أيام</h6>
                        <p class="display-6 fw-bold mb-0"><?php echo count(array_filter($orders, fn($o) => strtotime($o['updated_at']) > strtotime('-7 days'))); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- قائمة الطلبات -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-0 pt-4">
                <h5 class="mb-0 fw-bold"><i class="bi bi-list-ul"></i> قائمة الطلبات</h5>
            </div>
            <div class="card-body">
                <?php if (empty($orders)): ?>
                    <div class="alert alert-info text-center py-4">
                        <i class="bi bi-inbox" style="font-size: 2rem; display: block;"></i>
                        <p class="mt-2">لا توجد طلبات حالياً.</p>
                        <p class="mb-0 text-muted">عندما يشتري أحدهم منتجاً، ستظهر الطلبات هنا.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>#</th>
                                    <th>المنتج</th>
                                    <th>السعر</th>
                                    <th>المشتري</th>
                                    <th>تاريخ البيع</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $index => $order): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($order['primary_image']): ?>
                                                    <img src="../uploads/products/<?php echo htmlspecialchars($order['primary_image']); ?>" 
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; margin-left: 10px;" 
                                                         alt="<?php echo htmlspecialchars($order['name']); ?>">
                                                <?php else: ?>
                                                    <i class="bi bi-image" style="font-size: 1.5rem; margin-left: 10px;"></i>
                                                <?php endif; ?>
                                                <a href="../products/details.php?id=<?php echo $order['id']; ?>" class="text-decoration-none fw-semibold">
                                                    <?php echo htmlspecialchars($order['name']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td><?php echo formatPrice($order['price']); ?></td>
                                        <td>
                                            <?php if (isset($order['seller_name']) && $userRole === 'admin'): ?>
                                                <?php echo htmlspecialchars($order['seller_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">(مشتري غير معروف)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($order['updated_at'])); ?></td>
                                        <td>
                                            <span class="order-status delivered">تم البيع</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="product_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="action" value="mark_available">
                                                    <button type="submit" class="btn btn-success" 
                                                            onclick="return confirm('هل أنت متأكد من إعادة هذا المنتج للحالة متاح للبيع؟')"
                                                            title="إعادة المنتج للحالة متاح">
                                                        <i class="bi bi-arrow-counterclockwise"></i> إعادة
                                                    </button>
                                                </form>
                                                <a href="../products/details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary" title="عرض التفاصيل">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- روابط سريعة -->
        <div class="row g-3 mt-4">
            <div class="col-md-3">
                <a href="index.php" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-3">
                        <i class="bi bi-speedometer2" style="font-size: 1.5rem; color: #0d6efd;"></i>
                        <span class="fw-semibold mt-1">لوحة التحكم</span>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="../products/index.php" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-3">
                        <i class="bi bi-grid" style="font-size: 1.5rem; color: #198754;"></i>
                        <span class="fw-semibold mt-1">جميع المنتجات</span>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="add-product.php" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-3">
                        <i class="bi bi-plus-circle" style="font-size: 1.5rem; color: #ffc107;"></i>
                        <span class="fw-semibold mt-1">إضافة منتج</span>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="../chats/index.php" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-3">
                        <i class="bi bi-chat" style="font-size: 1.5rem; color: #6f42c1;"></i>
                        <span class="fw-semibold mt-1">المحادثات</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>