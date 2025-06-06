<?php
$page_title = "إدارة المستخدمين";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_role('admin'); 
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// Pagination variables
$current_page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset = ($current_page - 1) * $items_per_page;

// Search and filter functionality
$search_term_user = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_role_id_user = isset($_GET['role_id']) && filter_var($_GET['role_id'], FILTER_VALIDATE_INT) ? (int)$_GET['role_id'] : '';
$filter_active_user = isset($_GET['is_active']) ? sanitize_input($_GET['is_active']) : ''; 

$where_clauses_user = [];
$params_for_count_user = []; $types_for_count_user = "";
$params_for_data_user = [];  $types_for_data_user = "";

if (!empty($search_term_user)) {
    $where_clauses_user[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_like_user = "%" . $search_term_user . "%";
    for ($k=0; $k<3; $k++) {
        $params_for_count_user[] = $search_like_user; $types_for_count_user .= "s";
        $params_for_data_user[] = $search_like_user;  $types_for_data_user .= "s";
    }
}
if (!empty($filter_role_id_user)) {
    $where_clauses_user[] = "u.role_id = ?";
    $params_for_count_user[] = $filter_role_id_user; $types_for_count_user .= "i";
    $params_for_data_user[] = $filter_role_id_user;  $types_for_data_user .= "i";
}
if ($filter_active_user !== '') {
    $where_clauses_user[] = "u.is_active = ?";
    $params_for_count_user[] = (int)$filter_active_user; $types_for_count_user .= "i";
    $params_for_data_user[] = (int)$filter_active_user;  $types_for_data_user .= "i";
}

$where_sql_user = "";
if (!empty($where_clauses_user)) {
    $where_sql_user = " WHERE " . implode(" AND ", $where_clauses_user);
}

// Get total number of users
$total_sql_user = "SELECT COUNT(u.id) as total FROM users u" . $where_sql_user;
$stmt_total_user = $mysqli->prepare($total_sql_user);
$total_users = 0;
if ($stmt_total_user) {
    if (!empty($params_for_count_user)) {
        $stmt_total_user->bind_param($types_for_count_user, ...$params_for_count_user);
    }
    $stmt_total_user->execute();
    $total_result_user = $stmt_total_user->get_result();
    $total_users = ($total_result_user && $total_result_user->num_rows > 0) ? $total_result_user->fetch_assoc()['total'] : 0;
    $stmt_total_user->close();
} else {
    error_log("SQL Prepare Error for counting users: " . $mysqli->error);
}
$total_pages_user = ceil($total_users / $items_per_page);

// Fetch users for the current page
$sql_user = "SELECT u.id, u.full_name, u.username, u.email, u.created_at, u.is_active, u.role_id, r.display_name_ar as role_display_name, r.role_name as role_name_system
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id"
           . $where_sql_user . " ORDER BY u.full_name ASC LIMIT ? OFFSET ?";

$current_data_params_user = $params_for_data_user;
$current_data_params_user[] = $items_per_page;
$current_data_params_user[] = $offset;
$current_data_types_user = $types_for_data_user . 'ii';

$users_list = [];
$stmt_user = $mysqli->prepare($sql_user);
if ($stmt_user) {
    if (!empty($current_data_params_user) && $current_data_types_user !== '') { 
        $stmt_user->bind_param($current_data_types_user, ...$current_data_params_user);
    } else { 
        $stmt_user->bind_param('ii', $items_per_page, $offset);
    }
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $users_list = ($result_user && $result_user->num_rows > 0) ? $result_user->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_user->close();
} else {
    error_log("SQL Prepare Error for fetching users: " . $mysqli->error);
}

// Fetch roles list for filter
$roles_filter_list = [['id' => '', 'display_name_ar' => '-- الكل --']]; 
$roles_query_filter_page = "SELECT id, display_name_ar FROM roles ORDER BY display_name_ar ASC";
$roles_result_filter_page = $mysqli->query($roles_query_filter_page);
if ($roles_result_filter_page) {
    while ($role_row_filter_page = $roles_result_filter_page->fetch_assoc()) {
        $roles_filter_list[] = $role_row_filter_page;
    }
    $roles_result_filter_page->free();
}

$user_active_filter_options = [ 
    '' => '-- الكل --',
    '1' => 'نشط',
    '0' => 'غير نشط'
];

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-person-lines-fill"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة المستخدمين (<?php echo $total_users; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="prepareUserModal('add_user')">
                    <i class="bi bi-person-plus-fill"></i> إضافة مستخدم جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('users/index.php'); ?>" class="row gx-2 gy-2 align-items-end">
                <div class="col-md-4 col-lg-4">
                    <label for="search_users_page" class="form-label form-label-sm">بحث عام</label>
                    <input type="text" id="search_users_page" name="search" class="form-control form-control-sm" placeholder="الاسم، اسم المستخدم، البريد..." value="<?php echo esc_attr($search_term_user); ?>">
                </div>
                <div class="col-md-3 col-lg-3">
                    <label for="filter_role_id_user_page" class="form-label form-label-sm">الدور</label>
                    <select id="filter_role_id_user_page" name="role_id" class="form-select form-select-sm">
                        <?php foreach ($roles_filter_list as $role_filter_item): ?>
                            <option value="<?php echo $role_filter_item['id']; ?>" <?php echo ($filter_role_id_user == $role_filter_item['id']) ? 'selected' : ''; ?>><?php echo esc_html($role_filter_item['display_name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-lg-3">
                    <label for="filter_active_user_page" class="form-label form-label-sm">الحالة</label>
                    <select id="filter_active_user_page" name="is_active" class="form-select form-select-sm">
                         <?php foreach ($user_active_filter_options as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filter_active_user === (string)$key && $filter_active_user !== '') ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill"></i></button>
                </div>
                 <div class="col-md-1">
                     <a href="<?php echo base_url('users/index.php'); ?>" class="btn btn-outline-secondary btn-sm w-100" title="مسح الفلاتر"><i class="bi bi-eraser-fill"></i></a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($users_list) && (!empty($search_term_user) || !empty($filter_role_id_user) || $filter_active_user !== '')): ?>
                <div class="alert alert-warning text-center">لا يوجد مستخدمون يطابقون معايير البحث أو الفلترة.</div>
            <?php elseif (empty($users_list)): ?>
                <div class="alert alert-info text-center">لا يوجد مستخدمون مسجلون حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#userModal" onclick="prepareUserModal('add_user')">إضافة مستخدم جديد</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>الاسم الكامل</th>
                            <th>اسم المستخدم</th>
                            <th>البريد الإلكتروني</th>
                            <th>الدور</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الحالة</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_user = ($current_page - 1) * $items_per_page + 1; ?>
                        <?php foreach ($users_list as $user_item): ?>
                        <tr>
                            <td><?php echo $row_num_user++; ?></td>
                            <td><?php echo esc_html($user_item['full_name']); ?></td>
                            <td><?php echo esc_html($user_item['username']); ?></td>
                            <td><?php echo esc_html($user_item['email']); ?></td>
                            <td><span class="badge bg-<?php echo ($user_item['role_name_system'] === 'admin') ? 'primary' : 'secondary'; ?>"><?php echo esc_html($user_item['role_display_name'] ?? $user_item['role_name_system']); ?></span></td>
                            <td><?php echo format_date_custom($user_item['created_at'], 'Y-m-d H:i'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user_item['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user_item['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($user_item['id'] != get_current_user_id()): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="prepareUserModal('edit_user', <?php echo htmlspecialchars(json_encode($user_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#userModal"
                                        title="تعديل المستخدم">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <?php if ($user_item['username'] !== 'admin'): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger sweet-delete-btn delete-user-btn"
                                        data-id="<?php echo $user_item['id']; ?>"
                                        data-name="المستخدم <?php echo esc_attr($user_item['username']); ?>"
                                        data-delete-url="<?php echo base_url('users/user_actions.php?action=delete_user&id=' . $user_item['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        data-additional-message="سيتم حذف المستخدم بشكل نهائي ولا يمكن استعادته."
                                        title="حذف المستخدم">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="لا يمكن حذف المستخدم المسؤول الرئيسي"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                                 <?php else: ?>
                                    <small class="text-muted">(حسابك الحالي)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages_user > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_user = [];
            if (!empty($search_term_user)) $pagination_params_user['search'] = $search_term_user;
            if (!empty($filter_role_id_user)) $pagination_params_user['role_id'] = $filter_role_id_user;
            if ($filter_active_user !== '') $pagination_params_user['is_active'] = $filter_active_user;
            echo generate_pagination_links($current_page, $total_pages_user, 'users/index.php', $pagination_params_user);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/user_modal.php';
// confirm_delete_modal.php is no longer required here
?>

</div> 
<script>
function prepareUserModal(action, userData = null) {
    const userModal = document.getElementById('userModal'); 
    const modalTitle = userModal.querySelector('.modal-title');
    const userForm = userModal.querySelector('#userFormModal'); 
    const userIdInput = userModal.querySelector('#user_id_modal_users');
    const actionInput = userModal.querySelector('#user_form_action_modal');
    const submitButtonText = userModal.querySelector('#userSubmitButtonTextModal'); // Changed from submitButton
    const passwordInput = userModal.querySelector('#user_password_modal');
    const confirmPasswordInput = userModal.querySelector('#user_confirm_password_modal');
    const passwordHelpBlock = userModal.querySelector('#passwordHelpBlock_modal');
    const usernameInput = userModal.querySelector('#user_username_modal');

    userForm.reset();
    userIdInput.value = '';
    actionInput.value = action;
    passwordInput.removeAttribute('required');
    confirmPasswordInput.removeAttribute('required');
    usernameInput.removeAttribute('readonly');

    // No need to set form.action if using fetch correctly with its own URL parameter

    if (action === 'add_user') {
        modalTitle.textContent = 'إضافة مستخدم جديد';
        submitButtonText.textContent = 'إضافة المستخدم';
        passwordInput.setAttribute('required', 'required');
        confirmPasswordInput.setAttribute('required', 'required');
        passwordHelpBlock.textContent = 'كلمة المرور مطلوبة عند إضافة مستخدم جديد.';
    } else if (action === 'edit_user' && userData) {
        modalTitle.textContent = 'تعديل بيانات المستخدم: ' + userData.full_name;
        submitButtonText.textContent = 'حفظ التعديلات';
        passwordHelpBlock.textContent = 'اتركه فارغًا لعدم تغيير كلمة المرور الحالية.';
        
        userIdInput.value = userData.id;
        userModal.querySelector('#user_full_name_modal').value = userData.full_name || '';
        usernameInput.value = userData.username || ''; // Changed from userModal.querySelector
        userModal.querySelector('#user_email_modal').value = userData.email || '';
        userModal.querySelector('#user_role_id_modal').value = userData.role_id || '';
        userModal.querySelector('#user_is_active_modal').value = String(userData.is_active);

        if (userData.username === 'admin') {
            usernameInput.setAttribute('readonly', 'readonly');
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // The old confirmDeleteModalUser JavaScript block is removed.
    
    const userFormElement = document.getElementById('userFormModal');
    if(userFormElement) {
        userFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(userFormElement);
            const actionUrl = '<?php echo base_url('users/user_actions.php'); ?>'; // Action URL defined here

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('userModal'));
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