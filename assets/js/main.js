/**
 * assets/js/main.js
 * الملف الرئيسي للجافاسكريبت في منصة السوق
 * يحتوي على وظائف عامة تُستخدم في جميع صفحات المشروع
 */

document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // 1. تفعيل القوائم المنسدلة في Bootstrap (Dropdowns)
    // ============================================
    const dropdowns = document.querySelectorAll('.dropdown-toggle');
    dropdowns.forEach(dropdown => {
        new bootstrap.Dropdown(dropdown);
    });

    // ============================================
    // 2. التحقق من صحة النماذج (Form Validation)
    // ============================================
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // ============================================
    // 3. تحقق مخصص لحقل البريد الإلكتروني
    // ============================================
    document.querySelectorAll('input[type="email"]').forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.classList.add('is-invalid');
                this.nextElementSibling?.classList.add('invalid-feedback');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    });

    // ============================================
    // 4. تحقق مخصص لحقل رقم الهاتف (أرقام فقط)
    // ============================================
    document.querySelectorAll('input[type="tel"]').forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+\-\(\)\s]/g, '');
        });
    });

    // ============================================
    // 5. تفعيل الإشعارات (Toasts) - إن وجدت
    // ============================================
    const toastElements = document.querySelectorAll('.toast');
    toastElements.forEach(toastEl => {
        const toast = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 4000
        });
        toast.show();
    });

    // ============================================
    // 6. إغلاق رسائل التنبيه تلقائياً بعد 5 ثوانٍ
    // ============================================
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
        setTimeout(() => {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            } else {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    });

    // ============================================
    // 7. وظيفة مساعدة لإرسال طلبات AJAX باستخدام Fetch
    // ============================================
    window.ajaxRequest = function(url, method = 'GET', data = null, headers = {}) {
        const options = {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                ...headers
            }
        };
        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            options.body = JSON.stringify(data);
        }
        return fetch(url, options)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                throw error;
            });
    };

    // ============================================
    // 8. وظيفة مساعدة للتحقق من البريد الإلكتروني
    // ============================================
    window.isValidEmail = function(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    };

    // ============================================
    // 9. وظيفة مساعدة لتنسيق السعر (إضافة فاصلات)
    // ============================================
    window.formatPrice = function(price) {
        return Number(price).toLocaleString('ar-EG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ريال';
    };

    // ============================================
    // 10. وظيفة مساعدة لتأكيد الحذف
    // ============================================
    window.confirmDelete = function(message = 'هل أنت متأكد من حذف هذا العنصر؟') {
        return confirm(message);
    };

    // ============================================
    // 11. تفعيل زر "عرض الكل" لإظهار النصوص الطويلة
    // ============================================
    document.querySelectorAll('.read-more-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const target = document.getElementById(targetId);
            if (target) {
                target.classList.toggle('d-none');
                this.textContent = target.classList.contains('d-none') ? 'عرض المزيد' : 'عرض أقل';
            }
        });
    });

    // ============================================
    // 12. معالجة نموذج البحث (عند الضغط على Enter)
    // ============================================
    document.querySelectorAll('.search-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const input = this.querySelector('input[type="search"]');
            if (input && input.value.trim().length < 2) {
                e.preventDefault();
                alert('يرجى إدخال كلمتين على الأقل للبحث.');
            }
        });
    });

    console.log('✅ main.js تم تحميله بنجاح');
});

// ============================================
// 13. دالة تُنفذ عند تحميل الصفحة بالكامل (للصور)
// ============================================
window.addEventListener('load', function() {
    // تحسين تحميل الصور البطيئة (Lazy Loading)
    if ('loading' in HTMLImageElement.prototype) {
        document.querySelectorAll('img[loading="lazy"]').forEach(img => {
            img.src = img.dataset.src || img.src;
        });
    }
});