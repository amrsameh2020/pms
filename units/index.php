<?php
$page_title = "إدارة وحدات العقار"; 
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';

$property_id_for_page = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;

if ($property_id_for_page <= 0) {
    set_message("معرف العقار غير صحيح أو مفقود لعرض الوحدات.", "danger");
    redirect(base_url('properties/index.php'));
}

$stmt_property = $mysqli->prepare("SELECT id, name, property_code FROM properties WHERE id = ?");
$property_data_for_page = null; 
if ($stmt_property) {
    $stmt_property->bind_param("i", $property_id_for_page);
    $stmt_property->execute();
    $result_property = $stmt_property->get_result();
    if ($result_property->num_rows > 0) {
        $property_data_for_page = $result_property->fetch_assoc();
        $page_title = "وحدات العقار: " . esc_html($property_data_for_page['name']) . " (" . esc_html($property_data_for_page['property_code']) . ")";
    } else {
        set_message("العقار المحدد غير موجود.", "warning");
        redirect(base_url('properties/index.php'));
    }
    $stmt_property->close();
} else {
    error_log("Units Index: Failed to prepare property statement: " . $mysqli->error);
    set_message("خطأ في جلب بيانات العقار.", "danger");
    redirect(base_url('properties/index.php'));
}

require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

$current_page_unit = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_unit = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_unit = ($current_page_unit - 1) * $items_per_page_unit;

$filter_unit_status_page = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$filter_unit_type_id_page = isset($_GET['unit_type_id']) && filter_var($_GET['unit_type_id'], FILTER_VALIDATE_INT) ? (int)$_GET['unit_type_id'] : '';

$unit_statuses_for_page_filter_options = [
    '' => '-- الكل --', 
    'Vacant' => 'شاغرة', 'Occupied' => 'مشغولة', 'Under Maintenance' => 'تحت الصيانة', 'Reserved' => 'محجوزة'
];
$unit_statuses_display_for_table = [
    'Vacant' => 'شاغرة', 'Occupied' => 'مشغولة', 'Under Maintenance' => 'تحت الصيانة', 'Reserved' => 'محجوزة'
];

$unit_types_filter_list_page = [];
$utypes_query_filter_page = "SELECT id, display_name_ar FROM unit_types ORDER BY display_name_ar ASC";
if($utypes_result_filter_page = $mysqli->query($utypes_query_filter_page)){
    while($utype_row_filter_page = $utypes_result_filter_page->fetch_assoc()){
        $unit_types_filter_list_page[] = $utype_row_filter_page;
    }
    $utypes_result_filter_page->free();
}

$where_clauses_unit_page = ["u.property_id = ?"];
$params_for_count_unit_page = [$property_id_for_page]; $types_for_count_unit_page = "i";
$params_for_data_unit_page = [$property_id_for_page];  $types_for_data_unit_page = "i";

if (!empty($filter_unit_status_page)) {
    $where_clauses_unit_page[] = "u.status = ?";
    $params_for_count_unit_page[] = $filter_unit_status_page; $types_for_count_unit_page .= "s";
    $params_for_data_unit_page[] = $filter_unit_status_page;  $types_for_data_unit_page .= "s";
}
if (!empty($filter_unit_type_id_page)) {
    $where_clauses_unit_page[] = "u.unit_type_id = ?";
    $params_for_count_unit_page[] = $filter_unit_type_id_page; $types_for_count_unit_page .= "i";
    $params_for_data_unit_page[] = $filter_unit_type_id_page;  $types_for_data_unit_page .= "i";
}

$where_sql_unit_page = " WHERE " . implode(" AND ", $where_clauses_unit_page);

$total_sql_unit = "SELECT COUNT(u.id) as total FROM units u" . $where_sql_unit_page;
$stmt_total_unit = $mysqli->prepare($total_sql_unit);
$total_units_for_property_page = 0; 
if ($stmt_total_unit) {
    if (count($params_for_count_unit_page) > 0 && !empty($types_for_count_unit_page)) {
        $stmt_total_unit->bind_param($types_for_count_unit_page, ...$params_for_count_unit_page);
    }
    $stmt_total_unit->execute();
    $total_result_unit = $stmt_total_unit->get_result();
    $total_units_for_property_page = ($total_result_unit && $total_result_unit->num_rows > 0) ? $total_result_unit->fetch_assoc()['total'] : 0;
    $stmt_total_unit->close();
} else {
    error_log("SQL Prepare Error counting units for property (index page): " . $mysqli->error);
}
$total_pages_unit = ceil($total_units_for_property_page / $items_per_page_unit);

