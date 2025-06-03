<?php
$page_title = "إدارة الدفعات";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// Pagination variables
$current_page_pay = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_pay = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_pay = ($current_page_pay - 1) * $items_per_page_pay;

// Filtering and Search
$search_term_pay = isset($_GET['search']) ? sanitize_input($_GET['search']) : ''; // Invoice number or tenant name
$filter_payment_method_id_pay = isset($_GET['payment_method_id']) && filter_var($_GET['payment_method_id'], FILTER_VALIDATE_INT) ? (int)$_GET['payment_method_id'] : '';
$filter_status_pay = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$filter_date_from_pay = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$filter_date_to_pay = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

$where_clauses_pay = [];
$params_for_count_pay = []; $types_for_count_pay = "";
$params_for_data_pay = [];  $types_for_data_pay = "";

if (!empty($search_term_pay)) {
    $where_clauses_pay[] = "(i.invoice_number LIKE ? OR t.full_name LIKE ? OR pay.reference_number LIKE ?)"; // search by invoice no, tenant name or receipt_number (stored as reference_number)
    $search_like_pay = "%" . $search_term_pay . "%";
    for ($k=0; $k<3; $k++) {
        $params_for_count_pay[] = $search_like_pay; $types_for_count_pay .= "s";
        $params_for_data_pay[] = $search_like_pay;  $types_for_data_pay .= "s";
    }
}
if (!empty($filter_payment_method_id_pay)) {
    $where_clauses_pay[] = "pay.payment_method_id = ?";
    $params_for_count_pay[] = $filter_payment_method_id_pay; $types_for_count_pay .= "i";
    $params_for_data_pay[] = $filter_payment_method_id_pay;  $types_for_data_pay .= "i";
}
if (!empty($filter_status_pay)) {
    $where_clauses_pay[] = "pay.status = ?";
    $params_for_count_pay[] = $filter_status_pay; $types_for_count_pay .= "s";
    $params_for_data_pay[] = $filter_status_pay;  $types_for_data_pay .= "s";
}
if (!empty($filter_date_from_pay)) {
    $where_clauses_pay[] = "pay.payment_date >= ?";
    $params_for_count_pay[] = $filter_date_from_pay; $types_for_count_pay .= "s";
    $params_for_data_pay[] = $filter_date_from_pay;  $types_for_data_pay .= "s";
}
if (!empty($filter_date_to_pay)) {
    $where_clauses_pay[] = "pay.payment_date <= ?";
    $params_for_count_pay[] = $filter_date_to_pay; $types_for_count_pay .= "s";
    $params_for_data_pay[] = $filter_date_to_pay;  $types_for_data_pay .= "s";
}

$where_sql_pay = "";
if (!empty($where_clauses_pay)) {
    $where_sql_pay = " WHERE " . implode(" AND ", $where_clauses_pay);
}

// Total payments
$total_sql_pay = "SELECT COUNT(pay.id) as total 
                  FROM payments pay
                  LEFT JOIN invoices i ON pay.invoice_id = i.id
                  LEFT JOIN tenants t ON pay.tenant_id = t.id
                  LEFT JOIN payment_methods pm ON pay.payment_method_id = pm.id" . $where_sql_pay;
$stmt_total_pay = $mysqli->prepare($total_sql_pay);
$total_payments = 0;
if ($stmt_total_pay) {
    if (!empty($params_for_count_pay)) {
        $stmt_total_pay->bind_param($types_for_count_pay, ...$params_for_count_pay);
    }
    $stmt_total_pay->execute();
    $total_result_pay = $stmt_total_pay->get_result();
    $total_payments = ($total_result_pay && $total_result_pay->num_rows > 0) ? $total_result_pay->fetch_assoc()['total'] : 0;
    $stmt_total_pay->close();
} else {
    error_log("SQL Prepare Error for counting payments: " . $mysqli->error);
}
$total_pages_pay = ceil($total_payments / $items_per_page_pay);

// Fetch payments for the current page
$sql_pay = "SELECT pay.id, pay.invoice_id, pay.tenant_id, pay.payment_date, pay.amount_paid, 
                   pay.payment_method_id, pay.status as payment_status, pay.reference_number, pay.notes as payment_notes, pay.attachment_path,
                   i.invoice_number, i.total_amount as invoice_total,
                   t.full_name as tenant_name,
                   pm.display_name_ar as payment_method_name,
                   u.full_name as received_by_name
            FROM payments pay
            LEFT JOIN invoices i ON pay.invoice_id = i.id
            LEFT JOIN tenants t ON pay.tenant_id = t.id
            LEFT JOIN payment_methods pm ON pay.payment_method_id = pm.id
            LEFT JOIN users u ON pay.received_by_id = u.id"
            . $where_sql_pay . " ORDER BY pay.payment_date DESC, pay.id DESC LIMIT ? OFFSET ?";

