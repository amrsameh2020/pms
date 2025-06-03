<?php
$page_title = "إدارة المستأجرين";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// Pagination variables
$current_page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset = ($current_page - 1) * $items_per_page;

// Search and filter functionality
$search_term_tenant = isset($_GET['search']) ? sanitize_input($_GET['search']) : ''; // تم تغيير الاسم ليكون فريداً
$filter_tenant_type_id_page = isset($_GET['tenant_type_id']) && filter_var($_GET['tenant_type_id'], FILTER_VALIDATE_INT) ? (int)$_GET['tenant_type_id'] : '';


$where_clauses_tenant = []; // تم تغيير الاسم
$params_for_count_tenant = []; $types_for_count_tenant = "";
$params_for_data_tenant = [];  $types_for_data_tenant = "";

if (!empty($search_term_tenant)) {
    $where_clauses_tenant[] = "(t.full_name LIKE ? OR t.national_id_iqama LIKE ? OR t.phone_primary LIKE ? OR t.email LIKE ?)";
    $search_like_tenant = "%" . $search_term_tenant . "%";
    for ($i=0; $i<4; $i++) {
        $params_for_count_tenant[] = $search_like_tenant; $types_for_count_tenant .= "s";
        $params_for_data_tenant[] = $search_like_tenant;  $types_for_data_tenant .= "s";
    }
}
if (!empty($filter_tenant_type_id_page)) {
    $where_clauses_tenant[] = "t.tenant_type_id = ?";
    $params_for_count_tenant[] = $filter_tenant_type_id_page; $types_for_count_tenant .= "i";
    $params_for_data_tenant[] = $filter_tenant_type_id_page;  $types_for_data_tenant .= "i";
}


$where_sql_tenant = "";
if (!empty($where_clauses_tenant)) {
    $where_sql_tenant = " WHERE " . implode(" AND ", $where_clauses_tenant);
}

// Get total number of tenants
$total_sql_tenant = "SELECT COUNT(t.id) as total 
                     FROM tenants t" . $where_sql_tenant; // تم تعديل الاسم
$stmt_total_tenant = $mysqli->prepare($total_sql_tenant);
if ($stmt_total_tenant) {
    if (!empty($params_for_count_tenant)) {
        $stmt_total_tenant->bind_param($types_for_count_tenant, ...$params_for_count_tenant);
    }
    $stmt_total_tenant->execute();
    $total_result_tenant = $stmt_total_tenant->get_result();
    $total_tenants = ($total_result_tenant && $total_result_tenant->num_rows > 0) ? $total_result_tenant->fetch_assoc()['total'] : 0;
    $stmt_total_tenant->close();
} else {
    $total_tenants = 0;
    error_log("SQL Prepare Error for counting tenants: " . $mysqli->error);
}
$total_pages_tenant = ceil($total_tenants / $items_per_page); // تم تغيير الاسم

// Fetch tenants for the current page
// تم تحديث الاستعلام ليشمل الحقول الجديدة واسم نوع المستأجر
$sql_tenant = "SELECT t.id, t.full_name, t.national_id_iqama, t.phone_primary, t.phone_secondary, t.email, 
                      t.current_address, t.occupation, t.nationality, t.notes, 
                      t.buyer_vat_number, t.buyer_street_name, t.buyer_building_no, t.buyer_additional_no, 
                      t.buyer_district_name, t.buyer_city_name, t.buyer_postal_code, t.buyer_country_code,
                      t.emergency_contact_name, t.emergency_contact_phone,
                      t.tenant_type_id, tt.display_name_ar as tenant_type_name, t.gender, t.date_of_birth
               FROM tenants t
               LEFT JOIN tenant_types tt ON t.tenant_type_id = tt.id" 
               . $where_sql_tenant . " ORDER BY t.full_name ASC LIMIT ? OFFSET ?";

$current_data_params_tenant = $params_for_data_tenant;
$current_data_params_tenant[] = $items_per_page;
$current_data_params_tenant[] = $offset;
$current_data_types_tenant = $types_for_data_tenant . 'ii';

