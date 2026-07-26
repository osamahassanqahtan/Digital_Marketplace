<?php
/**
 * seller/add-product.php
 * إضافة منتج جديد من خلال لوحة البائع
 * تم تصحيح خطأ SQL الناتج عن استخدام كلمة 'condition' المحجوزة
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
$errors = [];
$success = false;

// جلب الأقسام لعرضها في القائمة المنسدلة
try {
    $db = Database::getConnection();
    $stmt = $db->query('SELECT id, name FROM categories ORDER BY name');
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'حدث خطأ في تحميل الأقسام.';
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تنظيف البيانات
    $name           = sanitizeInput($_POST['name'] ?? '');
    $description    = sanitizeInput($_POST['description'] ?? '');
    $price          = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $category_id    = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
    $condition      = $_POST['condition'] ?? 'used';
    $location       = sanitizeInput($_POST['location'] ?? '');
    $contact_phone  = sanitizeInput($_POST['contact_phone'] ?? '');
    $status         = $_POST['status'] ?? 'available';

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

    // معالجة الصور
    $uploadedImages = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $totalFiles = count($_FILES['images']['name']);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5 MB

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
    } else {
        $errors[] = 'يرجى رفع صورة واحدة على الأقل للمنتج.';
    }

    // إذا لم توجد أخطاء، أدخل المنتج
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // ✅ التصحيح: استخدام backticks حول `condition` لأنها كلمة محجوزة في MySQL
            $stmt = $db->prepare('INSERT INTO products (seller_id, category_id, name, description, price, `condition`, location, contact_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $category_id, $name, $description, $price, $condition, $location, $contact_phone, $status]);
            $productId = $db->lastInsertId();

            // إدراج الصور
            $isPrimary = true;
            $stmtImg = $db->prepare('INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)');
            foreach ($uploadedImages as $img) {
                $stmtImg->execute([$productId, $img, $isPrimary ? 1 : 0]);
                $isPrimary = false;
            }

            $db->commit();
            $success = true;
            $productAdded = $productId;
            
            // إعادة تعيين النموذج بعد النجاح
            $_POST = [];
            
        } catch (PDOException $e) {
            $db->rollBack();
            // عرض الخطأ الحقيقي للمطور (يمكن إخفاؤه في الإنتاج)
            $errors[] = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة منتج جديد - لوحة البائع</title>
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
    

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-plus-circle"></i> إضافة منتج جديد</h2>
            <a href="dashboard.php" class="btn btn-secondary rounded-pill">
                <i class="bi bi-arrow-right"></i> العودة للوحة
            </a>
        </div>

        <?php if ($success && isset($productAdded)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4">
                <i class="bi bi-check-circle"></i> تم إضافة المنتج بنجاح!
                <a href="../products/details.php?id=<?php echo $productAdded; ?>" class="alert-link">عرض المنتج</a>
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

        <div class="form-section">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">اسم المنتج <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">القسم <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">اختر القسم</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">الوصف <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="price" class="form-label">السعر (ريال) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="condition" class="form-label">الحالة</label>
                        <select class="form-select" id="condition" name="condition">
                            <option value="new" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'new') ? 'selected' : ''; ?>>جديد</option>
                            <option value="used" <?php echo (!isset($_POST['condition']) || $_POST['condition'] == 'used') ? 'selected' : ''; ?>>مستعمل</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">حالة العرض</label>
                        <select class="form-select" id="status" name="status">
                            <option value="available" <?php echo (isset($_POST['status']) && $_POST['status'] == 'available') ? 'selected' : ''; ?>>متاح</option>
                            <option value="pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="location" class="form-label">الموقع <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" placeholder="المدينة / المنطقة" required>
                    </div>
                    <div class="col-md-6">
                        <label for="contact_phone" class="form-label">رقم التواصل <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="images" class="form-label">الصور <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*" required>
                        <div class="image-preview" id="imagePreview"></div>
                        <small class="text-muted">الصيغ المسموحة: JPEG, PNG, WEBP, GIF - الحد الأقصى 5 ميجابايت لكل صورة</small>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-submit btn-primary w-100 text-white">
                            <i class="bi bi-save"></i> إضافة المنتج
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // معاينة الصور قبل الرفع
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
        });
    </script>
</body>
</html>