<?php
// auth/process_registration.php

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$register_page = base_url('auth/register.php');
$login_page = base_url('auth/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['message'] = "خطأ في التحقق من صحة الطلب (CSRF).";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $register_page);
        exit();
    }

    // Sanitize inputs
    $full_name = isset($_POST['full_name']) ? sanitize_input($_POST['full_name']) : '';
    $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
    $email = isset($_POST['email']) ? filter_var(sanitize_input($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // Store old data for pre-filling the form on error
    $_SESSION['old_data'] = [
        'full_name' => $full_name,
        'username' => $username,
        'email' => $email,
    ];
    $_SESSION['errors'] = [];

    // --- Validations ---
    // Full Name
    if (empty($full_name)) {
        $_SESSION['errors']['full_name'] = "الاسم الكامل مطلوب.";
    } elseif (mb_strlen($full_name) < 3 || mb_strlen($full_name) > 100) {
        $_SESSION['errors']['full_name'] = "يجب أن يكون الاسم الكامل بين 3 و 100 حرف.";
    }

    // Username
    if (empty($username)) {
        $_SESSION['errors']['username'] = "اسم المستخدم مطلوب.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $_SESSION['errors']['username'] = "يجب أن يتكون اسم المستخدم من 3 إلى 30 حرفًا إنجليزيًا أو أرقامًا أو شرطة سفلية (_).";
    } else {
        // Check if username already exists
        $stmt_check_username = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check_username->bind_param("s", $username);
        $stmt_check_username->execute();
        $stmt_check_username->store_result();
        if ($stmt_check_username->num_rows > 0) {
            $_SESSION['errors']['username'] = "اسم المستخدم هذا مسجل بالفعل. الرجاء اختيار اسم آخر.";
        }
        $stmt_check_username->close();
    }

    // Email
    if (empty($email)) {
        $_SESSION['errors']['email'] = "البريد الإلكتروني مطلوب.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['errors']['email'] = "صيغة البريد الإلكتروني غير صحيحة.";
    } else {
        // Check if email already exists
        $stmt_check_email = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            $_SESSION['errors']['email'] = "هذا البريد الإلكتروني مسجل بالفعل.";
        }
        $stmt_check_email->close();
    }

    // Password
    if (empty($password)) {
        $_SESSION['errors']['password'] = "كلمة المرور مطلوبة.";
    } elseif (strlen($password) < 8) {
        $_SESSION['errors']['password'] = "يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $_SESSION['errors']['password'] = "يجب أن تحتوي كلمة المرور على حرف كبير واحد على الأقل.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $_SESSION['errors']['password'] = "يجب أن تحتوي كلمة المرور على حرف صغير واحد على الأقل.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $_SESSION['errors']['password'] = "يجب أن تحتوي كلمة المرور على رقم واحد على الأقل.";
    } elseif (!preg_match('/[\W_]/', $password)) { // \W matches any non-word character, _ is for underscore
        $_SESSION['errors']['password'] = "يجب أن تحتوي كلمة المرور على رمز خاص واحد على الأقل (مثل !@#$%^&*).";
    }


    // Confirm Password
    if (empty($confirm_password)) {
        $_SESSION['errors']['confirm_password'] = "تأكيد كلمة المرور مطلوب.";
    } elseif ($password !== $confirm_password) {
        $_SESSION['errors']['confirm_password'] = "كلمتا المرور غير متطابقتين.";
    }


    // If there are validation errors, redirect back to registration page
    if (!empty($_SESSION['errors'])) {
        $_SESSION['message'] = "الرجاء تصحيح الأخطاء الموضحة أدناه.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $register_page);
        exit();
    }

    // --- Process Registration ---
    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Default role for new registrations (e.g., 'staff' or 'user')
    // Admins can change roles later if needed.
    $default_role = 'staff'; // أو 'user' حسب تصميمك

    $sql_insert_user = "INSERT INTO users (full_name, username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)";
    $stmt_insert_user = $mysqli->prepare($sql_insert_user);

    if ($stmt_insert_user) {
        $stmt_insert_user->bind_param("sssss", $full_name, $username, $email, $password_hash, $default_role);
        if ($stmt_insert_user->execute()) {
            // Registration successful
            unset($_SESSION['old_data']); // Clear old form data
            unset($_SESSION['errors']);   // Clear any previous errors

            // Optionally, log the user in directly, or redirect to login page with a success message
            $_SESSION['message'] = "تم إنشاء حسابك بنجاح! يمكنك الآن تسجيل الدخول.";
            $_SESSION['message_type'] = "success";
            header("Location: " . $login_page);
            exit();

        } else {
            // Database insertion error
            error_log("خطأ في تسجيل المستخدم (إدراج قاعدة البيانات): " . $stmt_insert_user->error);
            $_SESSION['message'] = "حدث خطأ أثناء إنشاء حسابك. يرجى المحاولة مرة أخرى.";
            $_SESSION['message_type'] = "danger";
        }
        $stmt_insert_user->close();
    } else {
        // SQL prepare error
        error_log("خطأ في تجهيز استعلام تسجيل المستخدم: " . $mysqli->error);
        $_SESSION['message'] = "حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقًا.";
        $_SESSION['message_type'] = "danger";
    }

    header("Location: " . $register_page); // Redirect back on error
    exit();

} else {
    // Not a POST request, redirect to registration page
    header("Location: " . $register_page);
    exit();
}
?>