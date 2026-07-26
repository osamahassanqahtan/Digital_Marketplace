<?php
/**
 * includes/validation.php
 * ملف متخصص في التحقق من صحة البيانات (Validation)
 * يحتوي على دوال ومصفوفات للتحقق من المدخلات وفق قواعد محددة
 */

/**
 * Class Validation
 * يوفر دوال ثابتة للتحقق من صحة البيانات
 */
class Validation {
    
    /**
     * التحقق من أن القيمة غير فارغة
     * @param mixed $value
     * @return bool
     */
    public static function required($value): bool {
        if (is_null($value)) return false;
        if (is_string($value)) return trim($value) !== '';
        if (is_array($value)) return !empty($value);
        return true;
    }
    
    /**
     * التحقق من أن القيمة بريد إلكتروني صحيح
     * @param string $value
     * @return bool
     */
    public static function email(string $value): bool {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * التحقق من أن القيمة رقم هاتف صحيح (أرقام وعلامات + - ( ) مسافات)
     * @param string $value
     * @param int $minLength الحد الأدنى (افتراضي 8)
     * @param int $maxLength الحد الأقصى (افتراضي 20)
     * @return bool
     */
    public static function phone(string $value, int $minLength = 8, int $maxLength = 20): bool {
        $cleaned = preg_replace('/[^0-9+]/', '', $value);
        $length = strlen($cleaned);
        return $length >= $minLength && $length <= $maxLength;
    }
    
    /**
     * التحقق من أن القيمة نصية بطول أدنى
     * @param string $value
     * @param int $min
     * @return bool
     */
    public static function minLength(string $value, int $min): bool {
        return mb_strlen($value, 'UTF-8') >= $min;
    }
    
    /**
     * التحقق من أن القيمة نصية بطول أقصى
     * @param string $value
     * @param int $max
     * @return bool
     */
    public static function maxLength(string $value, int $max): bool {
        return mb_strlen($value, 'UTF-8') <= $max;
    }
    
    /**
     * التحقق من أن القيمة بين طولين محددين
     * @param string $value
     * @param int $min
     * @param int $max
     * @return bool
     */
    public static function betweenLength(string $value, int $min, int $max): bool {
        $length = mb_strlen($value, 'UTF-8');
        return $length >= $min && $length <= $max;
    }
    
    /**
     * التحقق من أن القيمة رقمية (عدد صحيح أو عشري)
     * @param mixed $value
     * @return bool
     */
    public static function numeric($value): bool {
        return is_numeric($value);
    }
    
    /**
     * التحقق من أن القيمة عدد صحيح
     * @param mixed $value
     * @return bool
     */
    public static function integer($value): bool {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    /**
     * التحقق من أن القيمة عدد عشري (float)
     * @param mixed $value
     * @return bool
     */
    public static function float($value): bool {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }
    
    /**
     * التحقق من أن القيمة ضمن قائمة مسموحة
     * @param mixed $value
     * @param array $allowed
     * @return bool
     */
    public static function inArray($value, array $allowed): bool {
        return in_array($value, $allowed, true);
    }
    
    /**
     * التحقق من أن القيمة تاريخ صحيح بصيغة معينة (افتراضي Y-m-d)
     * @param string $value
     * @param string $format
     * @return bool
     */
    public static function date(string $value, string $format = 'Y-m-d'): bool {
        $d = DateTime::createFromFormat($format, $value);
        return $d && $d->format($format) === $value;
    }
    
    /**
     * التحقق من أن القيمة تاريخ ووقت صحيح (Y-m-d H:i:s)
     * @param string $value
     * @return bool
     */
    public static function datetime(string $value): bool {
        return self::date($value, 'Y-m-d H:i:s');
    }
    
    /**
     * التحقق من أن القيمة رابط URL صحيح
     * @param string $value
     * @return bool
     */
    public static function url(string $value): bool {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * التحقق من أن القيمة تطابق نمط regex معين
     * @param string $value
     * @param string $pattern
     * @return bool
     */
    public static function regex(string $value, string $pattern): bool {
        return preg_match($pattern, $value) === 1;
    }
    
    /**
     * التحقق من أن القيمة تطابق قيمة أخرى (للمقارنة مثل تأكيد كلمة المرور)
     * @param mixed $value
     * @param mixed $compare
     * @return bool
     */
    public static function same($value, $compare): bool {
        return $value === $compare;
    }
    
    /**
     * التحقق من أن القيمة مختلفة عن قيمة أخرى
     * @param mixed $value
     * @param mixed $compare
     * @return bool
     */
    public static function different($value, $compare): bool {
        return $value !== $compare;
    }
    
    /**
     * التحقق من أن القيمة أكبر من قيمة أخرى (للأرقام)
     * @param float|int $value
     * @param float|int $min
     * @return bool
     */
    public static function min($value, $min): bool {
        return $value >= $min;
    }
    
    /**
     * التحقق من أن القيمة أقل من قيمة أخرى
     * @param float|int $value
     * @param float|int $max
     * @return bool
     */
    public static function max($value, $max): bool {
        return $value <= $max;
    }
    
    /**
     * التحقق من أن القيمة بين قيمتين (للأرقام)
     * @param float|int $value
     * @param float|int $min
     * @param float|int $max
     * @return bool
     */
    public static function between($value, $min, $max): bool {
        return $value >= $min && $value <= $max;
    }
    
    /**
     * التحقق من أن الملف المرفوع من نوع صورة صالح
     * @param array $file $_FILES['name']
     * @param array $allowedTypes قائمة MIME types مسموحة
     * @param int $maxSize الحجم الأقصى بالبايت
     * @return bool
     */
    public static function image(array $file, array $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], int $maxSize = 5242880): bool {
        if ($file['error'] !== UPLOAD_ERR_OK) return false;
        if ($file['size'] > $maxSize) return false;
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        return in_array($mime, $allowedTypes);
    }
    
    /**
     * التحقق من أن جميع القيم في مصفوفة fulfill شرط معين
     * @param array $data
     * @param callable $callback
     * @return bool
     */
    public static function all(array $data, callable $callback): bool {
        foreach ($data as $value) {
            if (!$callback($value)) {
                return false;
            }
        }
        return true;
    }
}

/**
 * وظيفة مساعدة للتحقق من صحة مجموعة من القواعد على بيانات الإدخال
 * @param array $data البيانات (مصفوفة key => value)
 * @param array $rules قواعد التحقق (مصفوفة key => rule string)
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate(array $data, array $rules): array {
    $errors = [];
    
    foreach ($rules as $field => $ruleString) {
        $value = $data[$field] ?? null;
        $rulesList = explode('|', $ruleString);
        
        foreach ($rulesList as $rule) {
            $ruleParts = explode(':', $rule);
            $ruleName = $ruleParts[0];
            $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];
            
            $isValid = true;
            $errorMessage = '';
            
            switch ($ruleName) {
                case 'required':
                    $isValid = Validation::required($value);
                    $errorMessage = 'حقل ' . $field . ' مطلوب.';
                    break;
                case 'email':
                    $isValid = Validation::email($value);
                    $errorMessage = 'البريد الإلكتروني غير صحيح.';
                    break;
                case 'phone':
                    $min = $ruleParams[0] ?? 8;
                    $max = $ruleParams[1] ?? 20;
                    $isValid = Validation::phone($value, (int)$min, (int)$max);
                    $errorMessage = 'رقم الهاتف غير صحيح.';
                    break;
                case 'min':
                    $min = (int)($ruleParams[0] ?? 0);
                    $isValid = Validation::minLength($value, $min);
                    $errorMessage = 'حقل ' . $field . ' يجب أن لا يقل عن ' . $min . ' أحرف.';
                    break;
                case 'max':
                    $max = (int)($ruleParams[0] ?? 0);
                    $isValid = Validation::maxLength($value, $max);
                    $errorMessage = 'حقل ' . $field . ' يجب أن لا يزيد عن ' . $max . ' أحرف.';
                    break;
                case 'between':
                    $min = (int)($ruleParams[0] ?? 0);
                    $max = (int)($ruleParams[1] ?? 0);
                    $isValid = Validation::betweenLength($value, $min, $max);
                    $errorMessage = 'حقل ' . $field . ' يجب أن يكون بين ' . $min . ' و ' . $max . ' أحرف.';
                    break;
                case 'numeric':
                    $isValid = Validation::numeric($value);
                    $errorMessage = 'حقل ' . $field . ' يجب أن يكون رقماً.';
                    break;
                case 'integer':
                    $isValid = Validation::integer($value);
                    $errorMessage = 'حقل ' . $field . ' يجب أن يكون عدداً صحيحاً.';
                    break;
                case 'float':
                    $isValid = Validation::float($value);
                    $errorMessage = 'حقل ' . $field . ' يجب أن يكون عدداً عشرياً.';
                    break;
                case 'in':
                    $allowed = $ruleParams;
                    $isValid = Validation::inArray($value, $allowed);
                    $errorMessage = 'حقل ' . $field . ' يحتوي على قيمة غير مسموحة.';
                    break;
                case 'date':
                    $format = $ruleParams[0] ?? 'Y-m-d';
                    $isValid = Validation::date($value, $format);
                    $errorMessage = 'حقل ' . $field . ' يجب أن يكون تاريخاً صحيحاً.';
                    break;
                case 'url':
                    $isValid = Validation::url($value);
                    $errorMessage = 'حقل ' . $field . ' يجب أن يكون رابطاً صحيحاً.';
                    break;
                case 'same':
                    $compareField = $ruleParams[0] ?? '';
                    $compareValue = $data[$compareField] ?? null;
                    $isValid = Validation::same($value, $compareValue);
                    $errorMessage = 'حقل ' . $field . ' يجب أن يطابق حقل ' . $compareField . '.';
                    break;
                case 'different':
                    $compareField = $ruleParams[0] ?? '';
                    $compareValue = $data[$compareField] ?? null;
                    $isValid = Validation::different($value, $compareValue);
                    $errorMessage = 'حقل ' . $field . ' يجب أن يختلف عن حقل ' . $compareField . '.';
                    break;
                case 'min_value':
                    $min = (float)($ruleParams[0] ?? 0);
                    $isValid = Validation::min($value, $min);
                    $errorMessage = 'حقل ' . $field . ' يجب أن لا يقل عن ' . $min . '.';
                    break;
                case 'max_value':
                    $max = (float)($ruleParams[0] ?? 0);
                    $isValid = Validation::max($value, $max);
                    $errorMessage = 'حقل ' . $field . ' يجب أن لا يزيد عن ' . $max . '.';
                    break;
                case 'between_value':
                    $min = (float)($ruleParams[0] ?? 0);
                    $max = (float)($ruleParams[1] ?? 0);
                    $isValid = Validation::between($value, $min, $max);
                    $errorMessage = 'حقل ' . $field . ' يجب أن يكون بين ' . $min . ' و ' . $max . '.';
                    break;
                default:
                    // لو القاعدة غير معروفة نتجاوزها
                    continue 2;
            }
            
            if (!$isValid) {
                $errors[$field][] = $errorMessage;
                break; // نوقف فحص القواعد لهذا الحقل بعد أول خطأ
            }
        }
    }
    
    return ['valid' => empty($errors), 'errors' => $errors];
}