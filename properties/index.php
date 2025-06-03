<?php
$page_title = "إدارة العقارات";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// متغيرات التصفح
$current_page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset = ($current_page - 1) * $items_per_page;

// وظيفة البحث والفلترة
$search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_owner_id = isset($_GET['owner_id']) && filter_var($_GET['owner_id'], FILTER_VALIDATE_INT) ? (int)$_GET['owner_id'] : '';
$filter_property_type_id = isset($_GET['property_type_id']) && filter_var($_GET['property_type_id'], FILTER_VALIDATE_INT) ? (int)$_GET['property_type_id'] : '';


$where_clauses = [];
$params_for_count = [];
$params_for_data = [];
$types_for_count = "";
$types_for_data = "";

if (!empty($search_term)) {
    $where_clauses[] = "(p.name LIKE ? OR p.property_code LIKE ? OR p.address LIKE ? OR p.city LIKE ?)";
    $search_like = "%" . $search_term . "%";
    for ($i = 0; $i < 4; $i++) {
        $params_for_count[] = $search_like;
        $params_for_data[] = $search_like;
        $types_for_count .= "s";
        $types_for_data .= "s";
    }
}

if (!empty($filter_owner_id)) {
    $where_clauses[] = "p.owner_id = ?";
    $params_for_count[] = $filter_owner_id;
    $params_for_data[] = $filter_owner_id;
    $types_for_count .= "i";
    $types_for_data .= "i";
}

if (!empty($filter_property_type_id)) {
    $where_clauses[] = "p.property_type_id = ?";
    $params_for_count[] = $filter_property_type_id;
    $params_for_data[] = $filter_property_type_id;
    $types_for_count .= "i";
    $types_for_data .= "i";
}


$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// الحصول على العدد الإجمالي للعقارات
$total_sql = "SELECT COUNT(p.id) as total 
              FROM properties p 
              LEFT JOIN owners o ON p.owner_id = o.id
              LEFT JOIN property_types pt ON p.property_type_id = pt.id" . $where_sql;
$stmt_total = $mysqli->prepare($total_sql);
if($stmt_total){
    if (!empty($params_for_count)) {
        $stmt_total->bind_param($types_for_count, ...$params_for_count);
    }
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total_properties = ($total_result && $total_result->num_rows > 0) ? $total_result->fetch_assoc()['total'] : 0;
    $stmt_total->close();
} else {
    $total_properties = 0;
    error_log("SQL Prepare Error for counting properties: " . $mysqli->error);
}
$total_pages = ceil($total_properties / $items_per_page);

// جلب العقارات للصفحة الحالية
// تم تحديث الاستعلام ليشمل property_type_id, latitude, longitude, notes, property_type_name
$sql = "SELECT p.id, p.property_code, p.name, p.owner_id, p.address, p.city, p.number_of_units, 
               p.construction_year, p.land_area_sqm, p.latitude, p.longitude, p.notes, p.created_by_id,
               p.property_type_id, pt.display_name_ar as property_type_name, 
               o.name as owner_name 
        FROM properties p 
        LEFT JOIN owners o ON p.owner_id = o.id
        LEFT JOIN property_types pt ON p.property_type_id = pt.id"
       . $where_sql . " ORDER BY p.name ASC LIMIT ? OFFSET ?";

$current_data_params = $params_for_data;
$current_data_params[] = $items_per_page;
$current_data_params[] = $offset;
$current_data_types = $types_for_data . 'ii';

