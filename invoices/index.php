<?php
$page_title = "إدارة الفواتير";
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
$search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : ''; 
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : ''; 
$filter_zatca_status = isset($_GET['zatca_status']) ? sanitize_input($_GET['zatca_status']) : '';
$filter_lease_id = isset($_GET['lease_id']) && filter_var($_GET['lease_id'], FILTER_VALIDATE_INT) ? (int)$_GET['lease_id'] : '';
$filter_date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';


$where_clauses = [];
$params_for_count = []; $types_for_count = "";
$params_for_data = [];  $types_for_data = "";

if (!empty($search_term)) {
    $where_clauses[] = "(i.invoice_number LIKE ? OR t.full_name LIKE ? OR l.lease_contract_number LIKE ?)";
    $search_like = "%" . $search_term . "%";
    for ($k=0; $k<3; $k++) { 
        $params_for_count[] = $search_like; $types_for_count .= "s";
        $params_for_data[] = $search_like;  $types_for_data .= "s";
    }
}
if (!empty($filter_status)) {
    $where_clauses[] = "i.status = ?";
    $params_for_count[] = $filter_status; $types_for_count .= "s";
    $params_for_data[] = $filter_status;  $types_for_data .= "s";
}
if (!empty($filter_zatca_status)) {
    $where_clauses[] = "i.zatca_status = ?";
    $params_for_count[] = $filter_zatca_status; $types_for_count .= "s";
    $params_for_data[] = $filter_zatca_status;  $types_for_data .= "s";
}
if (!empty($filter_lease_id)) {
    $where_clauses[] = "i.lease_id = ?";
    $params_for_count[] = $filter_lease_id; $types_for_count .= "i";
    $params_for_data[] = $filter_lease_id;  $types_for_data .= "i";
}
if (!empty($filter_date_from)) {
    $where_clauses[] = "i.invoice_date >= ?";
    $params_for_count[] = $filter_date_from; $types_for_count .= "s";
    $params_for_data[] = $filter_date_from;  $types_for_data .= "s";
}
if (!empty($filter_date_to)) {
    $where_clauses[] = "i.invoice_date <= ?";
    $params_for_count[] = $filter_date_to; $types_for_count .= "s";
    $params_for_data[] = $filter_date_to;  $types_for_data .= "s";
}


$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Get total number of invoices
$total_sql = "SELECT COUNT(i.id) as total 
              FROM invoices i
              LEFT JOIN leases l ON i.lease_id = l.id
              LEFT JOIN tenants t ON i.tenant_id = t.id" . $where_sql;
$stmt_total = $mysqli->prepare($total_sql);
$total_invoices = 0;
if ($stmt_total) {
    if (!empty($params_for_count) && $types_for_count !== '') {
        $stmt_total->bind_param($types_for_count, ...$params_for_count);
    }
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total_invoices = ($total_result) ? $total_result->fetch_assoc()['total'] : 0;
    $stmt_total->close();
} else {
    error_log("SQL Prepare Error for counting invoices: " . $mysqli->error);
}
$total_pages = ceil($total_invoices / $items_per_page);


// Fetch invoices for the current page
$sql = "SELECT i.*, l.lease_contract_number, t.full_name as tenant_name
        FROM invoices i
        LEFT JOIN leases l ON i.lease_id = l.id
        LEFT JOIN tenants t ON i.tenant_id = t.id"
       . $where_sql . " ORDER BY i.invoice_date DESC, i.id DESC LIMIT ? OFFSET ?";

$current_data_params = $params_for_data;
$current_data_params[] = $items_per_page;
$current_data_params[] = $offset;
$current_data_types = $types_for_data . 'ii';

