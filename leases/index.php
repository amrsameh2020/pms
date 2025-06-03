<?php
$page_title = "إدارة عقود الإيجار";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// متغيرات التصفح
$current_page_lease = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1; // تم تغيير اسم المتغير
$items_per_page_lease = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10; // تم تغيير اسم المتغير
$offset_lease = ($current_page_lease - 1) * $items_per_page_lease; // تم تغيير اسم المتغير

// وظيفة البحث والفلترة
$search_term_lease = isset($_GET['search']) ? sanitize_input($_GET['search']) : ''; // تم تغيير اسم المتغير
$filter_status_lease = isset($_GET['status']) ? sanitize_input($_GET['status']) : ''; // تم تغيير اسم المتغير
$filter_property_id_lease = isset($_GET['property_id']) && filter_var($_GET['property_id'], FILTER_VALIDATE_INT) ? (int)$_GET['property_id'] : ''; // تم تغيير اسم المتغير
$filter_tenant_id_lease = isset($_GET['tenant_id']) && filter_var($_GET['tenant_id'], FILTER_VALIDATE_INT) ? (int)$_GET['tenant_id'] : ''; // تم تغيير اسم المتغير
$filter_lease_type_id_page = isset($_GET['lease_type_id']) && filter_var($_GET['lease_type_id'], FILTER_VALIDATE_INT) ? (int)$_GET['lease_type_id'] : ''; // فلتر جديد


$where_clauses_lease = []; // تم تغيير اسم المتغير
$params_for_count_lease = []; $types_for_count_lease = "";
$params_for_data_lease = [];  $types_for_data_lease = "";

if (!empty($search_term_lease)) {
    $where_clauses_lease[] = "(l.lease_contract_number LIKE ? OR t.full_name LIKE ? OR u.unit_number LIKE ? OR p.property_code LIKE ?)";
    $search_like_lease = "%" . $search_term_lease . "%";
    for ($i=0; $i<4; $i++) {
        $params_for_count_lease[] = $search_like_lease; $types_for_count_lease .= "s";
        $params_for_data_lease[] = $search_like_lease;  $types_for_data_lease .= "s";
    }
}
if (!empty($filter_status_lease)) {
    $where_clauses_lease[] = "l.status = ?";
    $params_for_count_lease[] = $filter_status_lease; $types_for_count_lease .= "s";
    $params_for_data_lease[] = $filter_status_lease;  $types_for_data_lease .= "s";
}
if (!empty($filter_property_id_lease)) {
    $where_clauses_lease[] = "p.id = ?";
    $params_for_count_lease[] = $filter_property_id_lease; $types_for_count_lease .= "i";
    $params_for_data_lease[] = $filter_property_id_lease;  $types_for_data_lease .= "i";
}
if (!empty($filter_tenant_id_lease)) {
    $where_clauses_lease[] = "l.tenant_id = ?";
    $params_for_count_lease[] = $filter_tenant_id_lease; $types_for_count_lease .= "i";
    $params_for_data_lease[] = $filter_tenant_id_lease;  $types_for_data_lease .= "i";
}
if (!empty($filter_lease_type_id_page)) {
    $where_clauses_lease[] = "l.lease_type_id = ?";
    $params_for_count_lease[] = $filter_lease_type_id_page; $types_for_count_lease .= "i";
    $params_for_data_lease[] = $filter_lease_type_id_page;  $types_for_data_lease .= "i";
}


$where_sql_lease = ""; // تم تغيير اسم المتغير
if (!empty($where_clauses_lease)) {
    $where_sql_lease = " WHERE " . implode(" AND ", $where_clauses_lease);
}

// الحصول على العدد الإجمالي للعقود
$total_sql_lease = "SELECT COUNT(l.id) as total 
              FROM leases l
              JOIN units u ON l.unit_id = u.id
              JOIN properties p ON u.property_id = p.id
              JOIN tenants t ON l.tenant_id = t.id
              LEFT JOIN lease_types lt ON l.lease_type_id = lt.id" . $where_sql_lease; // تم تغيير اسم المتغير و إضافة الربط
