<?php
// auth/process_login.php
require_once __DIR__ . '/../db_connect.php'; // يضمن تحميل config.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php'; // تضمين ملف سجل التدقيق

// لا تبدأ جلسة جديدة هنا، header_resources.php أو session_manager.php يجب أن يكون قد بدأها
if (session_status() == PHP_SESSION_NONE) {
    // هذا للتعامل مع الحالات التي قد يتم فيها استدعاء هذا الملف مباشرة (وهو أمر غير مثالي)
    // session_start(); // يفضل أن يتم التحكم بالجلسات من session_manager.php
}

$response = ['success' => false, 'message' => 'فشل تسجيل الدخول.']; // رسالة افتراضية

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $response['message'] = 'خطأ في التحقق (CSRF). يرجى تحديث الصفحة والمحاولة مرة أخرى.';
        // لا نسجل محاولة تسجيل دخول فاشلة هنا بسبب خطأ CSRF لأنه قد يكون هجومًا
        echo json_encode($response);
        exit;
    }

    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $user_attempt_id = null; // لتسجيل محاولة فاشلة قبل معرفة ID المستخدم
    $user_details_for_log = ['username_attempt' => $username_or_email];


    if (empty($username_or_email) || empty($password)) {
        $response['message'] = 'يرجى إدخال اسم المستخدم/البريد الإلكتروني وكلمة المرور.';
        log_audit_action($mysqli, AUDIT_LOGIN_ATTEMPT_FAILED, null, 'users', $user_details_for_log);
        echo json_encode($response);
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
        $response['message'] = 'خطأ في النظام، يرجى المحاولة لاحقًا.';
        // لا يمكن تسجيل هذا كفشل تسجيل دخول لأنه خطأ نظام
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param("ss", $username_or_email, $username_or_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $user_attempt_id = $user['id']; // لدينا ID المستخدم الآن
        $user_details_for_log['user_id_found'] = $user_attempt_id;

        if (password_verify($password, $user['password_hash'])) {
            if ($user['is_active'] == 1) {
                // Regenerate session ID to prevent session fixation
                // يجب استدعاء session_regenerate_id() قبل تعيين أي متغيرات جلسة جديدة
                if (session_status() == PHP_SESSION_ACTIVE) { // تأكد أن الجلسة نشطة
                     session_regenerate_id(true);
                } else {
                    // إذا لم تكن الجلسة قد بدأت بعد (وهو أمر غير مرجح هنا إذا تم تضمين session_manager)
                    // session_start(); 
                    // session_regenerate_id(true);
                    // هذا السطر للسلامة فقط، يجب أن تكون الجلسة قد بدأت بالفعل
                    error_log("Login process: Session was not active before regenerate. This might indicate an issue.");
                }


                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_full_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role_id'] = $user['role_id'];
                $_SESSION['user_role_name'] = $user['role_name'];
                $_SESSION['logged_in_timestamp'] = time();
                
                log_audit_action($mysqli, AUDIT_LOGIN_SUCCESS, $user['id'], 'users', ['username' => $user['username']]);
                
                $response['success'] = true;
                $response['message'] = 'تم تسجيل الدخول بنجاح!';
                $response['redirect_url'] = base_url('dashboard.php');
            } else {
                $response['message'] = 'حسابك غير نشط. يرجى الاتصال بالمسؤول.';
                log_audit_action($mysqli, AUDIT_LOGIN_ATTEMPT_FAILED, $user_attempt_id, 'users', array_merge($user_details_for_log, ['reason' => 'Account inactive']));
            }
        } else {
            $response['message'] = 'اسم المستخدم/البريد الإلكتروني أو كلمة المرور غير صحيحة.';
            log_audit_action($mysqli, AUDIT_LOGIN_ATTEMPT_FAILED, $user_attempt_id, 'users', array_merge($user_details_for_log, ['reason' => 'Incorrect password']));
        }
    } else {
        $response['message'] = 'اسم المستخدم/البريد الإلكتروني أو كلمة المرور غير صحيحة.';
        // لا يوجد user_id هنا لأن المستخدم غير موجود
        log_audit_action($mysqli, AUDIT_LOGIN_ATTEMPT_FAILED, null, 'users', array_merge($user_details_for_log, ['reason' => 'User not found']));
    }
    $stmt->close();

} else {
    $response['message'] = 'طريقة الطلب غير صالحة.';
    // لا يتم تسجيل هذا كفشل تسجيل دخول لأنه ليس محاولة فعلية
}

// لا تغلق اتصال $mysqli هنا إذا كان الملف سيتم تضمينه في سياق آخر
// $mysqli->close(); 
echo json_encode($response);
?>