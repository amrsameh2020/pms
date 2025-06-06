<?php
// auth/logout.php
// يجب تضمين هذه الملفات قبل أي إخراج HTML أو استدعاء session_start() إذا لم تكن قد بدأت بالفعل
require_once __DIR__ . '/../db_connect.php'; // For $mysqli if needed for logging before session destroy
require_once __DIR__ . '/../includes/session_manager.php'; // Handles session_start() and includes functions.php
// require_once __DIR__ . '/../includes/functions.php'; // Already included by session_manager.php if structured correctly
require_once __DIR__ . '/../includes/audit_log_functions.php'; // For logging

if (is_logged_in()) {
    $user_id_for_log = $_SESSION['user_id'] ?? null;
    $username_for_log = $_SESSION['username'] ?? 'unknown_on_logout';

    // Log the logout action before destroying the session
    if ($user_id_for_log && isset($mysqli)) { // Check if $mysqli is available
        log_audit_action($mysqli, AUDIT_LOGOUT, $user_id_for_log, 'users', ['username' => $username_for_log]);
    } elseif (!isset($mysqli)) {
        error_log("Logout action: mysqli connection not available for audit logging.");
    }

    // Unset all of the session variables.
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie.
    // Note: This will destroy the session, and not just the session data!
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finally, destroy the session.
    session_destroy();

    // ابدأ جلسة جديدة فقط لتعيين رسالة الفلاش
    // هذا ضروري لأن الجلسة القديمة تم تدميرها
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Set the flash message to be displayed on the login page
    set_message("تم تسجيل الخروج بنجاح.", "success");

} else {
    // إذا لم يكن المستخدم مسجل دخوله بالفعل، يمكنك تعيين رسالة أو فقط إعادة التوجيه
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    set_message("أنت غير مسجل الدخول بالفعل.", "info");
}

// Redirect to login page (without message parameters in URL)
$login_page_url = base_url('auth/login.php');
header("Location: " . $login_page_url);
exit;
?>