<?php
/**
 * includes/upload.php
 * دوال متخصصة في رفع الملفات بشكل آمن
 * تدعم الصور والملفات العامة مع التحقق من الأنواع والأحجام
 */

/**
 * رفع صورة مع تحسينات أمنية
 * @param array $file $_FILES['name']
 * @param string $targetDir المجلد الهدف (مثل '../uploads/products/')
 * @param int $maxWidth الحد الأقصى للعرض (اختياري، 0 يعني بدون تغيير)
 * @param int $maxHeight الحد الأقصى للارتفاع (اختياري، 0 يعني بدون تغيير)
 * @return array ['success' => bool, 'message' => string, 'filename' => string|null]
 */
function uploadImage(array $file, string $targetDir, int $maxWidth = 0, int $maxHeight = 0): array {
    // التحقق من وجود الملف
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطأ في رفع الملف: ' . uploadErrorMessage($file['error']), 'filename' => null];
    }
    
    // التحقق من نوع الملف
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'نوع الملف غير مدعوم. الأنواع المسموحة: JPEG, PNG, WEBP, GIF', 'filename' => null];
    }
    
    // التحقق من حجم الملف (5 ميجابايت افتراضياً)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'حجم الصورة يتجاوز 5 ميجابايت.', 'filename' => null];
    }
    
    // إنشاء اسم ملف فريد
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = uniqid() . '.' . $extension;
    $targetPath = rtrim($targetDir, '/') . '/' . $newName;
    
    // التأكد من وجود المجلد
    if (!ensureDirectoryExists($targetDir)) {
        return ['success' => false, 'message' => 'لا يمكن إنشاء المجلد الهدف.', 'filename' => null];
    }
    
    // نقل الملف المؤقت
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'message' => 'فشل نقل الصورة إلى المجلد النهائي.', 'filename' => null];
    }
    
    // ضبط الصلاحيات
    chmod($targetPath, 0644);
    
    // تغيير حجم الصورة إذا طُلب ذلك
    if ($maxWidth > 0 || $maxHeight > 0) {
        resizeImage($targetPath, $maxWidth, $maxHeight);
    }
    
    return ['success' => true, 'message' => 'تم رفع الصورة بنجاح.', 'filename' => $newName];
}

/**
 * رفع ملف عام (PDF, ZIP, DOC, إلخ)
 * @param array $file $_FILES['name']
 * @param string $targetDir المجلد الهدف
 * @param array $allowedExtensions قائمة الامتدادات المسموحة (بدون نقطة)
 * @param int $maxSize الحد الأقصى بالبايت (افتراضي 10 ميجابايت)
 * @return array ['success' => bool, 'message' => string, 'filename' => string|null]
 */
function uploadFile(array $file, string $targetDir, array $allowedExtensions = ['pdf', 'zip', 'doc', 'docx'], int $maxSize = 10485760): array {
    // التحقق من وجود الملف
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطأ في رفع الملف: ' . uploadErrorMessage($file['error']), 'filename' => null];
    }
    
    // التحقق من الامتداد
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'message' => 'امتداد الملف غير مسموح. الامتدادات المسموحة: ' . implode(', ', $allowedExtensions), 'filename' => null];
    }
    
    // التحقق من الحجم
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'حجم الملف يتجاوز الحد المسموح به (' . humanFileSize($maxSize) . ').', 'filename' => null];
    }
    
    // إنشاء اسم ملف فريد
    $newName = uniqid() . '.' . $extension;
    $targetPath = rtrim($targetDir, '/') . '/' . $newName;
    
    // التأكد من وجود المجلد
    if (!ensureDirectoryExists($targetDir)) {
        return ['success' => false, 'message' => 'لا يمكن إنشاء المجلد الهدف.', 'filename' => null];
    }
    
    // نقل الملف
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'message' => 'فشل نقل الملف إلى المجلد النهائي.', 'filename' => null];
    }
    
    chmod($targetPath, 0644);
    return ['success' => true, 'message' => 'تم رفع الملف بنجاح.', 'filename' => $newName];
}

