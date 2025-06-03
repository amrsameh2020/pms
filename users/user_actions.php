<?php
// users/user_actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_role('admin'); // فقط المسؤول يمكنه إدارة المستخدمين
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php'; // تضمين ملف سجل التدقيق

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$action = $_POST['action'] ?? '';
$current_user_id_performing_action = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $response = ['success' => false, 'message' => 'خطأ في التحقق (CSRF).'];
        echo json_encode($response);
        exit;
    }

    $user_id_from_post = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    $user_full_name = isset($_POST['user_full_name']) ? sanitize_input(trim($_POST['user_full_name'])) : null;
    $user_username = isset($_POST['user_username']) ? sanitize_input(trim(strtolower($_POST['user_username']))) : null; // Username to lowercase
    $user_email_input = isset($_POST['user_email']) ? trim($_POST['user_email']) : null;
    $user_email = ($user_email_input === '' || $user_email_input === null) ? null : filter_var(sanitize_input($user_email_input), FILTER_SANITIZE_EMAIL);
    $user_password = $_POST['user_password'] ?? ''; // لا تقم بعمل trim لكلمة المرور هنا
    $user_confirm_password = $_POST['user_confirm_password'] ?? '';
    $user_role_id = isset($_POST['role_id']) && filter_var($_POST['role_id'], FILTER_VALIDATE_INT) ? (int)$_POST['role_id'] : null;
    $user_is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0; // Default to inactive if not set or invalid

    if (!in_array($user_is_active, [0, 1])) {
        $user_is_active = 0; // Default to inactive if value is unexpected
    }
    
    // Validations
    if (empty($user_full_name) || empty($user_username) || empty($user_email) || $user_role_id === null) {
        $response = ['success' => false, 'message' => 'الحقول (الاسم الكامل، اسم المستخدم، البريد الإلكتروني، الدور) مطلوبة.'];
        echo json_encode($response); exit;
    }
    if (!preg_match('/^[a-z0-9_]{3,50}$/', $user_username)) {
        $response = ['success' => false, 'message' => 'اسم المستخدم يجب أن يتكون من 3 إلى 50 حرفًا إنجليزيًا صغيرًا أو أرقامًا أو شرطة سفلية فقط.'];
        echo json_encode($response); exit;
    }
    if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'صيغة البريد الإلكتروني غير صحيحة.'];
        echo json_encode($response); exit;
    }
    
    $mysqli->begin_transaction();
    try {
        if ($action === 'add_user') {
            if (empty($user_password)) {
                throw new Exception('كلمة المرور مطلوبة عند إضافة مستخدم جديد.');
            }
            if (strlen($user_password) < 8) { // Basic password length check
                throw new Exception('يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.');
            }
            if ($user_password !== $user_confirm_password) {
                throw new Exception('كلمتا المرور غير متطابقتين.');
            }

            // Check for duplicate username or email
            $stmt_check_add = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            if(!$stmt_check_add) throw new Exception('خطأ تجهيز فحص التكرار: ' . $mysqli->error);
            $stmt_check_add->bind_param("ss", $user_username, $user_email);
            $stmt_check_add->execute();
            $stmt_check_add->store_result();
            if ($stmt_check_add->num_rows > 0) {
                throw new Exception('اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل.');
            }
            $stmt_check_add->close();

            $password_hash = password_hash($user_password, PASSWORD_DEFAULT);
            $stmt_add = $mysqli->prepare("INSERT INTO users (full_name, username, email, password_hash, role_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt_add) throw new Exception('فشل في تحضير استعلام إضافة المستخدم: ' . $mysqli->error);
            
            $stmt_add->bind_param("ssssii", $user_full_name, $user_username, $user_email, $password_hash, $user_role_id, $user_is_active);
            if (!$stmt_add->execute()) throw new Exception('فشل في إضافة المستخدم: ' . $stmt_add->error);
            
            $new_user_id = $stmt_add->insert_id;
            $stmt_add->close();
            log_audit_action($mysqli, AUDIT_CREATE_USER, $new_user_id, 'users', ['username' => $user_username, 'role_id' => $user_role_id]);
            $response = ['success' => true, 'message' => 'تمت إضافة المستخدم بنجاح!'];

        } elseif ($action === 'edit_user' && $user_id_from_post) {
            if ($user_username === 'admin' && $user_id_from_post != 1) { // Assuming admin user has ID 1 and cannot be renamed by others
                 throw new Exception('لا يمكن تعيين اسم المستخدم "admin" لمستخدم آخر.');
            }
             // Prevent changing username of admin (ID 1) or current logged in user if they are admin
            $stmt_get_user_info = $mysqli->prepare("SELECT username, role_id FROM users WHERE id = ?");
            $original_username_db = null;
            $original_role_id_db = null;
            if($stmt_get_user_info) {
                $stmt_get_user_info->bind_param("i", $user_id_from_post);
                $stmt_get_user_info->execute();
                $res_user_info = $stmt_get_user_info->get_result();
                if($res_user_info->num_rows > 0) {
                    $user_info_row = $res_user_info->fetch_assoc();
                    $original_username_db = $user_info_row['username'];
                    $original_role_id_db = $user_info_row['role_id'];
                }
                $stmt_get_user_info->close();
            }
            if (!$original_username_db) throw new Exception("المستخدم المطلوب تعديله غير موجود.");


            if ($original_username_db === 'admin' && $user_username !== 'admin') {
                 throw new Exception('لا يمكن تغيير اسم المستخدم الخاص بالمسؤول الرئيسي (admin).');
            }
             // Prevent deactivating or changing role of the main admin user (ID 1) if attempted by someone other than admin ID 1
             // Or prevent admin ID 1 from deactivating self or changing own role to non-admin
            if ($user_id_from_post == 1 && ($user_is_active == 0 || $user_role_id != 1 /* Assuming admin role_id is 1 */)) {
                 if ($current_user_id_performing_action != 1 || $user_is_active == 0 || ($user_role_id != 1 && $original_role_id_db == 1) ) {
                     throw new Exception('لا يمكن تعطيل حساب المسؤول الرئيسي أو تغيير دوره إلى دور غير مسؤول.');
                 }
            }


            $stmt_check_edit = $mysqli->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            if (!$stmt_check_edit) throw new Exception('خطأ تجهيز فحص التكرار (تعديل): ' . $mysqli->error);
            $stmt_check_edit->bind_param("ssi", $user_username, $user_email, $user_id_from_post);
            $stmt_check_edit->execute();
            $stmt_check_edit->store_result();
            if ($stmt_check_edit->num_rows > 0) {
                throw new Exception('اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل لمستخدم آخر.');
            }
            $stmt_check_edit->close();

            $old_user_data_for_log = $mysqli->query("SELECT * FROM users WHERE id = " . (int)$user_id_from_post)->fetch_assoc();


            $sql_update_user = "UPDATE users SET full_name = ?, username = ?, email = ?, role_id = ?, is_active = ?";
            $types_update_user = "sssii";
            $params_update_user = [$user_full_name, $user_username, $user_email, $user_role_id, $user_is_active];

            if (!empty($user_password)) {
                if (strlen($user_password) < 8) {
                    throw new Exception('يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.');
                }
                if ($user_password !== $user_confirm_password) {
                    throw new Exception('كلمتا المرور غير متطابقتين.');
                }
                $password_hash_update = password_hash($user_password, PASSWORD_DEFAULT);
                $sql_update_user .= ", password_hash = ?";
                $types_update_user .= "s";
                $params_update_user[] = $password_hash_update;
            }
            $sql_update_user .= " WHERE id = ?";
            $types_update_user .= "i";
            $params_update_user[] = $user_id_from_post;

            $stmt_update = $mysqli->prepare($sql_update_user);
            if (!$stmt_update) throw new Exception('فشل في تحضير استعلام تعديل المستخدم: ' . $mysqli->error);
            
            $stmt_update->bind_param($types_update_user, ...$params_update_user);
            if (!$stmt_update->execute()) throw new Exception('فشل في تحديث بيانات المستخدم: ' . $stmt_update->error);
            $stmt_update->close();
            
            $new_user_data_for_log = $mysqli->query("SELECT * FROM users WHERE id = " . (int)$user_id_from_post)->fetch_assoc();
            // Remove password hash from logs
            unset($old_user_data_for_log['password_hash']);
            unset($new_user_data_for_log['password_hash']);
            log_audit_action($mysqli, AUDIT_EDIT_USER, $user_id_from_post, 'users', ['old_data' => $old_user_data_for_log, 'new_data' => $new_user_data_for_log]);
            $response = ['success' => true, 'message' => 'تم تحديث بيانات المستخدم بنجاح!'];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف المستخدم مفقود.");
        }
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("User Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_user') {
    if (!is_logged_in() || !user_has_role('admin')) { // Double check role for GET delete
        set_message("ليس لديك الصلاحية لحذف المستخدمين.", "danger");
        redirect(base_url('users/index.php'));
    }
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('users/index.php'));
    }

    $user_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($user_id_to_delete > 0) {
        if ($user_id_to_delete == 1 || $user_id_to_delete == $current_user_id_performing_action) { // لا يمكن حذف المستخدم admin أو المستخدم الحالي
            set_message("لا يمكن حذف حساب المسؤول الرئيسي أو حسابك الحالي.", "warning");
            redirect(base_url('users/index.php'));
        }
        
        $mysqli->begin_transaction();
        try {
            $stmt_user_del_info = $mysqli->prepare("SELECT username, full_name FROM users WHERE id = ?");
            $user_details_log = null;
            if($stmt_user_del_info){
                $stmt_user_del_info->bind_param("i", $user_id_to_delete);
                $stmt_user_del_info->execute();
                $res_user_del = $stmt_user_del_info->get_result();
                if($res_user_del->num_rows > 0) $user_details_log = $res_user_del->fetch_assoc();
                $stmt_user_del_info->close();
            }
            if(!$user_details_log) throw new Exception("المستخدم المطلوب حذفه غير موجود.");

            // ملاحظة: يجب عليك تحديد ماذا تفعل بالحقول created_by_id في الجداول الأخرى
            // التي تشير إلى هذا المستخدم. حالياً، هي ON DELETE SET NULL.

            $stmt_delete = $mysqli->prepare("DELETE FROM users WHERE id = ?");
            if (!$stmt_delete) throw new Exception('فشل في تحضير استعلام الحذف: ' . $mysqli->error);
            
            $stmt_delete->bind_param("i", $user_id_to_delete);
            if (!$stmt_delete->execute()) throw new Exception('فشل في حذف المستخدم: ' . $stmt_delete->error);
            $stmt_delete->close();
            
            log_audit_action($mysqli, AUDIT_DELETE_USER, $user_id_to_delete, 'users', $user_details_log);
            $mysqli->commit();
            set_message("تم حذف المستخدم بنجاح!", "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("User Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف المستخدم غير صحيح للحذف.", "danger");
    }
    redirect(base_url('users/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_user')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>