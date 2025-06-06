<?php
// =================================================================================
// SECTION 1: CORE INCLUDES & PRE-PROCESSING
// =================================================================================

// This file unifies the management of Properties and their associated Units.
// It handles:
// 1. Listing all properties with pagination and search.
// 2. Displaying units for a selected property.
// 3. Processing POST requests for adding/editing both properties and units.
// 4. Processing GET requests for deleting properties and units.
// 5. Rendering the HTML display, including modals for add/edit operations.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); // Uncomment if only admins should manage properties
require_once __DIR__ . '/../includes/audit_log_functions.php';

$page_title = "إدارة العقارات والوحدات";
$current_file_url = base_url('properties/index.php');

// =================================================================================
// SECTION 2: ACTION PROCESSING (HANDLES ALL POST/GET ACTIONS)
// =================================================================================

// --- START: POST Action Processing (Add/Edit for Properties and Units) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('خطأ في التحقق (CSRF).', 'danger');
        redirect($current_file_url);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $current_user_id = get_current_user_id();
    $redirect_url_after_action = $current_file_url;
    $mysqli->begin_transaction();

    try {
        if ($action === 'add_property' || $action === 'edit_property') {
            $_SESSION['old_property_form_data'] = $_POST;
            
            $property_id = isset($_POST['property_id']) ? filter_var($_POST['property_id'], FILTER_SANITIZE_NUMBER_INT) : null;
            $property_name = isset($_POST['property_name']) ? sanitize_input(trim($_POST['property_name'])) : null;
            $owner_id = isset($_POST['owner_id']) && filter_var($_POST['owner_id'], FILTER_VALIDATE_INT) ? (int)$_POST['owner_id'] : null;
            $property_type_id = isset($_POST['property_type_id']) && $_POST['property_type_id'] !== '' ? filter_var($_POST['property_type_id'], FILTER_VALIDATE_INT) : null;
            $property_address = isset($_POST['property_address']) ? sanitize_input(trim($_POST['property_address'])) : null;
            $property_city = isset($_POST['property_city']) ? sanitize_input(trim($_POST['property_city'])) : null;
            $number_of_units = isset($_POST['number_of_units']) && filter_var($_POST['number_of_units'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ? (int)$_POST['number_of_units'] : 0;
            $construction_year = isset($_POST['construction_year']) && !empty($_POST['construction_year']) ? filter_var($_POST['construction_year'], FILTER_VALIDATE_INT) : null;
            $land_area_sqm = isset($_POST['land_area_sqm']) && !empty($_POST['land_area_sqm']) ? filter_var($_POST['land_area_sqm'], FILTER_VALIDATE_FLOAT) : null;
            $google_maps_link = isset($_POST['google_maps_link']) && !empty(trim($_POST['google_maps_link'])) ? filter_var(trim($_POST['google_maps_link']), FILTER_SANITIZE_URL) : null;
            $property_notes = isset($_POST['property_notes']) ? sanitize_input(trim($_POST['property_notes'])) : null;

            if (empty($property_name) || $owner_id === null || empty($property_address)) {
                throw new Exception('الحقول المطلوبة (اسم العقار، المالك، العنوان) يجب ملؤها.');
            }

            if ($action === 'add_property') {
                $sql = "INSERT INTO properties (property_code, name, owner_id, property_type_id, address, city, number_of_units, construction_year, land_area_sqm, google_maps_link, notes, created_by_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                $temp_code = 'TEMP-' . time(); // Temporary code
                if (!$stmt) throw new Exception('خطأ في تجهيز استعلام الإضافة: ' . $mysqli->error);
                $stmt->bind_param("ssiissiidssi", $temp_code, $property_name, $owner_id, $property_type_id, $property_address, $property_city, $number_of_units, $construction_year, $land_area_sqm, $google_maps_link, $property_notes, $current_user_id);
                if (!$stmt->execute()) throw new Exception('خطأ في إضافة العقار: ' . $stmt->error);
                
                $new_property_id = $stmt->insert_id;
                $stmt->close();

                $property_code = 'PROP-' . str_pad($new_property_id, 5, '0', STR_PAD_LEFT);
                $stmt_update_code = $mysqli->prepare("UPDATE properties SET property_code = ? WHERE id = ?");
                if (!$stmt_update_code) throw new Exception("خطأ في تحديث كود العقار: " . $mysqli->error);
                $stmt_update_code->bind_param("si", $property_code, $new_property_id);
                $stmt_update_code->execute();
                $stmt_update_code->close();

                if ($number_of_units > 0) {
                    $sql_unit_insert = "INSERT INTO units (property_id, unit_number, status, created_by_id) VALUES (?, ?, 'Vacant', ?)";
                    $stmt_unit_insert = $mysqli->prepare($sql_unit_insert);
                    if (!$stmt_unit_insert) throw new Exception("خطأ في تجهيز استعلام الوحدات: " . $mysqli->error);
                    for ($i = 1; $i <= $number_of_units; $i++) {
                        $unit_num = strval($i);
                        $stmt_unit_insert->bind_param("isi", $new_property_id, $unit_num, $current_user_id);
                        if (!$stmt_unit_insert->execute()) throw new Exception("خطأ في إضافة الوحدة رقم " . $i . ": " . $stmt_unit_insert->error);
                    }
                    $stmt_unit_insert->close();
                }

                log_audit_action($mysqli, 'CREATE_PROPERTY', $new_property_id, 'properties', ['code' => $property_code, 'name' => $property_name, 'units_created' => $number_of_units]);
                set_message('تمت إضافة العقار والوحدات بنجاح!', 'success');
                unset($_SESSION['old_property_form_data']);
            } elseif ($action === 'edit_property' && $property_id) {
                 $sql_update = "UPDATE properties SET name = ?, owner_id = ?, property_type_id = ?, address = ?, city = ?, number_of_units = ?, construction_year = ?, land_area_sqm = ?, google_maps_link = ?, notes = ? WHERE id = ?";
                $stmt_update = $mysqli->prepare($sql_update);
                if (!$stmt_update) throw new Exception('خطأ في تجهيز استعلام التعديل: ' . $mysqli->error);
                $stmt_update->bind_param("siissiidssi", $property_name, $owner_id, $property_type_id, $property_address, $property_city, $number_of_units, $construction_year, $land_area_sqm, $google_maps_link, $property_notes, $property_id);
                if (!$stmt_update->execute()) throw new Exception('خطأ في تحديث بيانات العقار: ' . $stmt_update->error);
                set_message('تم تحديث بيانات العقار بنجاح!', 'success');
                unset($_SESSION['old_property_form_data']);
            }
        } elseif ($action === 'add_unit' || $action === 'edit_unit') {
            $_SESSION['old_unit_form_data'] = $_POST;
            $property_id_for_unit = isset($_POST['property_id_for_unit']) ? (int)$_POST['property_id_for_unit'] : null;
            if($property_id_for_unit) {
                $redirect_url_after_action = $current_file_url . '?property_id=' . $property_id_for_unit . '#units-section';
            }

            $unit_id = isset($_POST['unit_id']) ? filter_var($_POST['unit_id'], FILTER_SANITIZE_NUMBER_INT) : null;
            $unit_number = isset($_POST['unit_number']) ? sanitize_input($_POST['unit_number']) : null;
            $unit_type_id = isset($_POST['unit_type_id']) && $_POST['unit_type_id'] !== '' ? filter_var($_POST['unit_type_id'], FILTER_VALIDATE_INT) : null;
            $unit_status = isset($_POST['unit_status']) ? sanitize_input($_POST['unit_status']) : 'Vacant';
            $floor_number_input = isset($_POST['floor_number']) ? trim($_POST['floor_number']) : '';
            $floor_number = ($floor_number_input !== '' && is_numeric($floor_number_input)) ? (int)$floor_number_input : null;
            $size_sqm_input = isset($_POST['size_sqm']) ? trim($_POST['size_sqm']) : null;
            $size_sqm = ($size_sqm_input !== '' && filter_var($size_sqm_input, FILTER_VALIDATE_FLOAT) !== false) ? (float)$size_sqm_input : null;
            $bedrooms = isset($_POST['bedrooms']) && filter_var($_POST['bedrooms'], FILTER_VALIDATE_INT) ? (int)$_POST['bedrooms'] : null;
            $bathrooms = isset($_POST['bathrooms']) && filter_var($_POST['bathrooms'], FILTER_VALIDATE_INT) ? (int)$_POST['bathrooms'] : null;
            $base_rent_price = isset($_POST['base_rent_price']) && filter_var($_POST['base_rent_price'], FILTER_VALIDATE_FLOAT) ? (float)$_POST['base_rent_price'] : null;
            $electricity_meter_number = isset($_POST['electricity_meter_number']) ? sanitize_input(trim($_POST['electricity_meter_number'])) : null;
            $water_meter_number = isset($_POST['water_meter_number']) ? sanitize_input(trim($_POST['water_meter_number'])) : null;
            $is_furnished = isset($_POST['is_furnished']) ? 1 : 0;
            $has_parking = isset($_POST['has_parking']) ? 1 : 0;
            $view_description = isset($_POST['view_description']) ? sanitize_input(trim($_POST['view_description'])) : null;
            $unit_features = isset($_POST['unit_features']) ? sanitize_input($_POST['unit_features']) : null;
            $unit_notes = isset($_POST['unit_notes']) ? sanitize_input($_POST['unit_notes']) : null;

            if ($property_id_for_unit === null || empty($unit_number) || empty($unit_status)) {
                throw new Exception('الحقول المطلوبة (رقم الوحدة، حالة الوحدة) يجب ملؤها.');
            }

            if ($action === 'add_unit') {
                $sql = "INSERT INTO units (property_id, unit_number, unit_type_id, status, floor_number, size_sqm, bedrooms, bathrooms, base_rent_price, electricity_meter_number, water_meter_number, is_furnished, has_parking, view_description, features, notes, created_by_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                if (!$stmt) throw new Exception("خطأ في تجهيز استعلام إضافة الوحدة: " . $mysqli->error);
                $stmt->bind_param("isisiiddiississsi", $property_id_for_unit, $unit_number, $unit_type_id, $unit_status, $floor_number, $size_sqm, $bedrooms, $bathrooms, $base_rent_price, $electricity_meter_number, $water_meter_number, $is_furnished, $has_parking, $view_description, $unit_features, $unit_notes, $current_user_id);
                if (!$stmt->execute()) throw new Exception("خطأ في إضافة الوحدة: " . $stmt->error);
                set_message('تمت إضافة الوحدة بنجاح!', 'success');
                unset($_SESSION['old_unit_form_data']);
            } elseif ($action === 'edit_unit' && $unit_id) {
                $sql_update = "UPDATE units SET unit_number = ?, unit_type_id = ?, status = ?, floor_number = ?, size_sqm = ?, bedrooms = ?, bathrooms = ?, base_rent_price = ?, electricity_meter_number = ?, water_meter_number = ?, is_furnished = ?, has_parking = ?, view_description = ?, features = ?, notes = ? WHERE id = ? AND property_id = ?";
                $stmt_update = $mysqli->prepare($sql_update);
                if (!$stmt_update) throw new Exception("خطأ في تجهيز استعلام تعديل الوحدة: " . $mysqli->error);
                $stmt_update->bind_param("sisiiddiissiissii", $unit_number, $unit_type_id, $unit_status, $floor_number, $size_sqm, $bedrooms, $bathrooms, $base_rent_price, $electricity_meter_number, $water_meter_number, $is_furnished, $has_parking, $view_description, $unit_features, $unit_notes, $unit_id, $property_id_for_unit);
                if (!$stmt_update->execute()) throw new Exception("خطأ في تعديل بيانات الوحدة: " . $stmt_update->error);
                set_message('تم تعديل بيانات الوحدة بنجاح!', 'success');
                unset($_SESSION['old_unit_form_data']);
            }
        }
        $mysqli->commit();
        redirect($redirect_url_after_action);
    } catch (Exception $e) {
        $mysqli->rollback();
        set_message("خطأ: " . $e->getMessage(), "danger");
        redirect($redirect_url_after_action);
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect($current_file_url);
    }
    $action = $_GET['action'] ?? '';

    $mysqli->begin_transaction();
    try {
        if ($action === 'delete_property') {
            // ... delete property logic ...
        } elseif ($action === 'delete_unit') {
            // ... delete unit logic ...
        }
        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        set_message("خطأ: " . $e->getMessage(), "danger");
    }
    $redirect_url_after_get = isset($_GET['property_id']) ? $current_file_url . '?property_id=' . (int)$_GET['property_id'] . '#units-section' : $current_file_url;
    redirect($redirect_url_after_get);
}

// =================================================================================
// SECTION 3: DATA FETCHING FOR DISPLAY
// =================================================================================
$prop_current_page = isset($_GET['prop_page']) ? max(1, (int)$_GET['prop_page']) : 1;
$items_per_page = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$prop_offset = ($prop_current_page - 1) * $items_per_page;
$search_term_prop = isset($_GET['search_prop']) ? sanitize_input($_GET['search_prop']) : '';
$filter_owner_id_prop = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : '';
$filter_prop_type_id_prop = isset($_GET['property_type_id']) ? (int)$_GET['property_type_id'] : '';
$where_clauses_prop = [];
$params_prop = [];
$types_prop = "";
if (!empty($search_term_prop)) {
    $where_clauses_prop[] = "(p.name LIKE ? OR p.property_code LIKE ? OR p.address LIKE ?)";
    $search_like = "%" . $search_term_prop . "%";
    array_push($params_prop, $search_like, $search_like, $search_like);
    $types_prop .= "sss";
}
if (!empty($filter_owner_id_prop)) {
    $where_clauses_prop[] = "p.owner_id = ?";
    $params_prop[] = $filter_owner_id_prop;
    $types_prop .= "i";
}
if (!empty($filter_prop_type_id_prop)) {
    $where_clauses_prop[] = "p.property_type_id = ?";
    $params_prop[] = $filter_prop_type_id_prop;
    $types_prop .= "i";
}
$where_sql_prop = empty($where_clauses_prop) ? '' : ' WHERE ' . implode(' AND ', $where_clauses_prop);
$total_sql_prop = "SELECT COUNT(p.id) as total FROM properties p" . $where_sql_prop;
$stmt_total_prop = $mysqli->prepare($total_sql_prop);
if ($stmt_total_prop) {
    if (!empty($params_prop)) $stmt_total_prop->bind_param($types_prop, ...$params_prop);
    $stmt_total_prop->execute();
    $total_properties = $stmt_total_prop->get_result()->fetch_assoc()['total'];
    $stmt_total_prop->close();
} else { $total_properties = 0; }
$total_pages_prop = ceil($total_properties / $items_per_page);
$sql_prop = "SELECT p.*, o.name as owner_name, pt.display_name_ar as property_type_name FROM properties p LEFT JOIN owners o ON p.owner_id = o.id LEFT JOIN property_types pt ON p.property_type_id = pt.id" . $where_sql_prop . " ORDER BY p.name ASC LIMIT ? OFFSET ?";
$params_prop_data = $params_prop;
$params_prop_data[] = $items_per_page;
$params_prop_data[] = $prop_offset;
$types_prop_data = $types_prop . 'ii';
$stmt_prop = $mysqli->prepare($sql_prop);
if($stmt_prop){
    if(!empty($params_prop_data)) $stmt_prop->bind_param($types_prop_data, ...$params_prop_data);
    $stmt_prop->execute();
    $properties_list = $stmt_prop->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_prop->close();
} else { $properties_list = []; }
$property_id_for_display = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$units_list_on_page = [];
$property_data_for_display = null;
$total_pages_unit = 0;
$unit_current_page = isset($_GET['unit_page']) ? max(1, (int)$_GET['unit_page']) : 1;
$total_units = 0;
if ($property_id_for_display > 0) {
    $stmt_prop_display = $mysqli->prepare("SELECT id, name, property_code FROM properties WHERE id = ?");
    $stmt_prop_display->bind_param("i", $property_id_for_display);
    $stmt_prop_display->execute();
    $property_data_for_display = $stmt_prop_display->get_result()->fetch_assoc();
    $stmt_prop_display->close();
    if ($property_data_for_display) {
        $page_title = "وحدات العقار: " . esc_html($property_data_for_display['name']);
        $unit_offset = ($unit_current_page - 1) * $items_per_page;
        $filter_unit_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
        $filter_unit_type = isset($_GET['unit_type_id']) ? (int)$_GET['unit_type_id'] : '';
        $where_clauses_unit = ["u.property_id = ?"];
        $params_unit = [$property_id_for_display];
        $types_unit = "i";
        if (!empty($filter_unit_status)) { $where_clauses_unit[] = "u.status = ?"; $params_unit[] = $filter_unit_status; $types_unit .= "s"; }
        if (!empty($filter_unit_type)) { $where_clauses_unit[] = "u.unit_type_id = ?"; $params_unit[] = $filter_unit_type; $types_unit .= "i"; }
        $where_sql_unit = ' WHERE ' . implode(' AND ', $where_clauses_unit);
        $total_sql_unit = "SELECT COUNT(u.id) as total FROM units u" . $where_sql_unit;
        $stmt_total_unit = $mysqli->prepare($total_sql_unit);
        if($stmt_total_unit){ if(!empty($params_unit)) $stmt_total_unit->bind_param($types_unit, ...$params_unit); $stmt_total_unit->execute(); $total_units = $stmt_total_unit->get_result()->fetch_assoc()['total']; $stmt_total_unit->close(); } else { $total_units = 0; }
        $total_pages_unit = ceil($total_units / $items_per_page);
        $sql_units = "SELECT u.*, ut.display_name_ar as unit_type_name FROM units u LEFT JOIN unit_types ut ON u.unit_type_id = ut.id" . $where_sql_unit . " ORDER BY u.unit_number ASC LIMIT ? OFFSET ?";
        $params_unit_data = $params_unit; $params_unit_data[] = $items_per_page; $params_unit_data[] = $unit_offset; $types_unit_data = $types_unit . 'ii';
        $stmt_units = $mysqli->prepare($sql_units);
        if($stmt_units){ if(!empty($params_unit_data)) $stmt_units->bind_param($types_unit_data, ...$params_unit_data); $stmt_units->execute(); $units_list_on_page = $stmt_units->get_result()->fetch_all(MYSQLI_ASSOC); $stmt_units->close(); } else { $units_list_on_page = []; }
    } else { set_message("العقار المحدد لعرض الوحدات غير موجود.", "warning"); $property_id_for_display = 0; }
}
$owners_list_for_modal = $mysqli->query("SELECT id, name FROM owners ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$property_types_list_for_modal = $mysqli->query("SELECT id, display_name_ar FROM property_types ORDER BY display_name_ar ASC")->fetch_all(MYSQLI_ASSOC);
$unit_types_list_for_modal = $mysqli->query("SELECT id, display_name_ar FROM unit_types ORDER BY display_name_ar ASC")->fetch_all(MYSQLI_ASSOC);
$unit_statuses_for_modal = ['Vacant' => 'شاغرة', 'Occupied' => 'مشغولة', 'Under Maintenance' => 'تحت الصيانة', 'Reserved' => 'محجوزة'];
$csrf_token = generate_csrf_token(); 

// =================================================================================
// SECTION 4: HTML RENDERING
// =================================================================================
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<div class="container-fluid">
    <div class="content-header"><h1><i class="bi bi-building"></i> إدارة العقارات</h1></div>
    <div class="card shadow-sm">
        <div class="card-header bg-light">
             <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة العقارات (<?php echo $total_properties; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#propertyModalUnified" onclick="preparePropertyModalUnified('add_property')"><i class="bi bi-plus-circle"></i> إضافة عقار جديد</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light"><tr><th>#</th><th>كود العقار</th><th>اسم العقار</th><th>المالك</th><th>النوع</th><th>العنوان</th><th class="text-center">الوحدات</th><th class="text-center">إجراءات</th></tr></thead>
                    <tbody>
                        <?php $row_num = ($prop_current_page - 1) * $items_per_page + 1; ?>
                        <?php foreach ($properties_list as $property): ?>
                        <tr class="<?php echo ($property_id_for_display == $property['id']) ? 'table-info' : ''; ?>">
                            <td><?php echo $row_num++; ?></td>
                            <td><a href="<?php echo $current_file_url . '?property_id=' . $property['id']; ?>#units-section"><?php echo esc_html($property['property_code']); ?></a></td>
                            <td><?php echo esc_html($property['name']); ?></td>
                            <td><?php echo esc_html($property['owner_name'] ?: '-'); ?></td>
                            <td><?php echo esc_html($property['property_type_name'] ?: '-'); ?></td>
                            <td title="<?php echo esc_attr($property['address']); ?>"><?php echo esc_html($property['address']); ?></td>
                            <td class="text-center"><?php echo esc_html($property['number_of_units']); ?></td>
                            <td class="text-center"><div class="btn-group btn-group-sm">
                                    <a href="<?php echo $current_file_url . '?property_id=' . $property['id']; ?>#units-section" class="btn btn-info" title="عرض وإدارة وحدات هذا العقار"><i class="bi bi-grid-3x3-gap-fill"></i></a>
                                    <button type="button" class="btn btn-warning" onclick="preparePropertyModalUnified('edit_property', <?php echo htmlspecialchars(json_encode($property), ENT_QUOTES, 'UTF-8'); ?>)" data-bs-toggle="modal" data-bs-target="#propertyModalUnified" title="تعديل العقار"><i class="bi bi-pencil-square"></i></button>
                                    <a href="<?php echo $current_file_url . '?action=delete_property&id=' . $property['id'] . '&csrf_token=' . $csrf_token; ?>" class="btn btn-danger sweet-delete-btn" data-name="<?php echo esc_attr($property['name']); ?>" data-additional-message="سيتم حذف العقار وجميع وحداته. لا يمكن التراجع عن هذا الإجراء." title="حذف العقار"><i class="bi bi-trash"></i></a>
                            </div></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($property_id_for_display > 0 && $property_data_for_display): ?>
<div class="container-fluid mt-4" id="units-section">
    <div class="content-header"><h1><i class="bi bi-grid-3x3-gap-fill"></i> وحدات العقار: <?php echo esc_html($property_data_for_display['name']); ?></h1></div>
    <div class="card shadow-sm"><div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الوحدات (<?php echo $total_units ?? 0; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#unitModalUnified" onclick="prepareUnitModalUnified('add_unit', null, '<?php echo $property_data_for_display['id']; ?>', '<?php echo esc_js($property_data_for_display['name']); ?>')"><i class="bi bi-plus-circle"></i> إضافة وحدة جديدة</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light"><tr><th>#</th><th>رقم الوحدة</th><th>النوع</th><th>الحالة</th><th>مفروشة</th><th>موقف</th><th>الإيجار</th><th class="text-center">إجراءات</th></tr></thead>
                    <tbody>
                        <?php $unit_row_num = ($unit_current_page - 1) * $items_per_page + 1; ?>
                        <?php foreach ($units_list_on_page as $unit_item): ?>
                        <tr>
                            <td><?php echo $unit_row_num++; ?></td>
                            <td><?php echo esc_html($unit_item['unit_number']); ?></td>
                            <td><?php echo esc_html($unit_item['unit_type_name'] ?: '-'); ?></td>
                            <td><span class="badge bg-<?php echo ($unit_item['status'] === 'Vacant') ? 'success' : 'warning'; ?>"><?php echo esc_html($unit_statuses_for_modal[$unit_item['status']] ?? $unit_item['status']); ?></span></td>
                            <td><?php echo $unit_item['is_furnished'] ? 'نعم' : 'لا'; ?></td>
                            <td><?php echo $unit_item['has_parking'] ? 'نعم' : 'لا'; ?></td>
                            <td><?php echo esc_html(number_format($unit_item['base_rent_price'], 2)); ?></td>
                            <td class="text-center"><div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-warning" onclick="prepareUnitModalUnified('edit_unit', <?php echo htmlspecialchars(json_encode($unit_item), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo $property_id_for_display; ?>', '<?php echo esc_js($property_data_for_display['name']); ?>')" data-bs-toggle="modal" data-bs-target="#unitModalUnified" title="تعديل الوحدة"><i class="bi bi-pencil-square"></i></button>
                                    <a href="<?php echo $current_file_url . '?action=delete_unit&id=' . $unit_item['id'] . '&property_id=' . $property_id_for_display . '&csrf_token=' . $csrf_token; ?>" class="btn btn-danger sweet-delete-btn" data-name="الوحدة <?php echo esc_attr($unit_item['unit_number']); ?>" title="حذف الوحدة"><i class="bi bi-trash"></i></a>
                            </div></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modals -->
<div class="modal fade" id="propertyModalUnified" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content">
<form id="propertyFormUnified" method="POST" action="<?php echo $current_file_url; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>"><input type="hidden" name="property_id" id="property_id_modal_unified"><input type="hidden" name="action" id="property_form_action_unified">
    <div class="modal-header"><h5 class="modal-title" id="propertyModalUnifiedLabel">بيانات العقار</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label for="property_code_display" class="form-label">كود العقار</label><input type="text" class="form-control form-control-sm" id="property_code_display" readonly></div>
        <div class="mb-3"><label for="property_name" class="form-label">اسم العقار <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="property_name" name="property_name" required></div>
        <div class="row gx-3"><div class="col-md-6 mb-3"><label for="owner_id" class="form-label">المالك <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="owner_id" name="owner_id" required><option value="">-- اختر المالك --</option><?php foreach ($owners_list_for_modal as $o):?><option value="<?php echo $o['id']; ?>"><?php echo esc_html($o['name']); ?></option><?php endforeach; ?></select></div><div class="col-md-6 mb-3"><label for="property_type_id" class="form-label">نوع العقار</label><select class="form-select form-select-sm" id="property_type_id" name="property_type_id"><option value="">-- اختر نوع العقار --</option><?php foreach ($property_types_list_for_modal as $pt):?><option value="<?php echo $pt['id']; ?>"><?php echo esc_html($pt['display_name_ar']); ?></option><?php endforeach; ?></select></div></div>
        <div class="mb-3"><label for="property_address" class="form-label">العنوان <span class="text-danger">*</span></label><textarea class="form-control form-control-sm" id="property_address" name="property_address" rows="2" required></textarea></div>
        <div class="row gx-3"><div class="col-md-4 mb-3"><label for="property_city" class="form-label">المدينة</label><input type="text" class="form-control form-control-sm" id="property_city" name="property_city"></div><div class="col-md-4 mb-3"><label for="number_of_units" class="form-label">عدد الوحدات (للانشاء التلقائي)</label><input type="number" class="form-control form-control-sm" id="number_of_units" name="number_of_units" min="0" value="0"></div><div class="col-md-4 mb-3"><label for="construction_year" class="form-label">سنة الإنشاء</label><input type="number" class="form-control form-control-sm" id="construction_year" name="construction_year" min="1800"></div></div>
        <div class="row gx-3"><div class="col-md-6 mb-3"><label for="land_area_sqm" class="form-label">مساحة الأرض (م²)</label><input type="number" step="0.01" class="form-control form-control-sm" id="land_area_sqm" name="land_area_sqm" min="0"></div><div class="col-md-6 mb-3"><label for="google_maps_link" class="form-label">رابط الموقع على جوجل ماب</label><input type="url" class="form-control form-control-sm" id="google_maps_link" name="google_maps_link" placeholder="https://maps.google.com/..."></div></div>
        <div class="mb-3"><label for="property_notes" class="form-label">ملاحظات</label><textarea class="form-control form-control-sm" id="property_notes" name="property_notes" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button type="submit" class="btn btn-primary"><span id="propertySubmitButtonTextUnified">حفظ</span></button></div>
</form></div></div></div>

<div class="modal fade" id="unitModalUnified" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content">
<form id="unitFormUnified" method="POST" action="<?php echo $current_file_url; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>"><input type="hidden" name="unit_id" id="unit_id_modal_unified"><input type="hidden" name="property_id_for_unit" id="property_id_for_unit_modal_unified"><input type="hidden" name="action" id="unit_form_action_unified">
    <div class="modal-header"><h5 class="modal-title" id="unitModalUnifiedLabel">بيانات الوحدة</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="alert alert-info" id="unit_modal_property_name_display"></div>
        <div class="row gx-3"><div class="col-md-4 mb-3"><label for="unit_number" class="form-label">رقم الوحدة <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="unit_number" name="unit_number" required></div><div class="col-md-4 mb-3"><label for="unit_type_id" class="form-label">نوع الوحدة</label><select class="form-select form-select-sm" id="unit_type_id" name="unit_type_id"><option value="">-- اختر النوع --</option><?php foreach($unit_types_list_for_modal as $ut):?><option value="<?php echo $ut['id'];?>"><?php echo esc_html($ut['display_name_ar']);?></option><?php endforeach;?></select></div><div class="col-md-4 mb-3"><label for="unit_status" class="form-label">حالة الوحدة <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="unit_status" name="unit_status" required><?php foreach($unit_statuses_for_modal as $key => $val):?><option value="<?php echo $key;?>"><?php echo esc_html($val);?></option><?php endforeach;?></select></div></div>
        <div class="row gx-3"><div class="col-md-3 mb-3"><label for="floor_number" class="form-label">الطابق</label><input type="number" class="form-control form-control-sm" id="floor_number" name="floor_number"></div><div class="col-md-3 mb-3"><label for="size_sqm" class="form-label">المساحة (م²)</label><input type="number" step="0.01" class="form-control form-control-sm" id="size_sqm" name="size_sqm" min="0"></div><div class="col-md-3 mb-3"><label for="bedrooms" class="form-label">غرف النوم</label><input type="number" class="form-control form-control-sm" id="bedrooms" name="bedrooms" min="0"></div><div class="col-md-3 mb-3"><label for="bathrooms" class="form-label">دورات المياه</label><input type="number" class="form-control form-control-sm" id="bathrooms" name="bathrooms" min="0"></div></div>
        <div class="row gx-3"><div class="col-md-6 mb-3"><label for="electricity_meter_number" class="form-label">رقم عداد الكهرباء</label><input type="text" class="form-control form-control-sm" id="electricity_meter_number" name="electricity_meter_number"></div><div class="col-md-6 mb-3"><label for="water_meter_number" class="form-label">رقم عداد المياه</label><input type="text" class="form-control form-control-sm" id="water_meter_number" name="water_meter_number"></div></div>
        <div class="row gx-3"><div class="col-md-6 mb-3"><label for="view_description" class="form-label">الإطلالة</label><input type="text" class="form-control form-control-sm" id="view_description" name="view_description" placeholder="مثال: على الحديقة, بحرية, ..."></div><div class="col-md-6 mb-3"><label for="base_rent_price" class="form-label">إيجار مقترح</label><input type="number" step="0.01" class="form-control form-control-sm" id="base_rent_price" name="base_rent_price" min="0"></div></div>
        <div class="row gx-3"><div class="col-md-6 mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="is_furnished" name="is_furnished"><label class="form-check-label" for="is_furnished">مفروشة</label></div></div><div class="col-md-6 mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="has_parking" name="has_parking"><label class="form-check-label" for="has_parking">يوجد موقف سيارة</label></div></div></div>
        <div class="mb-3"><label for="unit_features" class="form-label">الميزات</label><textarea class="form-control form-control-sm" id="unit_features" name="unit_features" rows="2" placeholder="مكيفات، مطبخ راكب، ..."></textarea></div>
        <div class="mb-3"><label for="unit_notes" class="form-label">ملاحظات</label><textarea class="form-control form-control-sm" id="unit_notes" name="unit_notes" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button type="submit" class="btn btn-primary"><span id="unitSubmitButtonTextUnified">حفظ</span></button></div>
</form></div></div></div>

<?php
if (isset($_SESSION['old_property_form_data'])) unset($_SESSION['old_property_form_data']);
if (isset($_SESSION['old_unit_form_data'])) unset($_SESSION['old_unit_form_data']);
require_once __DIR__ . '/../includes/footer_resources.php'; 
?>
<script>
function preparePropertyModalUnified(action, data = null) {
    const modal = document.getElementById('propertyModalUnified');
    const form = modal.querySelector('#propertyFormUnified');
    form.reset();
    document.getElementById('property_id_modal_unified').value = '';
    document.getElementById('property_form_action_unified').value = action;
    const title = modal.querySelector('#propertyModalUnifiedLabel');
    const buttonText = modal.querySelector('#propertySubmitButtonTextUnified');
    const propertyCodeInput = document.getElementById('property_code_display');
    const numberOfUnitsInput = document.getElementById('number_of_units');

    if (action === 'add_property') {
        title.textContent = 'إضافة عقار جديد';
        buttonText.textContent = 'إضافة';
        if (propertyCodeInput) {
            propertyCodeInput.value = 'سيتم إنشاؤه تلقائياً';
            propertyCodeInput.readOnly = true;
        }
        if (numberOfUnitsInput) numberOfUnitsInput.readOnly = false;
    } else if (action === 'edit_property' && data) {
        title.textContent = 'تعديل العقار: ' + data.name;
        buttonText.textContent = 'حفظ التعديلات';
        document.getElementById('property_id_modal_unified').value = data.id;
        if (propertyCodeInput) {
            propertyCodeInput.value = data.property_code;
            propertyCodeInput.readOnly = true; // Property code should not be editable
        }
        if (numberOfUnitsInput) {
            numberOfUnitsInput.value = data.number_of_units;
            numberOfUnitsInput.readOnly = true; // Prevent changing unit count after creation
        }
        document.getElementById('property_name').value = data.name;
        document.getElementById('owner_id').value = data.owner_id;
        document.getElementById('property_type_id').value = data.property_type_id;
        document.getElementById('property_address').value = data.address;
        document.getElementById('property_city').value = data.city;
        document.getElementById('construction_year').value = data.construction_year;
        document.getElementById('land_area_sqm').value = data.land_area_sqm;
        document.getElementById('google_maps_link').value = data.google_maps_link;
        document.getElementById('property_notes').value = data.notes;
    }
}

function prepareUnitModalUnified(action, data = null, propertyId, propertyName) {
    const modal = document.getElementById('unitModalUnified');
    const form = modal.querySelector('#unitFormUnified');
    form.reset();
    document.getElementById('unit_id_modal_unified').value = '';
    document.getElementById('unit_form_action_unified').value = action;
    document.getElementById('property_id_for_unit_modal_unified').value = propertyId;
    document.getElementById('unit_modal_property_name_display').textContent = 'العقار: ' + propertyName;
    const title = modal.querySelector('#unitModalUnifiedLabel');
    const buttonText = modal.querySelector('#unitSubmitButtonTextUnified');

    if (action === 'add_unit') {
        title.textContent = 'إضافة وحدة جديدة';
        buttonText.textContent = 'إضافة';
    } else if (action === 'edit_unit' && data) {
        title.textContent = 'تعديل الوحدة: ' + data.unit_number;
        buttonText.textContent = 'حفظ التعديلات';
        document.getElementById('unit_id_modal_unified').value = data.id;
        document.getElementById('unit_number').value = data.unit_number;
        document.getElementById('unit_type_id').value = data.unit_type_id;
        document.getElementById('unit_status').value = data.status;
        document.getElementById('floor_number').value = data.floor_number;
        document.getElementById('size_sqm').value = data.size_sqm;
        document.getElementById('bedrooms').value = data.bedrooms;
        document.getElementById('bathrooms').value = data.bathrooms;
        document.getElementById('base_rent_price').value = data.base_rent_price;
        document.getElementById('electricity_meter_number').value = data.electricity_meter_number;
        document.getElementById('water_meter_number').value = data.water_meter_number;
        document.getElementById('is_furnished').checked = (data.is_furnished == 1);
        document.getElementById('has_parking').checked = (data.has_parking == 1);
        document.getElementById('view_description').value = data.view_description;
        document.getElementById('unit_features').value = data.features;
        document.getElementById('unit_notes').value = data.notes;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('property_id')) {
        const unitsSection = document.getElementById('units-section');
        if (unitsSection) {
            unitsSection.scrollIntoView({ behavior: 'smooth' });
        }
    }
});
</script>