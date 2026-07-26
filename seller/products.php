<?php
/**
 * seller/products.php
 * صفحة إدارة المنتجات للبائع - تعرض جميع منتجات البائع مع خيارات التعديل والحذف والتغيير
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
            
            // التحقق من ملكية المنتج (أو المدير)
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

// معالجة البحث والفلترة
$search = sanitizeInput($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$limit = 10;
$offset = ($page - 1) * $limit;

// بناء استعلام جلب المنتجات
try {
    $db = Database::getConnection();
    
    // بناء شروط البحث
    $whereConditions = [];
    $params = [];
    
    if ($userRole !== 'admin') {
        $whereConditions[] = "p.seller_id = ?";
        $params[] = $userId;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($statusFilter) && in_array($statusFilter, ['available', 'sold', 'pending'])) {
        $whereConditions[] = "p.status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    // ترتيب النتائج
    $orderBy = match($sort) {
        'price_low' => 'p.price ASC',
        'price_high' => 'p.price DESC',
        'oldest' => 'p.created_at ASC',
        default => 'p.created_at DESC'
    };
    
    // جلب عدد المنتجات الإجمالي
    $countSql = "SELECT COUNT(*) as total FROM products p $whereClause";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $totalProducts = $stmt->fetch()['total'];
    $totalPages = ceil($totalProducts / $limit);
    
    // جلب المنتجات مع الصورة الأساسية واسم البائع
    $sql = "SELECT p.*, 
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p
            $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $products = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل المنتجات.';
    $products = [];
    $totalProducts = 0;
    $totalPages = 0;
}

// جلب عدد الإشعارات غير المقروءة للبائع
$unreadNotifications = 0;
if (isLoggedIn()) {
    try {
        $stmt = $db->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $unreadNotifications = $stmt->fetch()['unread'];
    } catch (PDOException $e) {
        // تجاهل
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المنتجات - لوحة البائع</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-section {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .product-table th {
            background: #f1f3f5;
            border-bottom: 2px solid #dee2e6;
        }
        .product-table td {
            vertical-align: middle;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
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
                    <li class="nav-item"><a class="nav-link active" href="products.php"><i class="bi bi-box-seam"></i> منتجاتي</a></li>
                    <li class="nav-item"><a class="nav-link" href="add-product.php"><i class="bi bi-plus-circle"></i> إضافة منتج</a></li>
                    <li class="nav-item"><a class="nav-link" href="../notifications/index.php">
                        <i class="bi bi-bell"></i>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="badge bg-danger badge-notification"><?php echo $unreadNotifications; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li class="nav-item"><a class="nav-link" href="../auth/logout.php">تسجيل خروج</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-box-seam"></i> إدارة المنتجات</h2>
            <a href="add-product.php" class="btn btn-primary rounded-pill">
                <i class="bi bi-plus-circle"></i> إضافة منتج جديد
            </a>
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
        <?php $flash = getFlashMessage('success'); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show rounded-4">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- البحث والفلترة -->
        <div class="filter-section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="بحث عن منتج..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="">جميع الحالات</option>
                        <option value="available" <?php echo $statusFilter === 'available' ? 'selected' : ''; ?>>متاح</option>
                        <option value="sold" <?php echo $statusFilter === 'sold' ? 'selected' : ''; ?>>تم البيع</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="sort" onchange="this.form.submit()">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>الأحدث</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>الأقدم</option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>السعر (منخفض → مرتفع)</option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>السعر (مرتفع → منخفض)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="products.php" class="btn btn-outline-secondary w-100">إعادة تعيين</a>
                </div>
            </form>
        </div>

        <!-- عرض المنتجات -->
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="bi bi-box"></i>
                <h5 class="mt-3">لا توجد منتجات</h5>
                <p class="text-muted">لم تقم بإضافة أي منتجات بعد، أو لا توجد نتائج مطابقة للبحث.</p>
                <a href="add-product.php" class="btn btn-primary">إضافة منتج جديد</a>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table product-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الصورة</th>
                                    <th>الاسم</th>
                                    <th>السعر</th>
                                    <th>الحالة</th>
                                    <th>المشاهدات</th>
                                    <th>تاريخ الإضافة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $index => $product): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <?php if ($product['primary_image']): ?>
                                                <img src="../uploads/products/<?php echo htmlspecialchars($product['primary_image']); ?>" 
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <?php else: ?>
                                                <i class="bi bi-image" style="font-size: 1.8rem; color: #adb5bd;"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="../products/details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none fw-semibold">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo formatPrice($product['price']); ?></td>
                                        <td>
                                            <?php
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
                                            <span class="status-badge bg-<?php echo $statusColors[$product['status']] ?? 'secondary'; ?> text-white">
                                                <?php echo $statusLabels[$product['status']] ?? $product['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($product['views']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($product['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-warning" title="تعديل">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="delete-product.php?id=<?php echo $product['id']; ?>" 
                                                   class="btn btn-danger delete-confirm" 
                                                   data-confirm-message="هل أنت متأكد من حذف المنتج '<?php echo htmlspecialchars($product['name']); ?>'؟"
                                                   title="حذف">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                                <button type="button" class="btn btn-secondary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#statusModal<?php echo $product['id']; ?>" 
                                                        title="تغيير الحالة">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </div>
                                            <!-- Modal لتغيير الحالة -->
                                            <div class="modal fade" id="statusModal<?php echo $product['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-sm modal-dialog-centered">
                                                    <div class="modal-content rounded-4">
                                                        <form method="POST" action="">
                                                            <div class="modal-header border-0">
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
                                                            <div class="modal-footer border-0">
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
                    </div>
                </div>
            </div>

            <!-- ترقيم الصفحات -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">السابق</a>
                            </li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php 