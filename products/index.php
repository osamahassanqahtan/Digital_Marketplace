<?php
/**
 * products/index.php
 * عرض جميع المنتجات المتاحة مع إمكانية البحث والفلترة - تصميم عصري
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';

// جلب معلمات البحث والفلترة
$search = sanitizeInput($_GET['search'] ?? '');
$categoryId = filter_var($_GET['category'] ?? 0, FILTER_VALIDATE_INT);
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$limit = 12;
$offset = ($page - 1) * $limit;

// بناء استعلام البحث
$whereConditions = ['p.status = "available"'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($categoryId > 0) {
    $whereConditions[] = 'p.category_id = ?';
    $params[] = $categoryId;
}

$whereClause = implode(' AND ', $whereConditions);

// ترتيب النتائج
$orderBy = match($sort) {
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'oldest' => 'p.created_at ASC',
    default => 'p.created_at DESC'
};

try {
    $db = Database::getConnection();
    
    $countSql = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    $totalPages = ceil($total / $limit);

    $sql = "SELECT p.*, u.name as seller_name, c.name as category_name,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p
            JOIN users u ON p.seller_id = u.id
            JOIN categories c ON p.category_id = c.id
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    $stmtCat = $db->query('SELECT id, name FROM categories ORDER BY name');
    $categories = $stmtCat->fetchAll();

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل المنتجات.';
    $products = [];
    $categories = [];
}

// تضمين الهيدر
require_once '../includes/header.php';
?>

<style>
/* ==========================================
   تصميم صفحة المنتجات - عصري وجذاب
   ========================================== */

/* رأس الصفحة */
.products-header {
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 50%, #6D28D9 100%);
    padding: 50px 0 40px;
    border-radius: 0 0 40px 40px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    margin-top: 10px;
}
.products-header::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.products-header .page-title {
    color: #fff;
    font-weight: 900;
    font-size: 2.2rem;
    margin-bottom: 6px;
}
.products-header .page-subtitle {
    color: rgba(255,255,255,0.8);
    font-size: 1.05rem;
}
.products-header .search-box .input-group {
    background: rgba(255,255,255,0.15);
    border-radius: 50px;
    overflow: hidden;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2);
}
.products-header .search-box .form-control {
    background: transparent;
    border: none;
    color: #fff;
    padding: 12px 20px;
    font-size: 1rem;
}
.products-header .search-box .form-control::placeholder {
    color: rgba(255,255,255,0.6);
}
.products-header .search-box .form-control:focus {
    box-shadow: none;
    background: rgba(255,255,255,0.05);
}
.products-header .search-box .btn-search {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    padding: 12px 24px;
    transition: 0.3s;
}
.products-header .search-box .btn-search:hover {
    background: rgba(255,255,255,0.3);
}

/* أزرار الترتيب */
.filter-section {
    background: #fff;
    border-radius: 20px;
    padding: 16px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid #F1F5F9;
    margin-bottom: 30px;
}
.filter-section .btn-group .btn {
    border-radius: 30px !important;
    padding: 8px 20px;
    font-weight: 600;
    font-size: 0.85rem;
    border: 2px solid transparent;
    transition: 0.3s;
}
.filter-section .btn-group .btn-outline-secondary {
    border-color: #E2E8F0;
    color: #64748B;
}
.filter-section .btn-group .btn-outline-secondary:hover {
    border-color: #4F46E5;
    color: #4F46E5;
    background: #EEF2FF;
}
.filter-section .btn-group .btn.active {
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 4px 15px rgba(79,70,229,0.25);
}
.filter-section .form-select {
    border-radius: 30px;
    border: 2px solid #E2E8F0;
    padding: 8px 20px;
    font-weight: 500;
    color: #1E293B;
    cursor: pointer;
    transition: 0.3s;
}
.filter-section .form-select:focus {
    border-color: #4F46E5;
    box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
}

