<?php
/**
 * admin/dashboard.php
 * لوحة تحكم المدير - إحصائيات وإدارة المستخدمين والمنتجات
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// التحقق من تسجيل الدخول ودور المدير فقط
requireLogin('../auth/login.php');
requireRole('admin', '../index.php');

$message = '';
$messageType = '';

// معالجة تغيير دور المستخدم أو حظره/تفعيله
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    
    if ($userId > 0 && $userId != getCurrentUserId()) { // لا يمكن تعديل نفسه
        try {
            $db = Database::getConnection();
            
            if ($action === 'change_role') {
                $newRole = $_POST['role'] ?? 'buyer';
                $validRoles = ['admin', 'seller', 'buyer'];
                if (in_array($newRole, $validRoles)) {
                    $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
                    $stmt->execute([$newRole, $userId]);
                    $message = 'تم تحديث دور المستخدم بنجاح.';
                    $messageType = 'success';
                }
            } elseif ($action === 'toggle_status') {
                // جلب الحالة الحالية
                $stmt = $db->prepare('SELECT status FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if ($user) {
                    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
                    $stmt = $db->prepare('UPDATE users SET status = ? WHERE id = ?');
                    $stmt->execute([$newStatus, $userId]);
                    $message = 'تم تحديث حالة المستخدم بنجاح.';
                    $messageType = 'success';
                }
            } elseif ($action === 'delete_product') {
                $productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
                if ($productId > 0) {
                    // حذف المنتج مع صوره
                    $stmtImg = $db->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
                    $stmtImg->execute([$productId]);
                    $images = $stmtImg->fetchAll();
                    
                    $db->beginTransaction();
                    $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
                    $stmt->execute([$productId]);
                    
                    foreach ($images as $img) {
                        $filePath = '../uploads/products/' . $img['image_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                    $db->commit();
                    $message = 'تم حذف المنتج المخالف بنجاح.';
                    $messageType = 'success';
                }
            }
        } catch (PDOException $e) {
            if (isset($db)) $db->rollBack();
            $message = 'حدث خطأ في قاعدة البيانات.';
            $messageType = 'danger';
        }
    }
}

// جلب الإحصائيات
try {
    $db = Database::getConnection();
    
    // عدد المستخدمين
    $stmt = $db->query('SELECT COUNT(*) as total FROM users');
    $totalUsers = $stmt->fetch()['total'];
    
    // عدد المنتجات
    $stmt = $db->query('SELECT COUNT(*) as total FROM products');
    $totalProducts = $stmt->fetch()['total'];
    
    // عدد المنتجات المتاحة
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE status = 'available'");
    $availableProducts = $stmt->fetch()['total'];
    
    // عدد المنتجات المباعة
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE status = 'sold'");
    $soldProducts = $stmt->fetch()['total'];
    
    // عدد التقييمات
    $stmt = $db->query('SELECT COUNT(*) as total FROM reviews');
    $totalReviews = $stmt->fetch()['total'];
    
    // عدد التقارير المعلقة
    $stmt = $db->query("SELECT COUNT(*) as total FROM reports WHERE status = 'pending'");
    $pendingReports = $stmt->fetch()['total'];
    
    // جلب قائمة المستخدمين (آخر 10)
    $stmt = $db->query('SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 10');
    $recentUsers = $stmt->fetchAll();
    
    // جلب أحدث المنتجات (آخر 10)
    $stmt = $db->query('SELECT p.id, p.name, p.price, p.status, u.name as seller_name, p.created_at 
                         FROM products p 
                         JOIN users u ON p.seller_id = u.id 
                         ORDER BY p.created_at DESC LIMIT 10');
    $recentProducts = $stmt->fetchAll();
    
    // جلب التقارير المعلقة
    $stmt = $db->query('SELECT r.*, p.name as product_name, u.name as reporter_name 
                         FROM reports r 
                         JOIN products p ON r.product_id = p.id 
                         JOIN users u ON r.user_id = u.id 
                         WHERE r.status = "pending" 
                         ORDER BY r.created_at DESC LIMIT 5');
    $pendingReportsList = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل البيانات.';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة المدير - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">🏪 منصة السوق</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">لوحة التحكم</a></li>
                    <li class="nav-item"><a class="nav-link" href="../products/add.php">إضافة منتج</a></li>
                    <li class="nav-item"><a class="nav-link" href="../auth/logout.php">تسجيل خروج</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-speedometer2"></i> لوحة تحكم المدير</h2>
            <span class="badge bg-primary fs-6">مرحباً، <?php echo htmlspecialchars(getCurrentUserName()); ?></span>
        </div>

        <!-- عرض الرسائل -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- بطاقات الإحصائيات -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-people"></i> المستخدمين</h6>
                        <p class="card-text display-6"><?php echo $totalUsers ?? 0; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-box-seam"></i> المنتجات</h6>
                        <p class="card-text display-6"><?php echo $totalProducts ?? 0; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-hand-thumbs-up"></i> متاحة</h6>
                        <p class="card-text display-6"><?php echo $availableProducts ?? 0; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-flag"></i> تقارير معلقة</h6>
                        <p class="card-text display-6"><?php echo $pendingReports ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- المستخدمين -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-people"></i> أحدث المستخدمين</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>الاسم</th>
                                        <th>البريد</th>
                                        <th>الدور</th>
                                        <th>الحالة</th>
                                        <th>إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers ?? [] as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="change_role">
                                                    <select name="role" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                                        <option value="buyer" <?php echo $user['role'] == 'buyer' ? 'selected' : ''; ?>>مشتري</option>
                                                        <option value="seller" <?php echo $user['role'] == 'seller' ? 'selected' : ''; ?>>بائع</option>
                                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>مدير</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $user['status'] == 'active' ? 'نشط' : 'محظور'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <button type="submit" class="btn btn-sm btn-<?php echo $user['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                        <?php echo $user['status'] == 'active' ? 'حظر' : 'تفعيل'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- المنتجات -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-box-seam"></i> أحدث المنتجات</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>السعر</th>
                                        <th>البائع</th>
                                        <th>الحالة</th>
                                        <th>حذف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentProducts ?? [] as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo formatPrice($product['price']); ?></td>
                                            <td><?php echo htmlspecialchars($product['seller_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $product['status'] == 'available' ? 'success' : ($product['status'] == 'sold' ? 'danger' : 'warning'); ?>">
                                                    <?php echo $product['status'] == 'available' ? 'متاح' : ($product['status'] == 'sold' ? 'تم البيع' : 'قيد الانتظار'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" action="" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟')">
                                                    <input type="hidden" name="action" value="delete_product">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- التقارير المعلقة -->
        <?php if (!empty($pendingReportsList)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-flag"></i> التقارير المعلقة</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>المبلغ</th>
                                        <th>السبب</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingReportsList as $report): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($report['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                                            <td><?php echo htmlspecialchars($report['reason']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($report['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>