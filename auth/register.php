<?php
// auth/register.php
$page_title = "إنشاء حساب جديد";

// التأكد من تحميل الإعدادات الأساسية
if (file_exists(__DIR__ . '/../db_connect.php')) {
    require_once __DIR__ . '/../db_connect.php';
} else {
    if (!defined('APP_NAME')) define('APP_NAME', 'نظام إدارة العقارات');
    if (!defined('APP_BASE_URL')) define('APP_BASE_URL', '../');
    error_log("CRITICAL: db_connect.php not found from auth/register.php");
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// إذا كان المستخدم مسجل دخوله بالفعل، قم بإعادة توجيهه إلى لوحة التحكم
if (isset($_SESSION['user_id'])) {
    redirect(base_url('dashboard.php'));
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . (defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'نظام الإدارة'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #eef1f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 15px;
        }
        .register-card { /* Changed from login-card for clarity if needed */
            width: 100%;
            max-width: 500px; /* Slightly wider for more fields */
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .register-card-header {
            background-color: #28a745;
            color: white;
            padding: 25px;
            text-align: center;
        }
        .register-card-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.8rem;
        }
        .register-card-body {
            padding: 30px;
        }
        .btn-register { /* Changed from btn-login */
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            font-size: 1.1rem;
            transition: background-color 0.2s ease-in-out;
        }
        .btn-register:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .form-floating > .form-control,
        .form-floating > .form-select {
            height: calc(3.5rem + 2px);
            line-height: 1.25;
        }
        .form-floating > label {
            padding-right: 1.75rem;
        }
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label,
        .form-floating > .form-select:focus ~ label,
        .form-floating > .form-select:not([value=""]) ~ label {
            opacity: 0.65;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
        }
    </style>
</head>
<body>

    <div class="register-card">
        <div class="register-card-header">
            <h3>إنشاء حساب جديد</h3>
        </div>
        <div class="register-card-body">
            <?php
            if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
                echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message_type']) . ' alert-dismissible fade show" role="alert">';
                echo htmlspecialchars($_SESSION['message']);
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            }
            ?>
            <form id="registerForm" action="<?php echo base_url('auth/process_registration.php'); ?>" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">

                <div class="form-floating mb-3">
                    <input type="text" class="form-control <?php echo isset($_SESSION['errors']['full_name']) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" placeholder="الاسم الكامل" value="<?php echo isset($_SESSION['old_data']['full_name']) ? esc_attr($_SESSION['old_data']['full_name']) : ''; ?>" required>
                    <label for="full_name"><i class="bi bi-person-vcard-fill me-2"></i>الاسم الكامل</label>
                    <?php if (isset($_SESSION['errors']['full_name'])): ?>
                        <div class="invalid-feedback"><?php echo $_SESSION['errors']['full_name']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control <?php echo isset($_SESSION['errors']['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" placeholder="اسم المستخدم" value="<?php echo isset($_SESSION['old_data']['username']) ? esc_attr($_SESSION['old_data']['username']) : ''; ?>" required>
                    <label for="username"><i class="bi bi-person-fill me-2"></i>اسم المستخدم (باللغة الإنجليزية، بدون مسافات)</label>
                    <?php if (isset($_SESSION['errors']['username'])): ?>
                        <div class="invalid-feedback"><?php echo $_SESSION['errors']['username']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control <?php echo isset($_SESSION['errors']['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" placeholder="البريد الإلكتروني" value="<?php echo isset($_SESSION['old_data']['email']) ? esc_attr($_SESSION['old_data']['email']) : ''; ?>" required>
                    <label for="email"><i class="bi bi-envelope-fill me-2"></i>البريد الإلكتروني</label>
                    <?php if (isset($_SESSION['errors']['email'])): ?>
                        <div class="invalid-feedback"><?php echo $_SESSION['errors']['email']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" class="form-control <?php echo isset($_SESSION['errors']['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="كلمة المرور" required>
                    <label for="password"><i class="bi bi-lock-fill me-2"></i>كلمة المرور</label>
                    <?php if (isset($_SESSION['errors']['password'])): ?>
                        <div class="invalid-feedback"><?php echo $_SESSION['errors']['password']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" class="form-control <?php echo isset($_SESSION['errors']['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" placeholder="تأكيد كلمة المرور" required>
                    <label for="confirm_password"><i class="bi bi-shield-lock-fill me-2"></i>تأكيد كلمة المرور</label>
                    <?php if (isset($_SESSION['errors']['confirm_password'])): ?>
                        <div class="invalid-feedback"><?php echo $_SESSION['errors']['confirm_password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <p class="form-text text-muted small">
                    يجب أن تتكون كلمة المرور من 8 أحرف على الأقل، وتحتوي على حرف كبير واحد على الأقل، وحرف صغير واحد، ورقم واحد، ورمز خاص واحد على الأقل (مثل !@#$%^&*).
                </p>


                <div class="d-grid mb-3">
                    <button class="btn btn-register btn-lg" type="submit">
                        <i class="bi bi-person-plus-fill me-2"></i> إنشاء الحساب
                    </button>
                </div>
                <hr>
                <div class="text-center">
                    <p class="mb-0">لديك حساب بالفعل؟ <a href="<?php echo base_url('auth/login.php'); ?>" class="text-decoration-none fw-bold">تسجيل الدخول</a></p>
                </div>
                <?php
                    unset($_SESSION['old_data']);
                    unset($_SESSION['errors']);
                ?>
            </form>
        </div>
         <div class="text-center p-3 bg-light border-top">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'جميع الحقوق محفوظة'; ?></small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            'use strict'
            var forms = document.querySelectorAll('#registerForm')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>