$stmt = $mysqli->prepare($sql);
$invoices = [];
if ($stmt) {
    if (!empty($current_data_params) && $current_data_types !== '') {
        $stmt->bind_param($current_data_types, ...$current_data_params);
    } else {
         $stmt->bind_param('ii', $items_per_page, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $invoices = ($result) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    if ($stmt) $stmt->close();
} else {
    error_log("SQL Prepare Error for fetching invoices: " . $mysqli->error);
}

$invoice_statuses_display_filter = ['' => '-- الكل --'] + (isset($invoice_statuses_display) ? $invoice_statuses_display : ['Draft' => 'مسودة', 'Unpaid' => 'غير مدفوعة', 'Partially Paid' => 'مدفوعة جزئياً', 'Paid' => 'مدفوعة', 'Overdue' => 'متأخرة', 'Cancelled' => 'ملغاة', 'Void' => 'لاغية']);
$zatca_statuses_display_filter = [
    '' => '-- الكل --', 'Not Sent' => 'لم ترسل', 'Sent' => 'مرسلة', 'Generating' => 'قيد الإنشاء',
    'Compliance Check Pending' => 'فحص الامتثال معلق', 'Compliance Check Failed' => 'فشل فحص الامتثال',
    'Compliance Check Passed' => 'نجح فحص الامتثال', 'Clearance Pending' => 'التصريح معلق',
    'Cleared' => 'تم التصريح', 'Reporting Pending' => 'الإبلاغ معلق', 'Reported' => 'تم الإبلاغ',
    'Rejected' => 'مرفوضة', 'Error' => 'خطأ'
];

if (!isset($active_leases_list_for_modal)) { 
    $active_leases_list_for_modal = [];
    $leases_q_filter = "SELECT l.id as lease_id, l.lease_contract_number, t.full_name as tenant_name, t.id as tenant_id_for_lease_in_invoice_modal
                       FROM leases l
                       JOIN tenants t ON l.tenant_id = t.id
                       WHERE l.status = 'Active' OR l.status = 'Pending'
                       ORDER BY t.full_name ASC, l.lease_start_date DESC";
    if($leases_r_filter = $mysqli->query($leases_q_filter)){
        while($lease_r_filter = $leases_r_filter->fetch_assoc()){ $active_leases_list_for_modal[] = $lease_r_filter; }
        $leases_r_filter->free();
    }
}
$active_leases_filter_options = ['' => '-- الكل --'];
foreach($active_leases_list_for_modal as $l_f){
    $active_leases_filter_options[$l_f['lease_id']] = $l_f['lease_contract_number'] . ' (' . $l_f['tenant_name'] . ')';
}


$csrf_token = generate_csrf_token();
$default_vat_percentage_js = defined('VAT_PERCENTAGE') ? VAT_PERCENTAGE : 15.00;
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-receipt-cutoff"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الفواتير (<?php echo $total_invoices; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#invoiceModal" onclick="prepareInvoiceModal('add_invoice')">
                    <i class="bi bi-plus-circle"></i> إنشاء فاتورة جديدة
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('invoices/index.php'); ?>" class="row gx-2 gy-2 align-items-end">
                <div class="col-md-3 col-lg-2">
                    <label for="search_invoices" class="form-label form-label-sm">بحث عام</label>
                    <input type="text" id="search_invoices" name="search" class="form-control form-control-sm" placeholder="رقم الفاتورة، المستأجر، عقد..." value="<?php echo esc_attr($search_term); ?>">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label for="filter_status_inv" class="form-label form-label-sm">حالة الفاتورة</label>
                    <select id="filter_status_inv" name="status" class="form-select form-select-sm">
                        <?php foreach ($invoice_statuses_display_filter as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filter_status == $key && $filter_status !== '') ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-2 col-lg-2">
                    <label for="filter_zatca_status_inv" class="form-label form-label-sm">حالة ZATCA</label>
                    <select id="filter_zatca_status_inv" name="zatca_status" class="form-select form-select-sm">
                        <?php foreach ($zatca_statuses_display_filter as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filter_zatca_status == $key && $filter_zatca_status !== '') ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label for="filter_date_from" class="form-label form-label-sm">من تاريخ فاتورة</label>
                    <input type="date" id="filter_date_from" name="date_from" class="form-control form-control-sm" value="<?php echo esc_attr($filter_date_from); ?>">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label for="filter_date_to" class="form-label form-label-sm">إلى تاريخ فاتورة</label>
                    <input type="date" id="filter_date_to" name="date_to" class="form-control form-control-sm" value="<?php echo esc_attr($filter_date_to); ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill"></i></button>
                </div>
                 <div class="col-md-1">
                     <a href="<?php echo base_url('invoices/index.php'); ?>" class="btn btn-outline-secondary btn-sm w-100" title="مسح الفلاتر"><i class="bi bi-eraser-fill"></i></a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($invoices) && (!empty($search_term) || !empty($filter_status) || !empty($filter_zatca_status) || !empty($filter_lease_id) || !empty($filter_date_from) || !empty($filter_date_to))): ?>
                <div class="alert alert-warning text-center">لا توجد فواتير تطابق معايير البحث أو الفلترة.</div>
            <?php elseif (empty($invoices)): ?>
                <div class="alert alert-info text-center">لا توجد فواتير مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#invoiceModal" onclick="prepareInvoiceModal('add_invoice')">إنشاء فاتورة جديدة</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>رقم الفاتورة (ICV)</th>
                            <th>المستأجر/العميل</th>
                            <th>العقد</th>
                            <th>تاريخ الفاتورة</th>
                            <th>تاريخ الاستحقاق</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>الحالة</th>
                            <th>حالة ZATCA</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = ($current_page - 1) * $items_per_page + 1; ?>
                        <?php foreach ($invoices as $invoice):
                            $balance = $invoice['total_amount'] - $invoice['paid_amount'];
                            $status_class = 'secondary';
                            if ($invoice['status'] === 'Paid') $status_class = 'success';
                            elseif (in_array($invoice['status'], ['Unpaid', 'Partially Paid'])) $status_class = 'warning';
                            elseif ($invoice['status'] === 'Overdue') $status_class = 'danger';
                            elseif (in_array($invoice['status'], ['Cancelled', 'Void'])) $status_class = 'dark';

                            $zatca_status_class = 'secondary';
                            if (in_array($invoice['zatca_status'], ['Cleared', 'Reported', 'Compliance Check Passed'])) $zatca_status_class = 'success';
                            elseif (in_array($invoice['zatca_status'], ['Rejected', 'Error', 'Compliance Check Failed'])) $zatca_status_class = 'danger';
                            elseif (in_array($invoice['zatca_status'], ['Sent', 'Compliance Check Pending', 'Clearance Pending', 'Reporting Pending'])) $zatca_status_class = 'info';
                             elseif ($invoice['zatca_status'] === 'Generating') $zatca_status_class = 'warning';
                        ?>
                        <tr>
                            <td><?php echo $row_num++; ?></td>
                            <td>
                                <a href="<?php echo base_url('invoices/view_invoice.php?id=' . $invoice['id']); ?>"><?php echo esc_html($invoice['invoice_number']); ?></a>
                                <br><small class="text-muted">ICV: <?php echo esc_html($invoice['invoice_sequence_number']);?></small>
                            </td>
                            <td><?php echo esc_html($invoice['tenant_name'] ?: 'N/A'); ?></td>
                            <td><?php echo esc_html($invoice['lease_contract_number'] ?: '-'); ?></td>
                            <td><?php echo format_date_custom($invoice['invoice_date'], 'd-m-Y'); ?></td>
                            <td><?php echo format_date_custom($invoice['due_date'], 'd-m-Y'); ?></td>
                            <td><?php echo number_format($invoice['total_amount'], 2); ?></td>
                            <td><?php echo number_format($invoice['paid_amount'], 2); ?></td>
                            <td class="<?php echo ($balance > 0 && $invoice['status'] !== 'Paid') ? 'text-danger fw-bold' : ''; ?>"><?php echo number_format($balance, 2); ?></td>
                            <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo esc_html($invoice_statuses_display_filter[$invoice['status']] ?? $invoice['status']); ?></span></td>
                            <td><span class="badge bg-<?php echo $zatca_status_class; ?>"><?php echo esc_html($zatca_statuses_display_filter[$invoice['zatca_status']] ?? $invoice['zatca_status']); ?></span></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-warning edit-invoice-btn"
                                            data-bs-toggle="modal" data-bs-target="#invoiceModal"
                                            data-invoice='<?php echo htmlspecialchars(json_encode($invoice), ENT_QUOTES, 'UTF-8'); ?>'
                                            onclick="prepareInvoiceModal('edit_invoice', this.getAttribute('data-invoice'))"
                                            title="تعديل الفاتورة">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger sweet-delete-btn delete-invoice-btn"
                                            data-id="<?php echo $invoice['id']; ?>"
                                            data-name="الفاتورة رقم <?php echo esc_attr($invoice['invoice_number']); ?>"
                                            data-delete-url="<?php echo base_url('invoices/invoice_actions.php?action=delete_invoice&id=' . $invoice['id'] . '&csrf_token=' . $csrf_token); ?>"
                                            data-additional-message="سيتم حذف الفاتورة وبنودها. لا يمكن التراجع عن هذا الإجراء. تأكد من عدم وجود دفعات مرتبطة."
                                            title="حذف الفاتورة">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                     <a href="<?php echo base_url('invoices/view_invoice.php?id=' . $invoice['id']); ?>" class="btn btn-outline-primary" title="عرض/طباعة الفاتورة">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                    <?php if (in_array($invoice['zatca_status'], ['Not Sent', 'Error', 'Rejected', 'Compliance Check Failed']) || ZATCA_CURRENT_ENVIRONMENT === 'simulation'): ?>
                                    <button type="button" class="btn btn-outline-success sweet-process-zatca-btn"
                                            data-invoice-id="<?php echo $invoice['id']; ?>"
                                            data-invoice-type-zatca="<?php echo esc_attr($invoice['invoice_type_zatca']); ?>"
                                            title="معالجة وإرسال إلى ZATCA">
                                        <i class="bi bi-send-check-fill"></i> ZATCA
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params = [];
            if (!empty($search_term)) $pagination_params['search'] = $search_term;
            if (!empty($filter_status)) $pagination_params['status'] = $filter_status;
            if (!empty($filter_zatca_status)) $pagination_params['zatca_status'] = $filter_zatca_status;
            if (!empty($filter_lease_id)) $pagination_params['lease_id'] = $filter_lease_id;
            if (!empty($filter_date_from)) $pagination_params['date_from'] = $filter_date_from;
            if (!empty($filter_date_to)) $pagination_params['date_to'] = $filter_date_to;
            echo generate_pagination_links($current_page, $total_pages, 'invoices/index.php', $pagination_params);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/invoice_modal.php';
// confirm_delete_modal.php is no longer needed here
?>

</div> 
<script>
// This script block will be more focused now, as global handlers are in footer_resources.php
// The prepareInvoiceModal function and its associated item management script from invoice_modal.php are crucial.

async function fetchInvoiceItemsForEdit(invoiceId) {
    try {
        const response = await fetch(`<?php echo base_url('invoices/ajax_get_invoice_items.php'); ?>?invoice_id=${invoiceId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        return data;
    } catch (error) {
        console.error("Error fetching invoice items:", error);
        Swal.fire('خطأ!', 'فشل في جلب بنود الفاتورة للتعديل.', 'error');
        return { success: false, items: [] }; // Return empty on error
    }
}


async function prepareInvoiceModal(action, invoiceDataJSON = null) {
    const invoiceModalEl = document.getElementById('invoiceModal');
    const modalTitle = invoiceModalEl.querySelector('.modal-title');
    const invoiceForm = invoiceModalEl.querySelector('#invoiceForm');
    const invoiceIdInput = invoiceModalEl.querySelector('#invoice_id_modal');
    const formActionInput = invoiceModalEl.querySelector('#invoice_form_action_modal');
    const submitButtonText = invoiceModalEl.querySelector('#invoiceSubmitButtonText');
    const itemsContainer = invoiceModalEl.querySelector('#invoiceItemsContainerModal');
    const itemTemplate = invoiceModalEl.querySelector('#invoiceItemTemplateModal');
    const leaseSelectModal = invoiceModalEl.querySelector('#lease_id_modal');
    const tenantDirectSelectModal = invoiceModalEl.querySelector('#tenant_id_invoice_modal');
    
    invoiceForm.reset(); // Reset form fields
    invoiceIdInput.value = '';
    formActionInput.value = action;

    // Remove previously added item rows (excluding the template)
    itemsContainer.querySelectorAll('.invoice-item-row-modal:not(#invoiceItemTemplateModal)').forEach(row => row.remove());
    tenantDirectSelectModal.removeAttribute('disabled');


    invoiceForm.action = '<?php echo base_url('invoices/invoice_actions.php'); ?>';

    if (action === 'add_invoice') {
        modalTitle.textContent = 'إنشاء فاتورة جديدة';
        submitButtonText.textContent = 'إنشاء الفاتورة';
        invoiceModalEl.querySelector('#invoice_date_modal').value = new Date().toISOString().slice(0,10);
        invoiceModalEl.querySelector('#due_date_modal').value = new Date().toISOString().slice(0,10);
        var now = new Date();
        invoiceModalEl.querySelector('#invoice_time_modal').value = now.toTimeString().slice(0,8);
        invoiceModalEl.querySelector('#invoice_status_modal').value = 'Unpaid';
        invoiceModalEl.querySelector('#invoice_type_zatca_modal').value = 'SimplifiedInvoice';
        invoiceModalEl.querySelector('#transaction_type_code_modal').value = '388';
        invoiceModalEl.querySelector('#invoice_paid_amount_modal').value = '0.00';
        invoiceModalEl.querySelector('#invoice_total_discount_modal').value = '0.00';
        invoiceModalEl.querySelector('#invoice_vat_percentage_modal_header').value = '<?php echo $default_vat_percentage_js; ?>';
        
        // Add one default item row by cloning template
        if (itemTemplate) {
            const addItemButtonModal = document.getElementById('addItemBtnModal'); // Assuming addItemBtnModal is the ID of the add item button
            if (addItemButtonModal) addItemButtonModal.click(); // Simulate click to add first row and attach its events
        }

    } else if (action === 'edit_invoice' && invoiceDataJSON) {
        const invoiceData = JSON.parse(invoiceDataJSON);
        modalTitle.textContent = 'تعديل الفاتورة رقم: ' + invoiceData.invoice_number;
        submitButtonText.textContent = 'حفظ التعديلات';
        invoiceIdInput.value = invoiceData.id;

        // Populate header fields
        if(document.getElementById('invoice_number_modal')) document.getElementById('invoice_number_modal').value = invoiceData.invoice_number || '';
        if(document.getElementById('invoice_sequence_number_modal')) document.getElementById('invoice_sequence_number_modal').value = invoiceData.invoice_sequence_number || '';
        if(document.getElementById('invoice_date_modal')) document.getElementById('invoice_date_modal').value = invoiceData.invoice_date || '';
        if(document.getElementById('invoice_time_modal')) document.getElementById('invoice_time_modal').value = invoiceData.invoice_time || '';
        if(document.getElementById('due_date_modal')) document.getElementById('due_date_modal').value = invoiceData.due_date || '';
        if(document.getElementById('invoice_type_zatca_modal')) document.getElementById('invoice_type_zatca_modal').value = invoiceData.invoice_type_zatca || 'SimplifiedInvoice';
        if(document.getElementById('transaction_type_code_modal')) document.getElementById('transaction_type_code_modal').value = invoiceData.transaction_type_code || '388';
        if(document.getElementById('invoice_status_modal')) document.getElementById('invoice_status_modal').value = invoiceData.status || 'Unpaid';
        if(leaseSelectModal) leaseSelectModal.value = invoiceData.lease_id || '';
        if(tenantDirectSelectModal) {
            tenantDirectSelectModal.value = invoiceData.tenant_id || '';
            if(invoiceData.lease_id){
                tenantDirectSelectModal.setAttribute('disabled', 'disabled');
            }
        }
        if(document.getElementById('purchase_order_id_modal')) document.getElementById('purchase_order_id_modal').value = invoiceData.purchase_order_id || '';
        if(document.getElementById('contract_id_modal_invoice')) document.getElementById('contract_id_modal_invoice').value = invoiceData.contract_id || '';
        if(document.getElementById('invoice_description_modal')) document.getElementById('invoice_description_modal').value = invoiceData.description || '';
        if(document.getElementById('zatca_notes_modal')) document.getElementById('zatca_notes_modal').value = invoiceData.notes_zatca || '';
        if(document.getElementById('invoice_total_discount_modal')) document.getElementById('invoice_total_discount_modal').value = parseFloat(invoiceData.discount_amount || 0).toFixed(2);
        if(document.getElementById('invoice_vat_percentage_modal_header')) document.getElementById('invoice_vat_percentage_modal_header').value = parseFloat(invoiceData.vat_percentage || <?php echo $default_vat_percentage_js; ?>).toFixed(2);
        if(document.getElementById('invoice_paid_amount_modal')) document.getElementById('invoice_paid_amount_modal').value = parseFloat(invoiceData.paid_amount || 0).toFixed(2);

        // Fetch and populate items
        const itemsData = await fetchInvoiceItemsForEdit(invoiceData.id);
        if (itemsData.success && itemsData.items.length > 0) {
            const addItemButtonModal = document.getElementById('addItemBtnModal');
            itemsData.items.forEach(item => {
                if (addItemButtonModal) addItemButtonModal.click(); // Add a new row using existing logic
                var lastItemRow = itemsContainer.querySelector('.invoice-item-row-modal:not(#invoiceItemTemplateModal):last-child');
                if (lastItemRow) {
                    if(lastItemRow.querySelector('.item-name')) lastItemRow.querySelector('.item-name').value = item.item_name || '';
                    if(lastItemRow.querySelector('.item-quantity')) lastItemRow.querySelector('.item-quantity').value = parseFloat(item.quantity || 1).toFixed(2);
                    if(lastItemRow.querySelector('.item-unit-price')) lastItemRow.querySelector('.item-unit-price').value = parseFloat(item.unit_price_before_vat || 0).toFixed(2);
                    if(lastItemRow.querySelector('.item-vat-category')) lastItemRow.querySelector('.item-vat-category').value = item.item_vat_category_code || 'S';
                    if(lastItemRow.querySelector('.item-vat-percentage')) lastItemRow.querySelector('.item-vat-percentage').value = parseFloat(item.item_vat_percentage || <?php echo $default_vat_percentage_js; ?>).toFixed(2);
                    if(lastItemRow.querySelector('.item-discount')) lastItemRow.querySelector('.item-discount').value = parseFloat(item.item_discount_amount || 0).toFixed(2);
                }
            });
        } else if (itemsData.items.length === 0) { // If no items, add one blank row
            const addItemButtonModal = document.getElementById('addItemBtnModal');
            if (addItemButtonModal) addItemButtonModal.click();
        }
    }
    
    // Trigger calculation after populating
    // The calculateInvoiceTotals function should be available from invoice_modal.php's script part
    if(typeof calculateInvoiceTotals === "function") calculateInvoiceTotals();
    if(leaseSelectModal && leaseSelectModal.value) leaseSelectModal.dispatchEvent(new Event('change'));


}


document.addEventListener('DOMContentLoaded', function () {
    // Old confirmDeleteModalInvoice JavaScript block removed.
    // The form submission for invoiceForm is handled directly by invoice_modal.php's script
    // So no additional fetch handler needed here unless you want to override it.
    // Ensure the script in invoice_modal.php uses SweetAlert.
    // If invoice_actions.php redirects after POST, SweetAlert in footer will handle it.
    // If invoice_actions.php echoes JSON, the JS in invoice_modal.php should handle it with SweetAlert.
    // The existing invoice_actions.php uses set_message and redirect.

    // SweetAlert for ZATCA processing
    document.querySelectorAll('.sweet-process-zatca-btn').forEach(button => {
        button.addEventListener('click', function() {
            var invoiceId = this.getAttribute('data-invoice-id');
            var invoiceTypeZatca = this.getAttribute('data-invoice-type-zatca');
            var confirmationMessage = "سيتم الآن محاولة معالجة الفاتورة رقم " + invoiceId + " (نوع ZATCA: " + invoiceTypeZatca + ") وإرسالها إلى هيئة الزكاة والضريبة والجمارك.\n\nهل أنت متأكد أنك تريد المتابعة؟";
            
            Swal.fire({
                title: 'تأكيد معالجة ZATCA',
                text: confirmationMessage,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، متابعة!',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Using a hidden form to submit POST data for ZATCA processing
                    var hiddenForm = document.createElement('form');
                    hiddenForm.method = 'POST';
                    hiddenForm.action = '<?php echo base_url("invoices/process_zatca_submission.php"); // Assuming a dedicated handler ?>';
                    
                    var actionInput = document.createElement('input');
                    actionInput.type = 'hidden'; actionInput.name = 'action'; actionInput.value = 'process_zatca_submission';
                    hiddenForm.appendChild(actionInput);

                    var idInput = document.createElement('input');
                    idInput.type = 'hidden'; idInput.name = 'invoice_id'; idInput.value = invoiceId;
                    hiddenForm.appendChild(idInput);

                    var csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden'; csrfInput.name = 'csrf_token'; csrfInput.value = '<?php echo $csrf_token; ?>';
                    hiddenForm.appendChild(csrfInput);
                    
                    document.body.appendChild(hiddenForm);
                    hiddenForm.submit();
                }
            });
        });
    });

    // Auto-open modal if 'action=open_add_modal' is in URL
    const urlParamsForInvoice = new URLSearchParams(window.location.search);
    if (urlParamsForInvoice.has('action') && urlParamsForInvoice.get('action') === 'open_add_modal') {
        var invoiceModalToOpen = new bootstrap.Modal(document.getElementById('invoiceModal'));
        prepareInvoiceModal('add_invoice').then(() => { // Ensure modal content is ready
           invoiceModalToOpen.show();
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>