<?php
$page_title = "إدارة أنواع الوحدات";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); // يمكنك إضافة هذا إذا كانت هذه الصفحة للمسؤول فقط
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// متغيرات التصفح
$current_page_utype = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_utype = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_utype = ($current_page_utype - 1) * $items_per_page_utype;

// وظيفة البحث (بسيطة للاسم المعروض أو المعرف)
$search_term_utype = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query_part_utype = '';
$params_for_count_utype = [];
$types_for_count_utype = "";
$params_for_data_utype = [];
$types_for_data_utype = "";

if (!empty($search_term_utype)) {
    $search_query_part_utype = " WHERE type_name LIKE ? OR display_name_ar LIKE ?";
    $search_like_utype = "%" . $search_term_utype . "%";
    $params_for_count_utype = [$search_like_utype, $search_like_utype];
    $types_for_count_utype = "ss";
    $params_for_data_utype = $params_for_count_utype;
    $types_for_data_utype = $types_for_count_utype;
}

// الحصول على العدد الإجمالي لأنواع الوحدات
$total_sql_utype = "SELECT COUNT(*) as total FROM unit_types" . $search_query_part_utype;
$stmt_total_utype = $mysqli->prepare($total_sql_utype);
$total_unit_types = 0;
if ($stmt_total_utype) {
    if (!empty($params_for_count_utype)) {
        $stmt_total_utype->bind_param($types_for_count_utype, ...$params_for_count_utype);
    }
    $stmt_total_utype->execute();
    $total_result_utype = $stmt_total_utype->get_result();
    $total_unit_types = ($total_result_utype && $total_result_utype->num_rows > 0) ? $total_result_utype->fetch_assoc()['total'] : 0;
    $stmt_total_utype->close();
} else {
    error_log("SQL Prepare Error for counting unit types: " . $mysqli->error);
}
$total_pages_utype = ceil($total_unit_types / $items_per_page_utype);

// جلب أنواع الوحدات للصفحة الحالية
$sql_utype = "SELECT * FROM unit_types" . $search_query_part_utype . " ORDER BY display_name_ar ASC LIMIT ? OFFSET ?";
$current_data_params_utype = $params_for_data_utype;
$current_data_params_utype[] = $items_per_page_utype;
$current_data_params_utype[] = $offset_utype;
$current_data_types_utype = $types_for_data_utype . 'ii';

