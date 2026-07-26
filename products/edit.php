<?php
/**
 * products/edit.php
 * تعديل منتج موجود (للبائع أو المدير)
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

requireLogin('../auth/login.php');
requireRole(['seller', 'admin'], '../index.php');

$productId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($productId < 1) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

try {
    $db = Database::getConnection();

    // جلب بيانات المنتج مع صلاحية التحقق
    $sql = "SELECT p.*, u.id as seller_id FROM products p 
            JOIN users u ON p.seller_id = u.id 
            WHERE p.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        header('Location: index.php');
        exit;
    }

    // التحقق من الصلاحية: البائع نفسه أو مدير
    $currentUserId = getCurrentUserId();
    $currentRole = getCurrentUserRole();
    if ($currentRole !== 'admin' && $currentUserId != $product['seller_id']) {
        header('Location: index.php');
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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $name           = sanitizeInput($_POST['name'] ?? '');
    $description    = sanitizeInput($_POST['description'] ?? '');
    $price          = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $category_id    = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
    $condition      = $_POST['condition'] ?? 'used';
    $location       = sanitizeInput($_POST['location'] ?? '');
    $contact_phone  = sanitizeInput($_POST['contact_phone'] ?? '');
    $status         = $_POST['status'] ?? 'available';

    // التحقق من الحقول (نفس التحقق من add.php)
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

    // معالجة الصور الجديدة (إن وجدت)
    $uploadedImages = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize = 5 * 1024 * 1024;
        $totalFiles = count($_FILES['images']['name']);

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
                $errors[] = "نوع الملف غير مدعوم: $fileName";
                continue;
            }
            if ($fileSize > $maxSize) {
                $errors[] = "حجم الصورة $fileName يتجاوز 5 ميجابايت";
                continue;
            }

            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newName = uniqid() . '.' . $ext;
            $uploadPath = '../uploads/products/' . $newName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $uploadedImages[] = $newName;
            } else {
                $errors[] = "فشل رفع الصورة: $fileName";
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

            // إضافة الصور الجديدة (إذا وجدت)
            if (!empty($uploadedImages)) {
                $isPrimary = empty($existingImages) ? true : false; // إذا لم توجد صور سابقة، أول صورة تكون أساسية
                $stmtImgInsert = $db->prepare('INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)');
                foreach ($uploadedImages as $img) {
                    $stmtImgInsert->execute([$productId, $img, $isPrimary ? 1 : 0]);
                    $isPrimary = false;
                }
            }

            $db->commit();
            $success = true;
            // إعادة تحميل البيانات المحدثة
            $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            // تحديث قائمة الصور
            $stmtImg = $db->prepare('SELECT id, image_path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC');
            $stmtImg->execute([$productId]);
            $existingImages = $stmtImg->fetchAll();

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
    <title>تعديل المنتج - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4><i class="bi bi-pencil"></i> تعديل المنتج</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">تم تحديث المنتج بنجاح! <a href="view.php?id=<?php echo $productId; ?>">عرض المنتج</a></div>
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
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="category_id" class="form-label">القسم</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">اختر القسم</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] ?? 0) == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">الوصف</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">السعر (ريال)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($product['price'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="condition" class="form-label">الحالة</label>
                                    <select class="form-select" id="condition" name="condition">
                                        <option value="new" <?php echo ($product['condition'] ?? '') == 'new' ? 'selected' : ''; ?>>جديد</option>
                                        <option value="used" <?php echo ($product['condition'] ?? '') == 'used' ? 'selected' : ''; ?>>مستعمل</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">الموقع</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($product['location'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">رقم التواصل</label>
                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($product['contact_phone'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">حالة المنتج</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="available" <?php echo ($product['status'] ?? '') == 'available' ? 'selected' : ''; ?>>متاح</option>
                                    <option value="sold" <?php echo ($product['status'] ?? '') == 'sold' ? 'selected' : ''; ?>>تم البيع</option>
                                    <option value="pending" <?php echo ($product['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                                </select>
                            </div>

                            <!-- عرض الصور الحالية -->
                            <?php if (!empty($existingImages)): ?>
                                <div class="mb-3">
                                    <label class="form-label">الصور الحالية</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($existingImages as $img): ?>
                                            <div class="position-relative">
                                                <img src="../uploads/products/<?php echo htmlspecialchars($img['image_path']); ?>" 
                                                     style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; border-radius: 5px;" 
                                                     alt="صورة المنتج">
                                                <?php if ($img['is_primary']): ?>
                                                    <span class="badge bg-primary position-absolute top-0 start-0">أساسية</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">لحذف الصور، يمكنك تعديل ذلك لاحقاً عبر لوحة التحكم (ميزة قادمة).</small>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="images" class="form-label">إضافة صور جديدة (اختياري)</label>
                                <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                                <small class="text-muted">يمكنك إضافة صور جديدة بجانب الصور الموجودة. الصيغ المسموحة: JPEG, PNG, WEBP, GIF - الحد الأقصى 5 ميجابايت لكل صورة</small>
                            </div>

                            <button type="submit" class="btn btn-warning w-100"><i class="bi bi-save"></i> تحديث المنتج</button>
                        </form>
                        <div class="mt-3">
                            <a href="view.php?id=<?php echo $productId; ?>" class="btn btn-secondary">العودة للتفاصيل</a>
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