$tenants_list = []; // تم تغيير الاسم
$stmt_tenant = $mysqli->prepare($sql_tenant);
if ($stmt_tenant) {
    if (!empty($current_data_params_tenant)) { 
        $stmt_tenant->bind_param($current_data_types_tenant, ...$current_data_params_tenant);
    } else {
         $stmt_tenant->bind_param('ii', $items_per_page, $offset);
    }
    $stmt_tenant->execute();
    $result_tenant = $stmt_tenant->get_result();
    $tenants_list = ($result_tenant && $result_tenant->num_rows > 0) ? $result_tenant->fetch_all(MYSQLI_ASSOC) : [];
    if ($stmt_tenant) $stmt_tenant->close();
} else {
    error_log("SQL Prepare Error for fetching tenants: " . $mysqli->error);
}

// جلب قائمة أنواع المستأجرين للفلتر
$tenant_types_filter_list_page = [];
$ttypes_query_filter_idx = "SELECT id, display_name_ar FROM tenant_types ORDER BY display_name_ar ASC";
if($ttypes_result_filter_idx = $mysqli->query($ttypes_query_filter_idx)){
    while($ttype_row_filter_idx = $ttypes_result_filter_idx->fetch_assoc()){
        $tenant_types_filter_list_page[] = $ttype_row_filter_idx;
    }
    $ttypes_result_filter_idx->free();
}


