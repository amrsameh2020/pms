<?php
// unit_types/actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); // إذا كانت هذه الوظيفة للمسؤول فقط
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

    $unit_type_id = isset($_POST['unit_type_id']) ? filter_var($_POST['unit_type_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    $type_name = isset($_POST['type_name']) ? strtolower(sanitize_input(str_replace(' ', '_', trim($_POST['type_name'])))) : null;
    $display_name_ar = isset($_POST['display_name_ar']) ? sanitize_input(trim($_POST['display_name_ar'])) : null;

    if (empty($type_name) || empty($display_name_ar)) {
        $response = ['success' => false, 'message' => 'المعرف والاسم المعروض مطلوبان.'];
        echo json_encode($response); exit;
    }
    if (!preg_match('/^[a-z0-9_]+$/', $type_name)) {
        $response = ['success' => false, 'message' => 'المعرف يجب أن يحتوي على أحرف إنجليزية صغيرة وأرقام وشرطة سفلية فقط.'];
        echo json_encode($response); exit;
    }

    $mysqli->begin_transaction(); // <<--- بدء المعاملة
    try {
        if ($action === 'add_unit_type') {
            // ... (فحص التكرار كما في النسخة السابقة)
            $stmt_check = $mysqli->prepare("SELECT id FROM unit_types WHERE type_name = ?");
            if (!$stmt_check) throw new Exception('خطأ تجهيز فحص المعرف: ' . $mysqli->error);
            $stmt_check->bind_param("s", $type_name); $stmt_check->execute(); $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) throw new Exception('المعرف "' . esc_html($type_name) . '" مستخدم بالفعل.');
            $stmt_check->close();

            $stmt_insert = $mysqli->prepare("INSERT INTO unit_types (type_name, display_name_ar) VALUES (?, ?)");
            if (!$stmt_insert) throw new Exception('فشل في تحضير استعلام الإضافة: ' . $mysqli->error);
            $stmt_insert->bind_param("ss", $type_name, $display_name_ar);
            if (!$stmt_insert->execute()) throw new Exception('فشل في إضافة النوع: ' . $stmt_insert->error);
            
            $new_utype_id = $stmt_insert->insert_id;
            $stmt_insert->close();
            log_audit_action($mysqli, AUDIT_CREATE_UNIT_TYPE, $new_utype_id, 'unit_types', ['type_name' => $type_name, 'display_name_ar' => $display_name_ar]); // <<--- تسجيل الحدث
            $response = ['success' => true, 'message' => 'تمت إضافة نوع الوحدة بنجاح!'];

        } elseif ($action === 'edit_unit_type' && $unit_type_id) {
            $stmt_old_utype = $mysqli->prepare("SELECT type_name, display_name_ar FROM unit_types WHERE id = ?");
            $old_utype_data = null;
            if($stmt_old_utype){
                $stmt_old_utype->bind_param("i", $unit_type_id); $stmt_old_utype->execute();
                $res_old_utype = $stmt_old_utype->get_result();
                if($res_old_utype->num_rows > 0) $old_utype_data = $res_old_utype->fetch_assoc();
                $stmt_old_utype->close();
            }
            if(!$old_utype_data) throw new Exception("نوع الوحدة المطلوب تعديله غير موجود.");

            // ... (فحص التكرار عند التعديل كما في النسخة السابقة)
            $stmt_check_edit = $mysqli->prepare("SELECT id FROM unit_types WHERE type_name = ? AND id != ?");
            if (!$stmt_check_edit) throw new Exception('خطأ تجهيز فحص المعرف (تعديل): ' . $mysqli->error);
            $stmt_check_edit->bind_param("si", $type_name, $unit_type_id); $stmt_check_edit->execute(); $stmt_check_edit->store_result();
            if ($stmt_check_edit->num_rows > 0) throw new Exception('المعرف "' . esc_html($type_name) . '" مستخدم بالفعل لنوع آخر.');
            $stmt_check_edit->close();

            $stmt_update = $mysqli->prepare("UPDATE unit_types SET type_name = ?, display_name_ar = ? WHERE id = ?");
            if (!$stmt_update) throw new Exception('فشل في تحضير استعلام التحديث: ' . $mysqli->error);
            $stmt_update->bind_param("ssi", $type_name, $display_name_ar, $unit_type_id);
            if (!$stmt_update->execute()) throw new Exception('فشل في تحديث النوع: ' . $stmt_update->error);
            $stmt_update->close();
            
            $new_utype_data = compact('type_name', 'display_name_ar');
            log_audit_action($mysqli, 'EDIT_UNIT_TYPE', $unit_type_id, 'unit_types', ['old_data' => $old_utype_data, 'new_data' => $new_utype_data]); // <<--- تسجيل الحدث (استخدم AUDIT_EDIT_UNIT_TYPE إذا عرفته)
            $response = ['success' => true, 'message' => 'تم تحديث نوع الوحدة بنجاح!'];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف النوع مفقود.");
        }
        $mysqli->commit(); // <<--- تأكيد المعاملة

    } catch (Exception $e) {
        $mysqli->rollback(); // <<--- التراجع عن المعاملة
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Unit Type Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_unit_type') {
    // ... (منطق الحذف من GET request كما في النسخة السابقة مع إضافة سجل التدقيق والمعاملات)
     if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('unit_types/index.php'));
    }
    $ut_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($ut_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_utype_del_info = $mysqli->prepare("SELECT type_name, display_name_ar FROM unit_types WHERE id = ?");
            $utype_details_log = null;
            if($stmt_utype_del_info){
                $stmt_utype_del_info->bind_param("i", $ut_id_to_delete); $stmt_utype_del_info->execute();
                $res_utype_del = $stmt_utype_del_info->get_result();
                if($res_utype_del->num_rows > 0) $utype_details_log = $res_utype_del->fetch_assoc();
                $stmt_utype_del_info->close();
            }
            if(!$utype_details_log) throw new Exception("نوع الوحدة المطلوب حذفه غير موجود.");

            // ... (فحص استخدام النوع كما في النسخة السابقة)
            $stmt_check_usage_get = $mysqli->prepare("SELECT COUNT(*) as count FROM units WHERE unit_type_id = ?");
            if (!$stmt_check_usage_get) throw new Exception('خطأ في فحص استخدام نوع الوحدة: ' . $mysqli->error);
            $stmt_check_usage_get->bind_param("i", $ut_id_to_delete); $stmt_check_usage_get->execute();
            $usage_result_get = $stmt_check_usage_get->get_result()->fetch_assoc();
            $stmt_check_usage_get->close();
            if ($usage_result_get && $usage_result_get['count'] > 0) {
                throw new Exception('لا يمكن حذف هذا النوع لأنه مستخدم في (' . $usage_result_get['count'] . ') وحدة/وحدات.');
            }


            $stmt_delete_get = $mysqli->prepare("DELETE FROM unit_types WHERE id = ?");
            if (!$stmt_delete_get) throw new Exception('فشل في تحضير استعلام الحذف: ' . $mysqli->error);
            $stmt_delete_get->bind_param("i", $ut_id_to_delete);
            if (!$stmt_delete_get->execute()) throw new Exception('فشل في حذف النوع: ' . $stmt_delete_get->error);
            $stmt_delete_get->close();
            
            log_audit_action($mysqli, 'DELETE_UNIT_TYPE', $ut_id_to_delete, 'unit_types', $utype_details_log); // <<--- تسجيل الحدث (استخدم AUDIT_DELETE_UNIT_TYPE إذا عرفته)
            $mysqli->commit();
            set_message('تم حذف نوع الوحدة بنجاح!', "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Unit Type Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف نوع الوحدة غير صحيح للحذف.", "danger");
    }
    redirect(base_url('unit_types/index.php'));
}

// ... (بقية الكود)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_unit_type')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>