<?php
$page_title = "إدارة أدوار المستخدمين";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_role('admin'); // فقط المسؤول يمكنه إدارة الأدوار
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// متغيرات التصفح
$current_page_role = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_role = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_role = ($current_page_role - 1) * $items_per_page_role;

// البحث
$search_term_role = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query_part_role = '';
$params_for_count_role = []; $types_for_count_role = "";
$params_for_data_role = [];  $types_for_data_role = "";

if (!empty($search_term_role)) {
    $search_query_part_role = " WHERE role_name LIKE ? OR display_name_ar LIKE ? OR description LIKE ?";
    $search_like_role = "%" . $search_term_role . "%";
    for($i=0; $i<3; $i++){
        $params_for_count_role[] = $search_like_role; $types_for_count_role .= "s";
        $params_for_data_role[] = $search_like_role;  $types_for_data_role .= "s";
    }
}

// العدد الإجمالي للأدوار
$total_sql_role = "SELECT COUNT(*) as total FROM roles" . $search_query_part_role;
$stmt_total_role = $mysqli->prepare($total_sql_role);
$total_roles = 0;
if ($stmt_total_role) {
    if (!empty($params_for_count_role)) $stmt_total_role->bind_param($types_for_count_role, ...$params_for_count_role);
    $stmt_total_role->execute();
    $total_result_role = $stmt_total_role->get_result();
    $total_roles = ($total_result_role && $total_result_role->num_rows > 0) ? $total_result_role->fetch_assoc()['total'] : 0;
    $stmt_total_role->close();
} else { error_log("SQL Prepare Error counting roles: " . $mysqli->error); }
$total_pages_role = ceil($total_roles / $items_per_page_role);

// جلب الأدوار للصفحة الحالية
$sql_role = "SELECT * FROM roles" . $search_query_part_role . " ORDER BY display_name_ar ASC LIMIT ? OFFSET ?";
$current_data_params_role = $params_for_data_role;
$current_data_params_role[] = $items_per_page_role;
$current_data_params_role[] = $offset_role;
$current_data_types_role = $types_for_data_role . 'ii';

