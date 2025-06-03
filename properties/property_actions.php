<?php
// properties/property_actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_log_functions.php'; // تضمين ملف سجل التدقيق


header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$action = $_POST['action'] ?? '';
$current_user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $response = ['success' => false, 'message' => 'خطأ في التحقق (CSRF). يرجى المحاولة مرة أخرى.'];
        echo json_encode($response);
        exit;
    }

    $property_id = isset($_POST['property_id']) ? filter_var($_POST['property_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    $property_code = isset($_POST['property_code']) ? sanitize_input(trim($_POST['property_code'])) : null;
    $property_name = isset($_POST['property_name']) ? sanitize_input(trim($_POST['property_name'])) : null;
    $owner_id = isset($_POST['owner_id']) && filter_var($_POST['owner_id'], FILTER_VALIDATE_INT) ? (int)$_POST['owner_id'] : null;
    $property_type_id = isset($_POST['property_type_id']) && $_POST['property_type_id'] !== '' ? filter_var($_POST['property_type_id'], FILTER_VALIDATE_INT) : null;
    $property_address = isset($_POST['property_address']) ? sanitize_input(trim($_POST['property_address'])) : null;
    $property_city = isset($_POST['property_city']) ? sanitize_input(trim($_POST['property_city'])) : null;
    $number_of_units = isset($_POST['number_of_units']) && filter_var($_POST['number_of_units'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ? (int)$_POST['number_of_units'] : 0;
    
    $construction_year_input = isset($_POST['construction_year']) ? trim($_POST['construction_year']) : null;
    $construction_year = (!empty($construction_year_input) && filter_var($construction_year_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1800, 'max_range' => (int)date('Y') + 10]])) ? (int)$construction_year_input : null;
    
    $land_area_sqm_input = isset($_POST['land_area_sqm']) ? trim($_POST['land_area_sqm']) : null;
    $land_area_sqm = ($land_area_sqm_input !== '' && filter_var($land_area_sqm_input, FILTER_VALIDATE_FLOAT, ['flags' => FILTER_FLAG_ALLOW_FRACTION]) !== false && (float)$land_area_sqm_input >= 0) ? (float)$land_area_sqm_input : null;

    $latitude_input = isset($_POST['latitude']) ? trim($_POST['latitude']) : null;
    $latitude = ($latitude_input !== '' && filter_var($latitude_input, FILTER_VALIDATE_FLOAT, ['flags' => FILTER_FLAG_ALLOW_FRACTION]) !== false) ? (float)$latitude_input : null;
    
    $longitude_input = isset($_POST['longitude']) ? trim($_POST['longitude']) : null;
    $longitude = ($longitude_input !== '' && filter_var($longitude_input, FILTER_VALIDATE_FLOAT, ['flags' => FILTER_FLAG_ALLOW_FRACTION]) !== false) ? (float)$longitude_input : null;

    $property_notes = isset($_POST['property_notes']) ? sanitize_input(trim($_POST['property_notes'])) : null;

    if (empty($property_code) || empty($property_name) || $owner_id === null || empty($property_address)) {
        $response = ['success' => false, 'message' => 'الحقول المطلوبة (كود العقار، اسم العقار، المالك، العنوان) يجب ملؤها.'];
        echo json_encode($response); exit;
    }
    // ... (بقية التحققات)

    $mysqli->begin_transaction();
    try {
        if ($action === 'add_property') {
            $stmt_check_code = $mysqli->prepare("SELECT id FROM properties WHERE property_code = ?");
            if (!$stmt_check_code) throw new Exception('خطأ في تجهيز استعلام التحقق: ' . $mysqli->error);
            
            $stmt_check_code->bind_param("s", $property_code);
            $stmt_check_code->execute();
            $stmt_check_code->store_result();
            if ($stmt_check_code->num_rows > 0) {
                throw new Exception('كود العقار "' . esc_html($property_code) . '" مستخدم بالفعل.');
            }
            $stmt_check_code->close();

            $sql = "INSERT INTO properties (property_code, name, owner_id, property_type_id, address, city, number_of_units, construction_year, land_area_sqm, latitude, longitude, notes, created_by_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception('خطأ في تجهيز استعلام الإضافة: ' . $mysqli->error);
            
            $stmt->bind_param("ssiissiidddsi", 
                $property_code, $property_name, $owner_id, $property_type_id, $property_address, $property_city, 
                $number_of_units, $construction_year, $land_area_sqm, $latitude, $longitude,
                $property_notes, $current_user_id
            );
            if (!$stmt->execute()) throw new Exception('خطأ في إضافة العقار: ' . $stmt->error);
            
            $new_property_id = $stmt->insert_id;
            $stmt->close();
            log_audit_action($mysqli, AUDIT_CREATE_PROPERTY, $new_property_id, 'properties', ['code' => $property_code, 'name' => $property_name]);
            $response = ['success' => true, 'message' => 'تمت إضافة العقار بنجاح!'];

        } elseif ($action === 'edit_property' && $property_id) {
            $stmt_old_prop = $mysqli->prepare("SELECT * FROM properties WHERE id = ?");
            $old_prop_data = null;
            if($stmt_old_prop){
                $stmt_old_prop->bind_param("i", $property_id);
                $stmt_old_prop->execute();
                $res_old_prop = $stmt_old_prop->get_result();
                if($res_old_prop->num_rows > 0) $old_prop_data = $res_old_prop->fetch_assoc();
                $stmt_old_prop->close();
            }
            if(!$old_prop_data) throw new Exception("العقار المطلوب تعديله غير موجود.");

            $stmt_check_code_edit = $mysqli->prepare("SELECT id FROM properties WHERE property_code = ? AND id != ?");
            if (!$stmt_check_code_edit) throw new Exception('خطأ في تجهيز استعلام التحقق (تعديل): ' . $mysqli->error);
            
            $stmt_check_code_edit->bind_param("si", $property_code, $property_id);
            $stmt_check_code_edit->execute();
            $stmt_check_code_edit->store_result();
            if ($stmt_check_code_edit->num_rows > 0) {
                throw new Exception('كود العقار "' . esc_html($property_code) . '" مستخدم بالفعل لعقار آخر.');
            }
            $stmt_check_code_edit->close();

            $sql = "UPDATE properties SET 
                        property_code = ?, name = ?, owner_id = ?, property_type_id = ?, address = ?, city = ?, 
                        number_of_units = ?, construction_year = ?, land_area_sqm = ?, latitude = ?, longitude = ?, notes = ? 
                    WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception('خطأ في تجهيز استعلام التعديل: ' . $mysqli->error);
            
            $stmt->bind_param("ssiissiidddsi", 
                $property_code, $property_name, $owner_id, $property_type_id, $property_address, $property_city, 
                $number_of_units, $construction_year, $land_area_sqm, $latitude, $longitude,
                $property_notes, $property_id
            );
            if (!$stmt->execute()) throw new Exception('خطأ في تحديث بيانات العقار: ' . $stmt->error);
            $stmt->close();

            $new_prop_data = compact('property_code', 'property_name', 'owner_id', 'property_type_id', 'property_address', 'property_city', 'number_of_units', 'construction_year', 'land_area_sqm', 'latitude', 'longitude', 'property_notes');
            log_audit_action($mysqli, AUDIT_EDIT_PROPERTY, $property_id, 'properties', ['old_data' => $old_prop_data, 'new_data' => $new_prop_data]);
            $response = ['success' => true, 'message' => 'تم تحديث بيانات العقار بنجاح!'];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف العقار مفقود.");
        }
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Property Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_property') {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('properties/index.php'));
    }
    $property_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($property_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            $stmt_old_prop_del = $mysqli->prepare("SELECT property_code, name FROM properties WHERE id = ?");
            $prop_details_for_log = null;
            if($stmt_old_prop_del){
                $stmt_old_prop_del->bind_param("i", $property_id_to_delete);
                $stmt_old_prop_del->execute();
                $res_old_prop_del = $stmt_old_prop_del->get_result();
                if($res_old_prop_del->num_rows > 0) $prop_details_for_log = $res_old_prop_del->fetch_assoc();
                $stmt_old_prop_del->close();
            }
             if(!$prop_details_for_log) throw new Exception("العقار المطلوب حذفه غير موجود.");

            $stmt_check_leases_get = $mysqli->prepare("SELECT COUNT(l.id) as lease_count FROM leases l JOIN units u ON l.unit_id = u.id WHERE u.property_id = ?");
            if (!$stmt_check_leases_get) throw new Exception('خطأ في تجهيز فحص عقود الإيجار (GET): ' . $mysqli->error);
            
            $stmt_check_leases_get->bind_param("i", $property_id_to_delete);
            $stmt_check_leases_get->execute();
            $lease_count_res_get = $stmt_check_leases_get->get_result()->fetch_assoc();
            $stmt_check_leases_get->close();
            $leases_count_get = $lease_count_res_get ? $lease_count_res_get['lease_count'] : 0;

            if ($leases_count_get > 0) {
                throw new Exception('لا يمكن حذف هذا العقار لوجود (' . $leases_count_get . ') عقد/عقود إيجار مرتبطة بوحداته.');
            }
            
            $stmt_delete_get = $mysqli->prepare("DELETE FROM properties WHERE id = ?"); // ON DELETE CASCADE in DB should handle units
            if (!$stmt_delete_get) throw new Exception('فشل في تحضير استعلام الحذف (GET): ' . $mysqli->error);
            
            $stmt_delete_get->bind_param("i", $property_id_to_delete);
            if (!$stmt_delete_get->execute()) throw new Exception('فشل في حذف العقار (GET): ' . $stmt_delete_get->error);
            $stmt_delete_get->close();
            
            log_audit_action($mysqli, AUDIT_DELETE_PROPERTY, $property_id_to_delete, 'properties', $prop_details_for_log);
            $mysqli->commit();
            set_message('تم حذف العقار وجميع وحداته المرتبطة بنجاح!', "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Property Delete Action Error: " . $e->getMessage());
        }
    } else {
        set_message("معرف العقار غير صحيح للحذف.", "danger");
    }
    redirect(base_url('properties/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_property')) {
    $response = ['success' => false, 'message' => 'طلب غير صالح أو طريقة وصول غير مدعومة.'];
    echo json_encode($response);
}
?>