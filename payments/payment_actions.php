<?php
// payments/payment_actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php'; // تضمين ملف سجل التدقيق

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$action = $_POST['action'] ?? '';
$current_user_id = get_current_user_id();

$upload_dir_relative_payments = 'uploads/payment_attachments/';
$app_base_path_for_uploads_payments = defined('APP_BASE_URL') ? rtrim(parse_url(APP_BASE_URL, PHP_URL_PATH), '/') : '';
$doc_root_payments = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : __DIR__ . '/../..';
$upload_dir_absolute_payments = $doc_root_payments . $app_base_path_for_uploads_payments . '/' . $upload_dir_relative_payments;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_dir($upload_dir_absolute_payments)) {
        if (!mkdir($upload_dir_absolute_payments, 0775, true) && !is_dir($upload_dir_absolute_payments)) {
            $response = ['success' => false, 'message' => "فشل في إنشاء مجلد رفع مرفقات الدفع: " . $upload_dir_absolute_payments];
            error_log("Failed to create payment attachments upload directory: " . $upload_dir_absolute_payments);
            echo json_encode($response); exit;
        }
    }

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $response = ['success' => false, 'message' => 'خطأ في التحقق (CSRF).'];
        echo json_encode($response); exit;
    }

    $payment_id = isset($_POST['payment_id']) ? filter_var($_POST['payment_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    $invoice_id = isset($_POST['invoice_id']) && filter_var($_POST['invoice_id'], FILTER_VALIDATE_INT) ? (int)$_POST['invoice_id'] : null;
    $amount_paid_input = isset($_POST['amount_paid']) ? trim($_POST['amount_paid']) : null;
    $amount_paid = ($amount_paid_input !== '' && filter_var($amount_paid_input, FILTER_VALIDATE_FLOAT) !== false && (float)$amount_paid_input > 0) ? (float)$amount_paid_input : null;
    
    $payment_date_input = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : null;
    $payment_date = null;
    if ($payment_date_input) {
        $date_obj_pay = DateTime::createFromFormat('Y-m-d', $payment_date_input);
        if ($date_obj_pay && $date_obj_pay->format('Y-m-d') === $payment_date_input) $payment_date = $payment_date_input;
    }

    $payment_method_id = isset($_POST['payment_method_id']) && filter_var($_POST['payment_method_id'], FILTER_VALIDATE_INT) ? (int)$_POST['payment_method_id'] : null;
    $payment_status = isset($_POST['payment_status']) ? sanitize_input($_POST['payment_status']) : null;
    $allowed_statuses = ['Pending', 'Completed', 'Failed', 'Cancelled', 'Refunded'];
    if ($payment_status === null || !in_array($payment_status, $allowed_statuses, true)) {
        $response = ['success' => false, 'message' => 'حالة الدفع غير صالحة أو مفقودة.'];
        echo json_encode($response); exit;
    }

    // في جدول payments، العمود اسمه reference_number. النموذج يرسل receipt_number. سنستخدم reference_number هنا.
    $reference_number = isset($_POST['receipt_number']) ? sanitize_input(trim($_POST['receipt_number'])) : null; 
    $payment_notes = isset($_POST['payment_notes']) ? sanitize_input(trim($_POST['payment_notes'])) : null;
    $payment_attachment_path = null;

    if (isset($_FILES['payment_attachment']) && $_FILES['payment_attachment']['error'] == UPLOAD_ERR_OK) {
        // ... (منطق رفع مرفق الدفع كما في النسخة السابقة)
        $file_tmp_path_pay = $_FILES['payment_attachment']['tmp_name'];
        $file_name_pay = sanitize_input(basename($_FILES['payment_attachment']['name']));
        $file_extension_pay = strtolower(pathinfo($file_name_pay, PATHINFO_EXTENSION));
        $allowed_extensions_pay = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
        $max_file_size_pay = 5 * 1024 * 1024;

        if (!in_array($file_extension_pay, $allowed_extensions_pay)) {
            $response = ['success' => false, 'message' => "صيغة ملف المرفق غير مسموح بها."];
            echo json_encode($response); exit;
        }
        if ($_FILES['payment_attachment']['size'] > $max_file_size_pay) {
            $response = ['success' => false, 'message' => "حجم ملف المرفق يتجاوز الحد المسموح به."];
            echo json_encode($response); exit;
        }
        $new_file_name_pay = 'payment_attach_' . uniqid('', true) . '.' . $file_extension_pay;
        $destination_path_absolute_pay = $upload_dir_absolute_payments . $new_file_name_pay;
        $destination_path_relative_pay = $upload_dir_relative_payments . $new_file_name_pay;

        if (move_uploaded_file($file_tmp_path_pay, $destination_path_absolute_pay)) {
            $payment_attachment_path = $destination_path_relative_pay;
        } else {
            error_log("Failed to move payment attachment: " . $destination_path_absolute_pay);
            $response = ['success' => false, 'message' => "حدث خطأ أثناء رفع مرفق الدفعة."];
            echo json_encode($response); exit;
        }
    }

    if ($invoice_id === null || $amount_paid === null || $payment_date === null || $payment_method_id === null) {
        $response = ['success' => false, 'message' => "الحقول المطلوبة (الفاتورة، المبلغ، تاريخ الدفع، طريقة الدفع) يجب ملؤها."];
        echo json_encode($response); exit;
    }

    $mysqli->begin_transaction();
    try {
        if ($action === 'add_payment') {
            $stmt_invoice = $mysqli->prepare("SELECT tenant_id, total_amount FROM invoices WHERE id = ?");
            // ... (بقية منطق إضافة الدفعة كما في النسخة السابقة)
            if(!$stmt_invoice) throw new Exception("خطأ تجهيز جلب معلومات الفاتورة: " . $mysqli->error);
            $stmt_invoice->bind_param("i", $invoice_id);
            $stmt_invoice->execute();
            $invoice_result = $stmt_invoice->get_result();
            if ($invoice_result->num_rows === 0) throw new Exception("الفاتورة المحددة غير موجودة.");
            $invoice_data = $invoice_result->fetch_assoc();
            $tenant_id_from_invoice = $invoice_data['tenant_id'];
            $invoice_total_amount = (float)$invoice_data['total_amount'];
            $stmt_invoice->close();

            if ($payment_status === 'Completed') {
                // ... (فحص المبلغ المتبقي كما في النسخة السابقة)
                $stmt_paid = $mysqli->prepare("SELECT COALESCE(SUM(amount_paid),0) as total_paid FROM payments WHERE invoice_id = ? AND status = 'Completed'");
                if(!$stmt_paid) throw new Exception("خطأ تجهيز فحص المدفوعات السابقة: " . $mysqli->error);
                $stmt_paid->bind_param("i", $invoice_id); $stmt_paid->execute();
                $paid_result = $stmt_paid->get_result()->fetch_assoc();
                $current_total_paid = $paid_result ? (float)$paid_result['total_paid'] : 0.0;
                $stmt_paid->close();
                if (($current_total_paid + $amount_paid) > ($invoice_total_amount + 0.005)) {
                    throw new Exception("المبلغ الإجمالي يتجاوز إجمالي الفاتورة. المتبقي: " . max(0, $invoice_total_amount - $current_total_paid));
                }
            }

            $sql = "INSERT INTO payments (invoice_id, tenant_id, payment_date, amount_paid, payment_method_id, status, reference_number, notes, received_by_id, attachment_path) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception("خطأ في تجهيز استعلام إضافة الدفعة: " . $mysqli->error);
            
            $stmt->bind_param("iisdisssis", 
                $invoice_id, $tenant_id_from_invoice, $payment_date, $amount_paid, $payment_method_id, 
                $payment_status, $reference_number, 
                $payment_notes, $current_user_id, $payment_attachment_path
            );
            if (!$stmt->execute()) throw new Exception("خطأ في إضافة الدفعة: " . $stmt->error);
            $new_payment_id = $stmt->insert_id;
            $stmt->close();
            
            if ($payment_status === 'Completed') update_invoice_status($mysqli, $invoice_id);
            
            log_audit_action($mysqli, AUDIT_CREATE_PAYMENT, $new_payment_id, 'payments', ['invoice_id' => $invoice_id, 'amount' => $amount_paid, 'method_id' => $payment_method_id]);
            $response = ['success' => true, 'message' => "تم تسجيل الدفعة بنجاح!"];

        } elseif ($action === 'edit_payment' && $payment_id) {
            // ... (منطق تعديل الدفعة كما في النسخة السابقة، مع إضافة جلب البيانات القديمة)
            $stmt_old_payment = $mysqli->prepare("SELECT * FROM payments WHERE id = ?");
            $old_payment_data = null;
            if($stmt_old_payment){
                $stmt_old_payment->bind_param("i", $payment_id);
                $stmt_old_payment->execute();
                $res_old_payment = $stmt_old_payment->get_result();
                if($res_old_payment->num_rows > 0) $old_payment_data = $res_old_payment->fetch_assoc();
                $stmt_old_payment->close();
            }
            if(!$old_payment_data) throw new Exception("الدفعة المطلوبة للتعديل غير موجودة.");
            $original_invoice_id = $old_payment_data['invoice_id'];


            $stmt_invoice_edit = $mysqli->prepare("SELECT tenant_id, total_amount FROM invoices WHERE id = ?");
             // ... (بقية منطق تعديل الدفعة كما في النسخة السابقة)
            if(!$stmt_invoice_edit) throw new Exception("خطأ تجهيز جلب معلومات الفاتورة (تعديل): " . $mysqli->error);
            $stmt_invoice_edit->bind_param("i", $invoice_id); $stmt_invoice_edit->execute();
            $invoice_result_edit = $stmt_invoice_edit->get_result();
            if ($invoice_result_edit->num_rows === 0) throw new Exception("الفاتورة المحددة غير موجودة (تعديل).");
            $invoice_data_edit = $invoice_result_edit->fetch_assoc();
            $tenant_id_from_invoice_edit = $invoice_data_edit['tenant_id'];
            $invoice_total_amount_edit = (float)$invoice_data_edit['total_amount'];
            $stmt_invoice_edit->close();

            if ($payment_status === 'Completed') {
                // ... (فحص المبلغ المتبقي كما في النسخة السابقة)
                $stmt_paid_edit = $mysqli->prepare("SELECT COALESCE(SUM(amount_paid),0) as total_paid FROM payments WHERE invoice_id = ? AND status = 'Completed' AND id != ?");
                if(!$stmt_paid_edit) throw new Exception("خطأ تجهيز فحص المدفوعات السابقة (تعديل): " . $mysqli->error);
                $stmt_paid_edit->bind_param("ii", $invoice_id, $payment_id); $stmt_paid_edit->execute();
                $paid_result_edit = $stmt_paid_edit->get_result()->fetch_assoc();
                $current_total_paid_edit = $paid_result_edit ? (float)$paid_result_edit['total_paid'] : 0.0;
                $stmt_paid_edit->close();
                if (($current_total_paid_edit + $amount_paid) > ($invoice_total_amount_edit + 0.005)) {
                     throw new Exception("المبلغ الإجمالي يتجاوز إجمالي الفاتورة. المتبقي: " . max(0, $invoice_total_amount_edit - $current_total_paid_edit));
                }
            }

            $set_clauses_pay = "invoice_id = ?, tenant_id = ?, payment_date = ?, amount_paid = ?, payment_method_id = ?, status = ?, reference_number = ?, notes = ?";
            $types_update_pay = "iisdisss"; 
            $params_update_pay = [
                $invoice_id, $tenant_id_from_invoice_edit, $payment_date, $amount_paid, $payment_method_id, 
                $payment_status, $reference_number, $payment_notes
            ];

            if ($payment_attachment_path) {
                $set_clauses_pay .= ", attachment_path = ?";
                $types_update_pay .= "s";
                $params_update_pay[] = $payment_attachment_path;
            }
            $params_update_pay[] = $payment_id; 
            $types_update_pay .= "i";

            $sql_update = "UPDATE payments SET " . $set_clauses_pay . " WHERE id = ?";
            $stmt_update = $mysqli->prepare($sql_update);
            if (!$stmt_update) throw new Exception("خطأ في تجهيز استعلام تعديل الدفعة: " . $mysqli->error);
            $stmt_update->bind_param($types_update_pay, ...$params_update_pay);
            if (!$stmt_update->execute()) throw new Exception("خطأ في تعديل بيانات الدفعة: " . $stmt_update->error);
            $stmt_update->close();

            if ($payment_attachment_path && $old_payment_data['attachment_path'] && !empty($old_payment_data['attachment_path'])) {
                 $old_attach_abs_path = $doc_root_payments . $app_base_path_for_uploads_payments . '/' . $old_payment_data['attachment_path'];
                if (file_exists($old_attach_abs_path)) @unlink($old_attach_abs_path);
            }
            
            if(isset($original_invoice_id) && $original_invoice_id != $invoice_id) update_invoice_status($mysqli, $original_invoice_id);
            update_invoice_status($mysqli, $invoice_id);
            
            $new_payment_data = compact('invoice_id', 'tenant_id_from_invoice_edit', 'payment_date', 'amount_paid', 'payment_method_id', 'payment_status', 'reference_number', 'payment_notes', 'payment_attachment_path');
            log_audit_action($mysqli, AUDIT_EDIT_PAYMENT, $payment_id, 'payments', ['old_data' => $old_payment_data, 'new_data' => $new_payment_data]);
            $response = ['success' => true, 'message' => "تم تحديث بيانات الدفعة بنجاح!"];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف الدفعة مفقود.");
        }
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("[Payment Action Error] Message: " . $e->getMessage());
        if ($action === 'add_payment' && $payment_attachment_path && file_exists($upload_dir_absolute_payments . basename($payment_attachment_path))) {
            @unlink($upload_dir_absolute_payments . basename($payment_attachment_path));
        }
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_payment') {
    // ... (منطق الحذف من GET request كما في النسخة السابقة مع إضافة سجل التدقيق)
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('payments/index.php'));
    }
    $payment_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($payment_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_payment_info_del = $mysqli->prepare("SELECT invoice_id, amount_paid, payment_method_id, attachment_path FROM payments WHERE id = ?");
            $payment_details_log = null;
            if($stmt_payment_info_del){
                $stmt_payment_info_del->bind_param("i", $payment_id_to_delete);
                $stmt_payment_info_del->execute();
                $res_payment_info_del = $stmt_payment_info_del->get_result();
                if($res_payment_info_del->num_rows > 0) $payment_details_log = $res_payment_info_del->fetch_assoc();
                $stmt_payment_info_del->close();
            }
            if(!$payment_details_log) throw new Exception("الدفعة المطلوبة للحذف غير موجودة.");

            $sql_delete = "DELETE FROM payments WHERE id = ?";
            $stmt_delete = $mysqli->prepare($sql_delete);
            if (!$stmt_delete) throw new Exception("خطأ في تجهيز استعلام حذف الدفعة: " . $mysqli->error);
            $stmt_delete->bind_param("i", $payment_id_to_delete);
            if (!$stmt_delete->execute()) throw new Exception("خطأ في حذف الدفعة: " . $stmt_delete->error);
            $affected_rows = $stmt_delete->affected_rows;
            $stmt_delete->close();

            if ($affected_rows > 0) {
                if (!empty($payment_details_log['attachment_path'])) {
                     $attach_to_delete_abs = $doc_root_payments . $app_base_path_for_uploads_payments . '/' . $payment_details_log['attachment_path'];
                    if (file_exists($attach_to_delete_abs)) @unlink($attach_to_delete_abs);
                }
                if ($payment_details_log['invoice_id']) update_invoice_status($mysqli, $payment_details_log['invoice_id']);
                
                log_audit_action($mysqli, AUDIT_DELETE_PAYMENT, $payment_id_to_delete, 'payments', ['invoice_id' => $payment_details_log['invoice_id'], 'amount' => $payment_details_log['amount_paid']]);
                $mysqli->commit();
                set_message("تم حذف الدفعة بنجاح!", "success");
            } else {
                throw new Exception("لم يتم العثور على الدفعة أو لم يتم حذفها.");
            }
        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Payment Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف الدفعة غير صحيح للحذف.", "danger");
    }
    redirect(base_url('payments/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_payment')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>