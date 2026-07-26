/**
 * assets/js/dashboard.js
 * ملف JavaScript مسؤول عن الوظائف التفاعلية في لوحات التحكم
 * يشمل: تأكيدات الحذف، تحديث الإشعارات، tooltips، وتحسينات عامة
 */

document.addEventListener('DOMContentLoaded', function() {

    // 1. تفعيل Bootstrap Tooltips على جميع العناصر التي تحمل data-bs-toggle="tooltip"
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 2. تفعيل Bootstrap Popovers (إذا استُخدمت)
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // 3. تأكيد الحذف لكافة الروابط والأزرار التي تحمل class="delete-confirm"
    document.querySelectorAll('.delete-confirm, .confirm-delete').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm-message') || 'هل أنت متأكد من حذف هذا العنصر؟';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // 4. تحديث عدد الإشعارات غير المقروءة ديناميكياً (عن طريق AJAX كل 30 ثانية)
    const badgeElement = document.querySelector('.badge-notification');
    if (badgeElement) {
        // دالة لجلب عدد الإشعارات الجديدة من الخادم
        function updateUnreadNotifications() {
            fetch('../notifications/count.php', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.unread !== undefined) {
                    const count = parseInt(data.unread);
                    if (count > 0) {
                        badgeElement.textContent = count;
                        badgeElement.style.display = 'inline-block';
                    } else {
                        badgeElement.style.display = 'none';
                    }
                }
            })
            .catch(err => console.warn('فشل تحديث الإشعارات:', err));
        }

        // تحديث كل 30 ثانية
        setInterval(updateUnreadNotifications, 30000);
        // تحديث فوري عند تحميل الصفحة
        updateUnreadNotifications();
    }

    // 5. معالجة تغيير حالة المنتج من خلال زر في الجدول (إذا استخدمت نموذجاً مخفياً)
    document.querySelectorAll('.change-status-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const select = this.querySelector('select[name="status"]');
            if (select) {
                const currentVal = select.getAttribute('data-current');
                if (select.value === currentVal) {
                    e.preventDefault();
                    alert('لم تقم بتغيير الحالة.');
                    return false;
                }
            }
        });
    });

    // 6. إضافة خاصية "تحديد الكل" للجداول (للوحة المدير)
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('.row-checkbox').forEach(function(cb) {
                cb.checked = isChecked;
            });
        });
    }

    // 7. عرض رسائل الـ Alert بشكل تلقائي بعد 5 ثواني (لتختفي)
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(function(alert) {
        setTimeout(function() {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            } else {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() { alert.remove(); }, 500);
            }
        }, 5000);
    });

    // 8. دالة مساعدة لتحديث لوحة التحكم عبر AJAX (مثال لاستخدامها لاحقاً)
    window.refreshDashboard = function(url, containerId) {
        if (!url || !containerId) return;
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(response => response.text())
        .then(html => {
            document.getElementById(containerId).innerHTML = html;
        })
        .catch(err => console.warn('فشل تحديث اللوحة:', err));
    };

    console.log('✅ Dashboard JS تم تحميله بنجاح');
});