$stmt_total_lease = $mysqli->prepare($total_sql_lease);
$total_leases_page = 0; // تم تغيير اسم المتغير
if ($stmt_total_lease) {
    if (!empty($params_for_count_lease)) {
        $stmt_total_lease->bind_param($types_for_count_lease, ...$params_for_count_lease);
    }
    $stmt_total_lease->execute();
    $total_result_lease = $stmt_total_lease->get_result();
    $total_leases_page = ($total_result_lease && $total_result_lease->num_rows > 0) ? $total_result_lease->fetch_assoc()['total'] : 0;
    $stmt_total_lease->close();
} else {
    error_log("SQL Prepare Error for counting leases: " . $mysqli->error);
}
$total_pages_lease = ceil($total_leases_page / $items_per_page_lease); // تم تغيير اسم المتغير


// جلب العقود للصفحة الحالية
// تم تحديث الاستعلام ليشمل الحقول الجديدة واسم نوع العقد
$sql_lease = "SELECT l.id, l.lease_contract_number, l.lease_start_date, l.lease_end_date, l.rent_amount, l.status,
                     l.payment_frequency, l.payment_due_day, l.deposit_amount, l.grace_period_days, l.notes, l.contract_document_path,
                     l.lease_type_id, l.next_billing_date, l.last_billed_on, 
                     u.unit_number, u.id as unit_id_for_lease, 
                     p.name as property_name, p.property_code, 
                     t.full_name as tenant_name, t.id as tenant_id_for_lease, t.national_id_iqama as tenant_id_number,
                     lt.display_name_ar as lease_type_name
              FROM leases l
              JOIN units u ON l.unit_id = u.id
              JOIN properties p ON u.property_id = p.id
              JOIN tenants t ON l.tenant_id = t.id
              LEFT JOIN lease_types lt ON l.lease_type_id = lt.id"
       . $where_sql_lease . " ORDER BY l.lease_start_date DESC, l.id DESC LIMIT ? OFFSET ?";

$current_data_params_lease = $params_for_data_lease;
$current_data_params_lease[] = $items_per_page_lease;
$current_data_params_lease[] = $offset_lease;
$current_data_types_lease = $types_for_data_lease . 'ii';

$leases_list = []; // تم تغيير اسم المتغير
$stmt_lease = $mysqli->prepare($sql_lease);
if ($stmt_lease) {
    if (!empty($current_data_params_lease)) {
        $stmt_lease->bind_param($current_data_types_lease, ...$current_data_params_lease);
    } else {
         $stmt_lease->bind_param('ii', $items_per_page_lease, $offset_lease);
    }
    $stmt_lease->execute();
    $result_lease = $stmt_lease->get_result();
    $leases_list = ($result_lease && $result_lease->num_rows > 0) ? $result_lease->fetch_all(MYSQLI_ASSOC) : [];
    if ($stmt_lease) $stmt_lease->close();
} else {
    error_log("SQL Prepare Error for fetching leases: " . $mysqli->error);
}

// جلب قوائم للفلترة
$properties_filter_list_lease = []; // تم تغيير الاسم
$prop_q_lease = "SELECT id, name, property_code FROM properties ORDER BY name ASC";
if($prop_r_lease = $mysqli->query($prop_q_lease)){ while($row = $prop_r_lease->fetch_assoc()){ $properties_filter_list_lease[] = $row;} $prop_r_lease->free(); }

$tenants_filter_list_lease = []; // تم تغيير الاسم
$ten_q_lease = "SELECT id, full_name, national_id_iqama FROM tenants ORDER BY full_name ASC";
if($ten_r_lease = $mysqli->query($ten_q_lease)){ while($row = $ten_r_lease->fetch_assoc()){ $tenants_filter_list_lease[] = $row;} $ten_r_lease->free(); }