$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-person-badge-fill"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة المستأجرين (<?php echo $total_tenants; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#tenantModal" onclick="prepareTenantModal('add_tenant')">
                    <i class="bi bi-person-plus-fill"></i> إضافة مستأجر جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('tenants/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-5 col-lg-6">
                    <label for="search_tenants_page" class="form-label form-label-sm visually-hidden">بحث</label>
                    <input type="text" id="search_tenants_page" name="search" class="form-control form-control-sm" placeholder="ابحث بالاسم، رقم الهوية/الإقامة، الجوال، البريد..." value="<?php echo esc_attr($search_term_tenant); ?>">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filter_tenant_type_id_page_filter" class="form-label form-label-sm visually-hidden">نوع المستأجر</label>
                    <select id="filter_tenant_type_id_page_filter" name="tenant_type_id" class="form-select form-select-sm">
                        <option value="">-- فلترة حسب النوع --</option>
                         <?php foreach ($tenant_types_filter_list_page as $ttype_filter_item): ?>
                            <option value="<?php echo $ttype_filter_item['id']; ?>" <?php echo ($filter_tenant_type_id_page == $ttype_filter_item['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($ttype_filter_item['display_name_ar']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill"></i> بحث</button>
                </div>
                <div class="col-md-2 col-lg-2">
                     <a href="<?php echo base_url('tenants/index.php'); ?>" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-eraser-fill"></i> مسح</a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($tenants_list) && (!empty($search_term_tenant) || !empty($filter_tenant_type_id_page)) ): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج بحث تطابق معايير البحث أو الفلترة.</div>
            <?php elseif (empty($tenants_list)): ?>
                <div class="alert alert-info text-center">لا يوجد مستأجرون مسجلون حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#tenantModal" onclick="prepareTenantModal('add_tenant')">إضافة مستأجر جديد</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>الاسم الكامل</th>
                            <th>رقم الهوية/الإقامة</th>
                            <th>الجوال الأساسي</th>
                            <th>نوع المستأجر</th>
                            <th>البريد الإلكتروني</th>
                            <th class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_tenant = ($current_page - 1) * $items_per_page + 1; ?>
                        <?php foreach ($tenants_list as $tenant_item_page): // تم تغيير اسم المتغير ?>
                        <tr>
                            <td><?php echo $row_num_tenant++; ?></td>
                            <td><?php echo esc_html($tenant_item_page['full_name']); ?></td>
                            <td><?php echo esc_html($tenant_item_page['national_id_iqama']); ?></td>
                            <td><?php echo esc_html($tenant_item_page['phone_primary']); ?></td>
                            <td><?php echo esc_html($tenant_item_page['tenant_type_name'] ?: '-'); ?></td>
                            <td><?php echo esc_html($tenant_item_page['email'] ?: '-'); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="prepareTenantModal('edit_tenant', <?php echo htmlspecialchars(json_encode($tenant_item_page), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#tenantModal"
                                        title="تعديل بيانات المستأجر">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-tenant-btn"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                        data-id="<?php echo $tenant_item_page['id']; ?>"
                                        data-name="<?php echo esc_attr($tenant_item_page['full_name']); ?>"
                                        data-delete-url="<?php echo base_url('tenants/tenant_actions.php?action=delete_tenant&id=' . $tenant_item_page['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        title="حذف المستأجر">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <a href="<?php echo base_url('leases/index.php?tenant_id=' . $tenant_item_page['id']); ?>" class="btn btn-sm btn-outline-info" title="عرض عقود الإيجار لهذا المستأجر">
                                    <i class="bi bi-file-earmark-text-fill"></i> <span class="d-none d-md-inline">العقود</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages_tenant > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_tenant = []; // تم تغيير الاسم
            if (!empty($search_term_tenant)) $pagination_params_tenant['search'] = $search_term_tenant;
            if (!empty($filter_tenant_type_id_page)) $pagination_params_tenant['tenant_type_id'] = $filter_tenant_type_id_page;
            echo generate_pagination_links($current_page, $total_pages_tenant, 'tenants/index.php', $pagination_params_tenant);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/tenant_modal.php';
require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <script>
function prepareTenantModal(action, tenantData = null) {
    const tenantModal = document.getElementById('tenantModal'); // معرف النافذة المنبثقة
    const modalTitle = tenantModal.querySelector('#tenantModalLabel_tenants');
    const tenantForm = tenantModal.querySelector('#tenantFormModal');
    const tenantIdInput = tenantModal.querySelector('#tenant_id_modal_tenants');
    const actionInput = tenantModal.querySelector('#tenant_form_action_modal_tenants');
    const submitButton = tenantModal.querySelector('#tenantSubmitButtonTextModal'); // معرف زر الإرسال

    tenantForm.reset();
    tenantIdInput.value = '';
    actionInput.value = action;
    
    // Reset to the first tab
    var firstTabElTenant = tenantModal.querySelector('#nav-basic-info-tab-modal');
    if (firstTabElTenant) {
        var firstTabTenant = new bootstrap.Tab(firstTabElTenant);
        firstTabTenant.show();
    }

    const formUrl = '<?php echo base_url('tenants/tenant_actions.php'); ?>';
    // tenantForm.action = formUrl; // Not strictly needed for AJAX submission

    if (action === 'add_tenant') {
        modalTitle.textContent = 'إضافة مستأجر جديد';
        submitButton.textContent = 'إضافة المستأجر';
    } else if (action === 'edit_tenant' && tenantData) {
        modalTitle.textContent = 'تعديل بيانات المستأجر: ' + tenantData.full_name;
        submitButton.textContent = 'حفظ التعديلات';
        tenantIdInput.value = tenantData.id;
        
        // Populate Basic Info Tab
        if(document.getElementById('tenant_full_name_modal_tenants')) document.getElementById('tenant_full_name_modal_tenants').value = tenantData.full_name || '';
        if(document.getElementById('tenant_national_id_iqama_modal_tenants')) document.getElementById('tenant_national_id_iqama_modal_tenants').value = tenantData.national_id_iqama || '';
        if(document.getElementById('tenant_type_id_modal_tenants')) document.getElementById('tenant_type_id_modal_tenants').value = tenantData.tenant_type_id || '';
        if(document.getElementById('tenant_phone_primary_modal_tenants')) document.getElementById('tenant_phone_primary_modal_tenants').value = tenantData.phone_primary || '';
        if(document.getElementById('tenant_phone_secondary_modal_tenants')) document.getElementById('tenant_phone_secondary_modal_tenants').value = tenantData.phone_secondary || '';
        if(document.getElementById('tenant_email_modal_tenants')) document.getElementById('tenant_email_modal_tenants').value = tenantData.email || '';
        if(document.getElementById('tenant_gender_modal_tenants')) document.getElementById('tenant_gender_modal_tenants').value = tenantData.gender || '';
        if(document.getElementById('tenant_dob_modal_tenants')) document.getElementById('tenant_dob_modal_tenants').value = tenantData.date_of_birth || '';
        if(document.getElementById('tenant_current_address_modal_tenants')) document.getElementById('tenant_current_address_modal_tenants').value = tenantData.current_address || '';
        if(document.getElementById('tenant_occupation_modal_tenants')) document.getElementById('tenant_occupation_modal_tenants').value = tenantData.occupation || '';
        if(document.getElementById('tenant_nationality_modal_tenants')) document.getElementById('tenant_nationality_modal_tenants').value = tenantData.nationality || '';
        if(document.getElementById('tenant_notes_modal_tenants')) document.getElementById('tenant_notes_modal_tenants').value = tenantData.notes || '';

        // Populate ZATCA Buyer Info Tab
        if(document.getElementById('tenant_buyer_vat_number_modal_tenants')) document.getElementById('tenant_buyer_vat_number_modal_tenants').value = tenantData.buyer_vat_number || '';
        if(document.getElementById('tenant_buyer_street_name_modal_tenants')) document.getElementById('tenant_buyer_street_name_modal_tenants').value = tenantData.buyer_street_name || '';
        if(document.getElementById('tenant_buyer_building_no_modal_tenants')) document.getElementById('tenant_buyer_building_no_modal_tenants').value = tenantData.buyer_building_no || '';
        if(document.getElementById('tenant_buyer_additional_no_modal_tenants')) document.getElementById('tenant_buyer_additional_no_modal_tenants').value = tenantData.buyer_additional_no || '';
        if(document.getElementById('tenant_buyer_district_name_modal_tenants')) document.getElementById('tenant_buyer_district_name_modal_tenants').value = tenantData.buyer_district_name || '';
        if(document.getElementById('tenant_buyer_city_name_modal_tenants')) document.getElementById('tenant_buyer_city_name_modal_tenants').value = tenantData.buyer_city_name || '';
        if(document.getElementById('tenant_buyer_postal_code_modal_tenants')) document.getElementById('tenant_buyer_postal_code_modal_tenants').value = tenantData.buyer_postal_code || '';
        if(document.getElementById('tenant_buyer_country_code_modal_tenants')) document.getElementById('tenant_buyer_country_code_modal_tenants').value = tenantData.buyer_country_code || 'SA';
        
        // Populate Emergency Contact Tab
        if(document.getElementById('tenant_emergency_contact_name_modal_tenants')) document.getElementById('tenant_emergency_contact_name_modal_tenants').value = tenantData.emergency_contact_name || '';
        if(document.getElementById('tenant_emergency_contact_phone_modal_tenants')) document.getElementById('tenant_emergency_contact_phone_modal_tenants').value = tenantData.emergency_contact_phone || '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var confirmDeleteModalTenantPage = document.getElementById('confirmDeleteModal'); // تم تغيير الاسم
    if (confirmDeleteModalTenantPage) {
        confirmDeleteModalTenantPage.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button.classList.contains('delete-tenant-btn')) {
                var itemName = button.getAttribute('data-name');
                var deleteUrl = button.getAttribute('data-delete-url');
                var modalBodyText = confirmDeleteModalTenantPage.querySelector('.modal-body-text');
                if(modalBodyText) modalBodyText.textContent = 'هل أنت متأكد أنك تريد حذف المستأجر "' + itemName + '"؟';
                var additionalInfo = confirmDeleteModalTenantPage.querySelector('#additionalDeleteInfo');
                if(additionalInfo) additionalInfo.textContent = 'ملاحظة: قد تكون هناك عقود إيجار أو فواتير مرتبطة بهذا المستأجر.';
                
                var confirmDeleteButton = confirmDeleteModalTenantPage.querySelector('#confirmDeleteButton');
                if(confirmDeleteButton) {
                    var newConfirmDeleteButtonTenant = confirmDeleteButton.cloneNode(true); // تم تغيير الاسم
                    confirmDeleteButton.parentNode.replaceChild(newConfirmDeleteButtonTenant, confirmDeleteButton);
                    
                    newConfirmDeleteButtonTenant.setAttribute('data-delete-url', deleteUrl);
                    newConfirmDeleteButtonTenant.removeAttribute('href');
                    
                    newConfirmDeleteButtonTenant.addEventListener('click', function(e) {
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

    // Handle AJAX form submission for tenantFormModal
    const tenantFormElement = document.getElementById('tenantFormModal'); // تم تغيير ID
    if(tenantFormElement) {
        tenantFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(tenantFormElement);
            const actionUrl = tenantFormElement.getAttribute('action');

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var tenantModalInstance = bootstrap.Modal.getInstance(document.getElementById('tenantModal'));
                    if(tenantModalInstance) tenantModalInstance.hide();
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