<?php
// units/unit_actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json'); // استجابة AJAX

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$action = $_POST['action'] ?? '';
$current_user_id = get_current_user_id();
$property_id_for_redirect = null; // لعمليات إعادة التوجيه إذا لزم الأمر (عادة لا تستخدم مع AJAX الصريح)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $response = ['success' => false, 'message' => 'خطأ في التحقق (CSRF). يرجى المحاولة مرة أخرى.'];
        echo json_encode($response);
        exit;
    }

    // --- Common Unit Fields ---
    $unit_id = isset($_POST['unit_id']) && !empty($_POST['unit_id']) ? filter_var($_POST['unit_id'], FILTER_SANITIZE_NUMBER_INT) : null;
    $property_id = isset($_POST['property_id_for_unit']) && filter_var($_POST['property_id_for_unit'], FILTER_VALIDATE_INT) ? (int)$_POST['property_id_for_unit'] : null;
    
    if($property_id) $property_id_for_redirect = $property_id;

    $unit_number = isset($_POST['unit_number']) ? sanitize_input($_POST['unit_number']) : null;
    $unit_type_id = isset($_POST['unit_type_id']) && $_POST['unit_type_id'] !== '' ? filter_var($_POST['unit_type_id'], FILTER_VALIDATE_INT) : null; // الحقل الجديد
    $unit_status = isset($_POST['unit_status']) ? sanitize_input($_POST['unit_status']) : 'Vacant';
    $floor_number_input = isset($_POST['floor_number']) ? trim($_POST['floor_number']) : null;
    $floor_number = ($floor_number_input !== '' && filter_var($floor_number_input, FILTER_VALIDATE_INT) !== false) ? (int)$floor_number_input : null;
    
    $size_sqm_input = isset($_POST['size_sqm']) ? trim($_POST['size_sqm']) : null;
    $size_sqm = ($size_sqm_input !== '' && filter_var($size_sqm_input, FILTER_VALIDATE_FLOAT, ['flags' => FILTER_FLAG_ALLOW_FRACTION]) !== false && (float)$size_sqm_input >= 0) ? (float)$size_sqm_input : null;
    
    $bedrooms_input = isset($_POST['bedrooms']) ? trim($_POST['bedrooms']) : null;
    $bedrooms = ($bedrooms_input !== '' && filter_var($bedrooms_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false) ? (int)$bedrooms_input : null;

    $bathrooms_input = isset($_POST['bathrooms']) ? trim($_POST['bathrooms']) : null;
    $bathrooms = ($bathrooms_input !== '' && filter_var($bathrooms_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false) ? (int)$bathrooms_input : null;

    $base_rent_price_input = isset($_POST['base_rent_price']) ? trim($_POST['base_rent_price']) : null;
    $base_rent_price = ($base_rent_price_input !== '' && filter_var($base_rent_price_input, FILTER_VALIDATE_FLOAT, ['flags' => FILTER_FLAG_ALLOW_FRACTION]) !== false && (float)$base_rent_price_input >= 0) ? (float)$base_rent_price_input : null;
    
    $unit_features = isset($_POST['unit_features']) ? sanitize_input($_POST['unit_features']) : null;
    $unit_notes = isset($_POST['unit_notes']) ? sanitize_input($_POST['unit_notes']) : null;

    // --- Basic Validations ---
    if ($property_id === null || empty($unit_number) || empty($unit_status)) {
        $response = ['success' => false, 'message' => 'الحقول المطلوبة (معرف العقار، رقم الوحدة، حالة الوحدة) يجب ملؤها.'];
        echo json_encode($response);
        exit;
    }

    $mysqli->begin_transaction();
    try {
        if ($action === 'add_unit') {
            $stmt_check_unit = $mysqli->prepare("SELECT id FROM units WHERE property_id = ? AND unit_number = ?");
            if(!$stmt_check_unit) throw new Exception("خطأ في تجهيز استعلام التحقق: " . $mysqli->error);
            $stmt_check_unit->bind_param("is", $property_id, $unit_number);
            $stmt_check_unit->execute();
            $stmt_check_unit->store_result();
            if ($stmt_check_unit->num_rows > 0) {
                throw new Exception("رقم/اسم الوحدة '" . esc_html($unit_number) . "' مستخدم بالفعل في هذا العقار.");
            }
            $stmt_check_unit->close();

            $sql = "INSERT INTO units (property_id, unit_number, unit_type_id, status, floor_number, size_sqm, 
                                      bedrooms, bathrooms, base_rent_price, features, notes, created_by_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception("خطأ في تجهيز استعلام إضافة الوحدة: " . $mysqli->error);
            
            // i s i s (property_id, unit_number, unit_type_id, status)
            // i d (floor_number, size_sqm)
            // i i d (bedrooms, bathrooms, base_rent_price)
            // s s i (features, notes, created_by_id)
            // isisid iidd ssi
            $stmt->bind_param("isisi diiddssi",  // تم تعديل النوع ليشمل unit_type_id كـ integer
                $property_id, $unit_number, $unit_type_id, $unit_status, $floor_number, $size_sqm,
                $bedrooms, $bathrooms, $base_rent_price, $unit_features, $unit_notes, $current_user_id
            );
            if (!$stmt->execute()) throw new Exception("خطأ في إضافة الوحدة: " . $stmt->error);
            $stmt->close();
            
            $response = ['success' => true, 'message' => 'تمت إضافة الوحدة بنجاح!'];

        } elseif ($action === 'edit_unit') {
            if ($unit_id === null) throw new Exception("معرف الوحدة مفقود للتعديل.");

            $stmt_check_edit_unit = $mysqli->prepare("SELECT id FROM units WHERE property_id = ? AND unit_number = ? AND id != ?");
            if(!$stmt_check_edit_unit) throw new Exception("خطأ في تجهيز استعلام التحقق (تعديل): " . $mysqli->error);
            $stmt_check_edit_unit->bind_param("isi", $property_id, $unit_number, $unit_id);
            $stmt_check_edit_unit->execute();
            $stmt_check_edit_unit->store_result();
            if ($stmt_check_edit_unit->num_rows > 0) {
                throw new Exception("رقم/اسم الوحدة '" . esc_html($unit_number) . "' مستخدم بالفعل لوحدة أخرى في هذا العقار.");
            }
            $stmt_check_edit_unit->close();

            $sql = "UPDATE units SET 
                        property_id = ?, unit_number = ?, unit_type_id = ?, status = ?, floor_number = ?, size_sqm = ?,
                        bedrooms = ?, bathrooms = ?, base_rent_price = ?, features = ?, notes = ?
                    WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception("خطأ في تجهيز استعلام تعديل الوحدة: " . $mysqli->error);

            // isisid iidd ssi
            $stmt->bind_param("isisi diiddssi", // تم تعديل النوع ليشمل unit_type_id
                $property_id, $unit_number, $unit_type_id, $unit_status, $floor_number, $size_sqm,
                $bedrooms, $bathrooms, $base_rent_price, $unit_features, $unit_notes, $unit_id
            );
            if (!$stmt->execute()) throw new Exception("خطأ في تعديل بيانات الوحدة: " . $stmt->error);
            $stmt->close();
            $response = ['success' => true, 'message' => 'تم تعديل بيانات الوحدة بنجاح!'];
        } else {
            throw new Exception("الإجراء المطلوب غير معروف.");
        }
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Unit Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_unit') {
    // هذا الجزء للطلبات من نوع GET، عادةً بعد تأكيد من المستخدم
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect(base_url('properties/index.php')); // توجيه عام
    }
    
    $property_id_for_redirect_get = isset($_GET['property_id']) ? (int)$_GET['property_id'] : null;
    $redirect_url_on_get = $property_id_for_redirect_get ? base_url('units/index.php?property_id=' . $property_id_for_redirect_get) : base_url('properties/index.php');

    $unit_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($unit_id_to_delete > 0) {
        $mysqli->begin_transaction(); // بدء معاملة للحذف
        try {
            $check_leases_sql = "SELECT COUNT(*) as count FROM leases WHERE unit_id = ?"; //  AND status = 'Active' -- قد ترغب في التحقق من العقود النشطة فقط
            $stmt_check_leases = $mysqli->prepare($check_leases_sql);
            if(!$stmt_check_leases) throw new Exception("خطأ في تجهيز فحص عقود الإيجار: " . $mysqli->error);
            
            $stmt_check_leases->bind_param("i", $unit_id_to_delete);
            $stmt_check_leases->execute();
            $leases_count_res = $stmt_check_leases->get_result()->fetch_assoc();
            $leases_count = $leases_count_res ? $leases_count_res['count'] : 0;
            $stmt_check_leases->close();

            if ($leases_count > 0) {
                throw new Exception("لا يمكن حذف هذه الوحدة لوجود (" . $leases_count . ") عقد/عقود إيجار مرتبطة بها. يرجى إنهاء/إلغاء العقود أولاً أو تغيير الوحدة المرتبطة بها.");
            }
            
            // ON DELETE CASCADE for utility_readings.unit_id will handle utility readings.
            $sql_delete = "DELETE FROM units WHERE id = ?";
            $stmt_delete = $mysqli->prepare($sql_delete);
            if (!$stmt_delete) throw new Exception("خطأ في تجهيز استعلام حذف الوحدة: " . $mysqli->error);
            
            $stmt_delete->bind_param("i", $unit_id_to_delete);
            if (!$stmt_delete->execute()) throw new Exception("خطأ في حذف الوحدة: " . $stmt_delete->error);
            $stmt_delete->close();

            $mysqli->commit();
            set_message("تم حذف الوحدة بنجاح!", "success");

        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ: " . $e->getMessage(), "danger");
            error_log("Unit Delete Action Error: " . $e->getMessage());
        }
    } else {
        set_message("معرف الوحدة غير صحيح للحذف.", "danger");
    }
    redirect($redirect_url_on_get);
}

// إذا لم يكن الطلب POST أو GET للحذف بالشكل المتوقع
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'delete_unit')) {
     set_message("طلب غير صالح أو طريقة وصول غير مدعومة.", "danger");
     redirect(base_url('properties/index.php')); // توجيه عام
}
?>