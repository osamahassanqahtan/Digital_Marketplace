<?php
/**
 * index.php
 * الصفحة الرئيسية للمنصة - تصميم عصري وجذاب
 */

require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/helpers.php';
require_once 'includes/functions.php';

$pageTitle = 'منصة السوق - بيع وشراء المنتجات الجديدة والمستعملة';

// جلب المنتجات الأحدث (حد أقصى 8)
try {
    $db = Database::getConnection();
    
    // جلب أحدث 8 منتجات متاحة مع صورهم الأساسية وبيانات البائع
    $sql = "SELECT p.*, u.name as seller_name, 
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p
            JOIN users u ON p.seller_id = u.id
            WHERE p.status = 'available'
            ORDER BY p.created_at DESC
            LIMIT 8";
    $stmt = $db->query($sql);
    $latestProducts = $stmt->fetchAll();

    // جلب عدد المنتجات في كل قسم للعرض السريع
    $stmtCat = $db->query("SELECT c.id, c.name, COUNT(p.id) as count 
                           FROM categories c 
                           LEFT JOIN products p ON c.id = p.category_id AND p.status = 'available' 
                           GROUP BY c.id 
                           ORDER BY c.name");
    $categories = $stmtCat->fetchAll();

    // جلب عدد المستخدمين الكلي للمنصة
    $stmtUsers = $db->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmtUsers->fetch()['total'];

    // جلب عدد المنتجات الكلي
    $stmtProducts = $db->query("SELECT COUNT(*) as total FROM products WHERE status = 'available'");
    $totalProducts = $stmtProducts->fetch()['total'];

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل البيانات.';
    $latestProducts = [];
    $categories = [];
    $totalUsers = 0;
    $totalProducts = 0;
}

// تضمين الهيدر
require_once 'includes/header.php';
?>

<!-- قسم الترحيب (Hero Section) -->
<section class="hero-section text-white">
    <div class="container">
        <div class="row align-items-center min-vh-50 py-5">
            <div class="col-lg-6">
                <h1 class="display-3 fw-bold mb-3 animate__animated animate__fadeInUp">
                    اكتشف أفضل العروض
                </h1>
                <p class="lead mb-4 animate__animated animate__fadeInUp animate__delay-1s">
                    منصة السوق الوجهة الأولى لبيع وشراء المنتجات الجديدة والمستعملة في المملكة.
                    تواصل مع البائعين مباشرة واحصل على أفضل الصفقات.
                </p>
                <div class="d-flex flex-wrap gap-3 animate__animated animate__fadeInUp animate__delay-2s">
                    <a href="products/index.php" class="btn btn-light btn-lg rounded-pill px-5 fw-bold">
                        <i class="bi bi-grid"></i> استكشف المنتجات
                    </a>
                    <?php if (!isLoggedIn()): ?>
                        <a href="auth/register.php" class="btn btn-outline-light btn-lg rounded-pill px-5 fw-bold">
                            <i class="bi bi-person-plus"></i> انضم الآن
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 text-center d-none d-lg-block animate__animated animate__fadeInRight">
                <i class="bi bi-bag-heart" style="font-size: 12rem; opacity: 0.9;"></i>
            </div>
        </div>
    </div>
</section>

<!-- إحصائيات سريعة -->
<section class="stats-section py-4 bg-white shadow-sm">
    <div class="container">
        <div class="row text-center g-3">
            <div class="col-4 col-md-3">
                <div class="stat-item">
                    <i class="bi bi-box-seam fs-1 text-primary"></i>
                    <h3 class="fw-bold mb-0"><?php echo number_format($totalProducts); ?></h3>
                    <p class="text-muted small">منتج متاح</p>
                </div>
            </div>
            <div class="col-4 col-md-3">
                <div class="stat-item">
                    <i class="bi bi-people fs-1 text-success"></i>
                    <h3 class="fw-bold mb-0"><?php echo number_format($totalUsers); ?></h3>
                    <p class="text-muted small">مستخدم نشط</p>
                </div>
            </div>
            <div class="col-4 col-md-3">
                <div class="stat-item">
                    <i class="bi bi-tag fs-1 text-warning"></i>
                    <h3 class="fw-bold mb-0"><?php echo count($categories); ?></h3>
                    <p class="text-muted small">قسم مختلف</p>
                </div>
            </div>
            <div class="col-4 col-md-3 d-none d-md-block">
                <div class="stat-item">
                    <i class="bi bi-chat-dots fs-1 text-danger"></i>
                    <h3 class="fw-bold mb-0">24/7</h3>
                    <p class="text-muted small">دعم متواصل</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- الأقسام (Categories) -->
<section class="categories-section py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold"><i class="bi bi-grid"></i> الأقسام</h2>
            <a href="products/index.php" class="btn btn-outline-primary rounded-pill">عرض الكل <i class="bi bi-arrow-left"></i></a>
        </div>
        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-7 g-3">
            <?php foreach ($categories as $cat): ?>
                <div class="col">
                    <a href="products/category.php?id=<?php echo $cat['id']; ?>" class="text-decoration-none">
                        <div class="card category-card h-100 border-0 shadow-sm rounded-4 text-center p-2">
                            <div class="card-body">
                                <i class="bi bi-tag fs-1 text-primary"></i>
                                <h6 class="mt-2 fw-semibold"><?php echo htmlspecialchars($cat['name']); ?></h6>
                                <small class="text-muted"><?php echo $cat['count']; ?> منتج</small>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- أحدث المنتجات -->
<section class="products-section py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold"><i class="bi bi-clock-history"></i> أحدث المنتجات</h2>
            <a href="products/index.php" class="btn btn-primary rounded-pill">عرض الكل <i class="bi bi-arrow-left"></i></a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($latestProducts)): ?>
            <div class="alert alert-info text-center py-5">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-3">لا توجد منتجات متاحة حالياً. كن أول من يضيف منتجاً!</p>
                <?php if (isLoggedIn() && (hasRole('seller') || hasRole('admin'))): ?>
                    <a href="products/add.php" class="btn btn-primary">أضف منتجك الأول</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                <?php foreach ($latestProducts as $product): ?>
                    <div class="col">
                        <div class="card product-card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                            <div class="position-relative">
                                <?php if ($product['primary_image']): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($product['primary_image']); ?>" 
                                         class="card-img-top" style="height: 220px; object-fit: cover;" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 220px;">
                                        <i class="bi bi-image text-light" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="badge bg-<?php echo $product['condition'] == 'new' ? 'success' : 'warning'; ?> position-absolute top-0 start-0 m-2">
                                    <?php echo $product['condition'] == 'new' ? 'جديد' : 'مستعمل'; ?>
                                </span>
                                <?php if (isLoggedIn()): ?>
                                    <button class="btn btn-sm btn-light position-absolute top-0 end-0 m-2 rounded-circle favorite-btn" 
                                            data-product-id="<?php echo $product['id']; ?>" 
                                            style="width: 32px; height: 32px; padding: 0; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                        <i class="bi bi-heart text-danger"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="products/details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark fw-semibold">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h5>
                                <p class="card-text text-truncate small text-muted"><?php echo htmlspecialchars($product['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-success fs-6"><?php echo formatPrice($product['price']); ?></span>
                                    <small class="text-muted"><i class="bi bi-person"></i> <?php echo htmlspecialchars($product['seller_name']); ?></small>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 pb-3">
                                <a href="products/details.php?id=<?php echo $product['id']; ?>" class="btn btn-primary w-100 rounded-pill">تفاصيل</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- دعوة للتسجيل (للزوار فقط) -->
<?php if (!isLoggedIn()): ?>
    <section class="cta-section py-5">
        <div class="container">
            <div class="card border-0 shadow-lg rounded-5 bg-gradient-primary text-white overflow-hidden">
                <div class="card-body p-5 text-center">
                    <h2 class="fw-bold">هل لديك منتج تريد بيعه؟</h2>
                    <p class="lead mb-4">انضم إلى آلاف البائعين وابدأ في عرض منتجاتك اليوم.</p>
                    <a href="auth/register.php" class="btn btn-light btn-lg rounded-pill px-5 fw-bold">
                        <i class="bi bi-person-plus"></i> إنشاء حساب مجاني
                    </a>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- تضمين الفوتر -->
<?php require_once 'includes/footer.php'; ?>

<!-- إضافة مكتبة Animate.css (للتأثيرات الحركية) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<!-- كود JavaScript الخاص بالصفحة الرئيسية -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // إضافة/إزالة المفضلة باستخدام AJAX (للصفحة الرئيسية)
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = this.dataset.productId;
                const icon = this.querySelector('i');
                const isCurrentlyFavorite = icon.classList.contains('bi-heart-fill');
                const action = isCurrentlyFavorite ? 'remove' : 'add';
                
                fetch('favorites/toggle.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `product_id=${productId}&action=${action}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.is_favorite) {
                            icon.classList.replace('bi-heart', 'bi-heart-fill');
                        } else {
                            icon.classList.replace('bi-heart-fill', 'bi-heart');
                        }
                    } else {
                        alert(data.message || 'حدث خطأ');
                    }
                })
                .catch(err => {
                    alert('حدث خطأ في الاتصال');
                });
            });
        });
    });
