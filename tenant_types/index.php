<?php
$page_title = "إدارة أنواع المستأجرين";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); // للمسؤول فقط
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// متغيرات التصفح
$current_page_ttype = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_ttype = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_ttype = ($current_page_ttype - 1) * $items_per_page_ttype;

// البحث
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

// العدد الإجمالي لأنواع المستأجرين
$total_sql_ttype = "SELECT COUNT(*) as total FROM tenant_types" . $search_query_part_ttype;
$stmt_total_ttype = $mysqli->prepare($total_sql_ttype);
$total_tenant_types = 0;
if ($stmt_total_ttype) {
    if (!empty($params_for_count_ttype)) $stmt_total_ttype->bind_param($types_for_count_ttype, ...$params_for_count_ttype);
    $stmt_total_ttype->execute();
    $total_result_ttype = $stmt_total_ttype->get_result();
    $total_tenant_types = ($total_result_ttype && $total_result_ttype->num_rows > 0) ? $total_result_ttype->fetch_assoc()['total'] : 0;
    $stmt_total_ttype->close();
} else { error_log("SQL Prepare Error counting tenant types: " . $mysqli->error); }
$total_pages_ttype = ceil($total_tenant_types / $items_per_page_ttype);

// جلب أنواع المستأجرين للصفحة الحالية
$sql_ttype = "SELECT * FROM tenant_types" . $search_query_part_ttype . " ORDER BY display_name_ar ASC LIMIT ? OFFSET ?";
$current_data_params_ttype = $params_for_data_ttype;
$current_data_params_ttype[] = $items_per_page_ttype;
$current_data_params_ttype[] = $offset_ttype;
$current_data_types_ttype = $types_for_data_ttype . 'ii';

$tenant_types_list_page = [];
$stmt_ttype = $mysqli->prepare($sql_ttype);
if ($stmt_ttype) {
    if (!empty($current_data_params_ttype)) $stmt_ttype->bind_param($current_data_types_ttype, ...$current_data_params_ttype);
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
                                <button type="button" class="btn btn-sm btn-outline-danger delete-tenant-type-btn"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                        data-id="<?php echo $ttype_item['id']; ?>"
                                        data-name="<?php echo esc_attr($ttype_item['display_name_ar']); ?>"
                                        data-delete-url="<?php echo base_url('tenant_types/actions.php?action=delete_tenant_type&id=' . $ttype_item['id'] . '&csrf_token=' . $csrf_token); ?>"
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
// تضمين نافذة إضافة/تعديل نوع المستأجر
// المفترض أن يتم إنشاء هذا الملف
// require_once __DIR__ . '/../includes/modals/tenant_type_modal.php'; 
echo ''; // مؤقتاً
require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <script>
function prepareTenantTypeModal(action, ttypeData = null) {
    const ttypeModal = document.getElementById('tenantTypeModal'); // اسم النافذة المنبثقة المفترض
    const modalTitle = ttypeModal.querySelector('.modal-title'); // اسم الكلاس داخل النافذة
    const ttypeForm = ttypeModal.querySelector('form'); // افتراض وجود form واحد
    const ttypeIdInput = ttypeModal.querySelector('input[name="tenant_type_id"]'); // اسم الحقل المخفي
    const actionInput = ttypeModal.querySelector('input[name="action"]');
    const submitButton = ttypeModal.querySelector('button[type="submit"] span'); // النص داخل زر الإرسال

    ttypeForm.reset();
    if(ttypeIdInput) ttypeIdInput.value = '';
    actionInput.value = action;

    if (action === 'add_tenant_type') {
        modalTitle.textContent = 'إضافة نوع مستأجر جديد';
        if(submitButton) submitButton.textContent = 'إضافة النوع';
    } else if (action === 'edit_tenant_type' && ttypeData) {
        modalTitle.textContent = 'تعديل نوع المستأجر: ' + ttypeData.display_name_ar;
        if(submitButton) submitButton.textContent = 'حفظ التعديلات';
        if(ttypeIdInput) ttypeIdInput.value = ttypeData.id;
        
        // ملء الحقول - تأكد من أن معرفات الحقول في النافذة المنبثقة tenant_type_modal.php صحيحة
        let nameInput = ttypeModal.querySelector('input[name="type_name"]');
        let displayNameInput = ttypeModal.querySelector('input[name="display_name_ar"]');
        if(nameInput) nameInput.value = ttypeData.type_name || '';
        if(displayNameInput) displayNameInput.value = ttypeData.display_name_ar || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var confirmDeleteModalTType = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModalTType) {
        confirmDeleteModalTType.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button.classList.contains('delete-tenant-type-btn')) {
                var itemName = button.getAttribute('data-name');
                var deleteUrl = button.getAttribute('data-delete-url');
                var modalBodyText = confirmDeleteModalTType.querySelector('.modal-body-text');
                if(modalBodyText) modalBodyText.textContent = 'هل أنت متأكد أنك تريد حذف نوع المستأجر "' + itemName + '"؟';
                
                var additionalInfo = confirmDeleteModalTType.querySelector('#additionalDeleteInfo');
                if(additionalInfo) additionalInfo.textContent = 'ملاحظة: لا يمكن حذف نوع المستأجر إذا كان مستخدماً لأي مستأجرين حاليين.';

                var confirmDeleteButton = confirmDeleteModalTType.querySelector('#confirmDeleteButton');
                if(confirmDeleteButton) {
                    var newConfirmDeleteButtonTType = confirmDeleteButton.cloneNode(true);
                    confirmDeleteButton.parentNode.replaceChild(newConfirmDeleteButtonTType, confirmDeleteButton);
                    
                    newConfirmDeleteButtonTType.setAttribute('data-delete-url', deleteUrl);
                    newConfirmDeleteButtonTType.removeAttribute('href');
                    
                    newConfirmDeleteButtonTType.addEventListener('click', function(e) {
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

    // يفترض أن يكون لديك نافذة منبثقة بالمعرف tenantTypeModal ونموذج بداخلها
    const ttypeFormElement = document.querySelector('#tenantTypeModal form'); // تعديل السلكتور
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
                if (data.success) {
                    var ttypeModalInstance = bootstrap.Modal.getInstance(document.getElementById('tenantTypeModal'));
                    if(ttypeModalInstance) ttypeModalInstance.hide();
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