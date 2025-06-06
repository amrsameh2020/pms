<?php
$page_title = "إدارة أنواع المستأجرين";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); 
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// Pagination variables
$current_page_ttype = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_ttype = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_ttype = ($current_page_ttype - 1) * $items_per_page_ttype;

// Search
$search_term_ttype = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query_part_ttype = '';
$params_for_count_ttype = []; $types_for_count_ttype = "";
$params_for_data_ttype = [];  $types_for_data_ttype = "";

if (!empty($search_term_ttype)) {
    $search_query_part_ttype = " WHERE type_name LIKE ? OR display_name_ar LIKE ?";
    $search_like_ttype = "%" . $search_term_ttype . "%";
    $params_for_count_ttype = [$search_like_ttype, $search_like_ttype]; $types_for_count_ttype = "ss";
    $params_for_data_ttype = $params_for_count_ttype; $types_for_data_ttype = $types_for_count_ttype;
}

// Get total tenant types
$total_sql_ttype = "SELECT COUNT(*) as total FROM tenant_types" . $search_query_part_ttype;
$stmt_total_ttype = $mysqli->prepare($total_sql_ttype);
$total_tenant_types = 0;
if ($stmt_total_ttype) {
    if (!empty($params_for_count_ttype) && $types_for_count_ttype !== '') $stmt_total_ttype->bind_param($types_for_count_ttype, ...$params_for_count_ttype);
    $stmt_total_ttype->execute();
    $total_result_ttype = $stmt_total_ttype->get_result();
    $total_tenant_types = ($total_result_ttype && $total_result_ttype->num_rows > 0) ? $total_result_ttype->fetch_assoc()['total'] : 0;
    $stmt_total_ttype->close();
} else { error_log("SQL Prepare Error counting tenant types: " . $mysqli->error); }
$total_pages_ttype = ceil($total_tenant_types / $items_per_page_ttype);

// Fetch tenant types for current page
$sql_ttype = "SELECT * FROM tenant_types" . $search_query_part_ttype . " ORDER BY display_name_ar ASC LIMIT ? OFFSET ?";
$current_data_params_ttype = $params_for_data_ttype;
$current_data_params_ttype[] = $items_per_page_ttype;
$current_data_params_ttype[] = $offset_ttype;
$current_data_types_ttype = $types_for_data_ttype . 'ii';