$sql_units_page = "SELECT u.id, u.unit_number, u.floor_number, u.size_sqm, u.bedrooms, u.bathrooms, u.status, 
                          u.base_rent_price, u.features, u.notes, u.unit_type_id, 
                          ut.display_name_ar as unit_type_name 
                   FROM units u
                   LEFT JOIN unit_types ut ON u.unit_type_id = ut.id"
                 . $where_sql_unit_page . " ORDER BY u.unit_number ASC LIMIT ? OFFSET ?";

$current_data_params_unit_page = $params_for_data_unit_page;
$current_data_params_unit_page[] = $items_per_page_unit;
$current_data_params_unit_page[] = $offset_unit;
$current_data_types_unit_page = $types_for_data_unit_page . 'ii';

$units_list_on_page = [];
$stmt_units_page = $mysqli->prepare($sql_units_page);
if ($stmt_units_page) {
    if (count($current_data_params_unit_page) > 0 && !empty($current_data_types_unit_page) && $current_data_types_unit_page !== 'ii') { // Check if types is not just for limit/offset
       $stmt_units_page->bind_param($current_data_types_unit_page, ...$current_data_params_unit_page);
    } else { // Only limit and offset if no other params
        $stmt_units_page->bind_param('ii', $items_per_page_unit, $offset_unit);
    }
    $stmt_units_page->execute();
    $result_units_page = $stmt_units_page->get_result();
    $units_list_on_page = ($result_units_page && $result_units_page->num_rows > 0) ? $result_units_page->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_units_page->close();
} else {
    error_log("SQL Prepare Error fetching units for property (index page): " . $mysqli->error);
}

$csrf_token = generate_csrf_token(); 
?>

