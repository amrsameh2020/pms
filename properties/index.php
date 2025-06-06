<?php
// properties_unified.php (أو properties/index.php إذا كنت تستبدل الملف الحالي)

// 1. SECTION: Core Includes, POST/GET Action Processing, and List Data Fetching
// ---------------------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php'; // Handles session start and includes functions.php
require_login();
// require_role('admin'); // Uncomment if only admins can manage properties
require_once __DIR__ . '/../includes/audit_log_functions.php';
// functions.php is already included by session_manager.php

$page_title = "إدارة العقارات (موحد مع بحث Select2)";
$current_file_url = base_url(basename(__FILE__));

// --- START: Action Processing (from property_actions.php logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) { // Ensure action is set for POST
    $_SESSION['old_property_form_data'] = $_POST;

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('خطأ في التحقق (CSRF).', 'danger');
        redirect($current_file_url);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $current_user_id = get_current_user_id();

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

    try {
        if (empty($property_code) || empty($property_name) || $owner_id === null || empty($property_address)) {
            throw new Exception('الحقول المطلوبة (كود العقار، اسم العقار، المالك، العنوان) يجب ملؤها.');
        }

        $mysqli->begin_transaction();

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
            set_message('تمت إضافة العقار بنجاح!', 'success');
            unset($_SESSION['old_property_form_data']);

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

            $sql_update = "UPDATE properties SET 
                        property_code = ?, name = ?, owner_id = ?, property_type_id = ?, address = ?, city = ?, 
                        number_of_units = ?, construction_year = ?, land_area_sqm = ?, latitude = ?, longitude = ?, notes = ? 
                    WHERE id = ?";
            $stmt_update = $mysqli->prepare($sql_update);
            if (!$stmt_update) throw new Exception('خطأ في تجهيز استعلام التعديل: ' . $mysqli->error);
            $stmt_update->bind_param("ssiissiidddsi", 
                $property_code, $property_name, $owner_id, $property_type_id, $property_address, $property_city, 
                $number_of_units, $construction_year, $land_area_sqm, $latitude, $longitude,
                $property_notes, $property_id
            );
            if (!$stmt_update->execute()) throw new Exception('خطأ في تحديث بيانات العقار: ' . $stmt_update->error);
            $stmt_update->close();
            
            $new_prop_data = compact('property_code', 'property_name', 'owner_id', 'property_type_id', 'property_address', 'property_city', 'number_of_units', 'construction_year', 'land_area_sqm', 'latitude', 'longitude', 'property_notes');
            log_audit_action($mysqli, AUDIT_EDIT_PROPERTY, $property_id, 'properties', ['old_data' => $old_prop_data, 'new_data' => $new_prop_data]);
            set_message('تم تحديث بيانات العقار بنجاح!', 'success');
            unset($_SESSION['old_property_form_data']);
        } else {
            throw new Exception("الإجراء المطلوب غير معروف أو معرف العقار مفقود لطلب POST.");
        }
        $mysqli->commit();

    } catch (Exception $e) {
        $mysqli->rollback();
        set_message("خطأ: " . $e->getMessage(), "danger");
        error_log("Property Unified Action Error (POST): " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }
    redirect($current_file_url); 
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_property') {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect($current_file_url);
        exit;
    }
    $property_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($property_id_to_delete > 0) {
        $mysqli->begin_transaction();
        try {
            // ... (نفس منطق الحذف من property_actions.php الأصلي) ...
            set_message('تم حذف العقار بنجاح (مثال)!', "success"); // استبدل بمنطق الحذف الفعلي
            $mysqli->commit();
        } catch (Exception $e) {
            $mysqli->rollback();
            set_message("خطأ عند الحذف: " . $e->getMessage(), "danger");
        }
    } else {
        set_message("معرف العقار غير صحيح للحذف.", "danger");
    }
    redirect($current_file_url);
    exit;
}
// --- END: Action Processing ---

// --- START: List Display Logic (from properties/index.php) ---
$current_page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset = ($current_page - 1) * $items_per_page;

$search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_owner_id_get = isset($_GET['owner_id']) && filter_var($_GET['owner_id'], FILTER_VALIDATE_INT) ? (int)$_GET['owner_id'] : ''; // متغير مختلف لـ GET
$filter_property_type_id_get = isset($_GET['property_type_id']) && filter_var($_GET['property_type_id'], FILTER_VALIDATE_INT) ? (int)$_GET['property_type_id'] : ''; // متغير مختلف لـ GET

