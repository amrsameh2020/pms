<?php
// includes/session_manager.php

if (session_status() == PHP_SESSION_NONE) {
    $session_lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 1800; // Default 30 minutes
    
    ini_set('session.gc_maxlifetime', $session_lifetime);
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '', // Should be configured properly
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// APP_BASE_URL is crucial for redirects. functions.php (which defines base_url)
// should be included if set_message and redirect are used here.
// For safety, ensure functions.php is loaded IF those functions are called.
// As require_login and require_role use set_message and redirect, functions.php is needed.
if (!function_exists('set_message')) { // Check if functions.php was already included
    if (file_exists(__DIR__ . '/functions.php')) {
        require_once __DIR__ . '/functions.php';
    } else {
        // Fallback if functions.php is missing, which would be a critical error
        error_log("CRITICAL ERROR: functions.php not found and is required by session_manager.php");
        // Define minimal fallbacks to prevent fatal errors, though functionality will be impaired.
        if (!function_exists('set_message')) { function set_message($m, $t) { $_SESSION['message'] = $m; $_SESSION['message_type'] = $t;} }
        if (!function_exists('base_url')) { function base_url($p) { return '/'.$p;} } // Very basic fallback
        if (!function_exists('redirect')) { function redirect($u) { header("Location: $u"); exit;} }
    }
}


// Session timeout check
function check_session_timeout() {
    $session_lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 1800;
    if (isset($_SESSION['logged_in_timestamp']) && (time() - $_SESSION['logged_in_timestamp'] > $session_lifetime)) {
        session_unset();
        session_destroy();
        
        $login_page_url = base_url('auth/login.php');
        // Use set_message if available, which it should be now
        set_message("انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.", "warning");
        header("Location: " . $login_page_url); // Redirect after setting message
        exit;
    }
    if (is_logged_in()){
      $_SESSION['logged_in_timestamp'] = time();
    }
}
check_session_timeout();


function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        set_message("الرجاء تسجيل الدخول للمتابعة.", "warning");
        
        $login_page_url = base_url('auth/login.php');
        // AJAX check
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(401); 
            echo json_encode(['success' => false, 'message' => "الرجاء تسجيل الدخول للمتابعة.", 'redirect' => $login_page_url]);
        } else {
            redirect($login_page_url);
        }
        exit();
    }
}

function user_has_role(string $role_name_to_check): bool {
    if (is_logged_in() && isset($_SESSION['user_role_name'])) { // Changed from user_role to user_role_name
        if (is_array($role_name_to_check)) {
            return in_array($_SESSION['user_role_name'], $role_name_to_check, true);
        }
        return $_SESSION['user_role_name'] === $role_name_to_check;
    }
    return false;
}

function require_role(string $required_role_name) {
    require_login(); 

    if (!user_has_role($required_role_name)) {
        set_message("ليس لديك الصلاحيات الكافية للوصول لهذه الصفحة.", "danger");
        $dashboard_url = base_url('dashboard.php');
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => "ليس لديك الصلاحيات الكافية."]);
        } else {
            if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== $_SERVER['REQUEST_URI']) {
                redirect($_SERVER['HTTP_REFERER']);
            } else {
                redirect($dashboard_url);
            }
        }
        exit();
    }
}

function get_current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

// Changed from get_current_user_role to get_current_user_role_name
function get_current_user_role_name(): ?string { 
    return $_SESSION['user_role_name'] ?? null; // Changed from user_role
}

function get_current_user_fullname(): ?string {
    // Assuming user_full_name is set during login process
    return $_SESSION['user_full_name'] ?? null; 
}

// Removed CSRF and message functions as they should be in functions.php
// Removed APP_BASE_URL definition as it should come from config.php
// Removed format_date_custom, esc_attr, esc_html, generate_pagination_links, generate_zatca_qr_code_data_string
// as they are general utility functions and belong in functions.php

?>