$current_data_params_pay = $params_for_data_pay;
$current_data_params_pay[] = $items_per_page_pay;
$current_data_params_pay[] = $offset_pay;
$current_data_types_pay = $types_for_data_pay . 'ii';

$payments_list = [];
$stmt_pay = $mysqli->prepare($sql_pay);
if ($stmt_pay) {
    if (!empty($current_data_params_pay)) {
        $stmt_pay->bind_param($current_data_types_pay, ...$current_data_params_pay);
    } else {
         $stmt_pay->bind_param('ii', $items_per_page_pay, $offset_pay);
    }
    $stmt_pay->execute();
    $result_pay = $stmt_pay->get_result();
    $payments_list = ($result_pay && $result_pay->num_rows > 0) ? $result_pay->fetch_all(MYSQLI_ASSOC) : [];
    if($stmt_pay) $stmt_pay->close();
} else {
    error_log("SQL Prepare Error for fetching payments: " . $mysqli->error);
}

// Fetch payment methods for filter
$payment_methods_filter_list_pay = [];
$pm_filter_q = "SELECT id, display_name_ar FROM payment_methods WHERE is_active = 1 ORDER BY display_name_ar ASC";
if($pm_filter_r = $mysqli->query($pm_filter_q)){ while($row = $pm_filter_r->fetch_assoc()){ $payment_methods_filter_list_pay[] = $row;} $pm_filter_r->free(); }

