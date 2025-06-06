<?php
$page_title = "إدارة أنواع العقارات";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); 
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// Pagination variables
$current_page_ptype = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_ptype = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_ptype = ($current_page_ptype - 1) * $items_per_page_ptype;

// Search
$search_term_ptype = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query_part_ptype = '';
$params_for_count_ptype = [];
$types_for_count_ptype = "";
$params_for_data_ptype = [];
$types_for_data_ptype = "";

if (!empty($search_term_ptype)) {
    $search_query_part_ptype = " WHERE type_name LIKE ? OR display_name_ar LIKE ?";
    $search_like_ptype = "%" . $search_term_ptype . "%";
    $params_for_count_ptype = [$search_like_ptype, $search_like_ptype];
    $types_for_count_ptype = "ss";
    $params_for_data_ptype = $params_for_count_ptype;
    $types_for_data_ptype = $types_for_count_ptype;
}

// Get total property types
$total_sql_ptype = "SELECT COUNT(*) as total FROM property_types" . $search_query_part_ptype;
$stmt_total_ptype = $mysqli->prepare($total_sql_ptype);
$total_property_types = 0;
if ($stmt_total_ptype) {
    if (!empty($params_for_count_ptype) && $types_for_count_ptype !== '') {
        $stmt_total_ptype->bind_param($types_for_count_ptype, ...$params_for_count_ptype);
    }
    $stmt_total_ptype->execute();
    $total_result_ptype = $stmt_total_ptype->get_result();
    $total_property_types = ($total_result_ptype && $total_result_ptype->num_rows > 0) ? $total_result_ptype->fetch_assoc()['total'] : 0;
    $stmt_total_ptype->close();
} else {
    error_log("SQL Prepare Error for counting property types: " . $mysqli->error);
}
$total_pages_ptype = ceil($total_property_types / $items_per_page_ptype);

// Fetch property types for current page
$sql_ptype = "SELECT * FROM property_types" . $search_query_part_ptype . " ORDER BY display_name_ar ASC LIMIT ? OFFSET ?";
$current_data_params_ptype = $params_for_data_ptype;
$current_data_params_ptype[] = $items_per_page_ptype;
$current_data_params_ptype[] = $offset_ptype;
$current_data_types_ptype = $types_for_data_ptype . 'ii';

$property_types_list_page = [];
$stmt_ptype = $mysqli->prepare($sql_ptype);
if ($stmt_ptype) {
    if (!empty($current_data_params_ptype) && $current_data_types_ptype !== 'ii') {
        $stmt_ptype->bind_param($current_data_types_ptype, ...$current_data_params_ptype);
    } else {
        $stmt_ptype->bind_param('ii', $items_per_page_ptype, $offset_ptype);
    }
    $stmt_ptype->execute();
    $result_ptype = $stmt_ptype->get_result();
    $property_types_list_page = ($result_ptype && $result_ptype->num_rows > 0) ? $result_ptype->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_ptype->close();
} else {
    error_log("SQL Prepare Error for fetching property types: " . $mysqli->error);
}

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-tags"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة أنواع العقارات (<?php echo $total_property_types; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#propertyTypeModal" onclick="preparePropertyTypeModal('add_property_type')">
                    <i class="bi bi-plus-circle"></i> إضافة نوع جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('property_types/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-10 col-sm-8">
                     <label for="search_property_types_page" class="form-label form-label-sm visually-hidden">بحث</label>
                    <input type="text" id="search_property_types_page" name="search" class="form-control form-control-sm" placeholder="ابحث بالمعرف أو الاسم المعروض..." value="<?php echo esc_attr($search_term_ptype); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> بحث</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($property_types_list_page) && !empty($search_term_ptype)): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج بحث تطابق "<?php echo esc_html($search_term_ptype); ?>".</div>
            <?php elseif (empty($property_types_list_page)): ?>
                <div class="alert alert-info text-center">لا توجد أنواع عقارات مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#propertyTypeModal" onclick="preparePropertyTypeModal('add_property_type')">إضافة نوع جديد</a>.</div>
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
                        <?php $row_num_ptype = ($current_page_ptype - 1) * $items_per_page_ptype + 1; ?>
                        <?php foreach ($property_types_list_page as $ptype_item): ?>
                        <tr>
                            <td><?php echo $row_num_ptype++; ?></td>
                            <td><code><?php echo esc_html($ptype_item['type_name']); ?></code></td>
                            <td><?php echo esc_html($ptype_item['display_name_ar']); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="preparePropertyTypeModal('edit_property_type', <?php echo htmlspecialchars(json_encode($ptype_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#propertyTypeModal"
                                        title="تعديل نوع العقار">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger sweet-delete-btn delete-property-type-btn"
                                        data-id="<?php echo $ptype_item['id']; ?>"
                                        data-name="<?php echo esc_attr($ptype_item['display_name_ar']); ?>"
                                        data-delete-url="<?php echo base_url('property_types/actions.php?action=delete_property_type&id=' . $ptype_item['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        data-additional-message="ملاحظة: لا يمكن حذف نوع العقار إذا كان مستخدماً في أي عقارات حالية."
                                        title="حذف نوع العقار">
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
        <?php if ($total_pages_ptype > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_ptype = [];
            if (!empty($search_term_ptype)) $pagination_params_ptype['search'] = $search_term_ptype;
            echo generate_pagination_links($current_page_ptype, $total_pages_ptype, 'property_types/index.php', $pagination_params_ptype);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/property_type_modal.php';
// confirm_delete_modal.php is no longer required here
?>

</div> 
<script>
function preparePropertyTypeModal(action, ptypeData = null) {
    const ptypeModal = document.getElementById('propertyTypeModal');
    const modalTitle = ptypeModal.querySelector('#propertyTypeModalLabel_ptypes');
    const ptypeForm = ptypeModal.querySelector('#propertyTypeFormModal');
    const ptypeIdInput = ptypeModal.querySelector('#property_type_id_modal_ptypes');
    const actionInput = ptypeModal.querySelector('#property_type_form_action_modal_ptypes');
    const submitButtonText = ptypeModal.querySelector('#propertyTypeSubmitButtonTextModalPtypes');

    ptypeForm.reset();
    if(ptypeIdInput) ptypeIdInput.value = '';
    actionInput.value = action;

    if (action === 'add_property_type') {
        modalTitle.textContent = 'إضافة نوع عقار جديد';
        if(submitButtonText) submitButtonText.textContent = 'إضافة النوع';
    } else if (action === 'edit_property_type' && ptypeData) {
        modalTitle.textContent = 'تعديل نوع العقار: ' + ptypeData.display_name_ar;
        if(submitButtonText) submitButtonText.textContent = 'حفظ التعديلات';
        if(ptypeIdInput) ptypeIdInput.value = ptypeData.id;
        
        let nameInput = ptypeModal.querySelector('#property_type_name_modal_ptypes');
        let displayNameInput = ptypeModal.querySelector('#property_type_display_name_ar_modal_ptypes');
        if(nameInput) nameInput.value = ptypeData.type_name || '';
        if(displayNameInput) displayNameInput.value = ptypeData.display_name_ar || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Old confirmDeleteModalPType JavaScript block removed.

    const ptypeFormElement = document.getElementById('propertyTypeFormModal');
    if(ptypeFormElement) {
        ptypeFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(ptypeFormElement);
            const actionUrl = '<?php echo base_url('property_types/actions.php'); ?>';

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('propertyTypeModal'));
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
                    text: 'حدث خطأ غير متوقع أثناء معالجة طلبك.',
                    icon: 'error',
                    confirmButtonText: 'حسنًا'
                });
            });
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>