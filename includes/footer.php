<?php
/**
 * includes/footer.php
 * تذييل الصفحة الموحد - نسخة نهائية مع روابط ديناميكية وتصميم عصري
 */

// جلب السنة الحالية
$currentYear = date('Y');

// =============================================
// حساب المسار الأساسي تلقائياً (نفس طريقة الهيدر)
// =============================================
$currentDir = dirname($_SERVER['SCRIPT_NAME']);
$basePath = '';
if ($currentDir !== '/') {
    $levels = substr_count(trim($currentDir, '/'), '/');
    if ($levels > 0) {
        $basePath = str_repeat('../', $levels);
    }
}
if ($basePath !== '' && substr($basePath, -1) !== '/') {
    $basePath .= '/';
}
?>

<!-- ==========================================
     تذييل الصفحة (Footer)
     ========================================== -->
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            
            <!-- العمود الأول: عن المنصة -->
            <div class="col-lg-4 col-md-6">
                <h5 class="footer-title">
                    <i class="bi bi-shop" style="color: #FCD34D;"></i> منصة السوق
                </h5>
                <p class="footer-text">
                    منصة إلكترونية متكاملة تتيح لك بيع وشراء المنتجات الجديدة والمستعملة بسهولة وأمان. 
                    تواصل مع البائعين والمشترين في مكان واحد واستمتع بتجربة تسوق فريدة.
                </p>
                <div class="footer-social">
                    <a href="#" class="social-icon" aria-label="فيسبوك"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-icon" aria-label="تويتر"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="social-icon" aria-label="إنستغرام"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="social-icon" aria-label="يوتيوب"><i class="bi bi-youtube"></i></a>
                    <a href="#" class="social-icon" aria-label="واتساب"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>
            
            <!-- العمود الثاني: روابط سريعة -->
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">روابط سريعة</h5>
                <ul class="footer-links">
                    <li><a href="<?php echo $basePath; ?>index.php"><i class="bi bi-chevron-left"></i> الرئيسية</a></li>
                    <li><a href="<?php echo $basePath; ?>products/index.php"><i class="bi bi-chevron-left"></i> المنتجات</a></li>
                    <li><a href="<?php echo $basePath; ?>auth/register.php"><i class="bi bi-chevron-left"></i> إنشاء حساب</a></li>
                    <li><a href="<?php echo $basePath; ?>auth/login.php"><i class="bi bi-chevron-left"></i> تسجيل الدخول</a></li>
                    <?php if (function_exists('isLoggedIn') && function_exists('hasRole') && isLoggedIn()): ?>
                        <?php if (hasRole('seller')): ?>
                            <li><a href="<?php echo $basePath; ?>seller/index.php"><i class="bi bi-chevron-left"></i> لوحة البائع</a></li>
                        <?php endif; ?>
                        <?php if (hasRole('admin')): ?>
                            <li><a href="<?php echo $basePath; ?>admin/dashboard.php"><i class="bi bi-chevron-left"></i> لوحة المدير</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- العمود الثالث: خدماتنا -->
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">خدماتنا</h5>
                <ul class="footer-links">
                    <li><a href="<?php echo $basePath; ?>products/index.php"><i class="bi bi-chevron-left"></i> تسوق المنتجات</a></li>
                    <li><a href="<?php echo $basePath; ?>products/add.php"><i class="bi bi-chevron-left"></i> بيع منتجك</a></li>
                    <li><a href="<?php echo $basePath; ?>favorites/index.php"><i class="bi bi-chevron-left"></i> المفضلة</a></li>
                    <li><a href="<?php echo $basePath; ?>chat/index.php"><i class="bi bi-chevron-left"></i> المحادثات</a></li>
                    <li><a href="<?php echo $basePath; ?>notifications/index.php"><i class="bi bi-chevron-left"></i> الإشعارات</a></li>
                </ul>
            </div>
            
            <!-- العمود الرابع: التواصل والاشتراك -->
            <div class="col-lg-4 col-md-6">
                <h5 class="footer-title">تواصل معنا</h5>
                <ul class="footer-contact">
                    <li>
                        <i class="bi bi-geo-alt"></i>
                        <span>الرياض، المملكة العربية السعودية</span>
                    </li>
                    <li>
                        <i class="bi bi-envelope"></i>
                        <a href="mailto:support@marketplace.com">support@marketplace.com</a>
                    </li>
                    <li>
                        <i class="bi bi-telephone"></i>
                        <a href="tel:+966737065810">+966 73 706 5810</a>
                    </li>
                    <li>
                        <i class="bi bi-clock"></i>
                        <span>نعمل 24/7 طوال أيام الأسبوع</span>
                    </li>
                </ul>
                
                <!-- نموذج الاشتراك في النشرة البريدية -->
                <div class="footer-newsletter mt-3">
                    <p class="mb-2">📬 اشترك في نشرتنا البريدية</p>
                    <form action="<?php echo $basePath; ?>subscribe.php" method="POST" class="d-flex gap-2">
                        <input type="email" class="form-control form-control-sm" name="email" placeholder="بريدك الإلكتروني" required>
                        <button type="submit" class="btn btn-primary btn-sm">اشتراك</button>
                    </form>
                </div>
            </div>
            
        </div><!-- /.row -->
        
        <!-- الخط السفلي: حقوق النشر -->
        <hr class="footer-divider">
        <div class="footer-bottom">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">
                        &copy; <?php echo $currentYear; ?> <strong>منصة السوق</strong> – جميع الحقوق محفوظة.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">
                        <i class="bi bi-code-slash"></i> بتقنية <a href="#" class="text-decoration-none">فريق منصة السوق</a>
                        <span class="mx-2">|</span>
                        <a href="#" class="text-decoration-none">سياسة الخصوصية</a>
                        <span class="mx-2">|</span>
                        <a href="#" class="text-decoration-none">الشروط والأحكام</a>
                    </p>
                </div>
            </div>
        </div>
        
    </div><!-- /.container -->
