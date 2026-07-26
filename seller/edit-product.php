<?php
/**
 * seller/edit-product.php
 * تعديل منتج من لوحة البائع (للبائع أو المدير)
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';
require_once '../includes/upload.php';

// التحقق من تسجيل الدخول ودور البائع أو المدير
requireLogin('../auth/login.php');
requireRole(['seller', 'admin'], '../index.php');

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$productId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$redirect = $_GET['redirect'] ?? 'dashboard.php';

if ($productId < 1) {
    setFlashMessage('error', 'معرف المنتج غير صحيح.', 'danger');
    header('Location: ' . $redirect);
    exit;
}

$errors = [];
$success = false;

try {
    $db = Database::getConnection();
    
    // جلب بيانات المنتج مع التحقق من الصلاحية
    $sql = "SELECT p.*, u.id as seller_id FROM products p 
            JOIN users u ON p.seller_id = u.id 
            WHERE p.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        setFlashMessage('error', 'المنتج غير موجود.', 'danger');
        header('Location: ' . $redirect);
        exit;
    }
    
    // التحقق من الصلاحية: البائع نفسه أو مدير
    if ($userRole !== 'admin' && $userId != $product['seller_id']) {
        setFlashMessage('error', 'لا تملك صلاحية تعديل هذا المنتج.', 'danger');
        header('Location: ' . $redirect);
        exit;
    }
    
    // جلب الأقسام
    $stmtCat = $db->query('SELECT id, name FROM categories ORDER BY name');
    $categories = $stmtCat->fetchAll();
    
    // جلب صور المنتج الحالية
    $stmtImg = $db->prepare('SELECT id, image_path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC');
    $stmtImg->execute([$productId]);
    $existingImages = $stmtImg->fetchAll();
    
} catch (PDOException $e) {
    $errors[] = 'حدث خطأ في تحميل بيانات المنتج.';
    $product = null;
    $categories = [];
    $existingImages = [];
}

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($product)) {
    $name           = sanitizeInput($_POST['name'] ?? '');
    $description    = sanitizeInput($_POST['description'] ?? '');
    $price          = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $category_id    = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
    $condition      = $_POST['condition'] ?? 'used';
    $location       = sanitizeInput($_POST['location'] ?? '');
    $contact_phone  = sanitizeInput($_POST['contact_phone'] ?? '');
    $status         = $_POST['status'] ?? 'available';
    $deleteImages   = $_POST['delete_images'] ?? [];
    
    // التحقق من الحقول
    if (empty($name) || strlen($name) < 3) {
        $errors[] = 'اسم المنتج مطلوب (3 أحرف على الأقل).';
    }
    if (empty($description) || strlen($description) < 10) {
        $errors[] = 'الوصف مطلوب (10 أحرف على الأقل).';
    }
    if ($price === false || $price < 0) {
        $errors[] = 'السعر يجب أن يكون رقماً موجباً.';
    }
    if (empty($category_id) || $category_id < 1) {
        $errors[] = 'يرجى اختيار القسم.';
    }
    if (empty($location)) {
        $errors[] = 'الموقع مطلوب.';
    }
    if (empty($contact_phone) || !isValidPhone($contact_phone)) {
        $errors[] = 'رقم التواصل غير صحيح.';
    }
    
    // معالجة حذف الصور المحددة
    $imagesToDelete = array_filter($deleteImages, 'is_numeric');
    if (!empty($imagesToDelete)) {
        try {
            foreach ($imagesToDelete as $imgId) {
                $stmt = $db->prepare('SELECT image_path FROM product_images WHERE id = ? AND product_id = ?');
                $stmt->execute([$imgId, $productId]);
                $img = $stmt->fetch();
                if ($img) {
                    $filePath = '../uploads/products/' . $img['image_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $stmtDel = $db->prepare('DELETE FROM product_images WHERE id = ?');
                    $stmtDel->execute([$imgId]);
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'حدث خطأ في حذف الصور.';
        }
    }
    
    // معالجة الصور الجديدة
    $uploadedImages = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $totalFiles = count($_FILES['images']['name']);
        for ($i = 0; $i < $totalFiles; $i++) {
            $file = [
                'name' => $_FILES['images']['name'][$i],
                'type' => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error' => $_FILES['images']['error'][$i],
                'size' => $_FILES['images']['size'][$i]
            ];
            $result = uploadImage($file, '../uploads/products/', 1200, 1200);
            if ($result['success']) {
                $uploadedImages[] = $result['filename'];
            } else {
                $errors[] = $result['message'] . ' (' . $_FILES['images']['name'][$i] . ')';
            }
        }
    }
    
    // إذا لم توجد أخطاء، نفذ التحديث
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // تحديث بيانات المنتج
            $stmt = $db->prepare('UPDATE products SET 
                name = ?, description = ?, price = ?, category_id = ?, 
                condition = ?, location = ?, contact_phone = ?, status = ? 
                WHERE id = ?');
            $stmt->execute([$name, $description, $price, $category_id, 
                $condition, $location, $contact_phone, $status, $productId]);
            
            // إضافة الصور الجديدة
            if (!empty($uploadedImages)) {
                // التحقق من وجود صور حالية لتحديد أول صورة كأساسية
                $stmtCheck = $db->prepare('SELECT COUNT(*) as count FROM product_images WHERE product_id = ?');
                $stmtCheck->execute([$productId]);
                $count = $stmtCheck->fetch()['count'];
                $isPrimary = ($count == 0);
                
                $stmtImgInsert = $db->prepare('INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)');
                foreach ($uploadedImages as $img) {
                    $stmtImgInsert->execute([$productId, $img, $isPrimary ? 1 : 0]);
                    $isPrimary = false;
                }
            }
            
            $db->commit();
            $success = true;
            
            // تحديث بيانات المنتج المعروضة
            $product['name'] = $name;
            $product['description'] = $description;
            $product['price'] = $price;
            $product['category_id'] = $category_id;
            $product['condition'] = $condition;
            $product['location'] = $location;
            $product['contact_phone'] = $contact_phone;
            $product['status'] = $status;
            
            // تحديث قائمة الصور
            $stmtImg = $db->prepare('SELECT id, image_path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC');
            $stmtImg->execute([$productId]);
            $existingImages = $stmtImg->fetchAll();
            
            setFlashMessage('success', 'تم تحديث المنتج بنجاح.', 'success');
            
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'حدث خطأ في تحديث المنتج، حاول مرة أخرى.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المنتج - لوحة البائع</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-section {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        .form-section .form-label {
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4F46E5;
            box-shadow: 0 0 0 0.2rem rgba(79,70,229,0.15);
        }
        .btn-submit {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 700;
            transition: 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79,70,229,0.3);
        }
        .image-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        .image-item {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #e9ecef;
        }
        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-item .badge-primary {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #0d6efd;
            color: #fff;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        .image-item .delete-check {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(0,0,0,0.6);
            color: #fff;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        .image-item .delete-check input {
            margin-left: 5px;
        }
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .image-preview .preview-item {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }
    </style>
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">🏪 منصة السوق</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="../products/index.php">المنتجات</a></li>
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">لوحة التحكم</a></li>
                    <li class="nav-item"><a class="nav-link" href="../auth/logout.php">تسجيل خروج</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pencil-square"></i> تعديل المنتج</h2>
            <a href="dashboard.php" class="btn btn-secondary rounded-pill">
                <i class="bi bi-arrow-right"></i> العودة للوحة
            </a>
        </div>

        <!-- عرض رسائل الفلاش -->
        <?php $flash = getFlashMessage('success'); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show rounded-4">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($product): ?>
        <div class="form-section">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">اسم المنتج <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">القسم <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">اختر القسم</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">الوصف <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="price" class="form-label">السعر (ريال) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="condition" class="form-label">الحالة</label>
                        <select class="form-select" id="condition" name="condition">
                            <option value="new" <?php echo $product['condition'] == 'new' ? 'selected' : ''; ?>>جديد</option>
                            <option value="used" <?php echo $product['condition'] == 'used' ? 'selected' : ''; ?>>مستعمل</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">حالة العرض</label>
                        <select class="form-select" id="status" name="status">
                            <option value="available" <?php echo $product['status'] == 'available' ? 'selected' : ''; ?>>متاح</option>
                            <option value="sold" <?php echo $product['status'] == 'sold' ? 'selected' : ''; ?>>تم البيع</option>
                            <option value="pending" <?php echo $product['status'] == 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="location" class="form-label">الموقع <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($product['location']); ?>" placeholder="المدينة / المنطقة" required>
                    </div>
                    <div class="col-md-6">
                        <label for="contact_phone" class="form-label">رقم التواصل <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($product['contact_phone']); ?>" required>
                    </div>
                    
                    <!-- الصور الحالية -->
                    <?php if (!empty($existingImages)): ?>
                        <div class="col-12">
                            <label class="form-label">الصور الحالية</label>
                            <div class="image-grid">
                                <?php foreach ($existingImages as $img): ?>
                                    <div class="image-item">
                                        <img src="../uploads/products/<?php echo htmlspecialchars($img['image_path']); ?>" alt="صورة المنتج">
                                        <?php if ($img['is_primary']): ?>
                                            <span class="badge-primary">أساسية</span>
                                        <?php endif; ?>
                                        <div class="delete-check">
                                            <label>
                                                <input type="checkbox" name="delete_images[]" value="<?php echo $img['id']; ?>">
                                                حذف
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">✓ اختر الصور التي تريد حذفها، ثم احفظ التغييرات.</small>
                        </div>
                    <?php endif; ?>

                    <!-- إضافة صور جديدة -->
                    <div class="col-12">
                        <label for="images" class="form-label">إضافة صور جديدة (اختياري)</label>
                        <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                        <div class="image-preview" id="imagePreview"></div>
                        <small class="text-muted">الصيغ المسموحة: JPEG, PNG, WEBP, GIF - الحد الأقصى 5 ميجابايت لكل صورة</small>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-submit btn-primary w-100 text-white">
                            <i class="bi bi-save"></i> تحديث المنتج
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // معاينة الصور الجديدة قبل الرفع
        document.getElementById('images').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            const files = this.files;
            for (let i = 0; i < files.length; i++) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = document.createElement('img');
                    img.src = event.target.result;
                    img.className = 'preview-item';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(files[i]);
            }
   