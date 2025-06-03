<?php
$page_title = "إدارة الفواتير";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// متغيرات التصفح
$current_page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : (defined('ITEMS_PER_PAGE') && filter_var(ITEMS_PER_PAGE, FILTER_VALIDATE_INT) ? (int)ITEMS_PER_PAGE : 10);
$offset = ($current_page - 1) * $items_per_page;

// وظيفة البحث والفلترة
$search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : ''; // رقم الفاتورة، اسم المستأجر، رقم العقد
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : ''; // حالة الفاتورة الداخلية
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
    for ($k=0; $k<3; $k++) { // 3 placeholders
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

// الحصول على العدد الإجمالي للفواتير
$total_sql = "SELECT COUNT(i.id) as total 
              FROM invoices i
              LEFT JOIN leases l ON i.lease_id = l.id
              LEFT JOIN tenants t ON i.tenant_id = t.id" . $where_sql;
$stmt_total = $mysqli->prepare($total_sql);
if ($stmt_total && !empty($params_for_count)) {
    $stmt_total->bind_param($types_for_count, ...$params_for_count);
}
if ($stmt_total) {
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total_invoices = ($total_result) ? $total_result->fetch_assoc()['total'] : 0;
    $stmt_total->close();
} else {
    $total_invoices = 0;
    error_log("SQL Prepare Error for counting invoices: " . $mysqli->error);
}
$total_pages = ceil($total_invoices / $items_per_page);


// جلب الفواتير للصفحة الحالية
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
if ($stmt) {
    if (!empty($current_data_params)) {
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
    $invoices = [];
}

// لعرض أسماء الحالات والفلاتر بالعربية
// $invoice_statuses_display is defined in includes/modals/invoice_modal.php
// $zatca_statuses_display is defined in includes/modals/invoice_modal.php (or should be if used there)
// For filters, we add an "All" option
$invoice_statuses_display_filter = ['' => '-- الكل --'] + (isset($invoice_statuses_display) ? $invoice_statuses_display : ['Draft' => 'مسودة', 'Unpaid' => 'غير مدفوعة', 'Partially Paid' => 'مدفوعة جزئياً', 'Paid' => 'مدفوعة', 'Overdue' => 'متأخرة', 'Cancelled' => 'ملغاة', 'Void' => 'لاغية']);
$zatca_statuses_display_filter = [
    '' => '-- الكل --', 'Not Sent' => 'لم ترسل', 'Sent' => 'مرسلة', 'Generating' => 'قيد الإنشاء',
    'Compliance Check Pending' => 'فحص الامتثال معلق', 'Compliance Check Failed' => 'فشل فحص الامتثال',
    'Compliance Check Passed' => 'نجح فحص الامتثال', 'Clearance Pending' => 'التصريح معلق',
    'Cleared' => 'تم التصريح', 'Reporting Pending' => 'الإبلاغ معلق', 'Reported' => 'تم الإبلاغ',
    'Rejected' => 'مرفوضة', 'Error' => 'خطأ'
];
// $active_leases_filter is defined in includes/modals/invoice_modal.php
// We need to ensure it's available or re-fetch if necessary for filter dropdowns.
if (!isset($active_leases_list_for_modal)) { // In case modal wasn't included or variable scope
    $active_leases_list_for_modal = [];
    $leases_q_filter = "SELECT l.id as lease_id, l.lease_contract_number, t.full_name as tenant_name
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
$default_vat_percentage_js = defined('VAT_PERCENTAGE') ? VAT_PERCENTAGE : 15.00; // For JS in modal
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-receipt-cutoff"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الفواتير (<?php echo $total_invoices; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#invoiceModal" data-action="add_invoice">
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
                            <option value="<?php echo $key; ?>" <?php echo ($filter_status == $key) ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-2 col-lg-2">
                    <label for="filter_zatca_status_inv" class="form-label form-label-sm">حالة ZATCA</label>
                    <select id="filter_zatca_status_inv" name="zatca_status" class="form-select form-select-sm">
                        <?php foreach ($zatca_statuses_display_filter as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filter_zatca_status == $key) ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
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
                <div class="alert alert-info text-center">لا توجد فواتير مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#invoiceModal" data-action="add_invoice">إنشاء فاتورة جديدة</a>.</div>
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
                                            data-invoice_id="<?php echo $invoice['id']; ?>"
                                            data-action="edit_invoice"
                                            title="تعديل الفاتورة">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger delete-invoice-btn"
                                            data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                            data-id="<?php echo $invoice['id']; ?>"
                                            data-name="الفاتورة رقم <?php echo esc_attr($invoice['invoice_number']); ?>"
                                            data-delete-url="<?php echo base_url('invoices/invoice_actions.php?action=delete_invoice&id=' . $invoice['id'] . '&csrf_token=' . $csrf_token); ?>"
                                            title="حذف الفاتورة">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                     <a href="<?php echo base_url('invoices/view_invoice.php?id=' . $invoice['id']); ?>" class="btn btn-outline-primary" title="عرض/طباعة الفاتورة">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                    <?php if (in_array($invoice['zatca_status'], ['Not Sent', 'Error', 'Rejected', 'Compliance Check Failed'])): ?>
                                    <button type="button" class="btn btn-outline-success process-zatca-btn"
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
// تضمين نافذة إضافة/تعديل الفاتورة
require_once __DIR__ . '/../includes/modals/invoice_modal.php';
// تضمين نافذة تأكيد الحذف
require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <script>
document.addEventListener('DOMContentLoaded', function () {
    var invoiceModal = document.getElementById('invoiceModal');
    var itemsContainerModal = invoiceModal.querySelector('#invoiceItemsContainerModal'); // Moved outside for broader scope
    var itemTemplateModal = invoiceModal.querySelector('#invoiceItemTemplateModal'); // Moved outside

    // Function to fetch full invoice data for editing (header + items)
    // This is a placeholder. In a real app, this would be an AJAX call or data passed via PHP.
    async function fetchInvoiceDataForEdit(invoiceId) {
        // Simulate fetching data. Replace with actual AJAX call.
        // For now, we'll try to get data from a hypothetical PHP endpoint or use data attributes.
        // This example assumes you might have a PHP script that returns JSON for an invoice.
        // const response = await fetch('<?php echo base_url("invoices/get_invoice_details_ajax.php?id="); ?>' + invoiceId);
        // if (!response.ok) {
        //     alert('Failed to fetch invoice details for editing.');
        //     return null;
        // }
        // return await response.json();

        // Fallback: If no AJAX, this function needs to be smarter or data preloaded.
        // For this "no AJAX" version, we'll assume the button triggering edit might have some data,
        // but items are the tricky part.
        console.warn("Fetching full invoice data for edit (ID: " + invoiceId + ") needs a robust server-side mechanism or preloaded JS data due to 'no AJAX' constraint for items.");
        return null; // Indicates data needs to be populated by other means
    }


    if (invoiceModal) {
        invoiceModal.addEventListener('show.bs.modal', async function (event) { // Made async for fetch
            var button = event.relatedTarget;
            var action = button.getAttribute('data-action');
            var modalTitle = invoiceModal.querySelector('.modal-title');
            var invoiceForm = invoiceModal.querySelector('#invoiceForm');
            var invoiceIdInput = invoiceModal.querySelector('#invoice_id_modal');
            var formActionInput = invoiceModal.querySelector('#invoice_form_action_modal');
            var submitButtonText = invoiceModal.querySelector('#invoiceSubmitButtonText');
            var leaseSelectModal = invoiceModal.querySelector('#lease_id_modal');
            var tenantDirectSelectModal = invoiceModal.querySelector('#tenant_id_invoice_modal');

            invoiceForm.reset();
            invoiceIdInput.value = '';
            itemsContainerModal.querySelectorAll('.invoice-item-row-modal:not(#invoiceItemTemplateModal)').forEach(row => row.remove());
            tenantDirectSelectModal.removeAttribute('disabled');

            var form_url = '<?php echo base_url('invoices/invoice_actions.php'); ?>';

            if (action === 'add_invoice') {
                modalTitle.textContent = 'إنشاء فاتورة جديدة';
                formActionInput.value = 'add_invoice';
                submitButtonText.textContent = 'إنشاء الفاتورة';
                invoiceForm.action = form_url;
                
                invoiceModal.querySelector('#invoice_date_modal').value = new Date().toISOString().slice(0,10);
                invoiceModal.querySelector('#due_date_modal').value = new Date().toISOString().slice(0,10);
                var now = new Date();
                invoiceModal.querySelector('#invoice_time_modal').value = now.toTimeString().slice(0,8);
                invoiceModal.querySelector('#invoice_status_modal').value = 'Unpaid';
                invoiceModal.querySelector('#invoice_type_zatca_modal').value = 'SimplifiedInvoice';
                invoiceModal.querySelector('#transaction_type_code_modal').value = '388';
                invoiceModal.querySelector('#invoice_paid_amount_modal').value = '0.00';
                invoiceModal.querySelector('#invoice_total_discount_modal').value = '0.00';
                invoiceModal.querySelector('#invoice_vat_percentage_modal_header').value = '<?php echo $default_vat_percentage_js; ?>';

                // Add one or two default item rows
                var addItemButtonModal = document.getElementById('addItemBtnModal');
                if(addItemButtonModal) {
                    addItemButtonModal.click(); // Add one default item row
                    // addItemButtonModal.click(); // Optionally add a second one
                }

            } else if (action === 'edit_invoice') { // Edit action
                modalTitle.textContent = 'تعديل بيانات الفاتورة';
                formActionInput.value = 'edit_invoice';
                submitButtonText.textContent = 'حفظ التعديلات';
                invoiceForm.action = form_url;

                var invoiceIdToEdit = button.getAttribute('data-invoice_id');
                invoiceIdInput.value = invoiceIdToEdit;

                // Attempt to populate header from button data attributes (basic)
                // This should ideally come from a comprehensive data source for the invoice
                if(document.getElementById('invoice_number_modal')) document.getElementById('invoice_number_modal').value = button.getAttribute('data-invoice_number') || '';
                if(document.getElementById('invoice_sequence_number_modal')) document.getElementById('invoice_sequence_number_modal').value = button.getAttribute('data-invoice_sequence_number') || '';
                // ... (populate other header fields similarly if available on button) ...
                // This is where you would call `await fetchInvoiceDataForEdit(invoiceIdToEdit)`
                // and then use the response to populate ALL fields, including items.
                
                // For "no AJAX", the view_invoice.php page's JS (prepareEditInvoice) is a better place to populate this.
                // If this modal is directly on index.php, then index.php needs to make all invoice data (with items)
                // available to JS, perhaps in a data attribute on the edit button or a global JS object.
                
                alert("للتعديل الكامل: يجب تحميل بيانات الفاتورة وبنودها هنا. حاليًا، يتم ملء الرأس فقط بشكل جزئي.");
                // As a placeholder, add one item row for editing.
                 var addItemButtonModalEdit = document.getElementById('addItemBtnModal');
                 if(addItemButtonModalEdit && itemsContainerModal.querySelectorAll('.invoice-item-row-modal:not(#invoiceItemTemplateModal)').length === 0) {
                    addItemButtonModalEdit.click();
                 }
            }
            // Trigger calculation and lease-tenant logic after populating (or for add)
            if(invoiceModal.querySelector('#invoice_total_discount_modal')) invoiceModal.querySelector('#invoice_total_discount_modal').dispatchEvent(new Event('change'));
            if(leaseSelectModal && leaseSelectModal.value) leaseSelectModal.dispatchEvent(new Event('change'));
        });

         // Auto-open modal if 'action=open_add_modal' is in URL
        const urlParamsForInvoice = new URLSearchParams(window.location.search);
        if (urlParamsForInvoice.has('action') && urlParamsForInvoice.get('action') === 'open_add_modal') {
            var addInvoiceButton = document.querySelector('button[data-bs-target="#invoiceModal"][data-action="add_invoice"]');
            if (addInvoiceButton) {
                // Directly trigger the modal's 'show.bs.modal' event by simulating a click on the button
                // This ensures the modal's own setup logic for 'add_invoice' runs.
                addInvoiceButton.click();
            }
        }
    }


    var confirmDeleteModalInvoice = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModalInvoice) {
        confirmDeleteModalInvoice.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button.classList.contains('delete-invoice-btn')) {
                var itemName = button.getAttribute('data-name');
                var deleteUrl = button.getAttribute('data-delete-url');
                var modalBodyText = confirmDeleteModalInvoice.querySelector('.modal-body-text');
                if(modalBodyText) modalBodyText.textContent = 'هل أنت متأكد أنك تريد حذف ' + itemName + '؟';
                var additionalInfo = confirmDeleteModalInvoice.querySelector('#additionalDeleteInfo');
                if(additionalInfo) additionalInfo.textContent = 'ملاحظة: سيتم حذف الفاتورة وجميع بنودها المرتبطة. لا يمكن التراجع عن هذا الإجراء.';
                var confirmDeleteButton = confirmDeleteModalInvoice.querySelector('#confirmDeleteButton');
                if(confirmDeleteButton) confirmDeleteButton.setAttribute('href', deleteUrl);
            }
        });
    }
    
    document.querySelectorAll('.process-zatca-btn').forEach(button => {
        button.addEventListener('click', function() {
            var invoiceId = this.getAttribute('data-invoice-id');
            var invoiceTypeZatca = this.getAttribute('data-invoice-type-zatca');
            var confirmationMessage = "سيتم الآن محاولة معالجة الفاتورة رقم " + invoiceId + " (نوع ZATCA: " + invoiceTypeZatca + ") وإرسالها إلى هيئة الزكاة والضريبة والجمارك.\n\nهل أنت متأكد أنك تريد المتابعة؟";
            
            // Simple confirm, replace with a styled modal for better UX
            if (confirm(confirmationMessage)) {
                // Redirect to an action handler or use AJAX if preferred (though constraint is no AJAX)
                // For now, let's assume a GET request to the action handler for simplicity of this example.
                // A POST request would be better for actions that change state.
                var processUrl = '<?php echo base_url("invoices/invoice_actions.php?action=process_zatca_submission&id="); ?>' + invoiceId + '&csrf_token=<?php echo $csrf_token; ?>';
                // window.location.href = processUrl; // This would be for a GET request
                
                // For a POST-like action without true AJAX, you might submit a hidden form:
                var hiddenForm = document.createElement('form');
                hiddenForm.method = 'POST';
                hiddenForm.action = '<?php echo base_url("invoices/invoice_actions.php"); ?>';
                
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
                
                // alert('جاري معالجة الفاتورة مع ZATCA... (هذا الجزء قيد التطوير الفعلي للاتصال بالـ API)');
            }
        });
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>