$unit_types_list_page = [];
$stmt_utype = $mysqli->prepare($sql_utype);
if ($stmt_utype) {
    if (!empty($current_data_params_utype)) {
        $stmt_utype->bind_param($current_data_types_utype, ...$current_data_params_utype);
    } else {
        $stmt_utype->bind_param('ii', $items_per_page_utype, $offset_utype);
    }
    $stmt_utype->execute();
    $result_utype = $stmt_utype->get_result();
    $unit_types_list_page = ($result_utype && $result_utype->num_rows > 0) ? $result_utype->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_utype->close();
} else {
    error_log("SQL Prepare Error for fetching unit types: " . $mysqli->error);
}

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-bookmark-star-fill"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة أنواع الوحدات (<?php echo $total_unit_types; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#unitTypeModal" onclick="prepareUnitTypeModal('add_unit_type')">
                    <i class="bi bi-plus-circle"></i> إضافة نوع جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('unit_types/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-10 col-sm-8">
                     <label for="search_unit_types_page" class="form-label form-label-sm visually-hidden">بحث</label>
                    <input type="text" id="search_unit_types_page" name="search" class="form-control form-control-sm" placeholder="ابحث بالمعرف أو الاسم المعروض..." value="<?php echo esc_attr($search_term_utype); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> بحث</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($unit_types_list_page) && !empty($search_term_utype)): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج بحث تطابق "<?php echo esc_html($search_term_utype); ?>".</div>
            <?php elseif (empty($unit_types_list_page)): ?>
                <div class="alert alert-info text-center">لا توجد أنواع وحدات مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#unitTypeModal" onclick="prepareUnitTypeModal('add_unit_type')">إضافة نوع جديد</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>المعرف (<code>type_name</code>)</th>
                            <th>الاسم المعروض (بالعربية)</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_utype = ($current_page_utype - 1) * $items_per_page_utype + 1; ?>
                        <?php foreach ($unit_types_list_page as $utype_item): ?>
                        <tr>
                            <td><?php echo $row_num_utype++; ?></td>
                            <td><code><?php echo esc_html($utype_item['type_name']); ?></code></td>
                            <td><?php echo esc_html($utype_item['display_name_ar']); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="prepareUnitTypeModal('edit_unit_type', <?php echo htmlspecialchars(json_encode($utype_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#unitTypeModal"
                                        title="تعديل نوع الوحدة">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-unit-type-btn"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                        data-id="<?php echo $utype_item['id']; ?>"
                                        data-name="<?php echo esc_attr($utype_item['display_name_ar']); ?>"
                                        data-delete-url="<?php echo base_url('unit_types/actions.php?action=delete_unit_type&id=' . $utype_item['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        title="حذف نوع الوحدة">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages_utype > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_utype = [];
            if (!empty($search_term_utype)) $pagination_params_utype['search'] = $search_term_utype;
            echo generate_pagination_links($current_page_utype, $total_pages_utype, 'unit_types/index.php', $pagination_params_utype);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// تضمين نافذة إضافة/تعديل نوع الوحدة
require_once __DIR__ . '/../includes/modals/unit_type_modal.php';
// تضمين نافذة تأكيد الحذف
require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <script>
function prepareUnitTypeModal(action, unitTypeData = null) {
    const unitTypeModal = document.getElementById('unitTypeModal');
    const modalTitle = unitTypeModal.querySelector('#unitTypeModalLabel_utypes');
    const unitTypeForm = unitTypeModal.querySelector('#unitTypeFormModal');
    const unitTypeIdInput = unitTypeModal.querySelector('#unit_type_id_modal_utypes');
    const actionInput = unitTypeModal.querySelector('#unit_type_form_action_modal_utypes');
    const submitButton = unitTypeModal.querySelector('#unitTypeSubmitButtonTextModalUtypes');

    unitTypeForm.reset();
    unitTypeIdInput.value = '';
    actionInput.value = action;

    // const formUrl = '<?php echo base_url('unit_types/actions.php'); ?>'; // Not needed for fetch

    if (action === 'add_unit_type') {
        modalTitle.textContent = 'إضافة نوع وحدة جديد';
        submitButton.textContent = 'إضافة النوع';
    } else if (action === 'edit_unit_type' && unitTypeData) {
        modalTitle.textContent = 'تعديل نوع الوحدة: ' + unitTypeData.display_name_ar;
        submitButton.textContent = 'حفظ التعديلات';
        unitTypeIdInput.value = unitTypeData.id;
        
        if(document.getElementById('unit_type_name_modal_utypes')) document.getElementById('unit_type_name_modal_utypes').value = unitTypeData.type_name || '';
        if(document.getElementById('unit_type_display_name_ar_modal_utypes')) document.getElementById('unit_type_display_name_ar_modal_utypes').value = unitTypeData.display_name_ar || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var confirmDeleteModalUnitType = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModalUnitType) {
        confirmDeleteModalUnitType.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button.classList.contains('delete-unit-type-btn')) {
                var itemName = button.getAttribute('data-name');
                var deleteUrl = button.getAttribute('data-delete-url');
                var modalBodyText = confirmDeleteModalUnitType.querySelector('.modal-body-text');
                if(modalBodyText) modalBodyText.textContent = 'هل أنت متأكد أنك تريد حذف نوع الوحدة "' + itemName + '"؟';
                
                var additionalInfo = confirmDeleteModalUnitType.querySelector('#additionalDeleteInfo');
                if(additionalInfo) additionalInfo.textContent = 'ملاحظة: لا يمكن حذف نوع الوحدة إذا كان مستخدماً في أي وحدات حالية.';

                var confirmDeleteButton = confirmDeleteModalUnitType.querySelector('#confirmDeleteButton');
                if(confirmDeleteButton) {
                    var newConfirmDeleteButtonUType = confirmDeleteButton.cloneNode(true);
                    confirmDeleteButton.parentNode.replaceChild(newConfirmDeleteButtonUType, confirmDeleteButton);
                    
                    newConfirmDeleteButtonUType.setAttribute('data-delete-url', deleteUrl);
                    newConfirmDeleteButtonUType.removeAttribute('href');
                    
                    newConfirmDeleteButtonUType.addEventListener('click', function(e) {
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

    const unitTypeFormElement = document.getElementById('unitTypeFormModal');
    if(unitTypeFormElement) {
        unitTypeFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(unitTypeFormElement);
            const actionUrl = '<?php echo base_url('unit_types/actions.php'); ?>';

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var unitTypeModalInstance = bootstrap.Modal.getInstance(document.getElementById('unitTypeModal'));
                    if(unitTypeModalInstance) unitTypeModalInstance.hide();
                    window.location.reload(); 
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