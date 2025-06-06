<?php
// auth/login.php
$page_title = "تسجيل الدخول";

if (file_exists(__DIR__ . '/../db_connect.php')) {
    require_once __DIR__ . '/../db_connect.php';
} else {
    if (!defined('APP_NAME')) define('APP_NAME', 'نظام إدارة العقارات');
    if (!defined('APP_BASE_URL')) {
        $protocol_login = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host_login = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        define('APP_BASE_URL', rtrim($protocol_login . $host_login . dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'));
    }
    error_log("CRITICAL: db_connect.php not found from auth/login.php. APP_BASE_URL might be incorrect.");
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (file_exists(__DIR__ . '/../includes/functions.php')) {
    require_once __DIR__ . '/../includes/functions.php';
} else {
    function base_url($path = '') { return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/'); }
    error_log("CRITICAL: functions.php not found from auth/login.php.");
}

if (isset($_SESSION['user_id'])) {
    redirect(base_url('dashboard.php'));
}

if (isset($_GET['status']) && $_GET['status'] === 'logged_out' && !isset($_SESSION['flash_message'])) { // Check flash_message
    set_message("تم تسجيل خروجك بنجاح.", "success");
    // Redirect to clean URL after setting message for SweetAlert
    header("Location: " . base_url('auth/login.php'));
    exit();
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
        .form-floating > label { padding-right: 1.75rem; } 
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
            // Bootstrap alert rendering REMOVED from here. SweetAlert in footer will handle $_SESSION['flash_message']
            ?>
            <form id="loginForm" action="<?php echo base_url('auth/process_login.php'); ?>" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">

                <div class="form-floating mb-3">
                    <input type="text" class="form-control form-control-lg" id="username_or_email" name="username_or_email" placeholder="اسم المستخدم أو البريد الإلكتروني" required autofocus>
                    <label for="username_or_email"><i class="bi bi-person-fill me-2"></i>اسم المستخدم أو البريد الإلكتروني</label>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="كلمة المرور" required>
                    <label for="password"><i class="bi bi-lock-fill me-2"></i>كلمة المرور</label>
                </div>

                <div class="d-grid mb-3">
                    <button class="btn btn-login btn-lg" type="submit">
                        <i class="bi bi-box-arrow-in-right me-2"></i> تسجيل الدخول
                    </button>
                </div>
                <div class="text-center">
                     <p class="mb-0">ليس لديك حساب؟ <a href="<?php echo base_url('auth/register.php'); ?>" class="text-decoration-none fw-bold">إنشاء حساب جديد</a></p>
                </div>
            </form>
        </div>
        <div class="text-center p-3 bg-light border-top">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'جميع الحقوق محفوظة'; ?></small>
        </div>
    </div>

<?php
// Include footer_resources.php to enable SweetAlert for session messages
if (file_exists(__DIR__ . '/../includes/footer_resources.php')) {
    require_once __DIR__ . '/../includes/footer_resources.php';
}
?>
<?php /* Original script tags removed as they are now in footer_resources.php */ ?>
</body>
</html>