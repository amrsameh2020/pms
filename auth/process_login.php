<?php
// auth/process_login.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start(); // ابدأ الجلسة إذا لم تكن قد بدأت
}

$login_page = base_url('auth/login.php');
$dashboard_page = base_url('dashboard.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('خطأ في التحقق (CSRF). يرجى تحديث الصفحة والمحاولة مرة أخرى.', 'danger');
        redirect($login_page);
        exit;
    }

    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $user_attempt_id = null;
    $user_details_for_log = ['username_attempt' => $username_or_email];

    if (empty($username_or_email) || empty($password)) {
        set_message('يرجى إدخال اسم المستخدم/البريد الإلكتروني وكلمة المرور.', 'danger');
        $_SESSION['old_data']['username_or_email'] = $username_or_email;
        log_audit_action($mysqli, AUDIT_LOGIN_ATTEMPT_FAILED, null, 'users', $user_details_for_log);
        redirect($login_page);
        exit;
    }

    $sql = "SELECT u.id, u.username, u.full_name, u.email, u.password_hash, u.is_active, u.role_id, r.role_name 
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE (u.username = ? OR u.email = ?) 
            LIMIT 1";
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Login SQL prepare error: " . $mysqli->error);
        set_message('خطأ في النظام، يرجى المحاولة لاحقًا.', 'danger');
        redirect($login_page);
        exit;
    }

    $stmt->bind_param("ss", $username_or_email, $username_or_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $user_attempt_id = $user['id'];
        $user_details_for_log['user_id_found'] = $user_attempt_id;

        if (password_verify($password, $user['password_hash'])) {
            if ($user['is_active'] == 1) {
                if (session_status() == PHP_SESSION_ACTIVE) {
                     session_regenerate_id(true);
                } else {
                    session_start(); 
                    session_regenerate_id(true);
                    error_log("Login process: Session was not active before regenerate.");
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_full_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role_id'] = $user['role_id'];
                $_SESSION['user_role_name'] = $user['role_name'];
                $_SESSION['logged_in_timestamp'] = time();
                
                log_audit_action($mysqli, AUDIT_LOGIN_SUCCESS, $user['id'], 'users', ['username' => $user['username']]);
                
                $redirect_url = $_SESSION['redirect_after_login'] ?? $dashboard_page;
                unset($_SESSION['redirect_after_login']);
                // لا تقم بتعيين رسالة نجاح هنا، لأن المستخدم سيتم توجيهه مباشرة إلى لوحة التحكم
                // set_message('تم تسجيل الدخول بنجاح!', 'success'); // لا حاجة لهذه إذا تم التوجيه لـ dashboard
                redirect($redirect_url);
                exit;
            } else {
                set_message('حسابك غير نشط. يرجى الاتصال بالمسؤول.', 'danger');
                $_SESSION['old_data']['username_or_email'] = $username_or_email;
                log_audit_action($mysqli, AUDIT_LOGIN_ATTEMPT_FAILED, $user_attempt_id, 'users', array_merge($user_details_for_log, ['reason' => 'Account inactive']));
            }
        } else {
            set_message('اسم المستخدم/البريد الإلكتروني أو كلمة المرور غير صحيحة.', 'danger');
            $_SESSION['old_data']['username_or_email'] = $username_or_email;
            log_audit_action($mysqli, AUDIT_LOGIN_ATTEMPT_FAILED, $user_attempt_id, 'users', array_merge($user_details_for_log, ['reason' => 'Incorrect password']));
        }
    } else {
        set_message('اسم المستخدم/البريد الإلكتروني أو كلمة المرور غير صحيحة.', 'danger');
        $_SESSION['old_data']['username_or_email'] = $username_or_email;
        log_audit_action($mysqli, AUDIT_LOGIN_ATTEMPT_FAILED, null, 'users', array_merge($user_details_for_log, ['reason' => 'User not found']));
    }
    $stmt->close();
    redirect($login_page); // إعادة توجيه في حالة فشل تسجيل الدخول (كلمة مرور خاطئة، مستخدم غير موجود، حساب غير نشط)
    exit;

} else {
    // إذا لم يكن الطلب POST، قم بإعادة التوجيه إلى صفحة تسجيل الدخول
    set_message('طريقة الطلب غير صالحة.', 'warning'); // أو لا تعرض رسالة هنا
    redirect($login_page);
    exit;
}
?>