</footer>

<!-- ==========================================
     زر الرجوع للأعلى (Scroll to Top)
     ========================================== -->
<button id="scrollTopBtn" class="scroll-top-btn" aria-label="الرجوع للأعلى">
    <i class="bi bi-chevron-up"></i>
</button>

<!-- ==========================================
     تنسيقات الفوتر
     ========================================== -->
<style>
    .footer {
        background: #0F172A;
        color: rgba(255, 255, 255, 0.75);
        padding: 60px 0 30px;
        margin-top: 60px;
        border-top: 4px solid rgba(79, 70, 229, 0.3);
        position: relative;
    }
    
    .footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #4F46E5, #7C3AED, #FCD34D);
        opacity: 0.6;
    }
    
    .footer-title {
        color: #FFFFFF;
        font-weight: 800;
        font-size: 1.2rem;
        margin-bottom: 20px;
        position: relative;
        padding-bottom: 10px;
    }
    
    .footer-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 0;
        width: 40px;
        height: 3px;
        background: linear-gradient(90deg, #4F46E5, #7C3AED);
        border-radius: 4px;
    }
    
    .footer-text {
        font-size: 0.95rem;
        line-height: 1.8;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 20px;
    }
    
    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-links li {
        margin-bottom: 10px;
    }
    
    .footer-links a {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .footer-links a:hover {
        color: #818CF8;
        transform: translateX(-5px);
    }
    
    .footer-links a i {
        font-size: 0.8rem;
        transition: 0.3s;
    }
    
    .footer-links a:hover i {
        transform: translateX(-3px);
    }
    
    .footer-contact {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-contact li {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.95rem;
    }
    
    .footer-contact li i {
        color: #818CF8;
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
    }
    
    .footer-contact a {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        transition: 0.3s;
    }
    
    .footer-contact a:hover {
        color: #818CF8;
    }
    
    .footer-social {
        display: flex;
        gap: 12px;
        margin-top: 15px;
    }
    
    .social-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.06);
        color: rgba(255, 255, 255, 0.7);
        transition: all 0.3s ease;
        font-size: 1.1rem;
        border: 1px solid rgba(255, 255, 255, 0.06);
    }
    
    .social-icon:hover {
        background: #4F46E5;
        color: #FFFFFF;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
    }
    
    .footer-newsletter p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .footer-newsletter .form-control {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
        border-radius: 50px;
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    
    .footer-newsletter .form-control::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }
    
    .footer-newsletter .form-control:focus {
        background: rgba(255, 255, 255, 0.08);
        border-color: #4F46E5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }
    
    .footer-newsletter .btn-primary {
        background: linear-gradient(135deg, #4F46E5, #7C3AED);
        border: none;
        border-radius: 50px;
        padding: 8px 20px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: 0.3s;
    }
    
    .footer-newsletter .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
    }
    
    .footer-divider {
        border-color: rgba(255, 255, 255, 0.06);
        margin: 30px 0 20px;
    }
    
    .footer-bottom {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
    }
    
    .footer-bottom a {
        color: rgba(255, 255, 255, 0.6);
        transition: 0.3s;
    }
    
    .footer-bottom a:hover {
        color: #818CF8;
    }
    
    .scroll-top-btn {
        position: fixed;
        bottom: 30px;
        left: 30px;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4F46E5, #7C3AED);
        color: #fff;
        border: none;
        font-size: 1.3rem;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(79, 70, 229, 0.3);
        transition: all 0.3s ease;
        opacity: 0;
        visibility: hidden;
        transform: translateY(20px);
        z-index: 999;
    }
    
    .scroll-top-btn.visible {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .scroll-top-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 30px rgba(79, 70, 229, 0.4);
    }
    
    @media (max-width: 768px) {
        .footer {
            padding: 40px 0 20px;
        }
        .footer-title::after {
            width: 30px;
        }
        .footer-social {
            justify-content: center;
        }
        .footer-newsletter .d-flex {
            flex-direction: column;
        }
        .footer-newsletter .btn-primary {
            width: 100%;
        }
        .scroll-top-btn {
            bottom: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
    }
</style>

<!-- ==========================================
     كود JavaScript لزر الرجوع للأعلى
     ========================================== -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // زر الرجوع للأعلى
        const scrollBtn = document.getElementById('scrollTopBtn');
        if (scrollBtn) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    scrollBtn.classList.add('visible');
                } else {
                    scrollBtn.classList.remove('visible');
                }
            });
            scrollBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
    });
</script>

<!-- ==========================================
     Bootstrap JavaScript (مرة واحدة فقط)
     ========================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>