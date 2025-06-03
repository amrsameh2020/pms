<?php
// owners/owner_actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
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

    $owner_id = isset($_POST['owner_id']) ? filter_var($_POST['owner_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    // تم التعديل هنا ليتوافق مع النموذج الذي يرسل 'name'
    $name = isset($_POST['name']) ? sanitize_input(trim($_POST['name'])) : null; 
    $email_input = isset($_POST['email']) ? trim($_POST['email']) : null;
    $email = ($email_input === '' || $email_input === null) ? null : filter_var(sanitize_input($email_input), FILTER_SANITIZE_EMAIL);
    $phone = isset($_POST['phone']) ? sanitize_input(trim(preg_replace('/[^0-9]/', '', $_POST['phone']))) : null;
    $national_id_iqama = isset($_POST['national_id_iqama']) ? sanitize_input(trim($_POST['national_id_iqama'])) : null;
    $address = isset($_POST['address']) ? sanitize_input(trim($_POST['address'])) : null;
    $notes = isset($_POST['notes']) ? sanitize_input(trim($_POST['notes'])) : null;
    
    $registration_date_input = isset($_POST['registration_date']) ? trim($_POST['registration_date']) : null;
    $registration_date = null;
    if (!empty($registration_date_input)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $registration_date_input);
        if ($date_obj && $date_obj->format('Y-m-d') === $registration_date_input) {
            $registration_date = $registration_date_input;
        }
    }

    if (empty($name)) { // الآن التحقق من $name صحيح
        $response = ['success' => false, 'message' => 'اسم المالك مطلوب.'];
        echo json_encode($response);
        exit;
    }
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'البريد الإلكتروني غير صالح.'];
        echo json_encode($response);
        exit;
    }
    
    $mysqli->begin_transaction();
    try {
        if ($action === 'add_owner') {
            $fields_to_check_add = ['name' => $name];
            if ($email !== null) $fields_to_check_add['email'] = $email;
            if ($phone !== null && $phone !== '') $fields_to_check_add['phone'] = $phone;
            if ($national_id_iqama !== null && $national_id_iqama !== '') $fields_to_check_add['national_id_iqama'] = $national_id_iqama;
            
            $duplicate_errors_add = [];
            foreach ($fields_to_check_add as $field => $value) {
                $stmt_check = $mysqli->prepare("SELECT id FROM owners WHERE `$field` = ?");
                if ($stmt_check) {
                    $stmt_check->bind_param("s", $value);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    if ($stmt_check->num_rows > 0) {
                        if ($field === 'name') $duplicate_errors_add[] = "اسم المالك مستخدم بالفعل.";
                        if ($field === 'email') $duplicate_errors_add[] = "البريد الإلكتروني مستخدم بالفعل.";
                        if ($field === 'phone') $duplicate_errors_add[] = "رقم الهاتف مستخدم بالفعل.";
                        if ($field === 'national_id_iqama') $duplicate_errors_add[] = "رقم الهوية/الإقامة مستخدم بالفعل.";
                    }
                    $stmt_check->close();
                } else { throw new Exception("خطأ تجهيز فحص التكرار (إضافة مالك): " . $mysqli->error); }
            }
            if (!empty($duplicate_errors_add)) throw new Exception(implode("<br>", $duplicate_errors_add));

            $stmt = $mysqli->prepare("INSERT INTO owners (name, email, phone, national_id_iqama, address, registration_date, notes, created_by_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception('فشل في تحضير استعلام إضافة المالك: ' . $mysqli->error);
            
            $stmt->bind_param("sssssssi", $name, $email, $phone, $national_id_iqama, $address, $registration_date, $notes, $current_user_id);
            if (!$stmt->execute()) throw new Exception('فشل في إضافة المالك: ' . $stmt->error);
            
            $new_owner_id = $stmt->insert_id;
            $stmt->close();
            log_audit_action($mysqli, AUDIT_CREATE_OWNER, $new_owner_id, 'owners', ['name' => $name, 'email' => $email]);
            $response = ['success' => true, 'message' => 'تمت إضافة المالك بنجاح!'];

        } elseif ($action === 'edit_owner' && $owner_id) {
            $stmt_old = $mysqli->prepare("SELECT * FROM owners WHERE id = ?");
            $old_data = null;
            if($stmt_old){
                $stmt_old->bind_param("i", $owner_id);
                $stmt_old->execute();
                $result_old = $stmt_old->get_result();
                if($result_old->num_rows > 0) $old_data = $result_old->fetch_assoc();
                $stmt_old->close();
            }
            if(!$old_data) throw new Exception("المالك المطلوب تعديله غير موجود.");

            $fields_to_check_edit = ['name' => $name];
            if ($email !== null) $fields_to_check_edit['email'] = $email;
            if ($phone !== null && $phone !== '') $fields_to_check_edit['phone'] = $phone;
            if ($national_id_iqama !== null && $national_id_iqama !== '') $fields_to_check_edit['national_id_iqama'] = $national_id_iqama;

            $duplicate_errors_edit = [];
            foreach ($fields_to_check_edit as $field => $value) {
                $stmt_check_edit = $mysqli->prepare("SELECT id FROM owners WHERE `$field` = ? AND id != ?");
                 if ($stmt_check_edit) {
                    $stmt_check_edit->bind_param("si", $value, $owner_id);
                    $stmt_check_edit->execute();
                    $stmt_check_edit->store_result();
                    if ($stmt_check_edit->num_rows > 0) {
                         if ($field === 'name') $duplicate_errors_edit[] = "اسم المالك مستخدم بالفعل لمالك آخر.";
                        // ... (بقية رسائل الخطأ)
                    }
                    $stmt_check_edit->close();
                } else { throw new Exception("خطأ تجهيز فحص التكرار (تعديل مالك): " . $mysqli->error); }
            }
            if (!empty($duplicate_errors_edit)) throw new Exception(implode("<br>", $duplicate_errors_edit));

            $stmt = $mysqli->prepare("UPDATE owners SET name = ?, email = ?, phone = ?, national_id_iqama = ?, address = ?, registration_date = ?, notes = ? WHERE id = ?");
            if (!$stmt) throw new Exception('فشل في تحضير استعلام تعديل المالك: ' . $mysqli->error);
            
            $stmt->bind_param("sssssssi", $name, $email, $phone, $national_id_iqama, $address, $registration_date, $notes, $owner_id);
            if (!$stmt->execute()) throw new Exception('فشل في تحديث بيانات المالك: ' . $stmt->error);
            $stmt->close();

            $new_data = compact('name', 'email', 'phone', 'national_id_iqama', 'address', 'registration_date', 'notes');
            log_audit_action($mysqli, AUDIT_EDIT_OWNER, $owner_id, 'owners', ['old_data' => $old_data, 'new_data' => $new_data]);
            $response = ['success' => true, 'message' => 'تم تحديث بيانات المالك بنجاح!'];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف المالك مفقود.");
        }
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Owner Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_owner') { // تم تغيير 'delete' إلى 'delete_owner' ليكون أوضح
    // ... (منطق الحذف كما هو في النسخة السابقة، مع التأكد أن `delete-owner-btn` في index.php يمرر action=delete_owner)
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('owners/index.php'));
    }
    $owner_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($owner_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_old_owner = $mysqli->prepare("SELECT name, email FROM owners WHERE id = ?");
            $owner_details_for_log = null;
            if($stmt_old_owner){
                $stmt_old_owner->bind_param("i", $owner_id_to_delete);
                $stmt_old_owner->execute();
                $res_old_owner = $stmt_old_owner->get_result();
                if($res_old_owner->num_rows > 0) $owner_details_for_log = $res_old_owner->fetch_assoc();
                $stmt_old_owner->close();
            }
            if(!$owner_details_for_log) throw new Exception("المالك المطلوب حذفه غير موجود.");

            $stmt_check_properties = $mysqli->prepare("SELECT COUNT(*) as property_count FROM properties WHERE owner_id = ?");
            if (!$stmt_check_properties) throw new Exception('خطأ في التحقق من العقارات المرتبطة: ' . $mysqli->error);
            $stmt_check_properties->bind_param("i", $owner_id_to_delete);
            $stmt_check_properties->execute();
            $result_prop_check = $stmt_check_properties->get_result(); // تم تغيير اسم المتغير
            $row_prop_check = $result_prop_check->fetch_assoc(); // تم تغيير اسم المتغير
            $stmt_check_properties->close();

            if ($row_prop_check['property_count'] > 0) { // تم تغيير اسم المتغير
                throw new Exception('لا يمكن حذف المالك لوجود (' . $row_prop_check['property_count'] . ') عقار/عقارات مرتبطة به.');
            }
            
            $stmt_delete = $mysqli->prepare("DELETE FROM owners WHERE id = ?");
            if (!$stmt_delete) throw new Exception('فشل في تحضير استعلام الحذف: ' . $mysqli->error);
            $stmt_delete->bind_param("i", $owner_id_to_delete);
            if (!$stmt_delete->execute()) throw new Exception('فشل في حذف المالك: ' . $stmt_delete->error);
            $stmt_delete->close();
            
            log_audit_action($mysqli, AUDIT_DELETE_OWNER, $owner_id_to_delete, 'owners', $owner_details_for_log);
            $mysqli->commit();
            set_message("تم حذف المالك بنجاح!", "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Owner Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف المالك غير صحيح للحذف.", "danger");
    }
    redirect(base_url('owners/index.php'));
}

// Fallback for invalid requests if not POST or expected GET delete
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_owner')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response); 
}
?>