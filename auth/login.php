<?php
// auth/login.php
$page_title = "تسجيل الدخول";

// التأكد من تحميل الإعدادات الأساسية
// db_connect.php يتضمن config.php ويحمّل الإعدادات
if (file_exists(__DIR__ . '/../db_connect.php')) {
    require_once __DIR__ . '/../db_connect.php';
} else {
    // تعريفات احتياطية إذا لم يتمكن من تحميل db_connect.php
    if (!defined('APP_NAME')) define('APP_NAME', 'نظام إدارة العقارات');
    // APP_BASE_URL يجب أن يُعرّف بشكل صحيح في config.php
    // إذا لم يتم تحميله، فإن base_url() قد لا تعمل بشكل صحيح.
    // كحل مؤقت جدًا إذا كان config.php غير موجود أو لا يمكن الوصول إليه:
    if (!defined('APP_BASE_URL')) {
        $protocol_login = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host_login = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        // نفترض أن login.php في auth/ وأن جذر المشروع هو مجلد واحد للأعلى
        define('APP_BASE_URL', rtrim($protocol_login . $host_login . dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'));
    }
    error_log("CRITICAL: db_connect.php not found from auth/login.php. APP_BASE_URL might be incorrect.");
}


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// functions.php يجب أن يتم تضمينه بعد db_connect (الذي يتضمن config) لضمان تعريف APP_BASE_URL
if (file_exists(__DIR__ . '/../includes/functions.php')) {
    require_once __DIR__ . '/../includes/functions.php';
} else {
    // دالة base_url() لن تكون متاحة إذا لم يتم تضمين functions.php
    function base_url($path = '') { return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/'); } // تعريف احتياطي بسيط
    error_log("CRITICAL: functions.php not found from auth/login.php. base_url() might be using a fallback.");
}


if (isset($_SESSION['user_id'])) {
    redirect(base_url('dashboard.php')); // استخدم base_url() هنا أيضًا
}

// معالجة رسالة تسجيل الخروج إذا تم تمريرها عبر GET
if (isset($_GET['status']) && $_GET['status'] === 'logged_out' && !isset($_SESSION['message'])) {
    set_message("تم تسجيل خروجك بنجاح.", "success");
    // إعادة تحميل الصفحة بدون معامل status لتجنب ظهور الرسالة عند كل تحديث
    // أو يمكنك تركها لتظهر مرة واحدة. إذا أردت إزالتها من الـ URL:
    // header("Location: " . base_url('auth/login.php'));
    // exit();
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
        .login-card {
            width: 100%;
            max-width: 430px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .login-card-header {
            background-color: #28a745;
            color: white;
            padding: 25px;
            text-align: center;
        }
        .login-card-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.8rem;
        }
        .login-card-body {
            padding: 30px;
        }
        .btn-login {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            font-size: 1.1rem;
        }
        .btn-login:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .form-floating > label { padding-right: 1.75rem; } /* RTL fix */
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-card-header">
            <h3><?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'نظام الإدارة'; ?></h3>
            <p class="mb-0">مرحباً بك! الرجاء تسجيل الدخول للمتابعة.</p>
        </div>
        <div class="login-card-body">
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
            <form id="loginForm" action="<?php echo base_url('auth/process_login.php'); ?>" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">

                <div class="form-floating mb-3">
                    <input type="text" class="form-control form-control-lg <?php echo isset($_SESSION['errors']['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" placeholder="اسم المستخدم أو البريد الإلكتروني" value="<?php echo isset($_SESSION['old_data']['username']) ? esc_attr($_SESSION['old_data']['username']) : ''; ?>" required autofocus>
                    <label for="username"><i class="bi bi-person-fill me-2"></i>اسم المستخدم أو البريد الإلكتروني</label>
                    <?php if (isset($_SESSION['errors']['username'])): ?>
                        <div class="invalid-feedback"><?php echo $_SESSION['errors']['username']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" class="form-control form-control-lg <?php echo isset($_SESSION['errors']['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="كلمة المرور" required>
                    <label for="password"><i class="bi bi-lock-fill me-2"></i>كلمة المرور</label>
                     <?php if (isset($_SESSION['errors']['password'])): ?>
                        <div class="invalid-feedback"><?php echo $_SESSION['errors']['password']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="d-grid mb-3">
                    <button class="btn btn-login btn-lg" type="submit">
                        <i class="bi bi-box-arrow-in-right me-2"></i> تسجيل الدخول
                    </button>
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
            var forms = document.querySelectorAll('#loginForm')
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