/* بطاقات المنتجات */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 24px;
}
.product-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid #F1F5F9;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}
.product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(79,70,229,0.10);
    border-color: #E0E7FF;
}
.product-card .product-image {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: #F8FAFC;
}
.product-card .product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.product-card:hover .product-image img {
    transform: scale(1.05);
}
.product-card .product-image .badge-condition {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 4px 16px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 700;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.product-card .product-image .btn-favorite {
    position: absolute;
    top: 12px;
    left: 12px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.9);
    border: none;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    transition: 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #94A3B8;
}
.product-card .product-image .btn-favorite:hover {
    transform: scale(1.1);
    background: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.product-card .product-image .btn-favorite.active {
    color: #EF4444;
}
.product-card .product-body {
    padding: 18px 20px 16px;
}
.product-card .product-body .product-name {
    font-weight: 700;
    font-size: 1.05rem;
    color: #1E293B;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.product-card .product-body .product-name a {
    color: inherit;
    text-decoration: none;
    transition: 0.3s;
}
.product-card .product-body .product-name a:hover {
    color: #4F46E5;
}
.product-card .product-body .product-desc {
    font-size: 0.85rem;
    color: #94A3B8;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 10px;
    min-height: 40px;
}
.product-card .product-body .product-price {
    font-size: 1.4rem;
    font-weight: 900;
    color: #4F46E5;
}
.product-card .product-body .product-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #F1F5F9;
}
.product-card .product-body .product-meta .seller-name {
    font-size: 0.8rem;
    color: #64748B;
}
.product-card .product-body .product-meta .seller-name i {
    margin-left: 4px;
}
.product-card .product-body .product-meta .product-location {
    font-size: 0.75rem;
    color: #94A3B8;
}
.product-card .product-footer {
    padding: 0 20px 18px;
}
.product-card .product-footer .btn-details {
    width: 100%;
    border-radius: 30px;
    padding: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border: none;
    color: #fff;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(79,70,229,0.2);
}
.product-card .product-footer .btn-details:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(79,70,229,0.35);
}

/* ترقيم الصفحات */
.pagination-custom .page-link {
    border-radius: 12px !important;
    border: none;
    padding: 10px 18px;
    margin: 0 4px;
    color: #475569;
    font-weight: 600;
    background: #F1F5F9;
    transition: 0.3s;
}
.pagination-custom .page-link:hover {
    background: #E2E8F0;
    color: #4F46E5;
}
.pagination-custom .page-item.active .page-link {
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    color: #fff;
    box-shadow: 0 4px 15px rgba(79,70,229,0.25);
}
.pagination-custom .page-item.disabled .page-link {
    background: #F8FAFC;
    color: #CBD5E1;
}

/* حالة عدم وجود منتجات */
.empty-state {
    text-align: center;
    padding: 80px 20px;
}
.empty-state i {
    font-size: 4rem;
    color: #CBD5E1;
    display: block;
    margin-bottom: 16px;
}
.empty-state h4 {
    color: #1E293B;
    font-weight: 700;
}
.empty-state p {
    color: #94A3B8;
    font-size: 1rem;
}

/* التوافق مع الشاشات الصغيرة */
@media (max-width: 768px) {
    .products-header {
        padding: 30px 0 25px;
        border-radius: 0 0 25px 25px;
    }
    .products-header .page-title {
        font-size: 1.6rem;
    }
    .filter-section .btn-group .btn {
        font-size: 0.75rem;
        padding: 6px 14px;
    }
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 16px;
    }
    .product-card .product-image {
        height: 160px;
    }
    .product-card .product-body .product-price {
        font-size: 1.1rem;
    }
    .product-card .product-body .product-name {
        font-size: 0.95rem;
    }
}
@media (max-width: 576px) {
    .filter-section {
        padding: 12px 16px;
    }
    .filter-section .btn-group {
        flex-wrap: wrap;
        gap: 6px;
    }
    .product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    .product-card .product-image {
        height: 140px;
    }
    .product-card .product-body {
        padding: 12px 14px 10px;
    }
    .product-card .product-body .product-desc {
        display: none;
    }
    .product-card .product-footer {
        padding: 0 14px 12px;
    }
}
</style>

<!-- ==========================================
     رأس الصفحة
     ========================================== -->