$payment_statuses_filter_options = [ // تم تغيير الاسم من payment_statuses_modal_options
    '' => '-- كل الحالات --',
    'Pending' => 'معلقة',
    'Completed' => 'مكتملة',
    'Failed' => 'فشلت',
    'Cancelled' => 'ملغاة',
    'Refunded' => 'مستردة'
];

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-cash-coin"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الدفعات (<?php echo $total_payments; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal" onclick="preparePaymentModal('add_payment')">
                    <i class="bi bi-plus-circle"></i> تسجيل دفعة جديدة
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('payments/index.php'); ?>" class="row gx-2 gy-2 align-items-end">
                <div class="col-md-3 col-lg-3">
                    <label for="search_payments_page" class="form-label form-label-sm">بحث عام</label>
                    <input type="text" id="search_payments_page" name="search" class="form-control form-control-sm" placeholder="رقم الفاتورة، المستأجر، الإيصال..." value="<?php echo esc_attr($search_term_pay); ?>">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label for="filter_payment_method_id_pay_page" class="form-label form-label-sm">طريقة الدفع</label>
                    <select id="filter_payment_method_id_pay_page" name="payment_method_id" class="form-select form-select-sm">
                        <option value="">-- الكل --</option>
                        <?php foreach ($payment_methods_filter_list_pay as $pm_filter_item): ?>
                            <option value="<?php echo $pm_filter_item['id']; ?>" <?php echo ($filter_payment_method_id_pay == $pm_filter_item['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($pm_filter_item['display_name_ar']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-2 col-lg-2">
                    <label for="filter_status_pay_page" class="form-label form-label-sm">الحالة</label>
                    <select id="filter_status_pay_page" name="status" class="form-select form-select-sm">
                        <?php foreach ($payment_statuses_filter_options as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filter_status_pay == $key && $filter_status_pay !== '') ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-lg-1">
                    <label for="filter_date_from_pay_page" class="form-label form-label-sm">من تاريخ</label>
                    <input type="date" id="filter_date_from_pay_page" name="date_from" class="form-control form-control-sm" value="<?php echo esc_attr($filter_date_from_pay); ?>">
                </div>
                <div class="col-md-2 col-lg-1">
                     <label for="filter_date_to_pay_page" class="form-label form-label-sm">إلى تاريخ</label>
                    <input type="date" id="filter_date_to_pay_page" name="date_to" class="form-control form-control-sm" value="<?php echo esc_attr($filter_date_to_pay); ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill"></i></button>
                </div>
                 <div class="col-md-1">
                     <a href="<?php echo base_url('payments/index.php'); ?>" class="btn btn-outline-secondary btn-sm w-100" title="مسح الفلاتر"><i class="bi bi-eraser-fill"></i></a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($payments_list) && (!empty($search_term_pay) || !empty($filter_payment_method_id_pay) || !empty($filter_status_pay) || !empty($filter_date_from_pay) || !empty($filter_date_to_pay))): ?>
                <div class="alert alert-warning text-center">لا توجد دفعات تطابق معايير البحث أو الفلترة.</div>
            <?php elseif (empty($payments_list)): ?>
                <div class="alert alert-info text-center">لا توجد دفعات مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#paymentModal" onclick="preparePaymentModal('add_payment')">تسجيل دفعة جديدة</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>رقم الفاتورة</th>
                            <th>المستأجر</th>
                            <th>المبلغ المدفوع</th>
                            <th>طريقة الدفع</th>
                            <th>تاريخ الدفع</th>
                            <th>رقم الإيصال/المرجع</th>
                            <th>الحالة</th>
                            <th>بواسطة</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_pay = ($current_page_pay - 1) * $items_per_page_pay + 1; ?>
                        <?php foreach ($payments_list as $payment_item): ?>
                        <tr>
                            <td><?php echo $row_num_pay++; ?></td>
                            <td><a href="<?php echo base_url('invoices/view_invoice.php?id=' . $payment_item['invoice_id']); ?>"><?php echo esc_html($payment_item['invoice_number'] ?: '-'); ?></a></td>
                            <td><?php echo esc_html($payment_item['tenant_name'] ?: '-'); ?></td>
                            <td><?php echo number_format($payment_item['amount_paid'], 2); ?> ريال</td>
                            <td><?php echo esc_html($payment_item['payment_method_name'] ?: '-'); ?></td>
                            <td><?php echo format_date_custom($payment_item['payment_date'], 'Y-m-d'); ?></td>
                            <td><?php echo esc_html($payment_item['reference_number'] ?: '-'); // كان receipt_number ?></td>
                            <td>
                                <?php
                                $status_class_pay = 'secondary';
                                if ($payment_item['payment_status'] === 'Completed') $status_class_pay = 'success';
                                elseif ($payment_item['payment_status'] === 'Pending') $status_class_pay = 'warning';
                                elseif ($payment_item['payment_status'] === 'Failed' || $payment_item['payment_status'] === 'Cancelled') $status_class_pay = 'danger';
                                ?>
                                <span class="badge bg-<?php echo $status_class_pay; ?>"><?php echo esc_html($payment_statuses_filter_options[$payment_item['payment_status']] ?? $payment_item['payment_status']); ?></span>
                            </td>
                            <td><?php echo esc_html($payment_item['received_by_name'] ?: '-'); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="preparePaymentModal('edit_payment', <?php echo htmlspecialchars(json_encode($payment_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#paymentModal"
                                        title="تعديل الدفعة">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <?php if ($payment_item['payment_status'] !== 'Completed'): // لا يمكن حذف دفعة مكتملة بسهولة، قد تحتاج لعملية استرداد ?>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-payment-btn"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                        data-id="<?php echo $payment_item['id']; ?>"
                                        data-name="الدفعة للمستأجر <?php echo esc_attr($payment_item['tenant_name']); ?> (فاتورة: <?php echo esc_attr($payment_item['invoice_number']); ?>)"
                                        data-delete-url="<?php echo base_url('payments/payment_actions.php?action=delete_payment&id=' . $payment_item['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        title="حذف الدفعة">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (!empty($payment_item['attachment_path'])): ?>
                                    <a href="<?php echo base_url(esc_attr($payment_item['attachment_path'])); ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="عرض المرفق">
                                        <i class="bi bi-paperclip"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages_pay > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_pay = [];
            if (!empty($search_term_pay)) $pagination_params_pay['search'] = $search_term_pay;
            if (!empty($filter_payment_method_id_pay)) $pagination_params_pay['payment_method_id'] = $filter_payment_method_id_pay;
            if (!empty($filter_status_pay)) $pagination_params_pay['status'] = $filter_status_pay;
            if (!empty($filter_date_from_pay)) $pagination_params_pay['date_from'] = $filter_date_from_pay;
            if (!empty($filter_date_to_pay)) $pagination_params_pay['date_to'] = $filter_date_to_pay;
            echo generate_pagination_links($current_page_pay, $total_pages_pay, 'payments/index.php', $pagination_params_pay);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/modals/payment_modal.php'; // النافذة الخاصة بتسجيل الدفعات
require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <script>
function preparePaymentModal(action, paymentData = null) {
    const paymentModal = document.getElementById('paymentModal'); // معرف النافذة المنبثقة لتسجيل الدفعات
    const modalTitle = paymentModal.querySelector('#paymentModalLabel_payments_page');
    const paymentForm = paymentModal.querySelector('#paymentFormModal');
    const paymentIdInput = paymentModal.querySelector('#payment_id_modal_payments_page');
    const actionInput = paymentModal.querySelector('#payment_form_action_modal_payments_page');
    const submitButton = paymentModal.querySelector('#paymentSubmitButtonTextModalPaymentsPage');
    const invoiceSelect = paymentModal.querySelector('#invoice_id_modal_payments_page');
    const amountPaidInput = paymentModal.querySelector('#amount_paid_modal_payments_page');
    const invoiceDetailsText = paymentModal.querySelector('#invoice_details_text_payments_modal');
    const currentAttachmentSpan = paymentModal.querySelector('#current_payment_attachment_text_modal');


    paymentForm.reset(); // This will also clear the file input
    paymentIdInput.value = '';
    actionInput.value = action;
    invoiceDetailsText.textContent = '';
    if(currentAttachmentSpan) currentAttachmentSpan.innerHTML = '';

    // Enable invoice select for add, disable for edit (or handle carefully)
    invoiceSelect.disabled = (action === 'edit_payment');


    if (action === 'add_payment') {
        modalTitle.textContent = 'تسجيل دفعة جديدة';
        submitButton.textContent = 'تسجيل الدفعة';
        if(document.getElementById('payment_date_modal_payments_page')) document.getElementById('payment_date_modal_payments_page').value = new Date().toISOString().slice(0,10);
        if(document.getElementById('payment_status_modal_payments_page')) document.getElementById('payment_status_modal_payments_page').value = 'Completed';

    } else if (action === 'edit_payment' && paymentData) {
        modalTitle.textContent = 'تعديل بيانات الدفعة للفاتورة: ' + (paymentData.invoice_number || 'غير محددة');
        submitButton.textContent = 'حفظ التعديلات';
        paymentIdInput.value = paymentData.id;
        
        if(invoiceSelect) invoiceSelect.value = paymentData.invoice_id || '';
        // Manually trigger change event if needed to update remaining amount text
        var event = new Event('change');
        if(invoiceSelect) invoiceSelect.dispatchEvent(event);

        if(amountPaidInput) amountPaidInput.value = paymentData.amount_paid || '';
        if(document.getElementById('payment_date_modal_payments_page')) document.getElementById('payment_date_modal_payments_page').value = paymentData.payment_date || '';
        if(document.getElementById('payment_method_id_modal_payments_page')) document.getElementById('payment_method_id_modal_payments_page').value = paymentData.payment_method_id || '';
        if(document.getElementById('payment_status_modal_payments_page')) document.getElementById('payment_status_modal_payments_page').value = paymentData.payment_status || '';
        if(document.getElementById('receipt_number_modal_payments_page')) document.getElementById('receipt_number_modal_payments_page').value = paymentData.reference_number || ''; // reference_number is the DB column
        if(document.getElementById('payment_notes_modal_payments_page')) document.getElementById('payment_notes_modal_payments_page').value = paymentData.payment_notes || '';
        
        if (paymentData.attachment_path && currentAttachmentSpan) {
            var filename = paymentData.attachment_path.split('/').pop();
            currentAttachmentSpan.innerHTML = 'المرفق الحالي: <a href="<?php echo base_url(); ?>' + paymentData.attachment_path + '" target="_blank" class="text-decoration-none">' + filename + '</a> (لإبقائه، لا ترفع ملف جديد)';
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var confirmDeleteModalPaymentPage = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModalPaymentPage) {
        confirmDeleteModalPaymentPage.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button.classList.contains('delete-payment-btn')) {
                var itemName = button.getAttribute('data-name');
                var deleteUrl = button.getAttribute('data-delete-url');
                var modalBodyText = confirmDeleteModalPaymentPage.querySelector('.modal-body-text');
                if(modalBodyText) modalBodyText.textContent = 'هل أنت متأكد أنك تريد حذف ' + itemName + '؟';
                
                var additionalInfo = confirmDeleteModalPaymentPage.querySelector('#additionalDeleteInfo');
                if(additionalInfo) additionalInfo.textContent = 'سيتم تحديث حالة الفاتورة المرتبطة بعد الحذف.';

                var confirmDeleteButton = confirmDeleteModalPaymentPage.querySelector('#confirmDeleteButton');
                if(confirmDeleteButton) {
                    var newConfirmDeleteButtonPay = confirmDeleteButton.cloneNode(true);
                    confirmDeleteButton.parentNode.replaceChild(newConfirmDeleteButtonPay, confirmDeleteButton);
                    
                    newConfirmDeleteButtonPay.setAttribute('data-delete-url', deleteUrl);
                    newConfirmDeleteButtonPay.removeAttribute('href');
                    
                    newConfirmDeleteButtonPay.addEventListener('click', function(e) {
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

    const paymentFormElement = document.getElementById('paymentFormModal');
    if(paymentFormElement) {
        paymentFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(paymentFormElement);
            const actionUrl = '<?php echo base_url('payments/payment_actions.php'); ?>';

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var paymentModalInstance = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                    if(paymentModalInstance) paymentModalInstance.hide();
                    window.location.reload(); 
                } else {
                    alert('خطأ: ' + data.message); 
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ غير متوقع في تسجيل الدفعة.');
            });
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>