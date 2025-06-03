<?php
// property_types/actions.php
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

    $property_type_id = isset($_POST['property_type_id']) ? filter_var($_POST['property_type_id'], FILTER_SANITIZE_NUMBER_INT) : null;
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
        if ($action === 'add_property_type') {
            // ... (فحص التكرار كما في النسخة السابقة)
            $stmt_check = $mysqli->prepare("SELECT id FROM property_types WHERE type_name = ?");
            if (!$stmt_check) throw new Exception('خطأ تجهيز فحص المعرف: ' . $mysqli->error);
            $stmt_check->bind_param("s", $type_name); $stmt_check->execute(); $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) throw new Exception('المعرف "' . esc_html($type_name) . '" مستخدم بالفعل.');
            $stmt_check->close();

            $stmt_insert = $mysqli->prepare("INSERT INTO property_types (type_name, display_name_ar) VALUES (?, ?)");
            if (!$stmt_insert) throw new Exception('فشل في تحضير استعلام الإضافة: ' . $mysqli->error);
            $stmt_insert->bind_param("ss", $type_name, $display_name_ar);
            if (!$stmt_insert->execute()) throw new Exception('فشل في إضافة النوع: ' . $stmt_insert->error);
            
            $new_ptype_id = $stmt_insert->insert_id;
            $stmt_insert->close();
            log_audit_action($mysqli, AUDIT_CREATE_PROPERTY_TYPE, $new_ptype_id, 'property_types', ['type_name' => $type_name, 'display_name_ar' => $display_name_ar]); // <<--- تسجيل الحدث
            $response = ['success' => true, 'message' => 'تمت إضافة نوع العقار بنجاح!'];

        } elseif ($action === 'edit_property_type' && $property_type_id) {
            $stmt_old_ptype = $mysqli->prepare("SELECT type_name, display_name_ar FROM property_types WHERE id = ?");
            $old_ptype_data = null;
            if($stmt_old_ptype){
                $stmt_old_ptype->bind_param("i", $property_type_id); $stmt_old_ptype->execute();
                $res_old_ptype = $stmt_old_ptype->get_result();
                if($res_old_ptype->num_rows > 0) $old_ptype_data = $res_old_ptype->fetch_assoc();
                $stmt_old_ptype->close();
            }
            if(!$old_ptype_data) throw new Exception("نوع العقار المطلوب تعديله غير موجود.");

            // ... (فحص التكرار عند التعديل كما في النسخة السابقة)
            $stmt_check_edit = $mysqli->prepare("SELECT id FROM property_types WHERE type_name = ? AND id != ?");
            if (!$stmt_check_edit) throw new Exception('خطأ تجهيز فحص المعرف (تعديل): ' . $mysqli->error);
            $stmt_check_edit->bind_param("si", $type_name, $property_type_id); $stmt_check_edit->execute(); $stmt_check_edit->store_result();
            if ($stmt_check_edit->num_rows > 0) throw new Exception('المعرف "' . esc_html($type_name) . '" مستخدم بالفعل لنوع آخر.');
            $stmt_check_edit->close();

            $stmt_update = $mysqli->prepare("UPDATE property_types SET type_name = ?, display_name_ar = ? WHERE id = ?");
            if (!$stmt_update) throw new Exception('فشل في تحضير استعلام التحديث: ' . $mysqli->error);
            $stmt_update->bind_param("ssi", $type_name, $display_name_ar, $property_type_id);
            if (!$stmt_update->execute()) throw new Exception('فشل في تحديث النوع: ' . $stmt_update->error);
            $stmt_update->close();
            
            $new_ptype_data = compact('type_name', 'display_name_ar');
            log_audit_action($mysqli, AUDIT_EDIT_PROPERTY_TYPE, $property_type_id, 'property_types', ['old_data' => $old_ptype_data, 'new_data' => $new_ptype_data]); // <<--- تسجيل الحدث
            $response = ['success' => true, 'message' => 'تم تحديث نوع العقار بنجاح!'];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف النوع مفقود.");
        }
        $mysqli->commit(); // <<--- تأكيد المعاملة

    } catch (Exception $e) {
        $mysqli->rollback(); // <<--- التراجع عن المعاملة
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Property Type Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_property_type') {
    // ... (منطق الحذف من GET request كما في النسخة السابقة مع إضافة سجل التدقيق والمعاملات)
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('property_types/index.php'));
    }
    $pt_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($pt_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_ptype_del_info = $mysqli->prepare("SELECT type_name, display_name_ar FROM property_types WHERE id = ?");
            $ptype_details_log = null;
            if($stmt_ptype_del_info){
                $stmt_ptype_del_info->bind_param("i", $pt_id_to_delete); $stmt_ptype_del_info->execute();
                $res_ptype_del = $stmt_ptype_del_info->get_result();
                if($res_ptype_del->num_rows > 0) $ptype_details_log = $res_ptype_del->fetch_assoc();
                $stmt_ptype_del_info->close();
            }
            if(!$ptype_details_log) throw new Exception("نوع العقار المطلوب حذفه غير موجود.");

            // ... (فحص استخدام النوع كما في النسخة السابقة)
            $stmt_check_usage_get = $mysqli->prepare("SELECT COUNT(*) as count FROM properties WHERE property_type_id = ?");
            if (!$stmt_check_usage_get) throw new Exception('خطأ في فحص استخدام نوع العقار: ' . $mysqli->error);
            $stmt_check_usage_get->bind_param("i", $pt_id_to_delete); $stmt_check_usage_get->execute();
            $usage_result_get = $stmt_check_usage_get->get_result()->fetch_assoc();
            $stmt_check_usage_get->close();
            if ($usage_result_get && $usage_result_get['count'] > 0) {
                throw new Exception('لا يمكن حذف هذا النوع لأنه مستخدم في (' . $usage_result_get['count'] . ') عقار/عقارات.');
            }

            $stmt_delete_get = $mysqli->prepare("DELETE FROM property_types WHERE id = ?");
            if (!$stmt_delete_get) throw new Exception('فشل في تحضير استعلام الحذف: ' . $mysqli->error);
            $stmt_delete_get->bind_param("i", $pt_id_to_delete);
            if (!$stmt_delete_get->execute()) throw new Exception('فشل في حذف النوع: ' . $stmt_delete_get->error);
            $stmt_delete_get->close();
            
            log_audit_action($mysqli, AUDIT_DELETE_PROPERTY_TYPE, $pt_id_to_delete, 'property_types', $ptype_details_log); // <<--- تسجيل الحدث
            $mysqli->commit();
            set_message('تم حذف نوع العقار بنجاح!', "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Property Type Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف نوع العقار غير صحيح للحذف.", "danger");
    }
    redirect(base_url('property_types/index.php'));
}

// ... (بقية الكود)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_property_type')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>