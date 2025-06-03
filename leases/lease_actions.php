<?php
// leases/lease_actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php'; // تضمين ملف سجل التدقيق

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$action = $_POST['action'] ?? '';
$current_user_id = get_current_user_id();

$upload_dir_relative = 'uploads/contracts/';
$app_base_path_for_uploads = defined('APP_BASE_URL') ? rtrim(parse_url(APP_BASE_URL, PHP_URL_PATH), '/') : '';
$doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : __DIR__ . '/../..';
$upload_dir_absolute = $doc_root . $app_base_path_for_uploads . '/' . $upload_dir_relative;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_dir($upload_dir_absolute)) {
        if (!mkdir($upload_dir_absolute, 0775, true) && !is_dir($upload_dir_absolute)) {
            $response = ['success' => false, 'message' => "فشل في إنشاء مجلد الرفع: " . $upload_dir_absolute];
            error_log("Failed to create upload directory: " . $upload_dir_absolute);
            echo json_encode($response);
            exit;
        }
    }
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $response = ['success' => false, 'message' => 'خطأ في التحقق (CSRF).'];
        echo json_encode($response);
        exit;
    }

    $lease_id_from_post = isset($_POST['lease_id']) ? filter_var($_POST['lease_id'], FILTER_SANITIZE_NUMBER_INT) : null;

    $lease_contract_number = isset($_POST['lease_contract_number']) ? sanitize_input(trim($_POST['lease_contract_number'])) : null;
    $unit_id = isset($_POST['unit_id']) && filter_var($_POST['unit_id'], FILTER_VALIDATE_INT) ? (int)$_POST['unit_id'] : null;
    $tenant_id = isset($_POST['tenant_id']) && filter_var($_POST['tenant_id'], FILTER_VALIDATE_INT) ? (int)$_POST['tenant_id'] : null;
    $lease_type_id = isset($_POST['lease_type_id']) && $_POST['lease_type_id'] !== '' ? filter_var($_POST['lease_type_id'], FILTER_VALIDATE_INT) : null;
    
    $lease_start_date_input = isset($_POST['lease_start_date']) ? trim($_POST['lease_start_date']) : null;
    $lease_start_date = null;
    if ($lease_start_date_input) {
        $date_obj_start = DateTime::createFromFormat('Y-m-d', $lease_start_date_input);
        if ($date_obj_start && $date_obj_start->format('Y-m-d') === $lease_start_date_input) $lease_start_date = $lease_start_date_input;
    }

    $lease_end_date_input = isset($_POST['lease_end_date']) ? trim($_POST['lease_end_date']) : null;
    $lease_end_date = null;
    if ($lease_end_date_input) {
        $date_obj_end = DateTime::createFromFormat('Y-m-d', $lease_end_date_input);
        if ($date_obj_end && $date_obj_end->format('Y-m-d') === $lease_end_date_input) $lease_end_date = $lease_end_date_input;
    }
    
    $rent_amount_input = isset($_POST['rent_amount']) ? trim($_POST['rent_amount']) : null;
    $rent_amount = ($rent_amount_input !== '' && filter_var($rent_amount_input, FILTER_VALIDATE_FLOAT) !== false && (float)$rent_amount_input >= 0) ? (float)$rent_amount_input : null;
    
    $payment_frequency = isset($_POST['payment_frequency']) ? sanitize_input($_POST['payment_frequency']) : null;
    $allowed_payment_frequencies = ['Monthly', 'Quarterly', 'Semi-Annually', 'Annually', 'Custom'];
    if ($payment_frequency === null || !in_array($payment_frequency, $allowed_payment_frequencies, true)) {
         $response = ['success' => false, 'message' => "قيمة دورية السداد غير صالحة أو مفقودة."];
         echo json_encode($response); exit;
    }

    $payment_due_day_input = isset($_POST['payment_due_day']) ? trim($_POST['payment_due_day']) : null;
    $payment_due_day = ($payment_due_day_input !== '' && filter_var($payment_due_day_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]) !== false) ? (int)$payment_due_day_input : null;

    $deposit_amount_input = isset($_POST['deposit_amount']) ? trim($_POST['deposit_amount']) : null;
    $deposit_amount = ($deposit_amount_input !== '' && filter_var($deposit_amount_input, FILTER_VALIDATE_FLOAT) !== false && (float)$deposit_amount_input >= 0) ? (float)$deposit_amount_input : 0.00;

    $grace_period_days_input = isset($_POST['grace_period_days']) ? trim($_POST['grace_period_days']) : null;
    $grace_period_days = ($grace_period_days_input !== '' && filter_var($grace_period_days_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false) ? (int)$grace_period_days_input : 0;
    
    $lease_status = isset($_POST['lease_status']) ? sanitize_input($_POST['lease_status']) : null;
    
    $next_billing_date_input = isset($_POST['next_billing_date']) ? trim($_POST['next_billing_date']) : null;
    $next_billing_date = null;
    if ($next_billing_date_input) {
        $date_obj_next_billing = DateTime::createFromFormat('Y-m-d', $next_billing_date_input);
        if ($date_obj_next_billing && $date_obj_next_billing->format('Y-m-d') === $next_billing_date_input) $next_billing_date = $next_billing_date_input;
    }

    $last_billed_on_input = isset($_POST['last_billed_on']) ? trim($_POST['last_billed_on']) : null;
    $last_billed_on = null;
    if ($last_billed_on_input) {
        $date_obj_last_billed = DateTime::createFromFormat('Y-m-d', $last_billed_on_input);
        if ($date_obj_last_billed && $date_obj_last_billed->format('Y-m-d') === $last_billed_on_input) $last_billed_on = $last_billed_on_input;
    }
    
    $lease_notes = isset($_POST['lease_notes']) ? sanitize_input(trim($_POST['lease_notes'])) : null;
    $contract_document_path = null;

    if (empty($lease_contract_number) || $unit_id === null || $tenant_id === null || $lease_start_date === null || $lease_end_date === null || $rent_amount === null || empty($lease_status)) {
        $response = ['success' => false, 'message' => "الحقول المطلوبة (رقم العقد، الوحدة، المستأجر، تواريخ البدء والانتهاء، مبلغ الإيجار، الحالة) يجب ملؤها."];
        echo json_encode($response); exit;
    }
    if (strtotime($lease_end_date) <= strtotime($lease_start_date)) {
        $response = ['success' => false, 'message' => "تاريخ انتهاء العقد يجب أن يكون بعد تاريخ بدء العقد."];
        echo json_encode($response); exit;
    }

    if (isset($_FILES['contract_document']) && $_FILES['contract_document']['error'] == UPLOAD_ERR_OK) {
        // ... (منطق رفع الملفات كما في النسخة السابقة)
        $file_tmp_path = $_FILES['contract_document']['tmp_name'];
        $file_name = sanitize_input(basename($_FILES['contract_document']['name']));
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'png'];
        $max_file_size = 10 * 1024 * 1024; // 10 MB

        if (!in_array($file_extension, $allowed_extensions)) {
            $response = ['success' => false, 'message' => "صيغة الملف غير مسموح بها. الصيغ المسموحة: " . implode(', ', $allowed_extensions)];
            echo json_encode($response); exit;
        }
        if ($_FILES['contract_document']['size'] > $max_file_size) {
            $response = ['success' => false, 'message' => "حجم الملف يتجاوز الحد المسموح به (10 ميجابايت)."];
            echo json_encode($response); exit;
        }
        $new_file_name = uniqid('contract_', true) . '.' . $file_extension;
        $destination_path_absolute = $upload_dir_absolute . $new_file_name;
        $destination_path_relative = $upload_dir_relative . $new_file_name;

        if (move_uploaded_file($file_tmp_path, $destination_path_absolute)) {
            $contract_document_path = $destination_path_relative;
        } else {
            error_log("Failed to move uploaded file to: " . $destination_path_absolute . " from " . $file_tmp_path);
            $response = ['success' => false, 'message' => "حدث خطأ أثناء رفع ملف مستند العقد."];
            echo json_encode($response); exit;
        }
    }

    $mysqli->begin_transaction();
    try {
        if ($action === 'add_lease') {
            // ... (فحص تكرار رقم العقد كما في النسخة السابقة)
            $stmt_check_contract = $mysqli->prepare("SELECT id FROM leases WHERE lease_contract_number = ?");
            if(!$stmt_check_contract) throw new Exception("خطأ في تجهيز استعلام التحقق من رقم العقد: " . $mysqli->error);
            $stmt_check_contract->bind_param("s", $lease_contract_number);
            $stmt_check_contract->execute();
            $stmt_check_contract->store_result();
            if ($stmt_check_contract->num_rows > 0) {
                throw new Exception("رقم عقد الإيجار '" . esc_html($lease_contract_number) . "' مستخدم بالفعل.");
            }
            $stmt_check_contract->close();

            $sql = "INSERT INTO leases (lease_contract_number, unit_id, tenant_id, lease_type_id, lease_start_date, lease_end_date, 
                                       rent_amount, payment_frequency, payment_due_day, deposit_amount, grace_period_days, 
                                       status, next_billing_date, last_billed_on, notes, contract_document_path, created_by_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception("خطأ في تجهيز استعلام إضافة العقد: " . $mysqli->error);
            
            $stmt->bind_param("siiisssdidisssssi", 
                $lease_contract_number, $unit_id, $tenant_id, $lease_type_id, $lease_start_date, $lease_end_date,
                $rent_amount, $payment_frequency, $payment_due_day, $deposit_amount, $grace_period_days,
                $lease_status, $next_billing_date, $last_billed_on, $lease_notes, $contract_document_path, $current_user_id
            );
            if (!$stmt->execute()) throw new Exception("خطأ في إضافة عقد الإيجار: " . $stmt->error);
            $new_lease_id = $stmt->insert_id;
            $stmt->close();
            
            if ($lease_status === 'Active') {
                $mysqli->query("UPDATE units SET status = 'Occupied' WHERE id = " . (int)$unit_id);
            }
            log_audit_action($mysqli, AUDIT_CREATE_LEASE, $new_lease_id, 'leases', ['contract_number' => $lease_contract_number, 'unit_id' => $unit_id, 'tenant_id' => $tenant_id]);
            $response = ['success' => true, 'message' => "تمت إضافة عقد الإيجار بنجاح!"];

        } elseif ($action === 'edit_lease' && $lease_id_from_post) {
            // ... (فحص تكرار رقم العقد عند التعديل كما في النسخة السابقة)
            $stmt_check_contract_edit = $mysqli->prepare("SELECT id FROM leases WHERE lease_contract_number = ? AND id != ?");
            if(!$stmt_check_contract_edit) throw new Exception("خطأ في تجهيز استعلام التحقق (تعديل): " . $mysqli->error);
            $stmt_check_contract_edit->bind_param("si", $lease_contract_number, $lease_id_from_post);
            $stmt_check_contract_edit->execute();
            $stmt_check_contract_edit->store_result();
            if ($stmt_check_contract_edit->num_rows > 0) {
                throw new Exception("رقم عقد الإيجار '" . esc_html($lease_contract_number) . "' مستخدم بالفعل لعقد آخر.");
            }
            $stmt_check_contract_edit->close();
            
            $stmt_old_lease = $mysqli->prepare("SELECT * FROM leases WHERE id = ?");
            $old_lease_data = null;
            if($stmt_old_lease){
                $stmt_old_lease->bind_param("i", $lease_id_from_post);
                $stmt_old_lease->execute();
                $res_old_lease = $stmt_old_lease->get_result();
                if($res_old_lease->num_rows > 0) $old_lease_data = $res_old_lease->fetch_assoc();
                $stmt_old_lease->close();
            }
            if(!$old_lease_data) throw new Exception("العقد المطلوب تعديله غير موجود.");
            $original_unit_id = $old_lease_data['unit_id']; // لتحديث حالة الوحدة القديمة
            $old_doc_path_db = $old_lease_data['contract_document_path'];


            $set_clauses = "lease_contract_number = ?, unit_id = ?, tenant_id = ?, lease_type_id = ?, lease_start_date = ?, lease_end_date = ?, 
                            rent_amount = ?, payment_frequency = ?, payment_due_day = ?, deposit_amount = ?, grace_period_days = ?, 
                            status = ?, next_billing_date = ?, last_billed_on = ?, notes = ?";
            $types_update = "siiisssdidissss"; 
            $params_update = [
                $lease_contract_number, $unit_id, $tenant_id, $lease_type_id, $lease_start_date, $lease_end_date,
                $rent_amount, $payment_frequency, $payment_due_day, $deposit_amount, $grace_period_days,
                $lease_status, $next_billing_date, $last_billed_on, $lease_notes
            ];

            if ($contract_document_path) {
                $set_clauses .= ", contract_document_path = ?";
                $types_update .= "s";
                $params_update[] = $contract_document_path;
            }
            $params_update[] = $lease_id_from_post; 
            $types_update .= "i";

            $sql = "UPDATE leases SET " . $set_clauses . " WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception("خطأ في تجهيز استعلام تعديل العقد: " . $mysqli->error);
            
            $stmt->bind_param($types_update, ...$params_update);
            if (!$stmt->execute()) throw new Exception("خطأ في تعديل بيانات العقد: " . $stmt->error);
            $stmt->close();

            if ($contract_document_path && $old_doc_path_db && !empty($old_doc_path_db)) {
                 $old_doc_abs_path = $doc_root . $app_base_path_for_uploads . '/' . $old_doc_path_db;
                if (file_exists($old_doc_abs_path)) @unlink($old_doc_abs_path);
            }
            
            // ... (منطق تحديث حالة الوحدة كما في النسخة السابقة)
            if ($lease_status === 'Active') {
                $mysqli->query("UPDATE units SET status = 'Occupied' WHERE id = " . (int)$unit_id);
            } elseif (in_array($lease_status, ['Expired', 'Terminated'])) {
                $active_leases_for_current_unit_q = $mysqli->query("SELECT COUNT(*) as count FROM leases WHERE unit_id = ".(int)$unit_id." AND status = 'Active' AND id != ".(int)$lease_id_from_post);
                $active_leases_current_count = $active_leases_for_current_unit_q->fetch_assoc()['count'] ?? 0;
                if($active_leases_current_count == 0) $mysqli->query("UPDATE units SET status = 'Vacant' WHERE id = " . (int)$unit_id);
            }
            if ($original_unit_id && $original_unit_id != $unit_id) {
                $active_leases_for_original_unit_q = $mysqli->query("SELECT COUNT(*) as count FROM leases WHERE unit_id = ".(int)$original_unit_id." AND status = 'Active'");
                $active_leases_original_count = $active_leases_for_original_unit_q->fetch_assoc()['count'] ?? 0;
                if($active_leases_original_count == 0) $mysqli->query("UPDATE units SET status = 'Vacant' WHERE id = " . (int)$original_unit_id);
            }

            $new_lease_data = compact('lease_contract_number', 'unit_id', 'tenant_id', 'lease_type_id', 'lease_start_date', 'lease_end_date', 'rent_amount', 'payment_frequency', 'payment_due_day', 'deposit_amount', 'grace_period_days', 'lease_status', 'next_billing_date', 'last_billed_on', 'lease_notes', 'contract_document_path');
            log_audit_action($mysqli, AUDIT_EDIT_LEASE, $lease_id_from_post, 'leases', ['old_data' => $old_lease_data, 'new_data' => $new_lease_data]);
            $response = ['success' => true, 'message' => "تم تحديث بيانات عقد الإيجار بنجاح!"];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف العقد مفقود.");
        }
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("[Lease Action Error] Message: " . $e->getMessage() . " --- POST Data: " . http_build_query($_POST) . " --- Files Data: " . json_encode($_FILES));
        if ($action === 'add_lease' && $contract_document_path && file_exists($upload_dir_absolute . basename($contract_document_path))) {
            @unlink($upload_dir_absolute . basename($contract_document_path));
        }
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_lease') {
    // ... (منطق الحذف من GET request كما في النسخة السابقة مع إضافة سجل التدقيق)
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('leases/index.php'));
    }
    $lease_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($lease_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_lease_info_del = $mysqli->prepare("SELECT lease_contract_number, unit_id, tenant_id, contract_document_path FROM leases WHERE id = ?");
            $lease_details_log = null;
            if($stmt_lease_info_del){
                $stmt_lease_info_del->bind_param("i", $lease_id_to_delete);
                $stmt_lease_info_del->execute();
                $res_lease_info_del = $stmt_lease_info_del->get_result();
                if($res_lease_info_del->num_rows > 0) $lease_details_log = $res_lease_info_del->fetch_assoc();
                $stmt_lease_info_del->close();
            }
            if(!$lease_details_log) throw new Exception("العقد المطلوب حذفه غير موجود.");

            $sql_delete = "DELETE FROM leases WHERE id = ?";
            $stmt_delete = $mysqli->prepare($sql_delete);
            if (!$stmt_delete) throw new Exception("خطأ في تجهيز استعلام حذف العقد: " . $mysqli->error);
            $stmt_delete->bind_param("i", $lease_id_to_delete);
            if (!$stmt_delete->execute()) throw new Exception("خطأ في حذف عقد الإيجار: " . $stmt_delete->error);
            $stmt_delete->close();

            if (!empty($lease_details_log['contract_document_path'])) {
                 $doc_to_delete_abs = $doc_root . $app_base_path_for_uploads . '/' . $lease_details_log['contract_document_path'];
                if (file_exists($doc_to_delete_abs)) @unlink($doc_to_delete_abs);
            }
            
            if ($lease_details_log['unit_id']) {
                // ... (منطق تحديث حالة الوحدة كما في النسخة السابقة)
                $active_leases_for_unit_q_del = $mysqli->query("SELECT COUNT(*) as count FROM leases WHERE unit_id = ".(int)$lease_details_log['unit_id']." AND status = 'Active'");
                $active_leases_count_del = $active_leases_for_unit_q_del->fetch_assoc()['count'] ?? 0;
                if($active_leases_count_del == 0) $mysqli->query("UPDATE units SET status = 'Vacant' WHERE id = " . (int)$lease_details_log['unit_id']);
            }
            
            log_audit_action($mysqli, AUDIT_DELETE_LEASE, $lease_id_to_delete, 'leases', ['contract_number' => $lease_details_log['lease_contract_number']]);
            $mysqli->commit();
            set_message("تم حذف عقد الإيجار بنجاح!", "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Lease Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف العقد غير صحيح للحذف.", "danger");
    }
    redirect(base_url('leases/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_lease')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>