<div class="container-fluid">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1><i class="bi bi-grid-3x3-gap-fill"></i> <?php echo esc_html($page_title); ?></h1>
            <a href="<?php echo base_url('properties/index.php'); ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left-circle"></i> العودة لقائمة العقارات</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
             <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الوحدات (<?php echo $total_units_for_property_page; ?> وحدة)</h5>
                <button type="button" class="btn btn-success btn-sm" 
                        data-bs-toggle="modal" data-bs-target="#unitModal" 
                        onclick="prepareUnitModal('add_unit', null, '<?php echo $property_id_for_page; ?>', '<?php echo esc_js($property_data_for_page['name'] . ' (' . $property_data_for_page['property_code'] . ')'); ?>')">
                    <i class="bi bi-plus-circle"></i> إضافة وحدة جديدة
                </button>
            </div>
            <hr class="my-2">
             <form method="GET" action="<?php echo base_url('units/index.php'); ?>" class="row gx-2 gy-2 align-items-end">
                <input type="hidden" name="property_id" value="<?php echo $property_id_for_page; ?>">
                <div class="col-md-4">
                    <label for="filter_unit_type_id_page_filter" class="form-label form-label-sm">فلترة بنوع الوحدة</label>
                    <select id="filter_unit_type_id_page_filter" name="unit_type_id" class="form-select form-select-sm">
                        <option value="">-- الكل --</option>
                        <?php foreach($unit_types_filter_list_page as $utype_filter): ?>
                            <option value="<?php echo $utype_filter['id']; ?>" <?php echo ($filter_unit_type_id_page == $utype_filter['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($utype_filter['display_name_ar']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filter_unit_status_page_filter" class="form-label form-label-sm">الحالة</label>
                    <select id="filter_unit_status_page_filter" name="status" class="form-select form-select-sm">
                        <?php foreach ($unit_statuses_for_page_filter_options as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filter_unit_status_page === (string)$key && $filter_unit_status_page !== '') ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill"></i> فلترة</button>
                </div>
                <div class="col-md-2">
                     <a href="<?php echo base_url('units/index.php?property_id=' . $property_id_for_page); ?>" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-eraser-fill"></i> مسح</a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($units_list_on_page) && (!empty($filter_unit_status_page) || !empty($filter_unit_type_id_page))): ?>
                <div class="alert alert-warning text-center">لا توجد وحدات تطابق معايير الفلترة.</div>
            <?php elseif (empty($units_list_on_page)): ?>
                 <div class="alert alert-info text-center">لا توجد وحدات مضافة لهذا العقار بعد. يمكنك 
                    <a href="#" class="add-unit-btn" 
                       data-bs-toggle="modal" data-bs-target="#unitModal" 
                       onclick="prepareUnitModal('add_unit', null, '<?php echo $property_id_for_page; ?>', '<?php echo esc_js($property_data_for_page['name'] . ' (' . $property_data_for_page['property_code'] . ')'); ?>')">إضافة وحدة جديدة</a>.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>رقم/اسم الوحدة</th>
                            <th>النوع</th>
                            <th>الطابق</th>
                            <th>المساحة (م²)</th>
                            <th>غرف نوم</th>
                            <th>دورات مياه</th>
                            <th>الإيجار المقترح</th>
                            <th>الحالة</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_unit_page = ($current_page_unit - 1) * $items_per_page_unit + 1; ?>
                        <?php foreach ($units_list_on_page as $unit_item_page): ?>
                        <tr>
                            <td><?php echo $row_num_unit_page++; ?></td>
                            <td><?php echo esc_html($unit_item_page['unit_number']); ?></td>
                            <td><?php echo esc_html($unit_item_page['unit_type_name'] ?: '-'); ?></td>
                            <td><?php echo ($unit_item_page['floor_number'] !== null) ? esc_html($unit_item_page['floor_number']) : '-'; ?></td>
                            <td><?php echo ($unit_item_page['size_sqm'] !== null) ? number_format($unit_item_page['size_sqm'], 2) : '-'; ?></td>
                            <td><?php echo ($unit_item_page['bedrooms'] !== null) ? esc_html($unit_item_page['bedrooms']) : '-'; ?></td>
                            <td><?php echo ($unit_item_page['bathrooms'] !== null) ? esc_html($unit_item_page['bathrooms']) : '-'; ?></td>
                            <td><?php echo ($unit_item_page['base_rent_price'] !== null) ? number_format($unit_item_page['base_rent_price'], 2) . ' ريال' : '-'; ?></td>
                            <td>
                                 <span class="badge bg-<?php echo ($unit_item_page['status'] === 'Vacant') ? 'success' : (($unit_item_page['status'] === 'Occupied') ? 'danger' : 'warning'); ?>">
                                    <?php echo esc_html($unit_statuses_display_for_table[$unit_item_page['status']] ?? $unit_item_page['status']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="prepareUnitModal('edit_unit', <?php echo htmlspecialchars(json_encode($unit_item_page), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo $property_id_for_page; ?>', '<?php echo esc_js($property_data_for_page['name'] . ' (' . $property_data_for_page['property_code'] . ')'); ?>')"
                                        data-bs-toggle="modal" data-bs-target="#unitModal"
                                        title="تعديل الوحدة">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger sweet-delete-btn delete-unit-btn"
                                        data-id="<?php echo $unit_item_page['id']; ?>"
                                        data-name="الوحدة <?php echo esc_attr($unit_item_page['unit_number']); ?> في العقار <?php echo esc_attr($property_data_for_page['name']); ?>"
                                        data-delete-url="<?php echo base_url('units/unit_actions.php?action=delete_unit&id=' . $unit_item_page['id'] . '&property_id=' . $property_id_for_page . '&csrf_token=' . $csrf_token); ?>"
                                        data-additional-message="ملاحظة: حذف الوحدة لا يمكن التراجع عنه وقد يؤثر على عقود الإيجار المرتبطة."
                                        title="حذف الوحدة">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <a href="<?php echo base_url('leases/index.php?unit_id=' . $unit_item_page['id']); ?>" class="btn btn-sm btn-outline-info" title="عرض عقود الإيجار لهذه الوحدة">
                                    <i class="bi bi-file-earmark-text"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages_unit > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_unit_page = ['property_id' => $property_id_for_page];
            if (!empty($filter_unit_status_page)) $pagination_params_unit_page['status'] = $filter_unit_status_page;
            if (!empty($filter_unit_type_id_page)) $pagination_params_unit_page['unit_type_id'] = $filter_unit_type_id_page;
            echo generate_pagination_links($current_page_unit, $total_pages_unit, 'units/index.php', $pagination_params_unit_page);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/unit_modal.php';
// confirm_delete_modal.php is no longer required here
?>

</div> 
<script>
function prepareUnitModal(action, unitData = null, propertyId, propertyName) {
    const unitModal = document.getElementById('unitModal');
    const modalTitle = unitModal.querySelector('#unitModalLabel_page');
    const unitForm = unitModal.querySelector('#unitFormModal');
    const unitIdInput = unitModal.querySelector('#unit_id_modal_units_page');
    const propertyIdInputModal = unitModal.querySelector('#property_id_for_unit_modal_page');
    const propertyNameDisplayModal = unitModal.querySelector('#property_name_for_unit_modal_display_page');
    const actionInput = unitModal.querySelector('#unit_form_action_modal_page');
    const submitButtonText = unitModal.querySelector('#unitSubmitButtonTextModalPage');
    // const unitStatusWarning = document.getElementById('unit_status_warning_modal'); // This ID is not in unit_modal.php

    unitForm.reset();
    unitIdInput.value = '';
    actionInput.value = action;
    
    if(propertyIdInputModal) propertyIdInputModal.value = propertyId;
    if(propertyNameDisplayModal) propertyNameDisplayModal.textContent = propertyName || 'العقار غير محدد';
    // if(unitStatusWarning) unitStatusWarning.classList.add('d-none');


    // unitForm.action = '<?php echo base_url('units/unit_actions.php'); ?>'; // Not strictly needed for fetch

    if (action === 'add_unit') {
        modalTitle.textContent = 'إضافة وحدة جديدة إلى: ' + propertyName;
        submitButtonText.textContent = 'إضافة الوحدة';
    } else if (action === 'edit_unit' && unitData) {
        modalTitle.textContent = 'تعديل بيانات الوحدة في: ' + propertyName;
        submitButtonText.textContent = 'حفظ التعديلات';
        unitIdInput.value = unitData.id;
        
        if(document.getElementById('unit_number_modal_page')) document.getElementById('unit_number_modal_page').value = unitData.unit_number || '';
        if(document.getElementById('unit_type_id_modal_page')) document.getElementById('unit_type_id_modal_page').value = unitData.unit_type_id || '';
        if(document.getElementById('unit_status_modal_page')) document.getElementById('unit_status_modal_page').value = unitData.status || 'Vacant';
        if(document.getElementById('floor_number_modal_page')) document.getElementById('floor_number_modal_page').value = unitData.floor_number === null ? '' : unitData.floor_number;
        if(document.getElementById('size_sqm_modal_page')) document.getElementById('size_sqm_modal_page').value = unitData.size_sqm === null ? '' : unitData.size_sqm;
        if(document.getElementById('bedrooms_modal_page')) document.getElementById('bedrooms_modal_page').value = unitData.bedrooms === null ? '' : unitData.bedrooms;
        if(document.getElementById('bathrooms_modal_page')) document.getElementById('bathrooms_modal_page').value = unitData.bathrooms === null ? '' : unitData.bathrooms;
        if(document.getElementById('base_rent_price_modal_page')) document.getElementById('base_rent_price_modal_page').value = unitData.base_rent_price === null ? '' : unitData.base_rent_price;
        if(document.getElementById('unit_features_modal_page')) document.getElementById('unit_features_modal_page').value = unitData.features || '';
        if(document.getElementById('unit_notes_modal_page')) document.getElementById('unit_notes_modal_page').value = unitData.notes || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Old confirmDeleteModalUnitPage JavaScript block removed.
    
    const unitFormElement = document.getElementById('unitFormModal');
    if(unitFormElement) {
        unitFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(unitFormElement);
            const actionUrl = '<?php echo base_url('units/unit_actions.php'); ?>'; 

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('unitModal'));
                if (data.success) {
                    if(modalInstance) modalInstance.hide();
                    Swal.fire({
                        title: 'نجاح!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'حسنًا'
                    }).then(() => {
                        window.location.reload(); 
                    });
                } else {
                    Swal.fire({
                        title: 'خطأ!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonText: 'حسنًا'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                 Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ غير متوقع.',
                    icon: 'error',
                    confirmButtonText: 'حسنًا'
                });
            });
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>