/**
 * تغيير حجم الصورة (تحافظ على النسبة)
 * @param string $imagePath المسار الكامل للصورة
 * @param int $maxWidth العرض الأقصى
 * @param int $maxHeight الارتفاع الأقصى
 * @return bool
 */
function resizeImage(string $imagePath, int $maxWidth, int $maxHeight): bool {
    if (!file_exists($imagePath)) return false;
    
    // جلب معلومات الصورة
    list($origWidth, $origHeight, $type) = getimagesize($imagePath);
    
    // حساب النسبة الجديدة
    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    if ($ratio >= 1) return true; // لا حاجة لتغيير الحجم
    
    $newWidth = intval($origWidth * $ratio);
    $newHeight = intval($origHeight * $ratio);
    
    // إنشاء صورة جديدة
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // الحفاظ على الشفافية للـ PNG و WEBP
    imagealphablending($newImage, false);
    imagesavealpha($newImage, true);
    $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
    imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    
    // تحميل الصورة الأصلية حسب النوع
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($imagePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($imagePath);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($imagePath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($imagePath);
            break;
        default:
            return false;
    }
    
    // تغيير الحجم
    imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
    
    // حفظ الصورة الجديدة
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($newImage, $imagePath, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($newImage, $imagePath, 8);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($newImage, $imagePath, 85);
            break;
        case IMAGETYPE_GIF:
            imagegif($newImage, $imagePath);
            break;
    }
    
    // تنظيف الذاكرة
    imagedestroy($source);
    imagedestroy($newImage);
    
    return true;
}

/**
 * حذف ملف من السيرفر
 * @param string $filePath المسار الكامل للملف
 * @return bool
 */
function deleteUploadedFile(string $filePath): bool {
    if (file_exists($filePath) && is_file($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * حذف عدة ملفات دفعة واحدة
 * @param array $filePaths قائمة المسارات
 * @return int عدد الملفات المحذوفة
 */
function deleteMultipleFiles(array $filePaths): int {
    $count = 0;
    foreach ($filePaths as $path) {
        if (deleteUploadedFile($path)) {
            $count++;
        }
    }
    return $count;
}

/**
 * التحقق من وجود مجلد وإنشائه إذا لم يكن موجوداً
 * @param string $dir المسار
 * @return bool
 */
function ensureDirectoryExists(string $dir): bool {
    if (is_dir($dir)) return true;
    return mkdir($dir, 0755, true);
}

/**
 * الحصول على رسالة الخطأ المناسبة لـ $_FILES
 * @param int $errorCode
 * @return string
 */
function uploadErrorMessage(int $errorCode): string {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'حجم الملف يتجاوز الحد الأقصى المسموح به في الخادم.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'حجم الملف يتجاوز الحد الأقصى المسموح به في النموذج.';
        case UPLOAD_ERR_PARTIAL:
            return 'تم رفع الملف جزئياً.';
        case UPLOAD_ERR_NO_FILE:
            return 'لم يتم رفع أي ملف.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'المجلد المؤقت غير موجود.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'فشل كتابة الملف على القرص.';
        case UPLOAD_ERR_EXTENSION:
            return 'تم إيقاف رفع الملف بواسطة إضافة PHP.';
        default:
            return 'خطأ غير معروف في رفع الملف.';
    }
}

/**
 * توليد اسم ملف فريد مع الحفاظ على الامتداد
 * @param string $originalName الاسم الأصلي
 * @param string $prefix بادئة اختيارية
 * @return string
 */
function generateUniqueFileName(string $originalName, string $prefix = ''): string {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return $prefix . uniqid() . '.' . $extension;
}

/**
 * التحقق من أن الملف صورة صالحة
 * @param string $filePath
 * @return bool
 */
function isValidImage(string $filePath): bool {
    if (!file_exists($filePath)) return false;
    $info = getimagesize($filePath);
    return $info !== false;
}

/**
 * الحصول على نوع MIME للملف
 * @param string $filePath
 * @return string|null
 */
function getMimeType(string $filePath): ?string {
    if (!file_exists($filePath)) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    return $mime;
}

/**
 * تحويل حجم الملف إلى صيغة مقروءة
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function humanFileSize(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}