$roles_list_page = [];
$stmt_role = $mysqli->prepare($sql_role);
if ($stmt_role) {
    if (!empty($current_data_params_role)) $stmt_role->bind_param($current_data_types_role, ...$current_data_params_role);
    else $stmt_role->bind_param('ii', $items_per_page_role, $offset_role);
    $stmt_role->execute();
    $result_role = $stmt_role->get_result();
    $roles_list_page = ($result_role && $result_role->num_rows > 0) ? $result_role->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_role->close();
} else { error_log("SQL Prepare Error fetching roles: " . $mysqli->error); }

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-person-rolodex"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الأدوار (<?php echo $total_roles; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="prepareRoleModal('add_role')">
                    <i class="bi bi-plus-circle"></i> إضافة دور جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('roles/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-10 col-sm-8">
                     <label for="search_roles_page" class="form-label form-label-sm visually-hidden">بحث</label>
                    <input type="text" id="search_roles_page" name="search" class="form-control form-control-sm" placeholder="ابحث بالمعرف، الاسم المعروض، أو الوصف..." value="<?php echo esc_attr($search_term_role); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> بحث</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($roles_list_page) && !empty($search_term_role)): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج بحث تطابق "<?php echo esc_html($search_term_role); ?>".</div>
            <?php elseif (empty($roles_list_page)): ?>
                <div class="alert alert-info text-center">لا توجد أدوار مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="prepareRoleModal('add_role')">إضافة دور جديد</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>المعرف (<code>role_name</code>)</th>
                            <th>الاسم المعروض (بالعربية)</th>
                            <th>الوصف</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_role = ($current_page_role - 1) * $items_per_page_role + 1; ?>
                        <?php foreach ($roles_list_page as $role_item): ?>
                        <tr>
                            <td><?php echo $row_num_role++; ?></td>
                            <td><code><?php echo esc_html($role_item['role_name']); ?></code></td>
                            <td><?php echo esc_html($role_item['display_name_ar']); ?></td>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr($role_item['description']); ?>"><?php echo esc_html($role_item['description'] ?: '-'); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="prepareRoleModal('edit_role', <?php echo htmlspecialchars(json_encode($role_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#roleModal"
                                        title="تعديل الدور">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <?php if (!in_array($role_item['role_name'], ['admin', 'staff'])): // لا يمكن حذف الأدوار المحمية ?>
                                <button type="button" class="btn btn-sm btn-outline-danger sweet-delete-btn delete-role-btn"
                                        data-id="<?php echo $role_item['id']; ?>"
                                        data-name="<?php echo esc_attr($role_item['display_name_ar']); ?>"
                                        data-delete-url="<?php echo base_url('roles/actions.php?action=delete_role&id=' . $role_item['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        data-additional-message="ملاحظة: لا يمكن حذف الدور إذا كان معيناً لأي مستخدمين."
                                        title="حذف الدور">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="لا يمكن حذف هذا الدور المحمي"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages_role > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_role = [];
            if (!empty($search_term_role)) $pagination_params_role['search'] = $search_term_role;
            echo generate_pagination_links($current_page_role, $total_pages_role, 'roles/index.php', $pagination_params_role);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/role_modal.php';
// Note: confirm_delete_modal.php is no longer needed if the global SweetAlert handler is used.
// You can remove this line: require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <?php /* This was an unclosed div in the original, it's closed by footer_resources.php */ ?>
<script>
function prepareRoleModal(action, roleData = null) {
    const roleModal = document.getElementById('roleModal');
    const modalTitle = roleModal.querySelector('#roleModalLabel_roles_page');
    const roleForm = roleModal.querySelector('#roleFormModal');
    const roleIdInput = roleModal.querySelector('#role_id_modal_roles_page');
    const actionInput = roleModal.querySelector('#role_form_action_modal_roles_page');
    const submitButton = roleModal.querySelector('#roleSubmitButtonTextModalRolesPage'); // Corrected ID
    const roleNameInput = roleModal.querySelector('#role_name_modal_roles_page'); // Corrected ID

    roleForm.reset();
    if(roleIdInput) roleIdInput.value = '';
    actionInput.value = action;
    roleNameInput.readOnly = false; 

    if (action === 'add_role') {
        modalTitle.textContent = 'إضافة دور جديد';
        if(submitButton) submitButton.textContent = 'إضافة الدور';
    } else if (action === 'edit_role' && roleData) {
        modalTitle.textContent = 'تعديل الدور: ' + roleData.display_name_ar;
        if(submitButton) submitButton.textContent = 'حفظ التعديلات';
        if(roleIdInput) roleIdInput.value = roleData.id;
        
        if(roleNameInput) roleNameInput.value = roleData.role_name || '';
        if(document.getElementById('role_display_name_ar_modal_roles_page')) document.getElementById('role_display_name_ar_modal_roles_page').value = roleData.display_name_ar || '';
        if(document.getElementById('role_description_modal_roles_page')) document.getElementById('role_description_modal_roles_page').value = roleData.description || '';

        if (roleData.role_name === 'admin' || roleData.role_name === 'staff') {
            roleNameInput.readOnly = true;
            let smallHelp = roleNameInput.nextElementSibling;
            if(smallHelp && smallHelp.tagName === 'SMALL'){
                smallHelp.textContent = 'لا يمكن تعديل المعرف لهذا الدور المحمي.';
            }
        } else {
            let smallHelp = roleNameInput.nextElementSibling;
             if(smallHelp && smallHelp.tagName === 'SMALL'){
                smallHelp.textContent = 'يستخدم داخليياً في النظام، يجب أن يكون فريداً (أحرف إنجليزية، أرقام، وشرطة سفلية).';
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // The old confirmDeleteModalRole JavaScript block is removed as it's handled globally.

    const roleFormElement = document.getElementById('roleFormModal'); // Corrected ID
    if(roleFormElement) {
        roleFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(roleFormElement);
            const actionUrl = '<?php echo base_url('roles/actions.php'); ?>';

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('roleModal'));
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