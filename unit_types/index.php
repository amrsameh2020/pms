<?php
$page_title = "إدارة أنواع المرافق"; // This confirms it's for utility_types
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); 
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// Pagination
$current_page_utype = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_utype = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_utype = ($current_page_utype - 1) * $items_per_page_utype;

// Search
$search_term_utype = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query_part_utype = '';
$params_for_count_utype = []; $types_for_count_utype = "";
$params_for_data_utype = [];  $types_for_data_utype = "";

if (!empty($search_term_utype)) {
    $search_query_part_utype = " WHERE name LIKE ? OR unit_of_measure LIKE ?";
    $search_like_utype = "%" . $search_term_utype . "%";
    $params_for_count_utype = [$search_like_utype, $search_like_utype]; $types_for_count_utype = "ss";
    $params_for_data_utype = $params_for_count_utype; $types_for_data_utype = $types_for_count_utype;
}

// Total count
$total_sql_utype = "SELECT COUNT(*) as total FROM utility_types" . $search_query_part_utype;
$stmt_total_utype = $mysqli->prepare($total_sql_utype);
$total_utility_types = 0;
if ($stmt_total_utype) {
    if (!empty($params_for_count_utype)) $stmt_total_utype->bind_param($types_for_count_utype, ...$params_for_count_utype);
    $stmt_total_utype->execute();
    $total_result_utype = $stmt_total_utype->get_result();
    $total_utility_types = ($total_result_utype && $total_result_utype->num_rows > 0) ? $total_result_utype->fetch_assoc()['total'] : 0;
    $stmt_total_utype->close();
} else { error_log("SQL Prepare Error counting utility types: " . $mysqli->error); }
$total_pages_utype = ceil($total_utility_types / $items_per_page_utype);

// Fetch data
$sql_utype = "SELECT * FROM utility_types" . $search_query_part_utype . " ORDER BY name ASC LIMIT ? OFFSET ?";
$current_data_params_utype = $params_for_data_utype;
$current_data_params_utype[] = $items_per_page_utype;
$current_data_params_utype[] = $offset_utype;
$current_data_types_utype = $types_for_data_utype . 'ii';

$utility_types_list = [];
$stmt_utype = $mysqli->prepare($sql_utype);
if ($stmt_utype) {
    if (!empty($current_data_params_utype) && $current_data_types_utype !=='') $stmt_utype->bind_param($current_data_types_utype, ...$current_data_params_utype);
    else $stmt_utype->bind_param('ii', $items_per_page_utype, $offset_utype);
    $stmt_utype->execute();
    $result_utype = $stmt_utype->get_result();
    $utility_types_list = ($result_utype && $result_utype->num_rows > 0) ? $result_utype->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_utype->close();
} else { error_log("SQL Prepare Error fetching utility types: " . $mysqli->error); }

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-droplet-half"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة أنواع المرافق (<?php echo $total_utility_types; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#utilityTypeModal" onclick="prepareUtilityTypeModal('add_utility_type')">
                    <i class="bi bi-plus-circle"></i> إضافة نوع مرفق جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('utility_types/index.php'); // Correct path ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-10 col-sm-8">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="ابحث باسم النوع أو وحدة القياس..." value="<?php echo esc_attr($search_term_utype); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> بحث</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($utility_types_list) && !empty($search_term_utype)): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج بحث تطابق "<?php echo esc_html($search_term_utype); ?>".</div>
            <?php elseif (empty($utility_types_list)): ?>
                <div class="alert alert-info text-center">لا توجد أنواع مرافق مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#utilityTypeModal" onclick="prepareUtilityTypeModal('add_utility_type')">إضافة نوع جديد</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>اسم النوع</th>
                            <th>وحدة القياس</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_utype = ($current_page_utype - 1) * $items_per_page_utype + 1; ?>
                        <?php foreach ($utility_types_list as $utype_item): ?>
                        <tr>
                            <td><?php echo $row_num_utype++; ?></td>
                            <td><?php echo esc_html($utype_item['name']); ?></td>
                            <td><?php echo esc_html($utype_item['unit_of_measure']); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="prepareUtilityTypeModal('edit_utility_type', <?php echo htmlspecialchars(json_encode($utype_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#utilityTypeModal"
                                        title="تعديل نوع المرفق">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger sweet-delete-btn delete-utility-type-btn"
                                        data-id="<?php echo $utype_item['id']; ?>"
                                        data-name="<?php echo esc_attr($utype_item['name']); ?>"
                                        data-delete-url="<?php echo base_url('utility_types/actions.php?action=delete_utility_type&id=' . $utype_item['id'] . '&csrf_token=' . $csrf_token); // Correct path ?>"
                                        data-additional-message="ملاحظة: لا يمكن حذف نوع المرفق إذا كان مستخدماً في أي قراءات عدادات."
                                        title="حذف نوع المرفق">
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
            echo generate_pagination_links($current_page_utype, $total_pages_utype, 'utility_types/index.php', $pagination_params_utype); // Correct path
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/utility_type_modal.php'; 
// confirm_delete_modal.php is no longer required here
?>

</div>
<script>
// globalHandleSweetAlertSessionMessage is now in footer_resources.php
// document.addEventListener('DOMContentLoaded', function() {
//     globalHandleSweetAlertSessionMessage(); // This will be called by footer_resources.php
// });


function prepareUtilityTypeModal(action, utypeData = null) {
    const utypeModal = document.getElementById('utilityTypeModal');
    const modalTitle = utypeModal.querySelector('.modal-title');
    const utypeForm = utypeModal.querySelector('form'); 
    const utypeIdInput = utypeModal.querySelector('input[name="utility_type_id"]'); 
    const actionInput = utypeModal.querySelector('input[name="action"]'); 
    const submitButtonTextEl = utypeModal.querySelector('#utilityTypeSubmitButtonText'); // Corrected selector (ID of span)

    utypeForm.reset();
    if(utypeIdInput) utypeIdInput.value = '';
    actionInput.value = action;

    if (action === 'add_utility_type') {
        modalTitle.textContent = 'إضافة نوع مرفق جديد';
        if(submitButtonTextEl) submitButtonTextEl.textContent = 'إضافة النوع';
    } else if (action === 'edit_utility_type' && utypeData) {
        modalTitle.textContent = 'تعديل نوع المرفق: ' + utypeData.name;
        if(submitButtonTextEl) submitButtonTextEl.textContent = 'حفظ التعديلات';
        if(utypeIdInput) utypeIdInput.value = utypeData.id;
        
        let nameInput = utypeModal.querySelector('input[name="utility_type_name_modal"]'); 
        let unitMeasureInput = utypeModal.querySelector('input[name="unit_of_measure_modal"]'); 
        
        if(nameInput) nameInput.value = utypeData.name || '';
        if(unitMeasureInput) unitMeasureInput.value = utypeData.unit_of_measure || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Old confirmDeleteModalUType JavaScript block removed.

    const utypeFormElement = document.querySelector('#utilityTypeModal form'); 
    if(utypeFormElement) {
        utypeFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(utypeFormElement);
            const actionUrl = '<?php echo base_url('utility_types/actions.php'); // Correct path ?>';

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('utilityTypeModal'));
                if (data.success) {
                    if(modalInstance) modalInstance.hide();
                     Swal.fire({ 
                         title: 'نجاح!', 
                         text: data.message, 
                         icon: 'success', 
                         confirmButtonText: 'حسنًا' 
                    }).then(() => window.location.reload());
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