<section class="products-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="page-title"><i class="bi bi-grid-3x3-gap-fill"></i> جميع المنتجات</h1>
                <p class="page-subtitle">اكتشف أفضل العروض على المنتجات الجديدة والمستعملة</p>
            </div>
            <div class="col-lg-6">
                <div class="search-box">
                    <form method="GET" action="">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="ابحث عن منتج..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-search" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     الفلترة والترتيب
     ========================================== -->
<div class="container">
    <div class="filter-section">
        <div class="row align-items-center g-3">
            <div class="col-md-7">
                <div class="d-flex flex-wrap gap-2">
                    <span class="fw-semibold text-muted small me-2">ترتيب:</span>
                    <div class="btn-group" role="group">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest'])); ?>" class="btn btn-outline-secondary <?php echo $sort === 'newest' ? 'active' : ''; ?>">
                            <i class="bi bi-clock"></i> الأحدث
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_low'])); ?>" class="btn btn-outline-secondary <?php echo $sort === 'price_low' ? 'active' : ''; ?>">
                            <i class="bi bi-arrow-up"></i> الأقل سعراً
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_high'])); ?>" class="btn btn-outline-secondary <?php echo $sort === 'price_high' ? 'active' : ''; ?>">
                            <i class="bi bi-arrow-down"></i> الأعلى سعراً
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <form method="GET" action="">
                    <div class="d-flex gap-2">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <select class="form-select" name="category" onchange="this.form.submit()">
                            <option value="0">📂 جميع الأقسام</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==========================================
         عرض المنتجات
         ========================================== -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger rounded-4"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h4>لا توجد منتجات</h4>
            <p>لم يتم العثور على منتجات مطابقة لبحثك. حاول تغيير الفلترة أو البحث.</p>
            <a href="index.php" class="btn btn-primary rounded-pill mt-3">عرض جميع المنتجات</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($product['primary_image']): ?>
                            <img src="../uploads/products/<?php echo htmlspecialchars($product['primary_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                <i class="bi bi-image" style="font-size: 3rem; color: #CBD5E1;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <span class="badge-condition bg-<?php echo $product['condition'] == 'new' ? 'success' : 'warning'; ?> text-white">
                            <?php echo $product['condition'] == 'new' ? 'جديد' : 'مستعمل'; ?>
                        </span>
                        
                        <?php if (isLoggedIn()): ?>
                            <button class="btn-favorite favorite-btn <?php echo $isFavorite ?? false ? 'active' : ''; ?>" 
                                    data-product-id="<?php echo $product['id']; ?>">
                                <i class="bi <?php echo ($isFavorite ?? false) ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-body">
                        <h5 class="product-name">
                            <a href="details.php?id=<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h5>
                        <div class="product-desc"><?php echo htmlspecialchars($product['description']); ?></div>
                        
                        <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                        
                        <div class="product-meta">
                            <span class="seller-name">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($product['seller_name']); ?>
                            </span>
                            <span class="product-location">
                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($product['location']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="product-footer">
                        <a href="details.php?id=<?php echo $product['id']; ?>" class="btn-details">
                            <i class="bi bi-eye"></i> تفاصيل المنتج
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ==========================================
             ترقيم الصفحات
             ========================================== -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-5">
                <ul class="pagination pagination-custom justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="bi bi-chevron-right"></i> السابق
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="bi bi-chevron-right"></i> السابق</span>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                التالي <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">التالي <i class="bi bi-chevron-left"></i></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// تضمين الفوتر
require_once '../includes/footer.php';
?>

<!-- ==========================================
     كود JavaScript
     ========================================== -->
<script>
    // إضافة/إزالة المفضلة باستخدام AJAX
    document.querySelectorAll('.favorite-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const productId = this.dataset.productId;
            const icon = this.querySelector('i');
            const isCurrentlyFavorite = icon.classList.contains('bi-heart-fill');
            const action = isCurrentlyFavorite ? 'remove' : 'add';
            
            fetch('../favorites/toggle.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `product_id=${productId}&action=${action}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.is_favorite) {
                        icon.classList.replace('bi-heart', 'bi-heart-fill');
                        this.classList.add('active');
                    } else {
                        icon.classList.replace('bi-heart-fill', 'bi-heart');
                        this.classList.remove('active');
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
</script>
</body>
</html>