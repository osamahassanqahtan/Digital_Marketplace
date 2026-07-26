<?php
/**
 * products/category.php
 * عرض المنتجات حسب القسم - تصميم عصري وجذاب
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';

// جلب معرف القسم من الرابط
$categoryId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($categoryId < 1) {
    header('Location: index.php');
    exit;
}

// جلب معلمات البحث والترتيب
$search = sanitizeInput($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$limit = 12;
$offset = ($page - 1) * $limit;

try {
    $db = Database::getConnection();
    
    // جلب معلومات القسم
    $stmtCat = $db->prepare('SELECT id, name, slug FROM categories WHERE id = ?');
    $stmtCat->execute([$categoryId]);
    $category = $stmtCat->fetch();
    
    if (!$category) {
        header('Location: index.php');
        exit;
    }
    
    // بناء استعلام البحث مع فلترة القسم
    $whereConditions = ['p.category_id = ?', 'p.status = "available"'];
    $params = [$categoryId];
    
    if (!empty($search)) {
        $whereConditions[] = '(p.name LIKE ? OR p.description LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // ترتيب النتائج
    $orderBy = match($sort) {
        'price_low' => 'p.price ASC',
        'price_high' => 'p.price DESC',
        'oldest' => 'p.created_at ASC',
        default => 'p.created_at DESC'
    };
    
    // جلب عدد المنتجات الإجمالي للصفحات
    $countSql = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    $totalPages = ceil($total / $limit);
    
    // جلب المنتجات مع بيانات البائع والصورة الأساسية
    $sql = "SELECT p.*, u.name as seller_name,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p
            JOIN users u ON p.seller_id = u.id
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // جلب جميع الأقسام لقائمة التنقل السريع
    $stmtAllCats = $db->query('SELECT id, name FROM categories ORDER BY name');
    $allCategories = $stmtAllCats->fetchAll();

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل المنتجات.';
    $products = [];
    $allCategories = [];
}

// تضمين الهيدر الجديد
require_once '../includes/header.php';
?>

<style>
/* ==========================================
   تصميم صفحة القسم - عصري وجذاب
   ========================================== */

/* رأس القسم */
.category-header {
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 50%, #6D28D9 100%);
    padding: 50px 0 40px;
    border-radius: 0 0 40px 40px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}
.category-header::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.category-header::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
}
.category-header .category-icon {
    font-size: 3.5rem;
    color: rgba(255,255,255,0.2);
    position: absolute;
    left: 30px;
    bottom: 20px;
}
.category-header .page-title {
    color: #fff;
    font-weight: 900;
    font-size: 2.5rem;
    margin-bottom: 4px;
}
.category-header .page-title i {
    color: #FCD34D;
}
.category-header .page-subtitle {
    color: rgba(255,255,255,0.8);
    font-size: 1.05rem;
}
.category-header .product-count {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(4px);
    padding: 4px 18px;
    border-radius: 30px;
    color: #fff;
    font-size: 0.9rem;
    margin-top: 8px;
    border: 1px solid rgba(255,255,255,0.1);
}
.category-header .product-count i {
    margin-left: 6px;
}

/* شريط البحث والفلترة */
.filter-section {
    background: #fff;
    border-radius: 20px;
    padding: 16px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid #F1F5F9;
    margin-bottom: 30px;
}
.filter-section .search-box .input-group {
    border-radius: 30px;
    overflow: hidden;
    border: 2px solid #E2E8F0;
    transition: 0.3s;
}
.filter-section .search-box .input-group:focus-within {
    border-color: #4F46E5;
    box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
}
.filter-section .search-box .form-control {
    border: none;
    padding: 10px 18px;
    font-size: 0.95rem;
}
.filter-section .search-box .form-control:focus {
    box-shadow: none;
}
.filter-section .search-box .btn-search {
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border: none;
    color: #fff;
    padding: 10px 24px;
    font-weight: 600;
    transition: 0.3s;
}
.filter-section .search-box .btn-search:hover {
    opacity: 0.9;
}

.filter-section .sort-buttons .btn {
    border-radius: 30px !important;
    padding: 8px 20px;
    font-weight: 600;
    font-size: 0.85rem;
    border: 2px solid #E2E8F0;
    color: #64748B;
    transition: 0.3s;
}
.filter-section .sort-buttons .btn:hover {
    border-color: #4F46E5;
    color: #4F46E5;
    background: #EEF2FF;
}
.filter-section .sort-buttons .btn.active {
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 4px 15px rgba(79,70,229,0.25);
}
.filter-section .sort-buttons .btn i {
    margin-left: 4px;
}

