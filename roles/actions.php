<?php
// roles/actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_role('admin'); 
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php'; // <<--- تم الإضافة

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $response = ['success' => false, 'message' => 'خطأ في التحقق (CSRF).'];
        echo json_encode($response);
        exit;
    }

    $role_id = isset($_POST['role_id']) ? filter_var($_POST['role_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    $role_name = isset($_POST['role_name']) ? strtolower(sanitize_input(str_replace(' ', '_', trim($_POST['role_name'])))) : null;
    $display_name_ar = isset($_POST['display_name_ar']) ? sanitize_input(trim($_POST['display_name_ar'])) : null;
    $description = isset($_POST['description']) ? sanitize_input(trim($_POST['description'])) : null;

    if (empty($role_name) || empty($display_name_ar)) {
        $response = ['success' => false, 'message' => 'المعرف والاسم المعروض مطلوبان.'];
        echo json_encode($response); exit;
    }
    if (!preg_match('/^[a-z0-9_]+$/', $role_name)) {
        $response = ['success' => false, 'message' => 'المعرف يجب أن يحتوي على أحرف إنجليزية صغيرة وأرقام وشرطة سفلية فقط.'];
        echo json_encode($response); exit;
    }
    $protected_roles = ['admin', 'staff'];

    $mysqli->begin_transaction(); // <<--- بدء المعاملة
    try {
        if ($action === 'add_role') {
            if (in_array($role_name, $protected_roles)) {
                throw new Exception('لا يمكن إضافة دور بهذا الاسم المحجوز: ' . esc_html($role_name));
            }
            // ... (فحص التكرار كما في النسخة السابقة)
            $stmt_check = $mysqli->prepare("SELECT id FROM roles WHERE role_name = ?");
            if (!$stmt_check) throw new Exception('خطأ تجهيز فحص المعرف: ' . $mysqli->error);
            $stmt_check->bind_param("s", $role_name); $stmt_check->execute(); $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) throw new Exception('المعرف "' . esc_html($role_name) . '" مستخدم بالفعل.');
            $stmt_check->close();

            $stmt_insert = $mysqli->prepare("INSERT INTO roles (role_name, display_name_ar, description) VALUES (?, ?, ?)");
            if (!$stmt_insert) throw new Exception('فشل في تحضير استعلام الإضافة: ' . $mysqli->error);
            $stmt_insert->bind_param("sss", $role_name, $display_name_ar, $description);
            if (!$stmt_insert->execute()) throw new Exception('فشل في إضافة الدور: ' . $stmt_insert->error);
            
            $new_role_id = $stmt_insert->insert_id;
            $stmt_insert->close();
            log_audit_action($mysqli, AUDIT_CREATE_ROLE, $new_role_id, 'roles', ['role_name' => $role_name, 'display_name_ar' => $display_name_ar]); // <<--- تسجيل الحدث
            $response = ['success' => true, 'message' => 'تمت إضافة الدور بنجاح!'];

        } elseif ($action === 'edit_role' && $role_id) {
            // ... (منطق جلب الدور القديم وفحص الأدوار المحمية كما في النسخة السابقة)
            $stmt_old_role_info = $mysqli->prepare("SELECT role_name, display_name_ar, description FROM roles WHERE id = ?");
            $old_role_data = null;
            if ($stmt_old_role_info) {
                $stmt_old_role_info->bind_param("i", $role_id); $stmt_old_role_info->execute();
                $res_old_role = $stmt_old_role_info->get_result();
                if($res_old_role->num_rows > 0) $old_role_data = $res_old_role->fetch_assoc();
                $stmt_old_role_info->close();
            }
            if(!$old_role_data) throw new Exception("الدور المطلوب تعديله غير موجود.");
            if (in_array($old_role_data['role_name'], $protected_roles) && $old_role_data['role_name'] !== $role_name) {
                 throw new Exception('لا يمكن تغيير المعرف للأدوار المحمية.');
            }

            // ... (فحص التكرار عند التعديل كما في النسخة السابقة)
            $stmt_check_edit = $mysqli->prepare("SELECT id FROM roles WHERE role_name = ? AND id != ?");
            if (!$stmt_check_edit) throw new Exception('خطأ تجهيز فحص المعرف (تعديل): ' . $mysqli->error);
            $stmt_check_edit->bind_param("si", $role_name, $role_id); $stmt_check_edit->execute(); $stmt_check_edit->store_result();
            if ($stmt_check_edit->num_rows > 0) throw new Exception('المعرف "' . esc_html($role_name) . '" مستخدم بالفعل لدور آخر.');
            $stmt_check_edit->close();

            $stmt_update = $mysqli->prepare("UPDATE roles SET role_name = ?, display_name_ar = ?, description = ? WHERE id = ?");
            if (!$stmt_update) throw new Exception('فشل في تحضير استعلام التحديث: ' . $mysqli->error);
            $stmt_update->bind_param("sssi", $role_name, $display_name_ar, $description, $role_id);
            if (!$stmt_update->execute()) throw new Exception('فشل في تحديث الدور: ' . $stmt_update->error);
            $stmt_update->close();
            
            $new_role_data = compact('role_name', 'display_name_ar', 'description');
            log_audit_action($mysqli, AUDIT_EDIT_ROLE, $role_id, 'roles', ['old_data' => $old_role_data, 'new_data' => $new_role_data]); // <<--- تسجيل الحدث
            $response = ['success' => true, 'message' => 'تم تحديث الدور بنجاح!'];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف الدور مفقود.");
        }
        $mysqli->commit(); // <<--- تأكيد المعاملة

    } catch (Exception $e) {
        $mysqli->rollback(); // <<--- التراجع عن المعاملة
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Role Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_role') {
    // ... (منطق الحذف من GET request كما في النسخة السابقة مع إضافة سجل التدقيق والمعاملات)
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('roles/index.php'));
    }
    $role_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($role_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_role_del_info = $mysqli->prepare("SELECT role_name, display_name_ar FROM roles WHERE id = ?");
            $role_details_log = null;
            if($stmt_role_del_info){
                $stmt_role_del_info->bind_param("i", $role_id_to_delete);
                $stmt_role_del_info->execute();
                $res_role_del = $stmt_role_del_info->get_result();
                if($res_role_del->num_rows > 0) $role_details_log = $res_role_del->fetch_assoc();
                $stmt_role_del_info->close();
            }
            if(!$role_details_log) throw new Exception("الدور المطلوب حذفه غير موجود.");
            if (in_array($role_details_log['role_name'], ['admin', 'staff'])) {
                throw new Exception('لا يمكن حذف الأدوار المحمية (admin, staff).');
            }
            // ... (فحص استخدام الدور كما في النسخة السابقة)
            $stmt_check_usage_get = $mysqli->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ?");
            if (!$stmt_check_usage_get) throw new Exception('خطأ في فحص استخدام الدور: ' . $mysqli->error);
            $stmt_check_usage_get->bind_param("i", $role_id_to_delete); $stmt_check_usage_get->execute();
            $usage_result_get = $stmt_check_usage_get->get_result()->fetch_assoc();
            $stmt_check_usage_get->close();
            if ($usage_result_get && $usage_result_get['count'] > 0) {
                throw new Exception('لا يمكن حذف هذا الدور لأنه معين لمستخدمين.');
            }

            $stmt_delete_get = $mysqli->prepare("DELETE FROM roles WHERE id = ?");
            if (!$stmt_delete_get) throw new Exception('فشل في تحضير استعلام الحذف: ' . $mysqli->error);
            $stmt_delete_get->bind_param("i", $role_id_to_delete);
            if (!$stmt_delete_get->execute()) throw new Exception('فشل في حذف الدور: ' . $stmt_delete_get->error);
            $stmt_delete_get->close();
            
            log_audit_action($mysqli, AUDIT_DELETE_ROLE, $role_id_to_delete, 'roles', $role_details_log); // <<--- تسجيل الحدث
            $mysqli->commit();
            set_message('تم حذف الدور بنجاح!', "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Role Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف الدور غير صحيح للحذف.", "danger");
    }
    redirect(base_url('roles/index.php'));
}

// ... (بقية الكود)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_role')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>