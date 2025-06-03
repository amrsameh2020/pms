<?php
// tenants/tenant_actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php'; // تضمين ملف سجل التدقيق

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$action = $_POST['action'] ?? '';
$current_user_id = get_current_user_id();

// Helper function to get sanitized POST data, converting empty strings to null
function get_post_val_or_null($post_key) { // Renamed to avoid conflict
    if (isset($_POST[$post_key])) {
        $value = trim($_POST[$post_key]);
        return $value === '' ? null : sanitize_input($value);
    }
    return null;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $response = ['success' => false, 'message' => 'خطأ في التحقق (CSRF).'];
        echo json_encode($response);
        exit;
    }

    $tenant_id_from_post = isset($_POST['tenant_id']) ? filter_var($_POST['tenant_id'], FILTER_SANITIZE_NUMBER_INT) : null;

    $tenant_full_name = get_post_val_or_null('tenant_full_name');
    $tenant_national_id_iqama = get_post_val_or_null('tenant_national_id_iqama');
    $tenant_type_id = isset($_POST['tenant_type_id']) && $_POST['tenant_type_id'] !== '' ? filter_var($_POST['tenant_type_id'], FILTER_VALIDATE_INT) : null;
    $tenant_phone_primary = get_post_val_or_null('tenant_phone_primary');
    $tenant_phone_secondary = get_post_val_or_null('tenant_phone_secondary');
    $tenant_email_input = isset($_POST['tenant_email']) ? trim($_POST['tenant_email']) : null;
    $tenant_email = ($tenant_email_input === '' || $tenant_email_input === null) ? null : filter_var(sanitize_input($tenant_email_input), FILTER_SANITIZE_EMAIL);

    $gender_input = get_post_val_or_null('gender');
    $allowed_genders = ['Male', 'Female', 'Other'];
    $gender = ($gender_input !== null && in_array($gender_input, $allowed_genders)) ? $gender_input : null;

    $date_of_birth_input = get_post_val_or_null('date_of_birth');
    $date_of_birth = null;
    if ($date_of_birth_input) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_of_birth_input);
        if ($date_obj && $date_obj->format('Y-m-d') === $date_of_birth_input) {
            $date_of_birth = $date_of_birth_input;
        }
    }

    $tenant_current_address = get_post_val_or_null('tenant_current_address');
    $tenant_occupation = get_post_val_or_null('tenant_occupation');
    $tenant_nationality = get_post_val_or_null('tenant_nationality');
    $tenant_notes = get_post_val_or_null('tenant_notes');

    $tenant_buyer_vat_number = get_post_val_or_null('tenant_buyer_vat_number');
    $tenant_buyer_street_name = get_post_val_or_null('tenant_buyer_street_name');
    $tenant_buyer_building_no = get_post_val_or_null('tenant_buyer_building_no');
    $tenant_buyer_additional_no = get_post_val_or_null('tenant_buyer_additional_no');
    $tenant_buyer_district_name = get_post_val_or_null('tenant_buyer_district_name');
    $tenant_buyer_city_name = get_post_val_or_null('tenant_buyer_city_name');
    $tenant_buyer_postal_code = get_post_val_or_null('tenant_buyer_postal_code');
    $tenant_buyer_country_code_input = isset($_POST['tenant_buyer_country_code']) ? strtoupper(trim($_POST['tenant_buyer_country_code'])) : null;
    $tenant_buyer_country_code = ($tenant_buyer_country_code_input === '' || $tenant_buyer_country_code_input === null) ? 'SA' : sanitize_input($tenant_buyer_country_code_input);


    $tenant_emergency_contact_name = get_post_val_or_null('tenant_emergency_contact_name');
    $tenant_emergency_contact_phone = get_post_val_or_null('tenant_emergency_contact_phone');

    if (empty($tenant_full_name) || empty($tenant_national_id_iqama) || empty($tenant_phone_primary)) {
        $response = ['success' => false, 'message' => 'الحقول المطلوبة (الاسم، الهوية/الإقامة، الجوال الأساسي) يجب ملؤها.'];
        echo json_encode($response); exit;
    }
    if ($tenant_email !== null && !filter_var($tenant_email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'صيغة البريد الإلكتروني غير صحيحة.'];
        echo json_encode($response); exit;
    }
    
    $mysqli->begin_transaction();
    try {
        if ($action === 'add_tenant') {
            // ... (فحص التكرار كما في النسخة السابقة)
            $fields_to_check_add = [];
            if ($tenant_national_id_iqama !== null) $fields_to_check_add['national_id_iqama'] = $tenant_national_id_iqama;
            if ($tenant_phone_primary !== null) $fields_to_check_add['phone_primary'] = $tenant_phone_primary;
            if ($tenant_email !== null) $fields_to_check_add['email'] = $tenant_email;
            $duplicate_errors_add = []; // تعريفها هنا
            foreach ($fields_to_check_add as $field => $value) {
                $stmt_check = $mysqli->prepare("SELECT id FROM tenants WHERE `$field` = ?");
                if ($stmt_check) {
                    $stmt_check->bind_param("s", $value); $stmt_check->execute(); $stmt_check->store_result();
                    if ($stmt_check->num_rows > 0) { $duplicate_errors_add[] = "حقل '$field' مستخدم بالفعل."; }
                    $stmt_check->close();
                } else { throw new Exception("خطأ تجهيز فحص التكرار: " . $mysqli->error); }
            }
            if (!empty($duplicate_errors_add)) throw new Exception(implode(" ", $duplicate_errors_add));


            $sql = "INSERT INTO tenants (full_name, national_id_iqama, tenant_type_id, phone_primary, phone_secondary, email, 
                                       gender, date_of_birth, current_address, occupation, nationality, notes, 
                                       buyer_vat_number, buyer_street_name, buyer_building_no, buyer_additional_no, 
                                       buyer_district_name, buyer_city_name, buyer_postal_code, buyer_country_code,
                                       emergency_contact_name, emergency_contact_phone, created_by_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception("خطأ في تجهيز استعلام إضافة المستأجر: " . $mysqli->error);
            
            $stmt->bind_param("ssisssssssssssssssssssi", 
                $tenant_full_name, $tenant_national_id_iqama, $tenant_type_id, $tenant_phone_primary, $tenant_phone_secondary, $tenant_email,
                $gender, $date_of_birth, $tenant_current_address, $tenant_occupation, $tenant_nationality, $tenant_notes,
                $tenant_buyer_vat_number, $tenant_buyer_street_name, $tenant_buyer_building_no, $tenant_buyer_additional_no,
                $tenant_buyer_district_name, $tenant_buyer_city_name, $tenant_buyer_postal_code, $tenant_buyer_country_code,
                $tenant_emergency_contact_name, $tenant_emergency_contact_phone, $current_user_id
            );
            if (!$stmt->execute()) throw new Exception("خطأ في إضافة المستأجر: " . $stmt->error);
            $new_tenant_id = $stmt->insert_id;
            $stmt->close();
            log_audit_action($mysqli, AUDIT_CREATE_TENANT, $new_tenant_id, 'tenants', ['full_name' => $tenant_full_name, 'national_id' => $tenant_national_id_iqama]);
            $response = ['success' => true, 'message' => "تمت إضافة المستأجر بنجاح!"];

        } elseif ($action === 'edit_tenant' && $tenant_id_from_post) {
            // ... (فحص التكرار كما في النسخة السابقة)
            $fields_to_check_edit = [];
            if ($tenant_national_id_iqama !== null) $fields_to_check_edit['national_id_iqama'] = $tenant_national_id_iqama;
            if ($tenant_phone_primary !== null) $fields_to_check_edit['phone_primary'] = $tenant_phone_primary;
            if ($tenant_email !== null) $fields_to_check_edit['email'] = $tenant_email;
            $duplicate_errors_edit = []; // تعريفها هنا
            foreach ($fields_to_check_edit as $field => $value) {
                $stmt_check_edit = $mysqli->prepare("SELECT id FROM tenants WHERE `$field` = ? AND id != ?");
                if($stmt_check_edit){
                    $stmt_check_edit->bind_param("si", $value, $tenant_id_from_post); $stmt_check_edit->execute(); $stmt_check_edit->store_result();
                    if ($stmt_check_edit->num_rows > 0) { $duplicate_errors_edit[] = "حقل '$field' مستخدم بالفعل لمستأجر آخر."; }
                    $stmt_check_edit->close();
                } else { throw new Exception("خطأ تجهيز فحص التكرار (تعديل): " . $mysqli->error); }
            }
            if (!empty($duplicate_errors_edit)) throw new Exception(implode(" ", $duplicate_errors_edit));

            $stmt_old_tenant = $mysqli->prepare("SELECT * FROM tenants WHERE id = ?");
            $old_tenant_data = null;
            if($stmt_old_tenant){
                $stmt_old_tenant->bind_param("i", $tenant_id_from_post);
                $stmt_old_tenant->execute();
                $res_old_tenant = $stmt_old_tenant->get_result();
                if($res_old_tenant->num_rows > 0) $old_tenant_data = $res_old_tenant->fetch_assoc();
                $stmt_old_tenant->close();
            }
            if(!$old_tenant_data) throw new Exception("المستأجر المطلوب تعديله غير موجود.");


            $sql = "UPDATE tenants SET 
                        full_name = ?, national_id_iqama = ?, tenant_type_id = ?, phone_primary = ?, phone_secondary = ?, email = ?,
                        gender = ?, date_of_birth = ?, current_address = ?, occupation = ?, nationality = ?, notes = ?,
                        buyer_vat_number = ?, buyer_street_name = ?, buyer_building_no = ?, buyer_additional_no = ?,
                        buyer_district_name = ?, buyer_city_name = ?, buyer_postal_code = ?, buyer_country_code = ?,
                        emergency_contact_name = ?, emergency_contact_phone = ?
                    WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception("خطأ في تجهيز استعلام تعديل المستأجر: " . $mysqli->error);
            
            $stmt->bind_param("ssisssssssssssssssssssi",
                $tenant_full_name, $tenant_national_id_iqama, $tenant_type_id, $tenant_phone_primary, $tenant_phone_secondary, $tenant_email,
                $gender, $date_of_birth, $tenant_current_address, $tenant_occupation, $tenant_nationality, $tenant_notes,
                $tenant_buyer_vat_number, $tenant_buyer_street_name, $tenant_buyer_building_no, $tenant_buyer_additional_no,
                $tenant_buyer_district_name, $tenant_buyer_city_name, $tenant_buyer_postal_code, $tenant_buyer_country_code,
                $tenant_emergency_contact_name, $tenant_emergency_contact_phone,
                $tenant_id_from_post
            );
            if (!$stmt->execute()) throw new Exception("خطأ في تعديل بيانات المستأجر: " . $stmt->error);
            $stmt->close();
            
            $new_tenant_data = compact('tenant_full_name', 'tenant_national_id_iqama', 'tenant_type_id', 'tenant_phone_primary', 'tenant_phone_secondary', 'tenant_email', 'gender', 'date_of_birth', 'tenant_current_address', 'tenant_occupation', 'tenant_nationality', 'tenant_notes', 'tenant_buyer_vat_number', 'tenant_buyer_street_name', 'tenant_buyer_building_no', 'tenant_buyer_additional_no', 'tenant_buyer_district_name', 'tenant_buyer_city_name', 'tenant_buyer_postal_code', 'tenant_buyer_country_code', 'tenant_emergency_contact_name', 'tenant_emergency_contact_phone');
            log_audit_action($mysqli, AUDIT_EDIT_TENANT, $tenant_id_from_post, 'tenants', ['old_data' => $old_tenant_data, 'new_data' => $new_tenant_data]);
            $response = ['success' => true, 'message' => "تم تعديل بيانات المستأجر بنجاح!"];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف المستأجر مفقود.");
        }
        $mysqli->commit();
        if(isset($_SESSION['old_data_tenant_modal'])) unset($_SESSION['old_data_tenant_modal']); 

    } catch (Exception $e) {
        $mysqli->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Tenant Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
        if(!isset($_SESSION['old_data_tenant_modal'])) $_SESSION['old_data_tenant_modal'] = $_POST;
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_tenant') {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('tenants/index.php'));
    }
    $tenant_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($tenant_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_old_tenant_del = $mysqli->prepare("SELECT full_name, national_id_iqama FROM tenants WHERE id = ?");
            $tenant_details_log = null;
            if($stmt_old_tenant_del){
                $stmt_old_tenant_del->bind_param("i", $tenant_id_to_delete);
                $stmt_old_tenant_del->execute();
                $res_old_tenant_del = $stmt_old_tenant_del->get_result();
                if($res_old_tenant_del->num_rows > 0) $tenant_details_log = $res_old_tenant_del->fetch_assoc();
                $stmt_old_tenant_del->close();
            }
            if(!$tenant_details_log) throw new Exception("المستأجر المطلوب حذفه غير موجود.");
            
            // ... (نفس منطق التحقق من العقود المرتبطة كما في النسخة السابقة)
            $check_leases_sql = "SELECT COUNT(*) as count FROM leases WHERE tenant_id = ?";
            $stmt_check_leases = $mysqli->prepare($check_leases_sql);
            if(!$stmt_check_leases) throw new Exception("خطأ تجهيز فحص عقود الإيجار: " . $mysqli->error);
            $stmt_check_leases->bind_param("i", $tenant_id_to_delete);
            $stmt_check_leases->execute();
            $leases_count_res = $stmt_check_leases->get_result()->fetch_assoc();
            $leases_count = $leases_count_res ? $leases_count_res['count'] : 0;
            $stmt_check_leases->close();
            if ($leases_count > 0) throw new Exception("لا يمكن حذف هذا المستأجر لوجود (" . $leases_count . ") عقد/عقود إيجار مرتبطة به.");

            $sql_delete = "DELETE FROM tenants WHERE id = ?";
            $stmt_delete = $mysqli->prepare($sql_delete);
            if (!$stmt_delete) throw new Exception("خطأ في تجهيز استعلام حذف المستأجر: " . $mysqli->error);
            $stmt_delete->bind_param("i", $tenant_id_to_delete);
            if (!$stmt_delete->execute()) throw new Exception("خطأ في حذف المستأجر: " . $stmt_delete->error);
            $stmt_delete->close();
            
            log_audit_action($mysqli, AUDIT_DELETE_TENANT, $tenant_id_to_delete, 'tenants', $tenant_details_log);
            $mysqli->commit();
            set_message("تم حذف المستأجر بنجاح!", "success");

        } catch (Exception $e) {
             $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Tenant Delete Action Error (GET): " . $e->getMessage());
        }
    } else {
        set_message("معرف المستأجر غير صحيح للحذف.", "danger");
    }
    redirect(base_url('tenants/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_tenant')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>