$where_clauses = [];
$params_for_query = []; // مصفوفة واحدة للمعاملات
$types_for_query = "";  // سلسلة واحدة للأنواع

if (!empty($search_term)) {
    $where_clauses[] = "(p.name LIKE ? OR p.property_code LIKE ? OR p.address LIKE ? OR p.city LIKE ?)";
    $search_like = "%" . $search_term . "%";
    for ($i = 0; $i < 4; $i++) {
        $params_for_query[] = $search_like;
        $types_for_query .= "s";
    }
}
if (!empty($filter_owner_id_get)) {
    $where_clauses[] = "p.owner_id = ?";
    $params_for_query[] = $filter_owner_id_get;
    $types_for_query .= "i";
}
if (!empty($filter_property_type_id_get)) {
    $where_clauses[] = "p.property_type_id = ?";
    $params_for_query[] = $filter_property_type_id_get;
    $types_for_query .= "i";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

$total_sql = "SELECT COUNT(p.id) as total 
              FROM properties p 
              LEFT JOIN owners o ON p.owner_id = o.id
              LEFT JOIN property_types pt ON p.property_type_id = pt.id" . $where_sql;
$stmt_total = $mysqli->prepare($total_sql);
$total_properties = 0;
if($stmt_total){
    if (!empty($params_for_query) && $types_for_query !== '') { // استخدام params_for_query
        $stmt_total->bind_param($types_for_query, ...$params_for_query);
    }
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total_properties = ($total_result && $total_result->num_rows > 0) ? $total_result->fetch_assoc()['total'] : 0;
    $stmt_total->close();
} else { error_log("SQL Prepare Error for counting properties: " . $mysqli->error); }
$total_pages = ceil($total_properties / $items_per_page);

$sql_list = "SELECT p.id, p.property_code, p.name, p.owner_id, p.address, p.city, p.number_of_units, 
               p.construction_year, p.land_area_sqm, p.latitude, p.longitude, p.notes, p.created_by_id,
               p.property_type_id, pt.display_name_ar as property_type_name, 
               o.name as owner_name 
        FROM properties p 
        LEFT JOIN owners o ON p.owner_id = o.id
        LEFT JOIN property_types pt ON p.property_type_id = pt.id"
       . $where_sql . " ORDER BY p.name ASC LIMIT ? OFFSET ?";

$current_data_params_list = $params_for_query; // استخدام params_for_query
$current_data_params_list[] = $items_per_page;
$current_data_params_list[] = $offset;
$current_data_types_list = $types_for_query . 'ii';

$properties_list_data = []; // اسم متغير مختلف
$stmt_list = $mysqli->prepare($sql_list);
if ($stmt_list) {
    if (!empty($current_data_params_list) && $current_data_types_list !== 'ii') {
        $stmt_list->bind_param($current_data_types_list, ...$current_data_params_list);
    } else { 
        $stmt_list->bind_param('ii', $items_per_page, $offset);
    }
    $stmt_list->execute();
    $result_list = $stmt_list->get_result();
    $properties_list_data = ($result_list && $result_list->num_rows > 0) ? $result_list->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_list->close();
} else { error_log("SQL Prepare Error for fetching properties list: " . $mysqli->error); }

// جلب قائمة الملاك وأنواع العقارات للفلاتر والنافذة المنبثقة
$owners_list_for_modal_and_filter = [];
$owners_q = "SELECT id, name FROM owners ORDER BY name ASC";
if($owners_r = $mysqli->query($owners_q)){ while($row = $owners_r->fetch_assoc()){ $owners_list_for_modal_and_filter[] = $row;} $owners_r->free(); }

$property_types_list_for_modal_and_filter = [];
$ptypes_q = "SELECT id, display_name_ar FROM property_types ORDER BY display_name_ar ASC";
if($ptypes_r = $mysqli->query($ptypes_q)){ while($row = $ptypes_r->fetch_assoc()){ $property_types_list_for_modal_and_filter[] = $row;} $ptypes_r->free(); }

$csrf_token = generate_csrf_token();
// --- END: List Display Logic ---

require_once __DIR__ . '/../includes/header_resources.php';
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" />
<style>
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding-right: 2.5rem; /* Adjust for RTL and icon */
    }
    .select2-container .select2-selection--single .select2-selection__clear {
        margin-left: 0.5rem; /* RTL adjustment */
        margin-right: auto;
    }
