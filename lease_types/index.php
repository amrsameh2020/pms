<?php
$page_title = "إدارة أنواع عقود الإيجار";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); 
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// Pagination variables
$current_page_ltype = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_ltype = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_ltype = ($current_page_ltype - 1) * $items_per_page_ltype;

// Search
$search_term_ltype = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query_part_ltype = '';
$params_for_count_ltype = [];
$types_for_count_ltype = "";
$params_for_data_ltype = [];
$types_for_data_ltype = "";

if (!empty($search_term_ltype)) {
    $search_query_part_ltype = " WHERE type_name LIKE ? OR display_name_ar LIKE ?";
    $search_like_ltype = "%" . $search_term_ltype . "%";
    $params_for_count_ltype = [$search_like_ltype, $search_like_ltype];
    $types_for_count_ltype = "ss";
    $params_for_data_ltype = $params_for_count_ltype; 
    $types_for_data_ltype = $types_for_count_ltype;
}

// Get total lease types
$total_sql_ltype = "SELECT COUNT(*) as total FROM lease_types" . $search_query_part_ltype;
$stmt_total_ltype = $mysqli->prepare($total_sql_ltype);
$total_lease_types = 0;
if ($stmt_total_ltype) {
    if (!empty($params_for_count_ltype)) {
        $stmt_total_ltype->bind_param($types_for_count_ltype, ...$params_for_count_ltype);
    }
    $stmt_total_ltype->execute();
    $total_result_ltype = $stmt_total_ltype->get_result();
    $total_lease_types = ($total_result_ltype && $total_result_ltype->num_rows > 0) ? $total_result_ltype->fetch_assoc()['total'] : 0;
    $stmt_total_ltype->close();
} else {
    error_log("SQL Prepare Error for counting lease types: " . $mysqli->error);
}
$total_pages_ltype = ceil($total_lease_types / $items_per_page_ltype);

// Fetch lease types for current page
$sql_ltype = "SELECT * FROM lease_types" . $search_query_part_ltype . " ORDER BY display_name_ar ASC LIMIT ? OFFSET ?";
$current_data_params_ltype = $params_for_data_ltype;
$current_data_params_ltype[] = $items_per_page_ltype;
$current_data_params_ltype[] = $offset_ltype;
$current_data_types_ltype = $types_for_data_ltype . 'ii';

$lease_types_list_page = []; 
$stmt_ltype = $mysqli->prepare($sql_ltype);
if ($stmt_ltype) {
    if (!empty($current_data_params_ltype) && $current_data_types_ltype !== 'ii') {
        $stmt_ltype->bind_param($current_data_types_ltype, ...$current_data_params_ltype);
    } else {
        $stmt_ltype->bind_param('ii', $items_per_page_ltype, $offset_ltype);
    }
    $stmt_ltype->execute();
    $result_ltype = $stmt_ltype->get_result();
    $lease_types_list_page = ($result_ltype && $result_ltype->num_rows > 0) ? $result_ltype->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_ltype->close();
} else {
    error_log("SQL Prepare Error for fetching lease types: " . $mysqli->error);
}

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-tags-fill"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة أنواع عقود الإيجار (<?php echo $total_lease_types; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#leaseTypeModal" onclick="prepareLeaseTypeModal('add_lease_type')">
                    <i class="bi bi-plus-circle"></i> إضافة نوع جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('lease_types/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-10 col-sm-8">
                     <label for="search_lease_types_page" class="form-label form-label-sm visually-hidden">بحث</label>
                    <input type="text" id="search_lease_types_page" name="search" class="form-control form-control-sm" placeholder="ابحث بالمعرف أو الاسم المعروض..." value="<?php echo esc_attr($search_term_ltype); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> بحث</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($lease_types_list_page) && !empty($search_term_ltype)): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج بحث تطابق "<?php echo esc_html($search_term_ltype); ?>".</div>
            <?php elseif (empty($lease_types_list_page)): ?>
                <div class="alert alert-info text-center">لا توجد أنواع عقود إيجار مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#leaseTypeModal" onclick="prepareLeaseTypeModal('add_lease_type')">إضافة نوع جديد</a>.</div>
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
                        <?php $row_num_ltype = ($current_page_ltype - 1) * $items_per_page_ltype + 1; ?>
                        <?php foreach ($lease_types_list_page as $ltype_item): ?>
                        <tr>
                            <td><?php echo $row_num_ltype++; ?></td>
                            <td><code><?php echo esc_html($ltype_item['type_name']); ?></code></td>
                            <td><?php echo esc_html($ltype_item['display_name_ar']); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="prepareLeaseTypeModal('edit_lease_type', <?php echo htmlspecialchars(json_encode($ltype_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#leaseTypeModal"
                                        title="تعديل نوع العقد">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger sweet-delete-btn delete-lease-type-btn"
                                        data-id="<?php echo $ltype_item['id']; ?>"
                                        data-name="<?php echo esc_attr($ltype_item['display_name_ar']); ?>"
                                        data-delete-url="<?php echo base_url('lease_types/actions.php?action=delete_lease_type&id=' . $ltype_item['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        data-additional-message="ملاحظة: لا يمكن حذف نوع العقد إذا كان مستخدماً في أي عقود إيجار حالية."
                                        title="حذف نوع العقد">
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
        <?php if ($total_pages_ltype > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_ltype = [];
            if (!empty($search_term_ltype)) $pagination_params_ltype['search'] = $search_term_ltype;
            echo generate_pagination_links($current_page_ltype, $total_pages_ltype, 'lease_types/index.php', $pagination_params_ltype);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/lease_type_modal.php';
// confirm_delete_modal.php is no longer required
?>

</div> 
<script>
function prepareLeaseTypeModal(action, leaseTypeData = null) {
    const leaseTypeModal = document.getElementById('leaseTypeModal');
    const modalTitle = leaseTypeModal.querySelector('#leaseTypeModalLabel_ltypes');
    const leaseTypeForm = leaseTypeModal.querySelector('#leaseTypeFormModal');
    const leaseTypeIdInput = leaseTypeModal.querySelector('#lease_type_id_modal_ltypes');
    const actionInput = leaseTypeModal.querySelector('#lease_type_form_action_modal_ltypes');
    const submitButtonText = leaseTypeModal.querySelector('#leaseTypeSubmitButtonTextModalLtypes'); // Corrected selector

    leaseTypeForm.reset();
    leaseTypeIdInput.value = '';
    actionInput.value = action;

    if (action === 'add_lease_type') {
        modalTitle.textContent = 'إضافة نوع عقد إيجار جديد';
        submitButtonText.textContent = 'إضافة النوع';
    } else if (action === 'edit_lease_type' && leaseTypeData) {
        modalTitle.textContent = 'تعديل نوع عقد الإيجار: ' + leaseTypeData.display_name_ar;
        submitButtonText.textContent = 'حفظ التعديلات';
        leaseTypeIdInput.value = leaseTypeData.id;
        
        if(document.getElementById('lease_type_name_modal_ltypes')) document.getElementById('lease_type_name_modal_ltypes').value = leaseTypeData.type_name || '';
        if(document.getElementById('lease_type_display_name_ar_modal_ltypes')) document.getElementById('lease_type_display_name_ar_modal_ltypes').value = leaseTypeData.display_name_ar || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Old confirmDeleteModalLeaseType JavaScript block removed.

    const leaseTypeFormElement = document.getElementById('leaseTypeFormModal');
    if(leaseTypeFormElement) {
        leaseTypeFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(leaseTypeFormElement);
            const actionUrl = '<?php echo base_url('lease_types/actions.php'); ?>';

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('leaseTypeModal'));
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