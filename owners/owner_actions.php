<?php
// owners/owner_actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php';

// المسار الذي سيتم إعادة التوجيه إليه بعد العملية
$redirect_url = base_url('owners/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('خطأ في التحقق (CSRF).', 'danger');
        redirect($redirect_url);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $current_user_id = get_current_user_id();

    $owner_id = isset($_POST['owner_id']) ? filter_var($_POST['owner_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    $name = isset($_POST['name']) ? sanitize_input(trim($_POST['name'])) : null; 
    $email_input = isset($_POST['email']) ? trim($_POST['email']) : null;
    $email = ($email_input === '' || $email_input === null) ? null : filter_var(sanitize_input($email_input), FILTER_SANITIZE_EMAIL);
    $phone_input = isset($_POST['phone']) ? trim($_POST['phone']) : null;
    $phone = ($phone_input === '') ? null : sanitize_input(preg_replace('/[^0-9]/', '', $phone_input));
    $national_id_iqama_input = isset($_POST['national_id_iqama']) ? trim($_POST['national_id_iqama']) : null;
    $national_id_iqama = ($national_id_iqama_input === '') ? null : sanitize_input($national_id_iqama_input);
    $address_input = isset($_POST['address']) ? trim($_POST['address']) : null;
    $address = ($address_input === '') ? null : sanitize_input($address_input);
    $notes_input = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    $notes = ($notes_input === '') ? null : sanitize_input($notes_input);
    
    $registration_date_input = isset($_POST['registration_date']) ? trim($_POST['registration_date']) : null;
    $registration_date = null;
    if (!empty($registration_date_input)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $registration_date_input);
        if ($date_obj && $date_obj->format('Y-m-d') === $registration_date_input) {
            $registration_date = $registration_date_input;
        }
    }
    
    // حفظ البيانات القديمة لإعادة ملء النموذج في حالة الخطأ عبر الجلسة
    $_SESSION['old_owner_form_data'] = $_POST;

    try {
        if (empty($name)) {
            throw new Exception('اسم المالك مطلوب.');
        }
        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // يمكنك اختيار جعل هذا الحقل إلزاميًا إذا أردت
            // throw new Exception('البريد الإلكتروني غير صالح.');
        }
    
        $mysqli->begin_transaction();

        if ($action === 'add_owner') {
            $fields_to_check_add = [];
            if (!empty($name)) $fields_to_check_add['name'] = $name;
            if (!empty($email)) $fields_to_check_add['email'] = $email;
            if (!empty($phone)) $fields_to_check_add['phone'] = $phone;
            if (!empty($national_id_iqama)) $fields_to_check_add['national_id_iqama'] = $national_id_iqama;
            
            $duplicate_errors_add = [];
            foreach ($fields_to_check_add as $field => $value) {
                $stmt_check = $mysqli->prepare("SELECT id FROM owners WHERE `$field` = ?");
                if ($stmt_check) {
                    $stmt_check->bind_param("s", $value);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    if ($stmt_check->num_rows > 0) {
                        if ($field === 'name') $duplicate_errors_add[] = "اسم المالك مستخدم بالفعل.";
                        elseif ($field === 'email') $duplicate_errors_add[] = "البريد الإلكتروني مستخدم بالفعل.";
                        elseif ($field === 'phone') $duplicate_errors_add[] = "رقم الهاتف مستخدم بالفعل.";
                        elseif ($field === 'national_id_iqama') $duplicate_errors_add[] = "رقم الهوية/الإقامة مستخدم بالفعل.";
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
            set_message('تمت إضافة المالك بنجاح!', 'success');
            unset($_SESSION['old_owner_form_data']); 

        } elseif ($action === 'edit_owner' && $owner_id) {
            $stmt_old = $mysqli->prepare("SELECT * FROM owners WHERE id = ?");
            $old_data = null; // بيانات قديمة لسجل التدقيق
            // ... (بقية منطق جلب البيانات القديمة كما في ملفك الأصلي)
            if($stmt_old){ /* ... */ }
            if(!$old_data) throw new Exception("المالك المطلوب تعديله غير موجود.");

            $fields_to_check_edit = [];
             if (!empty($name)) $fields_to_check_edit['name'] = $name;
            if (!empty($email)) $fields_to_check_edit['email'] = $email;
            if (!empty($phone)) $fields_to_check_edit['phone'] = $phone;
            if (!empty($national_id_iqama)) $fields_to_check_edit['national_id_iqama'] = $national_id_iqama;

            $duplicate_errors_edit = [];
            // ... (بقية منطق التحقق من التكرار عند التعديل كما في ملفك الأصلي)
            foreach ($fields_to_check_edit as $field => $value) { /* ... */ }
            if (!empty($duplicate_errors_edit)) throw new Exception(implode("<br>", $duplicate_errors_edit));

            $stmt = $mysqli->prepare("UPDATE owners SET name = ?, email = ?, phone = ?, national_id_iqama = ?, address = ?, registration_date = ?, notes = ? WHERE id = ?");
            if (!$stmt) throw new Exception('فشل في تحضير استعلام تعديل المالك: ' . $mysqli->error);
            
            $stmt->bind_param("sssssssi", $name, $email, $phone, $national_id_iqama, $address, $registration_date, $notes, $owner_id);
            if (!$stmt->execute()) throw new Exception('فشل في تحديث بيانات المالك: ' . $stmt->error);
            $stmt->close();

            $new_data = compact('name', 'email', 'phone', 'national_id_iqama', 'address', 'registration_date', 'notes');
            log_audit_action($mysqli, AUDIT_EDIT_OWNER, $owner_id, 'owners', ['old_data' => $old_data, 'new_data' => $new_data]);
            set_message('تم تحديث بيانات المالك بنجاح!', 'success');
            unset($_SESSION['old_owner_form_data']);
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف المالك مفقود.");
        }
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        set_message("خطأ: " . $e->getMessage(), "danger");
        error_log("Owner Action Error (POST): " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
        // لا تقم بإلغاء تعيين old_owner_form_data هنا حتى يمكن إعادة ملء النموذج
    }
    
    redirect($redirect_url); // إعادة التوجيه دائمًا بعد معالجة POST
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_owner') {
    // هذا الجزء يستخدم بالفعل set_message و redirect وهو صحيح
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect($redirect_url);
        exit;
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
            $result_prop_check = $stmt_check_properties->get_result(); 
            $row_prop_check = $result_prop_check->fetch_assoc(); 
            $stmt_check_properties->close();

            if ($row_prop_check['property_count'] > 0) { 
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
    redirect($redirect_url);
    exit;
}

// Fallback for invalid requests
set_message('طلب غير صالح أو طريقة وصول غير مدعومة.', 'warning');
redirect(base_url('dashboard.php'));
exit;
?>