</style>
<?php
require_once __DIR__ . '/../includes/navigation.php';
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-building"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة العقارات (<?php echo $total_properties; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#propertyModalUnified" onclick="preparePropertyModalUnified('add_property')">
                    <i class="bi bi-plus-circle"></i> إضافة عقار جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo $current_file_url; ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-4 col-lg-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="ابحث بالكود، الاسم، العنوان، المدينة..." value="<?php echo esc_attr($search_term); ?>">
                </div>
                <div class="col-md-3 col-lg-3">
                    <select name="owner_id" class="form-select form-select-sm">
                        <option value="">-- كل الملاك --</option>
                        <?php foreach ($owners_list_for_modal_and_filter as $owner_item): ?>
                            <option value="<?php echo $owner_item['id']; ?>" <?php echo ($filter_owner_id_get == $owner_item['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($owner_item['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <select name="property_type_id" class="form-select form-select-sm">
                        <option value="">-- كل الأنواع --</option>
                        <?php foreach ($property_types_list_for_modal_and_filter as $ptype_item): ?>
                            <option value="<?php echo $ptype_item['id']; ?>" <?php echo ($filter_property_type_id_get == $ptype_item['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($ptype_item['display_name_ar']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill"></i> فلترة</button>
                </div>
                 <div class="col-md-1 col-lg-2">
                     <a href="<?php echo $current_file_url; ?>" class="btn btn-outline-secondary btn-sm w-100" title="مسح الفلاتر"><i class="bi bi-eraser-fill"></i> مسح</a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($properties_list_data) && (!empty($search_term) || !empty($filter_owner_id_get) || !empty($filter_property_type_id_get))): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج تطابق معايير البحث أو الفلترة.</div>
            <?php elseif (empty($properties_list_data)): ?>
                <div class="alert alert-info text-center">لا توجد عقارات مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#propertyModalUnified" onclick="preparePropertyModalUnified('add_property')">إضافة عقار جديد</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>كود العقار</th><th>اسم العقار</th><th>المالك</th><th>النوع</th><th>العنوان</th><th>المدينة</th><th class="text-center">الوحدات</th><th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = ($current_page - 1) * $items_per_page + 1; ?>
                        <?php foreach ($properties_list_data as $property_item): ?>
                        <tr>
                            <td><?php echo $row_num++; ?></td>
                            <td><?php echo esc_html($property_item['property_code']); ?></td>
                            <td><?php echo esc_html($property_item['name']); ?></td>
                            <td><?php echo esc_html($property_item['owner_name'] ?: '-'); ?></td>
                            <td><?php echo esc_html($property_item['property_type_name'] ?: '-'); ?></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr($property_item['address']); ?>"><?php echo esc_html($property_item['address']); ?></td>
                            <td><?php echo esc_html($property_item['city'] ?: '-'); ?></td>
                            <td class="text-center"><?php echo esc_html($property_item['number_of_units']); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="preparePropertyModalUnified('edit_property', <?php echo htmlspecialchars(json_encode($property_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#propertyModalUnified"
                                        title="تعديل بيانات العقار">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger sweet-delete-btn"
                                        data-id="<?php echo $property_item['id']; ?>"
                                        data-name="<?php echo esc_attr($property_item['name'] . ' (الكود: ' . $property_item['property_code'] . ')'); ?>"
                                        data-delete-url="<?php echo $current_file_url . '?action=delete_property&id=' . $property_item['id'] . '&csrf_token=' . $csrf_token; ?>"
                                        data-additional-message="سيتم أيضًا حذف جميع الوحدات المرتبطة به (إذا لم تكن مرتبطة بعقود إيجار نشطة)."
                                        title="حذف العقار">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <a href="<?php echo base_url('units/index.php?property_id=' . $property_item['id']); ?>" class="btn btn-sm btn-outline-info" title="إدارة وحدات هذا العقار">
                                    <i class="bi bi-grid-3x3-gap-fill"></i> <span class="d-none d-md-inline">الوحدات</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params = [];
            if (!empty($search_term)) $pagination_params['search'] = $search_term;
            if (!empty($filter_owner_id_get)) $pagination_params['owner_id'] = $filter_owner_id_get;
            if (!empty($filter_property_type_id_get)) $pagination_params['property_type_id'] = $filter_property_type_id_get;
            echo generate_pagination_links($current_page, $total_pages, basename(__FILE__), $pagination_params);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="propertyModalUnified" tabindex="-1" aria-labelledby="propertyModalUnifiedLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="propertyFormUnified" method="POST" action="<?php echo $current_file_url; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="property_id" id="property_id_modal_unified" value="">
                <input type="hidden" name="action" id="property_form_action_unified" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="propertyModalUnifiedLabel">بيانات العقار</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="property_code_modal_unified" class="form-label">كود العقار <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="property_code_modal_unified" name="property_code" required 
                                   value="<?php echo isset($_SESSION['old_property_form_data']['property_code']) ? esc_attr($_SESSION['old_property_form_data']['property_code']) : ''; ?>">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="property_name_modal_unified" class="form-label">اسم العقار <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="property_name_modal_unified" name="property_name" required
                                   value="<?php echo isset($_SESSION['old_property_form_data']['property_name']) ? esc_attr($_SESSION['old_property_form_data']['property_name']) : ''; ?>">
                        </div>
                    </div>
                     <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="owner_id_modal_unified" class="form-label">المالك <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select2-basic" id="owner_id_modal_unified" name="owner_id" required style="width: 100%;">
                                <option value="">-- اختر المالك --</option>
                                <?php foreach ($owners_list_for_modal_and_filter as $owner_item): ?>
                                    <option value="<?php echo $owner_item['id']; ?>" <?php echo (isset($_SESSION['old_property_form_data']['owner_id']) && $_SESSION['old_property_form_data']['owner_id'] == $owner_item['id']) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($owner_item['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="property_type_id_modal_unified" class="form-label">نوع العقار</label>
                            <select class="form-select form-select-sm select2-basic" id="property_type_id_modal_unified" name="property_type_id" style="width: 100%;">
                                <option value="">-- اختر نوع العقار --</option>
                                 <?php foreach ($property_types_list_for_modal_and_filter as $ptype_item): ?>
                                    <option value="<?php echo $ptype_item['id']; ?>" <?php echo (isset($_SESSION['old_property_form_data']['property_type_id']) && $_SESSION['old_property_form_data']['property_type_id'] == $ptype_item['id']) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($ptype_item['display_name_ar']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="property_address_modal_unified" class="form-label">العنوان التفصيلي <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="property_address_modal_unified" name="property_address" rows="2" required><?php echo isset($_SESSION['old_property_form_data']['property_address']) ? esc_html($_SESSION['old_property_form_data']['property_address']) : ''; ?></textarea>
                    </div>
                     <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="property_city_modal_unified" class="form-label">المدينة</label>
                            <input type="text" class="form-control form-control-sm" id="property_city_modal_unified" name="property_city" value="<?php echo isset($_SESSION['old_property_form_data']['property_city']) ? esc_attr($_SESSION['old_property_form_data']['property_city']) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="number_of_units_modal_unified" class="form-label">عدد الوحدات <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="number_of_units_modal_unified" name="number_of_units" required min="0" value="<?php echo isset($_SESSION['old_property_form_data']['number_of_units']) ? esc_attr($_SESSION['old_property_form_data']['number_of_units']) : '0'; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="construction_year_modal_unified" class="form-label">سنة الإنشاء</label>
                            <input type="number" class="form-control form-control-sm" id="construction_year_modal_unified" name="construction_year" min="1800" max="<?php echo date('Y') + 10; ?>" value="<?php echo isset($_SESSION['old_property_form_data']['construction_year']) ? esc_attr($_SESSION['old_property_form_data']['construction_year']) : ''; ?>">
                        </div>
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="land_area_sqm_modal_unified" class="form-label">مساحة الأرض (م²)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="land_area_sqm_modal_unified" name="land_area_sqm" min="0" value="<?php echo isset($_SESSION['old_property_form_data']['land_area_sqm']) ? esc_attr($_SESSION['old_property_form_data']['land_area_sqm']) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="latitude_modal_unified" class="form-label">خط العرض (Latitude)</label>
                            <input type="text" class="form-control form-control-sm" id="latitude_modal_unified" name="latitude" value="<?php echo isset($_SESSION['old_property_form_data']['latitude']) ? esc_attr($_SESSION['old_property_form_data']['latitude']) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="longitude_modal_unified" class="form-label">خط الطول (Longitude)</label>
                            <input type="text" class="form-control form-control-sm" id="longitude_modal_unified" name="longitude" value="<?php echo isset($_SESSION['old_property_form_data']['longitude']) ? esc_attr($_SESSION['old_property_form_data']['longitude']) : ''; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="property_notes_modal_unified" class="form-label">ملاحظات إضافية</label>
                        <textarea class="form-control form-control-sm" id="property_notes_modal_unified" name="property_notes" rows="3"><?php echo isset($_SESSION['old_property_form_data']['property_notes']) ? esc_html($_SESSION['old_property_form_data']['property_notes']) : ''; ?></textarea>
                    </div>
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="propertySubmitButtonTextUnified">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
if (isset($_SESSION['old_property_form_data'])) {
    unset($_SESSION['old_property_form_data']);
}
?>

</div> <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
function preparePropertyModalUnified(action, propertyData = null) {
    const propertyModal = document.getElementById('propertyModalUnified');
    const modalTitle = propertyModal.querySelector('#propertyModalUnifiedLabel');
    const propertyForm = propertyModal.querySelector('#propertyFormUnified');
    const propertyIdInput = propertyModal.querySelector('#property_id_modal_unified');
    const actionInput = propertyModal.querySelector('#property_form_action_unified');
    const submitButtonText = propertyModal.querySelector('#propertySubmitButtonTextUnified');

    // Reset form and Select2 fields
    propertyForm.reset();
    $('#owner_id_modal_unified').val(null).trigger('change');
    $('#property_type_id_modal_unified').val(null).trigger('change');

    propertyIdInput.value = '';
    actionInput.value = action;
    
    if (action === 'add_property') {
        modalTitle.textContent = 'إضافة عقار جديد';
        submitButtonText.textContent = 'إضافة العقار';
        // Clear any session-prefilled data specifically for add action
        // (PHP already handles this for inputs, Select2 needs JS clear)
    } else if (action === 'edit_property' && propertyData) {
        if (typeof propertyData === 'string') {
            try { propertyData = JSON.parse(propertyData); } catch (e) { console.error("Error parsing propertyData JSON:", e); return; }
        }
        modalTitle.textContent = 'تعديل بيانات العقار: ' + (propertyData.name || '');
        submitButtonText.textContent = 'حفظ التعديلات';
        propertyIdInput.value = propertyData.id || '';
        
        propertyModal.querySelector('#property_code_modal_unified').value = propertyData.property_code || '';
        propertyModal.querySelector('#property_name_modal_unified').value = propertyData.name || '';
        $('#owner_id_modal_unified').val(propertyData.owner_id || '').trigger('change');
        $('#property_type_id_modal_unified').val(propertyData.property_type_id || '').trigger('change');
        propertyModal.querySelector('#property_address_modal_unified').value = propertyData.address || '';
        propertyModal.querySelector('#property_city_modal_unified').value = propertyData.city || '';
        propertyModal.querySelector('#number_of_units_modal_unified').value = propertyData.number_of_units || '0';
        propertyModal.querySelector('#construction_year_modal_unified').value = propertyData.construction_year || '';
        propertyModal.querySelector('#land_area_sqm_modal_unified').value = propertyData.land_area_sqm || '';
        propertyModal.querySelector('#latitude_modal_unified').value = propertyData.latitude || '';
        propertyModal.querySelector('#longitude_modal_unified').value = propertyData.longitude || '';
        propertyModal.querySelector('#property_notes_modal_unified').value = propertyData.notes || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Initialize Select2 for modal dropdowns
    $('#owner_id_modal_unified').select2({
        theme: "bootstrap-5",
        dropdownParent: $('#propertyModalUnified'),
        placeholder: "-- اختر المالك --",
        allowClear: true,
        language: "ar" // Requires Arabic language file for Select2 if you want full RTL support in dropdown
    });
    $('#property_type_id_modal_unified').select2({
        theme: "bootstrap-5",
        dropdownParent: $('#propertyModalUnified'),
        placeholder: "-- اختر نوع العقار --",
        allowClear: true,
        language: "ar"
    });

    // The form submission is now traditional (non-AJAX) as per your request
    // So, no fetch() JavaScript is needed here for propertyFormUnified.
    // PHP at the top of this file handles the POST.
});
</script>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>