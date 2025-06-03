<?php
// utility_types/actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); // If only for admin
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$action = $_POST['action'] ?? '';
$current_user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $response = ['success' => false, 'message' => 'خطأ في التحقق (CSRF).'];
        echo json_encode($response);
        exit;
    }

    $utility_type_id = isset($_POST['utility_type_id']) ? filter_var($_POST['utility_type_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    // Name attributes in utility_type_modal.php are 'utility_type_name_modal' and 'unit_of_measure_modal'
    $name = isset($_POST['utility_type_name_modal']) ? sanitize_input(trim($_POST['utility_type_name_modal'])) : null;
    $unit_of_measure = isset($_POST['unit_of_measure_modal']) ? sanitize_input(trim($_POST['unit_of_measure_modal'])) : null;

    if (empty($name) || empty($unit_of_measure)) {
        $response = ['success' => false, 'message' => 'اسم النوع ووحدة القياس مطلوبان.'];
        echo json_encode($response); exit;
    }
    
    $mysqli->begin_transaction();
    try {
        if ($action === 'add_utility_type') {
            $stmt_check = $mysqli->prepare("SELECT id FROM utility_types WHERE name = ?");
            if (!$stmt_check) throw new Exception('خطأ تجهيز فحص الاسم: ' . $mysqli->error);
            $stmt_check->bind_param("s", $name); $stmt_check->execute(); $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) throw new Exception('اسم النوع "' . esc_html($name) . '" مستخدم بالفعل.');
            $stmt_check->close();

            $stmt_insert = $mysqli->prepare("INSERT INTO utility_types (name, unit_of_measure) VALUES (?, ?)");
            if (!$stmt_insert) throw new Exception('فشل في تحضير استعلام الإضافة: ' . $mysqli->error);
            $stmt_insert->bind_param("ss", $name, $unit_of_measure);
            if (!$stmt_insert->execute()) throw new Exception('فشل في إضافة نوع المرفق: ' . $stmt_insert->error);
            
            $new_ut_id = $stmt_insert->insert_id;
            $stmt_insert->close();
            log_audit_action($mysqli, 'CREATE_UTILITY_TYPE', $new_ut_id, 'utility_types', ['name' => $name, 'unit_of_measure' => $unit_of_measure]);
            $response = ['success' => true, 'message' => 'تمت إضافة نوع المرفق بنجاح!'];

        } elseif ($action === 'edit_utility_type' && $utility_type_id) {
            $stmt_old_data = $mysqli->prepare("SELECT name, unit_of_measure FROM utility_types WHERE id = ?");
            $old_data = null;
            if($stmt_old_data){
                $stmt_old_data->bind_param("i", $utility_type_id); $stmt_old_data->execute();
                $res_old = $stmt_old_data->get_result();
                if($res_old->num_rows > 0) $old_data = $res_old->fetch_assoc();
                $stmt_old_data->close();
            }
            if(!$old_data) throw new Exception("نوع المرفق المطلوب تعديله غير موجود.");

            $stmt_check_edit = $mysqli->prepare("SELECT id FROM utility_types WHERE name = ? AND id != ?");
            if (!$stmt_check_edit) throw new Exception('خطأ تجهيز فحص الاسم (تعديل): ' . $mysqli->error);
            $stmt_check_edit->bind_param("si", $name, $utility_type_id); $stmt_check_edit->execute(); $stmt_check_edit->store_result();
            if ($stmt_check_edit->num_rows > 0) throw new Exception('اسم النوع "' . esc_html($name) . '" مستخدم بالفعل لنوع آخر.');
            $stmt_check_edit->close();

            $stmt_update = $mysqli->prepare("UPDATE utility_types SET name = ?, unit_of_measure = ? WHERE id = ?");
            if (!$stmt_update) throw new Exception('فشل في تحضير استعلام التحديث: ' . $mysqli->error);
            $stmt_update->bind_param("ssi", $name, $unit_of_measure, $utility_type_id);
            if (!$stmt_update->execute()) throw new Exception('فشل في تحديث نوع المرفق: ' . $stmt_update->error);
            $stmt_update->close();
            
            $new_data = compact('name', 'unit_of_measure');
            log_audit_action($mysqli, 'EDIT_UTILITY_TYPE', $utility_type_id, 'utility_types', ['old_data' => $old_data, 'new_data' => $new_data]);
            $response = ['success' => true, 'message' => 'تم تحديث نوع المرفق بنجاح!'];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف النوع مفقود.");
        }
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Utility Type Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_utility_type') {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('utility_types/index.php'));
    }
    $ut_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($ut_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_del_info = $mysqli->prepare("SELECT name FROM utility_types WHERE id = ?");
            $del_info_log = null;
            if($stmt_del_info){
                $stmt_del_info->bind_param("i", $ut_id_to_delete); $stmt_del_info->execute();
                $res_del = $stmt_del_info->get_result();
                if($res_del->num_rows > 0) $del_info_log = $res_del->fetch_assoc();
                $stmt_del_info->close();
            }
            if(!$del_info_log) throw new Exception("نوع المرفق المطلوب حذفه غير موجود.");

            $stmt_check_usage = $mysqli->prepare("SELECT COUNT(*) as count FROM utility_readings WHERE utility_type_id = ?");
            if (!$stmt_check_usage) throw new Exception('خطأ في فحص استخدام نوع المرفق: ' . $mysqli->error);
            $stmt_check_usage->bind_param("i", $ut_id_to_delete); $stmt_check_usage->execute();
            $usage_result = $stmt_check_usage->get_result()->fetch_assoc();
            $stmt_check_usage->close();
            if ($usage_result && $usage_result['count'] > 0) {
                throw new Exception('لا يمكن حذف هذا النوع لأنه مستخدم في (' . $usage_result['count'] . ') قراءة/قراءات عدادات.');
            }

            $stmt_delete = $mysqli->prepare("DELETE FROM utility_types WHERE id = ?");
            if (!$stmt_delete) throw new Exception('فشل في تحضير استعلام الحذف: ' . $mysqli->error);
            $stmt_delete->bind_param("i", $ut_id_to_delete);
            if (!$stmt_delete->execute()) throw new Exception('فشل في حذف نوع المرفق: ' . $stmt_delete->error);
            $stmt_delete->close();
            
            log_audit_action($mysqli, 'DELETE_UTILITY_TYPE', $ut_id_to_delete, 'utility_types', $del_info_log);
            $mysqli->commit();
            set_message('تم حذف نوع المرفق بنجاح!', "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Utility Type Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف نوع المرفق غير صحيح للحذف.", "danger");
    }
    redirect(base_url('utility_types/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_utility_type')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>