/* أزرار الأقسام السريعة */
.category-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 30px;
}
.category-pills .pill {
    padding: 6px 18px;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: 0.3s;
    text-decoration: none;
    border: 2px solid #E2E8F0;
    color: #64748B;
    background: #fff;
}
.category-pills .pill:hover {
    border-color: #4F46E5;
    color: #4F46E5;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79,70,229,0.08);
}
.category-pills .pill.active {
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 4px 15px rgba(79,70,229,0.2);
}
.category-pills .pill i {
    margin-left: 4px;
}

/* شبكة المنتجات */
.products-grid {
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
.product-card .product-body {
    padding: 18px 20px 14px;
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
    .category-header {
        padding: 30px 0 25px;
        border-radius: 0 0 25px 25px;
    }
    .category-header .page-title {
        font-size: 1.8rem;
    }
    .category-header .category-icon {
        font-size: 2.5rem;
        left: 15px;
        bottom: 10px;
    }
    .filter-section {
        padding: 12px 16px;
    }
    .filter-section .sort-buttons .btn {
        font-size: 0.75rem;
        padding: 6px 14px;
    }
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
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
    .category-pills .pill {
        font-size: 0.7rem;
        padding: 4px 12px;
    }
}
@media (max-width: 576px) {
    .products-grid {
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
    .product-card .product-footer .btn-details {
        font-size: 0.8rem;
        padding: 8px;
    }
}
</style>

<!-- ==========================================
     رأس القسم
     ========================================== -->
<section class="category-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="page-title">
                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($category['name']); ?>
                </h1>
                <p class="page-subtitle">استكشف جميع المنتجات في هذا القسم</p>
                <span class="product-count">
                    <i class="bi bi-box"></i> <?php echo $total; ?> منتج
                </span>
            </div>
            <div class="col-lg-4 text-center d-none d-lg-block">
                <i class="bi bi-grid category-icon"></i>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     المحتوى
     ========================================== -->
<div class="container pb-4">
    
    <!-- شريط البحث والفلترة -->
    <div class="filter-section">
        <div class="row align-items-center g-3">
            <div class="col-md-5">
                <div class="search-box">
                    <form method="GET" action="">
                        <input type="hidden" name="id" value="<?php echo $categoryId; ?>">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="🔍 ابحث في هذا القسم..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-search" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-md-7">
                <div class="sort-buttons d-flex flex-wrap gap-2 justify-content-md-end">
                    <a href="?id=<?php echo $categoryId; ?>&sort=newest<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $sort === 'newest' ? 'active' : ''; ?>">
                        <i class="bi bi-clock"></i> الأحدث
                    </a>
                    <a href="?id=<?php echo $categoryId; ?>&sort=price_low<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $sort === 'price_low' ? 'active' : ''; ?>">
                        <i class="bi bi-arrow-up"></i> الأقل سعراً
                    </a>
                    <a href="?id=<?php echo $categoryId; ?>&sort=price_high<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $sort === 'price_high' ? 'active' : ''; ?>">
                        <i class="bi bi-arrow-down"></i> الأعلى سعراً
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- الأقسام السريعة -->
    <div class="category-pills">
        <a href="index.php" class="pill <?php echo !isset($_GET['id']) ? 'active' : ''; ?>">
            <i class="bi bi-grid"></i> جميع الأقسام
        </a>
        <?php foreach ($allCategories as $cat): ?>
            <a href="category.php?id=<?php echo $cat['id']; ?>" class="pill <?php echo $cat['id'] == $categoryId ? 'active' : ''; ?>">
                <i class="bi bi-tag"></i> <?php echo htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- عرض المنتجات -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger rounded-4"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h4>لا توجد منتجات</h4>
            <p>
                <?php if (!empty($search)): ?>
                    لم يتم العثور على نتائج مطابقة لبحثك في هذا القسم.
                <?php else: ?>
                    لا توجد منتجات في هذا القسم حالياً.
                <?php endif; ?>
            </p>
            <a href="category.php?id=<?php echo $categoryId; ?>" class="btn btn-primary rounded-pill mt-3">
                <i class="bi bi-arrow-counterclockwise"></i> عرض الكل
            </a>
        </div>
    <?php else: ?>
        <div class="products-grid">
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

        <!-- ترقيم الصفحات -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-5">
                <ul class="pagination pagination-custom justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?php echo $categoryId; ?>&page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
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
                            <a class="page-link" href="?id=<?php echo $categoryId; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?php echo $categoryId; ?>&page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
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
// تضمين الفوتر الجديد
require_once '../includes/footer.php';
?>
</body>
</html>