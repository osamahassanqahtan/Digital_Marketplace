<?php
/**
 * products/add.php
 * إضافة منتج جديد (للبائعين والإداريين)
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// التحقق من تسجيل الدخول ودور البائع أو المدير
requireLogin('../auth/login.php');
requireRole(['seller', 'admin'], '../index.php');

$errors = [];
$success = false;

// جلب الأقسام لعرضها في القائمة المنسدلة
try {
    $db = Database::getConnection();
    $stmt = $db->query('SELECT id, name FROM categories ORDER BY name');
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'حدث خطأ في تحميل الأقسام.';
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
    $seller_id      = getCurrentUserId();

    // التحقق من الحقول
    if (empty($name)) {
        $errors[] = 'اسم المنتج مطلوب.';
    } elseif (strlen($name) < 3) {
        $errors[] = 'اسم المنتج يجب أن لا يقل عن 3 أحرف.';
    }

    if (empty($description)) {
        $errors[] = 'الوصف مطلوب.';
    } elseif (strlen($description) < 10) {
        $errors[] = 'الوصف يجب أن لا يقل عن 10 أحرف.';
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

    if (empty($contact_phone)) {
        $errors[] = 'رقم التواصل مطلوب.';
    } elseif (!isValidPhone($contact_phone)) {
        $errors[] = 'رقم التواصل غير صحيح.';
    }

    // معالجة الصور
    $uploadedImages = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $totalFiles = count($_FILES['images']['name']);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5 MB

        for ($i = 0; $i < $totalFiles; $i++) {
            $fileName = $_FILES['images']['name'][$i];
            $fileType = $_FILES['images']['type'][$i];
            $fileTmp = $_FILES['images']['tmp_name'][$i];
            $fileError = $_FILES['images']['error'][$i];
            $fileSize = $_FILES['images']['size'][$i];

            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = "خطأ في رفع الصورة: $fileName";
                continue;
            }
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "نوع الملف غير مدعوم: $fileName (يسمح بـ JPEG, PNG, WEBP, GIF)";
                continue;
            }
            if ($fileSize > $maxSize) {
                $errors[] = "حجم الصورة $fileName يتجاوز 5 ميجابايت";
                continue;
            }

            // إنشاء اسم فريد
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newName = uniqid() . '.' . $ext;
            $uploadPath = '../uploads/products/' . $newName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $uploadedImages[] = $newName;
            } else {
                $errors[] = "فشل رفع الصورة: $fileName";
            }
        }
    } else {
        $errors[] = 'يرجى رفع صورة واحدة على الأقل للمنتج.';
    }

    // إذا لم توجد أخطاء، أدخل المنتج
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare('INSERT INTO products (seller_id, category_id, name, description, price, condition, location, contact_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$seller_id, $category_id, $name, $description, $price, $condition, $location, $contact_phone, 'available']);
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
            // إعادة تعيين النموذج بعد النجاح
            $_POST = [];
            $uploadedImages = [];
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'حدث خطأ في حفظ المنتج، حاول مرة أخرى.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة منتج جديد - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h4>إضافة منتج جديد</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">تم إضافة المنتج بنجاح! <a href="view.php?id=<?php echo $productId; ?>">عرض المنتج</a></div>
                        <?php endif; ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="name" class="form-label">اسم المنتج</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="category_id" class="form-label">القسم</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">اختر القسم</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">الوصف</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">السعر (ريال)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="condition" class="form-label">الحالة</label>
                                    <select class="form-select" id="condition" name="condition">
                                        <option value="new" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'new') ? 'selected' : ''; ?>>جديد</option>
                                        <option value="used" <?php echo (!isset($_POST['condition']) || $_POST['condition'] == 'used') ? 'selected' : ''; ?>>مستعمل</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">الموقع</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">رقم التواصل</label>
                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="images" class="form-label">الصور (يمكنك اختيار عدة صور)</label>
                                <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*" required>
                                <small class="text-muted">الصيغ المسموحة: JPEG, PNG, WEBP, GIF - الحد الأقصى 5 ميجابايت لكل صورة</small>
                            </div>
                            <button type="submit" class="btn btn-success w-100">إضافة المنتج</button>
                        </form>
                        <div class="mt-3">
                            <a href="../index.php" class="btn btn-secondary">العودة للرئيسية</a>
                            <?php if (hasRole('seller')): ?>
                                <a href="../seller/dashboard.php" class="btn btn-outline-primary">لوحة البائع</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>