<?php
// payment_methods/actions.php
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

    $payment_method_id = isset($_POST['payment_method_id']) ? filter_var($_POST['payment_method_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    $method_name = isset($_POST['method_name']) ? sanitize_input(trim($_POST['method_name'])) : null;
    $display_name_ar = isset($_POST['display_name_ar']) ? sanitize_input(trim($_POST['display_name_ar'])) : null;
    $zatca_code_input = isset($_POST['zatca_code']) ? trim($_POST['zatca_code']) : '';
    $zatca_code = ($zatca_code_input === '') ? null : sanitize_input($zatca_code_input);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($method_name) || empty($display_name_ar)) {
        $response = ['success' => false, 'message' => 'المعرف والاسم المعروض مطلوبان.'];
        echo json_encode($response); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $method_name)) {
        $response = ['success' => false, 'message' => 'المعرف يجب أن يحتوي على أحرف إنجليزية وأرقام وشرطة سفلية فقط.'];
        echo json_encode($response); exit;
    }
    if ($zatca_code !== null && (strlen($zatca_code) > 2 || !ctype_digit($zatca_code))) {
        $response = ['success' => false, 'message' => 'رمز ZATCA يجب أن يكون رقمًا مكونًا من خانتين على الأكثر إذا تم إدخاله.'];
        echo json_encode($response); exit;
    }

    $mysqli->begin_transaction(); // <<--- بدء المعاملة
    try {
        if ($action === 'add_payment_method') {
            $stmt_check = $mysqli->prepare("SELECT id FROM payment_methods WHERE method_name = ?");
            if (!$stmt_check) throw new Exception('خطأ تجهيز فحص المعرف: ' . $mysqli->error);
            $stmt_check->bind_param("s", $method_name); $stmt_check->execute(); $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) throw new Exception('المعرف "' . esc_html($method_name) . '" مستخدم بالفعل.');
            $stmt_check->close();

            $stmt_insert = $mysqli->prepare("INSERT INTO payment_methods (method_name, display_name_ar, zatca_code, is_active) VALUES (?, ?, ?, ?)");
            if (!$stmt_insert) throw new Exception('فشل في تحضير استعلام الإضافة: ' . $mysqli->error);
            $stmt_insert->bind_param("sssi", $method_name, $display_name_ar, $zatca_code, $is_active);
            if (!$stmt_insert->execute()) throw new Exception('فشل في إضافة الطريقة: ' . $stmt_insert->error);
            
            $new_pm_id = $stmt_insert->insert_id;
            $stmt_insert->close();
            // افترض أن AUDIT_CREATE_PAYMENT_METHOD معرف
            log_audit_action($mysqli, 'CREATE_PAYMENT_METHOD', $new_pm_id, 'payment_methods', ['method_name' => $method_name, 'display_name_ar' => $display_name_ar, 'is_active' => $is_active]); // <<--- تسجيل الحدث
            $response = ['success' => true, 'message' => 'تمت إضافة طريقة الدفع بنجاح!'];

        } elseif ($action === 'edit_payment_method' && $payment_method_id) {
            $stmt_old_pm = $mysqli->prepare("SELECT * FROM payment_methods WHERE id = ?");
            $old_pm_data = null;
            if($stmt_old_pm){
                $stmt_old_pm->bind_param("i", $payment_method_id); $stmt_old_pm->execute();
                $res_old_pm = $stmt_old_pm->get_result();
                if($res_old_pm->num_rows > 0) $old_pm_data = $res_old_pm->fetch_assoc();
                $stmt_old_pm->close();
            }
            if(!$old_pm_data) throw new Exception("طريقة الدفع المطلوبة للتعديل غير موجودة.");

            $stmt_check_edit = $mysqli->prepare("SELECT id FROM payment_methods WHERE method_name = ? AND id != ?");
            if (!$stmt_check_edit) throw new Exception('خطأ تجهيز فحص المعرف (تعديل): ' . $mysqli->error);
            $stmt_check_edit->bind_param("si", $method_name, $payment_method_id); $stmt_check_edit->execute(); $stmt_check_edit->store_result();
            if ($stmt_check_edit->num_rows > 0) throw new Exception('المعرف "' . esc_html($method_name) . '" مستخدم بالفعل لطريقة أخرى.');
            $stmt_check_edit->close();

            $stmt_update = $mysqli->prepare("UPDATE payment_methods SET method_name = ?, display_name_ar = ?, zatca_code = ?, is_active = ? WHERE id = ?");
            if (!$stmt_update) throw new Exception('فشل في تحضير استعلام التحديث: ' . $mysqli->error);
            $stmt_update->bind_param("sssii", $method_name, $display_name_ar, $zatca_code, $is_active, $payment_method_id);
            if (!$stmt_update->execute()) throw new Exception('فشل في تحديث الطريقة: ' . $stmt_update->error);
            $stmt_update->close();
            
            $new_pm_data = compact('method_name', 'display_name_ar', 'zatca_code', 'is_active');
             // افترض أن AUDIT_EDIT_PAYMENT_METHOD معرف
            log_audit_action($mysqli, 'EDIT_PAYMENT_METHOD', $payment_method_id, 'payment_methods', ['old_data' => $old_pm_data, 'new_data' => $new_pm_data]); // <<--- تسجيل الحدث
            $response = ['success' => true, 'message' => 'تم تحديث طريقة الدفع بنجاح!'];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف الطريقة مفقود.");
        }
        $mysqli->commit(); // <<--- تأكيد المعاملة

    } catch (Exception $e) {
        $mysqli->rollback(); // <<--- التراجع عن المعاملة
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Payment Method Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_payment_method') {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('payment_methods/index.php'));
    }
    $pm_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($pm_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_pm_del_info = $mysqli->prepare("SELECT method_name, display_name_ar FROM payment_methods WHERE id = ?");
            $pm_details_log = null;
            if($stmt_pm_del_info){
                $stmt_pm_del_info->bind_param("i", $pm_id_to_delete); $stmt_pm_del_info->execute();
                $res_pm_del = $stmt_pm_del_info->get_result();
                if($res_pm_del->num_rows > 0) $pm_details_log = $res_pm_del->fetch_assoc();
                $stmt_pm_del_info->close();
            }
            if(!$pm_details_log) throw new Exception("طريقة الدفع المطلوبة للحذف غير موجودة.");
            
            $stmt_check_usage_get = $mysqli->prepare("SELECT COUNT(*) as count FROM payments WHERE payment_method_id = ?");
            if (!$stmt_check_usage_get) throw new Exception('خطأ في فحص استخدام طريقة الدفع: ' . $mysqli->error);
            $stmt_check_usage_get->bind_param("i", $pm_id_to_delete); $stmt_check_usage_get->execute();
            $usage_result_get = $stmt_check_usage_get->get_result()->fetch_assoc();
            $stmt_check_usage_get->close();
            if ($usage_result_get && $usage_result_get['count'] > 0) {
                throw new Exception('لا يمكن حذف طريقة الدفع هذه لأنها مستخدمة. يمكنك تعطيلها بدلاً من ذلك.');
            }

            $stmt_delete_get = $mysqli->prepare("DELETE FROM payment_methods WHERE id = ?");
            if (!$stmt_delete_get) throw new Exception('فشل في تحضير استعلام الحذف: ' . $mysqli->error);
            $stmt_delete_get->bind_param("i", $pm_id_to_delete);
            if (!$stmt_delete_get->execute()) throw new Exception('فشل في حذف الطريقة: ' . $stmt_delete_get->error);
            $stmt_delete_get->close();
            
            // افترض أن AUDIT_DELETE_PAYMENT_METHOD معرف
            log_audit_action($mysqli, 'DELETE_PAYMENT_METHOD', $pm_id_to_delete, 'payment_methods', $pm_details_log); // <<--- تسجيل الحدث
            $mysqli->commit();
            set_message('تم حذف طريقة الدفع بنجاح!', "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Payment Method Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف طريقة الدفع غير صحيح للحذف.", "danger");
    }
    redirect(base_url('payment_methods/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_payment_method')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>