$lease_types_filter_list_page = []; // جلب أنواع العقود للفلتر
$ltypes_query_filter_page = "SELECT id, display_name_ar FROM lease_types ORDER BY display_name_ar ASC";
if($ltypes_result_filter_page = $mysqli->query($ltypes_query_filter_page)){
    while($ltype_row_filter_page = $ltypes_result_filter_page->fetch_assoc()){
        $lease_types_filter_list_page[] = $ltype_row_filter_page;
    }
    $ltypes_result_filter_page->free();
}


$lease_statuses_display_page = [ // تم تغيير الاسم
    'Pending' => 'معلق', 'Active' => 'نشط', 'Expired' => 'منتهي الصلاحية', 'Terminated' => 'ملغي', 'Draft' => 'مسودة'
];


$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-file-earmark-text-fill"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة عقود الإيجار (<?php echo $total_leases_page; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#leaseModal" onclick="prepareLeaseModal('add_lease')">
                    <i class="bi bi-plus-circle"></i> إضافة عقد جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('leases/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-3 col-lg-2">
                    <label for="search_leases_page" class="form-label form-label-sm visually-hidden">بحث عام</label>
                    <input type="text" id="search_leases_page" name="search" class="form-control form-control-sm" placeholder="رقم العقد، المستأجر، الوحدة..." value="<?php echo esc_attr($search_term_lease); ?>">
                </div>
                <div class="col-md-2 col-lg-2">
                     <label for="filter_status_lease_page" class="form-label form-label-sm visually-hidden">الحالة</label>
                    <select id="filter_status_lease_page" name="status" class="form-select form-select-sm">
                        <option value="">-- كل الحالات --</option>
                        <?php foreach ($lease_statuses_display_page as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filter_status_lease == $key) ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-2 col-lg-2">
                    <label for="filter_property_id_lease_page" class="form-label form-label-sm visually-hidden">العقار</label>
                    <select id="filter_property_id_lease_page" name="property_id" class="form-select form-select-sm">
                        <option value="">-- كل العقارات --</option>
                        <?php foreach ($properties_filter_list_lease as $prop_item_lease): ?>
                            <option value="<?php echo $prop_item_lease['id']; ?>" <?php echo ($filter_property_id_lease == $prop_item_lease['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($prop_item_lease['name'] . ' (' . $prop_item_lease['property_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                     <label for="filter_lease_type_id_page_filter" class="form-label form-label-sm visually-hidden">نوع العقد</label>
                     <select id="filter_lease_type_id_page_filter" name="lease_type_id" class="form-select form-select-sm">
                        <option value="">-- كل الأنواع --</option>
                        <?php foreach ($lease_types_filter_list_page as $ltype_filter_item): ?>
                            <option value="<?php echo $ltype_filter_item['id']; ?>" <?php echo ($filter_lease_type_id_page == $ltype_filter_item['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($ltype_filter_item['display_name_ar']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill"></i> فلترة</button>
                </div>
                <div class="col-md-1 col-lg-2">
                     <a href="<?php echo base_url('leases/index.php'); ?>" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-eraser-fill"></i> مسح</a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($leases_list) && (!empty($search_term_lease) || !empty($filter_status_lease) || !empty($filter_property_id_lease) || !empty($filter_tenant_id_lease) || !empty($filter_lease_type_id_page))): ?>
                <div class="alert alert-warning text-center">لا توجد عقود تطابق معايير البحث أو الفلترة.</div>
            <?php elseif (empty($leases_list)): ?>
                <div class="alert alert-info text-center">لا توجد عقود إيجار مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#leaseModal" onclick="prepareLeaseModal('add_lease')">إضافة عقد جديد</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>رقم العقد</th>
                            <th>العقار - الوحدة</th>
                            <th>المستأجر</th>
                            <th>نوع العقد</th>
                            <th>تاريخ البدء</th>
                            <th>تاريخ الانتهاء</th>
                            <th>مبلغ الإيجار</th>
                            <th>الحالة</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_lease = ($current_page_lease - 1) * $items_per_page_lease + 1; ?>
                        <?php foreach ($leases_list as $lease_item_page): // تم تغيير اسم المتغير ?>
                        <tr>
                            <td><?php echo $row_num_lease++; ?></td>
                            <td><?php echo esc_html($lease_item_page['lease_contract_number']); ?></td>
                            <td><?php echo esc_html($lease_item_page['property_name'] . ' - ' . $lease_item_page['unit_number']); ?></td>
                            <td><?php echo esc_html($lease_item_page['tenant_name']); ?> <small class="text-muted">(<?php echo esc_html($lease_item_page['tenant_id_number']); ?>)</small></td>
                            <td><?php echo esc_html($lease_item_page['lease_type_name'] ?: '-'); ?></td>
                            <td><?php echo format_date_custom($lease_item_page['lease_start_date'], 'Y-m-d'); ?></td>
                            <td><?php echo format_date_custom($lease_item_page['lease_end_date'], 'Y-m-d'); ?></td>
                            <td><?php echo number_format($lease_item_page['rent_amount'], 2); ?> ريال</td>
                            <td>
                                <?php
                                $status_class_lease = 'secondary'; // تم تغيير الاسم
                                if ($lease_item_page['status'] === 'Active') $status_class_lease = 'success';
                                elseif ($lease_item_page['status'] === 'Expired') $status_class_lease = 'danger';
                                elseif ($lease_item_page['status'] === 'Pending') $status_class_lease = 'warning';
                                elseif ($lease_item_page['status'] === 'Terminated') $status_class_lease = 'dark';
                                ?>
                                <span class="badge bg-<?php echo $status_class_lease; ?>"><?php echo esc_html($lease_statuses_display_page[$lease_item_page['status']] ?? $lease_item_page['status']); ?></span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="prepareLeaseModal('edit_lease', <?php echo htmlspecialchars(json_encode($lease_item_page), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#leaseModal"
                                        title="تعديل بيانات العقد">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-lease-btn"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                        data-id="<?php echo $lease_item_page['id']; ?>"
                                        data-name="العقد رقم <?php echo esc_attr($lease_item_page['lease_contract_number']); ?>"
                                        data-delete-url="<?php echo base_url('leases/lease_actions.php?action=delete_lease&id=' . $lease_item_page['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        title="حذف العقد">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php if (!empty($lease_item_page['contract_document_path'])): ?>
                                    <a href="<?php echo base_url(esc_attr($lease_item_page['contract_document_path'])); ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="عرض مستند العقد">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                <?php endif; ?>
                                 <a href="<?php echo base_url('invoices/index.php?lease_id=' . $lease_item_page['id']); ?>" class="btn btn-sm btn-outline-success" title="عرض فواتير هذا العقد">
                                    <i class="bi bi-receipt"></i> <span class="d-none d-md-inline">الفواتير</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages_lease > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_lease = []; // تم تغيير الاسم
            if (!empty($search_term_lease)) $pagination_params_lease['search'] = $search_term_lease;
            if (!empty($filter_status_lease)) $pagination_params_lease['status'] = $filter_status_lease;
            if (!empty($filter_property_id_lease)) $pagination_params_lease['property_id'] = $filter_property_id_lease;
            if (!empty($filter_tenant_id_lease)) $pagination_params_lease['tenant_id'] = $filter_tenant_id_lease;
            if (!empty($filter_lease_type_id_page)) $pagination_params_lease['lease_type_id'] = $filter_lease_type_id_page;
            echo generate_pagination_links($current_page_lease, $total_pages_lease, 'leases/index.php', $pagination_params_lease);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/lease_modal.php';
require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <script>
function prepareLeaseModal(action, leaseData = null) {
    const leaseModal = document.getElementById('leaseModal'); // معرف النافذة المنبثقة
    const modalTitle = leaseModal.querySelector('#leaseModalLabel_leases');
    const leaseForm = leaseModal.querySelector('#leaseFormModal');
    const leaseIdInput = leaseModal.querySelector('#lease_id_modal_leases');
    const actionInput = leaseModal.querySelector('#lease_form_action_modal_leases');
    const submitButton = leaseModal.querySelector('#leaseSubmitButtonTextModalLeases');
    const currentDocSpan = leaseModal.querySelector('#current_contract_document_modal_leases');
    const unitStatusWarningLease = leaseModal.querySelector('#unit_status_warning_modal_leases');


    leaseForm.reset();
    leaseIdInput.value = '';
    actionInput.value = action;
    currentDocSpan.innerHTML = ''; // مسح معلومات المستند الحالي
    if(unitStatusWarningLease) unitStatusWarningLease.classList.add('d-none');


    const formUrl = '<?php echo base_url('leases/lease_actions.php'); ?>';
    // leaseForm.action = formUrl; // ليس ضروريًا لـ fetch

    if (action === 'add_lease') {
        modalTitle.textContent = 'إضافة عقد إيجار جديد';
        submitButton.textContent = 'إضافة العقد';
         // Set default dates
        const today = new Date().toISOString().slice(0,10);
        if(document.getElementById('lease_start_date_modal_leases')) document.getElementById('lease_start_date_modal_leases').value = today;
        // Calculate end date one year from today for example
        let endDate = new Date();
        endDate.setFullYear(endDate.getFullYear() + 1);
        if(document.getElementById('lease_end_date_modal_leases')) document.getElementById('lease_end_date_modal_leases').value = endDate.toISOString().slice(0,10);
        if(document.getElementById('lease_status_modal_leases')) document.getElementById('lease_status_modal_leases').value = 'Pending'; // Default status

    } else if (action === 'edit_lease' && leaseData) {
        modalTitle.textContent = 'تعديل بيانات عقد الإيجار: ' + leaseData.lease_contract_number;
        submitButton.textContent = 'حفظ التعديلات';
        leaseIdInput.value = leaseData.id;
        
        // ملء حقول النموذج ببيانات العقد للتعديل
        if(document.getElementById('lease_contract_number_modal_leases')) document.getElementById('lease_contract_number_modal_leases').value = leaseData.lease_contract_number || '';
        if(document.getElementById('unit_id_modal_leases')) {
            document.getElementById('unit_id_modal_leases').value = leaseData.unit_id_for_lease || leaseData.unit_id || ''; // unit_id_for_lease from SELECT alias
             // Trigger change to show warning if unit is not vacant
            var unitSelectEdit = document.getElementById('unit_id_modal_leases');
            var selectedUnitOptionEdit = unitSelectEdit.options[unitSelectEdit.selectedIndex];
            if (selectedUnitOptionEdit && unitStatusWarningLease) {
                var unitStatusEdit = selectedUnitOptionEdit.getAttribute('data-unit-status');
                 if (unitStatusEdit && unitStatusEdit.toLowerCase() !== 'vacant') {
                    unitStatusWarningLease.classList.remove('d-none');
                    unitStatusWarningLease.textContent = 'تحذير: هذه الوحدة حاليًا "' + unitStatusEdit + '".';
                }
            }
        }
        if(document.getElementById('tenant_id_modal_leases')) document.getElementById('tenant_id_modal_leases').value = leaseData.tenant_id_for_lease || leaseData.tenant_id || '';
        if(document.getElementById('lease_type_id_modal_leases')) document.getElementById('lease_type_id_modal_leases').value = leaseData.lease_type_id || '';
        if(document.getElementById('lease_start_date_modal_leases')) document.getElementById('lease_start_date_modal_leases').value = leaseData.lease_start_date || '';
        if(document.getElementById('lease_end_date_modal_leases')) document.getElementById('lease_end_date_modal_leases').value = leaseData.lease_end_date || '';
        if(document.getElementById('rent_amount_modal_leases')) document.getElementById('rent_amount_modal_leases').value = leaseData.rent_amount || '';
        if(document.getElementById('payment_frequency_modal_leases')) document.getElementById('payment_frequency_modal_leases').value = leaseData.payment_frequency || 'Monthly';
        if(document.getElementById('payment_due_day_modal_leases')) document.getElementById('payment_due_day_modal_leases').value = leaseData.payment_due_day || '';
        if(document.getElementById('deposit_amount_modal_leases')) document.getElementById('deposit_amount_modal_leases').value = leaseData.deposit_amount || '0.00';
        if(document.getElementById('grace_period_days_modal_leases')) document.getElementById('grace_period_days_modal_leases').value = leaseData.grace_period_days || '0';
        if(document.getElementById('lease_status_modal_leases')) document.getElementById('lease_status_modal_leases').value = leaseData.status || '';
        if(document.getElementById('next_billing_date_modal_leases')) document.getElementById('next_billing_date_modal_leases').value = leaseData.next_billing_date || '';
        if(document.getElementById('last_billed_on_modal_leases')) document.getElementById('last_billed_on_modal_leases').value = leaseData.last_billed_on || '';
        if(document.getElementById('lease_notes_modal_leases')) document.getElementById('lease_notes_modal_leases').value = leaseData.notes || '';
        
        if (leaseData.contract_document_path && leaseData.contract_document_path !== '') {
            var filename = leaseData.contract_document_path.split('/').pop();
            currentDocSpan.innerHTML = 'المستند الحالي: <a href="<?php echo base_url(); ?>' + leaseData.contract_document_path + '" target="_blank">' + filename + '</a> (لإبقائه، لا ترفع ملف جديد)';
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var confirmDeleteModalLeasePage = document.getElementById('confirmDeleteModal'); // تم تغيير الاسم
    if (confirmDeleteModalLeasePage) {
        confirmDeleteModalLeasePage.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button.classList.contains('delete-lease-btn')) {
                var itemName = button.getAttribute('data-name');
                var deleteUrl = button.getAttribute('data-delete-url');
                var modalBodyText = confirmDeleteModalLeasePage.querySelector('.modal-body-text');
                if(modalBodyText) modalBodyText.textContent = 'هل أنت متأكد أنك تريد حذف ' + itemName + '؟';
                
                var additionalInfo = confirmDeleteModalLeasePage.querySelector('#additionalDeleteInfo');
                if(additionalInfo) additionalInfo.textContent = 'ملاحظة: حذف العقد لا يحذف الفواتير المرتبطة به تلقائياً، ولكن قد يجعلها يتيمة إذا لم يتم التعامل معها.';

                var confirmDeleteButton = confirmDeleteModalLeasePage.querySelector('#confirmDeleteButton');
                if(confirmDeleteButton) {
                    var newConfirmDeleteButtonLease = confirmDeleteButton.cloneNode(true); // تم تغيير الاسم
                    confirmDeleteButton.parentNode.replaceChild(newConfirmDeleteButtonLease, confirmDeleteButton);
                    
                    newConfirmDeleteButtonLease.setAttribute('data-delete-url', deleteUrl);
                    newConfirmDeleteButtonLease.removeAttribute('href');
                    
                    newConfirmDeleteButtonLease.addEventListener('click', function(e) {
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

    const leaseFormElement = document.getElementById('leaseFormModal'); // تم تغيير ID
    if(leaseFormElement) {
        leaseFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(leaseFormElement);
            const actionUrl = leaseFormElement.getAttribute('action');

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var leaseModalInstance = bootstrap.Modal.getInstance(document.getElementById('leaseModal'));
                    if(leaseModalInstance) leaseModalInstance.hide();
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