</script>

<!-- تنسيقات الصفحة الرئيسية (سيتم نقلها إلى ملف منفصل في الرسالة التالية) -->
<style>
    /* Hero Section */
    .hero-section {
        background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 50%, #6D28D9 100%);
        padding: 60px 0 80px;
        border-radius: 0 0 40px 40px;
        margin-bottom: -20px;
    }
    .hero-section .display-3 {
        font-weight: 800;
        letter-spacing: -1px;
    }

    /* Stats Section */
    .stats-section .stat-item {
        padding: 15px 0;
    }
    .stats-section .stat-item h3 {
        color: #1a202c;
    }

    /* Category Cards */
    .category-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        cursor: pointer;
    }
    .category-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    }

    /* Product Cards */
    .product-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .product-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 16px 40px rgba(0,0,0,0.08) !important;
    }
    .product-card .card-img-top {
        border-radius: 16px 16px 0 0;
    }

    /* CTA Section */
    .bg-gradient-primary {
        background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
    }
    .cta-section .card {
        transition: transform 0.3s;
    }
    .cta-section .card:hover {
        transform: scale(1.01);
    }

    /* تحسينات عامة */
    .btn-primary {
        background: linear-gradient(135deg, #4F46E5, #7C3AED);
        border: none;
        transition: 0.3s;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(79,70,229,0.3);
    }
    .btn-light:hover {
        background: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255,255,255,0.2);
    }
    .min-vh-50 {
        min-height: 50vh;
    }
</style>

</body>
</html>