$properties = [];
$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if (!empty($current_data_params)) {
        $stmt->bind_param($current_data_types, ...$current_data_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $properties = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    error_log("SQL Prepare Error for fetching properties: " . $mysqli->error);
}


// جلب قائمة الملاك للفلتر
$owners_filter_list = [];
$owners_query_filter_page = "SELECT id, name FROM owners ORDER BY name ASC";
$owners_result_filter_page = $mysqli->query($owners_query_filter_page);
if ($owners_result_filter_page) {
    while ($owner_row_filter_page = $owners_result_filter_page->fetch_assoc()) {
        $owners_filter_list[] = $owner_row_filter_page;
    }
    $owners_result_filter_page->free();
}

// جلب قائمة أنواع العقارات للفلتر
$property_types_filter_list = [];
$ptypes_query_filter_page = "SELECT id, display_name_ar FROM property_types ORDER BY display_name_ar ASC";
$ptypes_result_filter_page = $mysqli->query($ptypes_query_filter_page);
if ($ptypes_result_filter_page) {
    while ($ptype_row_filter_page = $ptypes_result_filter_page->fetch_assoc()) {
        $property_types_filter_list[] = $ptype_row_filter_page;
    }
    $ptypes_result_filter_page->free();
}

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-building"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة العقارات (<?php echo $total_properties; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#propertyModal" onclick="preparePropertyModal('add_property')">
                    <i class="bi bi-plus-circle"></i> إضافة عقار جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('properties/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-4 col-lg-3">
                    <label for="search_properties_page" class="form-label form-label-sm visually-hidden">بحث</label>
                    <input type="text" id="search_properties_page" name="search" class="form-control form-control-sm" placeholder="ابحث بالكود، الاسم، العنوان، المدينة..." value="<?php echo esc_attr($search_term); ?>">
                </div>
                <div class="col-md-3 col-lg-3">
                     <label for="filter_owner_id_page" class="form-label form-label-sm visually-hidden">المالك</label>
                    <select id="filter_owner_id_page" name="owner_id" class="form-select form-select-sm">
                        <option value="">-- فلترة حسب المالك --</option>
                        <?php foreach ($owners_filter_list as $owner_item_filter_p): ?>
                            <option value="<?php echo $owner_item_filter_p['id']; ?>" <?php echo ($filter_owner_id == $owner_item_filter_p['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($owner_item_filter_p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filter_property_type_id_page" class="form-label form-label-sm visually-hidden">نوع العقار</label>
                    <select id="filter_property_type_id_page" name="property_type_id" class="form-select form-select-sm">
                        <option value="">-- فلترة حسب النوع --</option>
                        <?php foreach ($property_types_filter_list as $ptype_item_filter_p): ?>
                            <option value="<?php echo $ptype_item_filter_p['id']; ?>" <?php echo ($filter_property_type_id == $ptype_item_filter_p['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($ptype_item_filter_p['display_name_ar']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill"></i> فلترة</button>
                </div>
                <div class="col-md-1 col-lg-2">
                     <a href="<?php echo base_url('properties/index.php'); ?>" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-eraser-fill"></i> مسح</a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($properties) && (!empty($search_term) || !empty($filter_owner_id) || !empty($filter_property_type_id))): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج تطابق معايير البحث أو الفلترة.</div>
            <?php elseif (empty($properties)): ?>
                <div class="alert alert-info text-center">لا توجد عقارات مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#propertyModal" onclick="preparePropertyModal('add_property')">إضافة عقار جديد</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>كود العقار</th>
                            <th>اسم العقار</th>
                            <th>المالك</th>
                            <th>النوع</th>
                            <th>العنوان</th>
                            <th>المدينة</th>
                            <th class="text-center">الوحدات</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = ($current_page - 1) * $items_per_page + 1; ?>
                        <?php foreach ($properties as $property): ?>
                        <tr>
                            <td><?php echo $row_num++; ?></td>
                            <td><?php echo esc_html($property['property_code']); ?></td>
                            <td><?php echo esc_html($property['name']); ?></td>
                            <td><?php echo esc_html($property['owner_name'] ?: '-'); ?></td>
                            <td><?php echo esc_html($property['property_type_name'] ?: '-'); // عرض اسم نوع العقار ?></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr($property['address']); ?>">
                                <?php echo esc_html($property['address']); ?>
                            </td>
                            <td><?php echo esc_html($property['city'] ?: '-'); ?></td>
                            <td class="text-center"><?php echo esc_html($property['number_of_units']); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="preparePropertyModal('edit_property', <?php echo htmlspecialchars(json_encode($property), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#propertyModal"
                                        title="تعديل بيانات العقار">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-property-btn"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                        data-id="<?php echo $property['id']; ?>"
                                        data-name="<?php echo esc_attr($property['name'] . ' (الكود: ' . $property['property_code'] . ')'); ?>"
                                        data-delete-url="<?php echo base_url('properties/property_actions.php?action=delete_property&id=' . $property['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        title="حذف العقار">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <a href="<?php echo base_url('units/index.php?property_id=' . $property['id']); ?>" class="btn btn-sm btn-outline-info" title="إدارة وحدات هذا العقار">
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
            if (!empty($filter_owner_id)) $pagination_params['owner_id'] = $filter_owner_id;
            if (!empty($filter_property_type_id)) $pagination_params['property_type_id'] = $filter_property_type_id;
            echo generate_pagination_links($current_page, $total_pages, 'properties/index.php', $pagination_params);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/property_modal.php';
require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <script>
function preparePropertyModal(action, propertyData = null) {
    const propertyModal = document.getElementById('propertyModal'); // معرف النافذة المنبثقة
    const modalTitle = propertyModal.querySelector('.modal-title');
    const propertyForm = propertyModal.querySelector('#propertyFormModal'); // معرف النموذج
    const propertyIdInput = propertyModal.querySelector('#property_id_modal_properties');
    const actionInput = propertyModal.querySelector('#property_form_action_modal');
    const submitButton = propertyModal.querySelector('#propertySubmitButtonTextModal'); // معرف زر الإرسال

    propertyForm.reset();
    propertyIdInput.value = '';
    actionInput.value = action;

    const formUrl = '<?php echo base_url('properties/property_actions.php'); ?>';
    propertyForm.action = formUrl; // ما زال جيداً للاحتفاظ به كمرجع

    if (action === 'add_property') {
        modalTitle.textContent = 'إضافة عقار جديد';
        submitButton.textContent = 'إضافة العقار';
    } else if (action === 'edit_property' && propertyData) {
        modalTitle.textContent = 'تعديل بيانات العقار: ' + propertyData.name;
        submitButton.textContent = 'حفظ التعديلات';
        propertyIdInput.value = propertyData.id;
        
        // ملء حقول النموذج ببيانات العقار للتعديل
        // استخدم المعرفات الفريدة لحقول النافذة المنبثقة
        propertyModal.querySelector('#property_code_modal_prop').value = propertyData.property_code || '';
        propertyModal.querySelector('#property_name_modal_prop').value = propertyData.name || '';
        propertyModal.querySelector('#owner_id_modal_prop').value = propertyData.owner_id || '';
        propertyModal.querySelector('#property_type_id_modal_prop').value = propertyData.property_type_id || ''; // الحقل الجديد
        propertyModal.querySelector('#property_address_modal_prop').value = propertyData.address || '';
        propertyModal.querySelector('#property_city_modal_prop').value = propertyData.city || '';
        propertyModal.querySelector('#number_of_units_modal_prop').value = propertyData.number_of_units || '0';
        propertyModal.querySelector('#construction_year_modal_prop').value = propertyData.construction_year || '';
        propertyModal.querySelector('#land_area_sqm_modal_prop').value = propertyData.land_area_sqm || '';
        propertyModal.querySelector('#latitude_modal_prop').value = propertyData.latitude || ''; // الحقل الجديد
        propertyModal.querySelector('#longitude_modal_prop').value = propertyData.longitude || ''; // الحقل الجديد
        propertyModal.querySelector('#property_notes_modal_prop').value = propertyData.notes || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // التعامل مع نافذة تأكيد الحذف (عامة)
    var confirmDeleteModalProp = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModalProp) {
        confirmDeleteModalProp.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button.classList.contains('delete-property-btn')) { // تحقق إذا كان زر حذف عقار
                var itemName = button.getAttribute('data-name');
                var deleteUrl = button.getAttribute('data-delete-url');

                var modalBodyText = confirmDeleteModalProp.querySelector('.modal-body-text');
                if (modalBodyText) {
                     modalBodyText.textContent = 'هل أنت متأكد أنك تريد حذف العقار "' + itemName + '"؟';
                }
                var additionalInfo = confirmDeleteModalProp.querySelector('#additionalDeleteInfo');
                if(additionalInfo) additionalInfo.textContent = 'ملاحظة: سيتم أيضًا حذف جميع الوحدات المرتبطة به (إذا لم تكن مرتبطة بعقود إيجار نشطة). قد تحتاج أيضًا إلى التحقق من العقود المرتبطة بوحدات هذا العقار.';

                var confirmDeleteButton = confirmDeleteModalProp.querySelector('#confirmDeleteButton');
                if (confirmDeleteButton) {
                    var newConfirmDeleteButton = confirmDeleteButton.cloneNode(true);
                    confirmDeleteButton.parentNode.replaceChild(newConfirmDeleteButton, confirmDeleteButton);
                    
                    newConfirmDeleteButton.setAttribute('data-delete-url', deleteUrl);
                    newConfirmDeleteButton.removeAttribute('href');
                    
                    newConfirmDeleteButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        const urlToDelete = this.getAttribute('data-delete-url');
                        if(urlToDelete){
                           window.location.href = urlToDelete;
                        }
                    });
                }
            }
        });
    }
    
    // Handle AJAX form submission for propertyFormModal
    const propertyFormElement = document.getElementById('propertyFormModal');
    if(propertyFormElement) {
        propertyFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(propertyFormElement);
            const actionUrl = propertyFormElement.getAttribute('action');

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var propertyModalInstance = bootstrap.Modal.getInstance(document.getElementById('propertyModal'));
                    if(propertyModalInstance) propertyModalInstance.hide();
                    window.location.reload(); // Or update table dynamically
                } else {
                    alert('خطأ: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ غير متوقع.');
            });
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>