$tenant_types_list_page = [];
$stmt_ttype = $mysqli->prepare($sql_ttype);
if ($stmt_ttype) {
    if (!empty($current_data_params_ttype) && $current_data_types_ttype !== 'ii') $stmt_ttype->bind_param($current_data_types_ttype, ...$current_data_params_ttype);
    else $stmt_ttype->bind_param('ii', $items_per_page_ttype, $offset_ttype);
    $stmt_ttype->execute();
    $result_ttype = $stmt_ttype->get_result();
    $tenant_types_list_page = ($result_ttype && $result_ttype->num_rows > 0) ? $result_ttype->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_ttype->close();
} else { error_log("SQL Prepare Error fetching tenant types: " . $mysqli->error); }

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-person-bounding-box"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة أنواع المستأجرين (<?php echo $total_tenant_types; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#tenantTypeModal" onclick="prepareTenantTypeModal('add_tenant_type')">
                    <i class="bi bi-plus-circle"></i> إضافة نوع جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('tenant_types/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-10 col-sm-8">
                     <label for="search_tenant_types_page" class="form-label form-label-sm visually-hidden">بحث</label>
                    <input type="text" id="search_tenant_types_page" name="search" class="form-control form-control-sm" placeholder="ابحث بالمعرف أو الاسم المعروض..." value="<?php echo esc_attr($search_term_ttype); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> بحث</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($tenant_types_list_page) && !empty($search_term_ttype)): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج بحث تطابق "<?php echo esc_html($search_term_ttype); ?>".</div>
            <?php elseif (empty($tenant_types_list_page)): ?>
                <div class="alert alert-info text-center">لا توجد أنواع مستأجرين مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#tenantTypeModal" onclick="prepareTenantTypeModal('add_tenant_type')">إضافة نوع جديد</a>.</div>
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
                        <?php $row_num_ttype = ($current_page_ttype - 1) * $items_per_page_ttype + 1; ?>
                        <?php foreach ($tenant_types_list_page as $ttype_item): ?>
                        <tr>
                            <td><?php echo $row_num_ttype++; ?></td>
                            <td><code><?php echo esc_html($ttype_item['type_name']); ?></code></td>
                            <td><?php echo esc_html($ttype_item['display_name_ar']); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="prepareTenantTypeModal('edit_tenant_type', <?php echo htmlspecialchars(json_encode($ttype_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#tenantTypeModal"
                                        title="تعديل نوع المستأجر">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger sweet-delete-btn delete-tenant-type-btn"
                                        data-id="<?php echo $ttype_item['id']; ?>"
                                        data-name="<?php echo esc_attr($ttype_item['display_name_ar']); ?>"
                                        data-delete-url="<?php echo base_url('tenant_types/actions.php?action=delete_tenant_type&id=' . $ttype_item['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        data-additional-message="ملاحظة: لا يمكن حذف نوع المستأجر إذا كان مستخدماً لأي مستأجرين حاليين."
                                        title="حذف نوع المستأجر">
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
        <?php if ($total_pages_ttype > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_ttype = [];
            if (!empty($search_term_ttype)) $pagination_params_ttype['search'] = $search_term_ttype;
            echo generate_pagination_links($current_page_ttype, $total_pages_ttype, 'tenant_types/index.php', $pagination_params_ttype);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/tenant_type_modal.php'; 
// confirm_delete_modal.php is no longer required
?>

</div> 
<script>
function prepareTenantTypeModal(action, ttypeData = null) {
    const ttypeModal = document.getElementById('tenantTypeModal'); 
    const modalTitle = ttypeModal.querySelector('#tenantTypeModalLabel_ttypes');
    const ttypeForm = ttypeModal.querySelector('#tenantTypeFormModal'); 
    const ttypeIdInput = ttypeModal.querySelector('#tenant_type_id_modal_ttypes');
    const actionInput = ttypeModal.querySelector('#tenant_type_form_action_modal_ttypes');
    const submitButtonText = ttypeModal.querySelector('#tenantTypeSubmitButtonTextModalTtypes');

    ttypeForm.reset();
    if(ttypeIdInput) ttypeIdInput.value = '';
    actionInput.value = action;

    if (action === 'add_tenant_type') {
        modalTitle.textContent = 'إضافة نوع مستأجر جديد';
        if(submitButtonText) submitButtonText.textContent = 'إضافة النوع';
    } else if (action === 'edit_tenant_type' && ttypeData) {
        modalTitle.textContent = 'تعديل نوع المستأجر: ' + ttypeData.display_name_ar;
        if(submitButtonText) submitButtonText.textContent = 'حفظ التعديلات';
        if(ttypeIdInput) ttypeIdInput.value = ttypeData.id;
        
        let nameInput = ttypeModal.querySelector('#tenant_type_name_modal_ttypes');
        let displayNameInput = ttypeModal.querySelector('#tenant_type_display_name_ar_modal_ttypes');
        if(nameInput) nameInput.value = ttypeData.type_name || '';
        if(displayNameInput) displayNameInput.value = ttypeData.display_name_ar || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Old confirmDeleteModalTType JavaScript block removed.

    const ttypeFormElement = document.querySelector('#tenantTypeModal #tenantTypeFormModal'); // More specific selector
    if(ttypeFormElement) {
        ttypeFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(ttypeFormElement);
            const actionUrl = '<?php echo base_url('tenant_types/actions.php'); ?>';

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('tenantTypeModal'));
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