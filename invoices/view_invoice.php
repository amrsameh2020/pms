<?php
$page_title = "عرض تفاصيل الفاتورة";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    set_message("معرف الفاتورة غير صحيح أو مفقود.", "danger");
    redirect(base_url('invoices/index.php'));
}

// Fetch invoice header data
$stmt_invoice = $mysqli->prepare(
    "SELECT i.*, 
            t.full_name as tenant_full_name, t.national_id_iqama as tenant_national_id, t.email as tenant_email, t.phone_primary as tenant_phone,
            t.current_address as tenant_address_current, 
            t.buyer_vat_number, t.buyer_street_name, t.buyer_building_no, t.buyer_additional_no, 
            t.buyer_postal_code, t.buyer_district_name, t.buyer_city_name, t.buyer_country_code,
            l.lease_contract_number,
            u_created.full_name as created_by_fullname
     FROM invoices i
     LEFT JOIN tenants t ON i.tenant_id = t.id
     LEFT JOIN leases l ON i.lease_id = l.id
     LEFT JOIN users u_created ON i.created_by_id = u_created.id
     WHERE i.id = ?"
);
if(!$stmt_invoice){
    error_log("View Invoice Prepare Error (Invoice): " . $mysqli->error);
    set_message("خطأ في تجهيز عرض الفاتورة.", "danger");
    redirect(base_url('invoices/index.php'));
}
$stmt_invoice->bind_param("i", $invoice_id);
$stmt_invoice->execute();
$result_invoice = $stmt_invoice->get_result();

if ($result_invoice->num_rows === 0) {
    set_message("الفاتورة المطلوبة غير موجودة.", "warning");
    redirect(base_url('invoices/index.php'));
}
$invoice = $result_invoice->fetch_assoc();
$stmt_invoice->close();

// Fetch invoice items
$invoice_items = [];
$stmt_items = $mysqli->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
if ($stmt_items) {
    $stmt_items->bind_param("i", $invoice_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $invoice_items = $result_items->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();
} else {
    error_log("View Invoice Prepare Error (Items): " . $mysqli->error);
    // Continue without items, or show an error
}

// ZATCA Status and QR display (using placeholder for QR image generation for now)
$zatca_qr_image_src = '';
if (!empty($invoice['zatca_qr_code_data'])) {
    // In a real scenario, you'd generate an image from this TLV Base64 data
    // For now, let's assume generate_zatca_qr_code_placeholder returns a data URI or path
    // For this example, we'll just display the raw data if no image generation is in place.
    // $zatca_qr_image_src = generate_qr_image_from_tlv_base64($invoice['zatca_qr_code_data']);
    // Using the simple placeholder function for now
    $zatca_qr_image_src = generate_zatca_qr_code_placeholder([
        'seller_name' => defined('ZATCA_SELLER_NAME') ? ZATCA_SELLER_NAME : '',
        'vat_number' => defined('ZATCA_SELLER_VAT_NUMBER') ? ZATCA_SELLER_VAT_NUMBER : '',
        'timestamp' => $invoice['invoice_date'] . 'T' . $invoice['invoice_time'],
        'total_amount' => $invoice['total_amount'], // Amount with VAT
        'vat_amount' => $invoice['vat_amount'],     // VAT Amount
        'xml_hash' => $invoice['zatca_invoice_hash'] ?? 'N/A' // XML Invoice Hash
        // Potentially other data required by your QR generation logic
    ]);
}

// Display names for statuses (can be moved to functions.php if used elsewhere)
$invoice_statuses_display_view = [
    'Draft' => 'مسودة', 'Unpaid' => 'غير مدفوعة', 'Partially Paid' => 'مدفوعة جزئياً',
    'Paid' => 'مدفوعة', 'Overdue' => 'متأخرة', 'Cancelled' => 'ملغاة', 'Void' => 'لاغية'
];
$zatca_statuses_display_view = [
    'Not Sent' => 'لم ترسل', 'Sent' => 'مرسلة', 'Generating' => 'قيد الإنشاء',
    'Compliance Check Pending' => 'فحص الامتثال معلق', 'Compliance Check Failed' => 'فشل فحص الامتثال',
    'Compliance Check Passed' => 'نجح فحص الامتثال', 'Clearance Pending' => 'التصريح معلق',
    'Cleared' => 'تم التصريح (Clearance)', 'Reporting Pending' => 'الإبلاغ معلق', 'Reported' => 'تم الإبلاغ (Reporting)',
    'Rejected' => 'مرفوضة من ZATCA', 'Error' => 'خطأ في معالجة ZATCA'
];
$invoice_type_zatca_display_view = [
    'Invoice' => 'فاتورة ضريبية (B2B)', 'SimplifiedInvoice' => 'فاتورة ضريبية مبسطة (B2C)',
    'DebitNote' => 'إشعار مدين', 'CreditNote' => 'إشعار دائن'
];


require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';
?>
<style>
    .invoice-box {
        max-width: 900px;
        margin: auto;
        padding: 30px;
        border: 1px solid #eee;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        font-size: 16px;
        line-height: 24px;
        font-family: 'Cairo', sans-serif;
        color: #555;
        background-color: #fff;
    }
    .invoice-box table { width: 100%; line-height: inherit; text-align: right; }
    .invoice-box table td { padding: 5px; vertical-align: top; }
    .invoice-box table tr td:nth-child(2) { text-align: left; } /* For English text if any */
    .invoice-box table tr.top table td { padding-bottom: 20px; }
    .invoice-box table tr.top table td.title { font-size: 35px; line-height: 35px; color: #333; }
    .invoice-box table tr.information table td { padding-bottom: 20px; }
    .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; text-align:right; }
    .invoice-box table tr.details td { padding-bottom: 10px; }
    .invoice-box table tr.item td { border-bottom: 1px solid #eee; text-align:right; }
    .invoice-box table tr.item.last td { border-bottom: none; }
    .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; text-align:left; }
    .invoice-box .qr-code-zatca { max-width: 150px; margin-top: 20px; }
    .print-button-container { text-align: center; margin-top: 20px; margin-bottom: 40px;}
    @media print {
        body { -webkit-print-color-adjust: exact; /* Chrome, Safari */ color-adjust: exact; /* Firefox */ }
        .no-print { display: none !important; }
        .invoice-box { box-shadow: none; border: none; margin: 0; max-width: 100%; padding:0;}
    }
</style>

<div class="container-fluid">
    <div class="content-header no-print">
        <h1><?php echo esc_html($page_title) . ': ' . esc_html($invoice['invoice_number']); ?></h1>
    </div>

    <div class="print-button-container no-print">
        <button onclick="window.print();" class="btn btn-primary"><i class="bi bi-printer-fill"></i> طباعة الفاتورة</button>
        <a href="<?php echo base_url('invoices/index.php'); ?>" class="btn btn-outline-secondary"><i class="bi bi-list-ul"></i> العودة للقائمة</a>
         <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#invoiceModal" data-invoice_id="<?php echo $invoice['id']; ?>" onclick="prepareEditInvoice(this)">
            <i class="bi bi-pencil-square"></i> تعديل هذه الفاتورة
        </button>
    </div>

    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title">
                                <?php /* <img src="<?php echo base_url('assets/images/your-logo.png'); ?>" style="width:100%; max-width:150px;"> */ ?>
                                <h2 style="margin:0;"><?php echo esc_html(defined('ZATCA_SELLER_NAME') ? ZATCA_SELLER_NAME : APP_NAME); ?></h2>
                            </td>
                            <td>
                                فاتورة رقم: <strong><?php echo esc_html($invoice['invoice_number']); ?></strong><br>
                                رقم تسلسل الفاتورة (ICV): <?php echo esc_html($invoice['invoice_sequence_number']); ?><br>
                                تاريخ الإصدار: <?php echo format_date_custom($invoice['invoice_date'] . ' ' . $invoice['invoice_time'], 'd-m-Y H:i A'); ?><br>
                                تاريخ الاستحقاق: <?php echo format_date_custom($invoice['due_date'], 'd-m-Y'); ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(defined('ZATCA_SELLER_NAME') ? ZATCA_SELLER_NAME : APP_NAME); ?></strong><br>
                                <?php echo esc_html(defined('ZATCA_SELLER_STREET_NAME') ? ZATCA_SELLER_STREET_NAME : ''); ?>
                                <?php echo esc_html(defined('ZATCA_SELLER_BUILDING_NO') ? ', مبنى ' . ZATCA_SELLER_BUILDING_NO : ''); ?><br>
                                <?php echo esc_html(defined('ZATCA_SELLER_DISTRICT_NAME') ? ZATCA_SELLER_DISTRICT_NAME : ''); ?>
                                <?php echo esc_html(defined('ZATCA_SELLER_CITY_NAME') ? ', ' . ZATCA_SELLER_CITY_NAME : ''); ?>
                                <?php echo esc_html(defined('ZATCA_SELLER_POSTAL_CODE') ? ', ' . ZATCA_SELLER_POSTAL_CODE : ''); ?><br>
                                <?php echo esc_html(defined('ZATCA_SELLER_COUNTRY_CODE') ? ZATCA_SELLER_COUNTRY_CODE : ''); ?><br>
                                الرقم الضريبي: <?php echo esc_html(defined('ZATCA_SELLER_VAT_NUMBER') ? ZATCA_SELLER_VAT_NUMBER : ''); ?>
                            </td>
                            <td>
                                <strong>فاتورة إلى: <?php echo esc_html($invoice['tenant_full_name']); ?></strong><br>
                                <?php echo esc_html($invoice['tenant_address_current'] ?: ($invoice['buyer_street_name'] ? $invoice['buyer_street_name'] . ($invoice['buyer_building_no'] ? ', مبنى '.$invoice['buyer_building_no'] : '') : '')); ?><br>
                                <?php echo esc_html($invoice['buyer_district_name'] ? $invoice['buyer_district_name'] : ''); ?>
                                <?php echo esc_html($invoice['buyer_city_name'] ? ', ' . $invoice['buyer_city_name'] : ''); ?>
                                <?php echo esc_html($invoice['buyer_postal_code'] ? ', ' . $invoice['buyer_postal_code'] : ''); ?><br>
                                <?php echo esc_html($invoice['buyer_country_code'] ?: ''); ?><br>
                                <?php if(!empty($invoice['buyer_vat_number'])): ?>
                                    الرقم الضريبي للمشتري: <?php echo esc_html($invoice['buyer_vat_number']); ?><br>
                                <?php endif; ?>
                                <?php echo esc_html($invoice['tenant_phone'] ? 'جوال: ' . $invoice['tenant_phone'] : ''); ?><br>
                                <?php echo esc_html($invoice['tenant_email'] ? 'بريد: ' . $invoice['tenant_email'] : ''); ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="heading">
                <td>نوع الفاتورة (ZATCA)</td>
                <td style="text-align:left;"><?php echo esc_html($invoice_type_zatca_display_view[$invoice['invoice_type_zatca']] ?? $invoice['invoice_type_zatca']); ?></td>
            </tr>
            <tr class="details">
                <td>الحالة الداخلية</td>
                <td style="text-align:left;"><?php echo esc_html($invoice_statuses_display_view[$invoice['status']] ?? $invoice['status']); ?></td>
            </tr>
             <tr class="details">
                <td>حالة ZATCA</td>
                <td style="text-align:left;"><?php echo esc_html($zatca_statuses_display_view[$invoice['zatca_status']] ?? $invoice['zatca_status']); ?></td>
            </tr>
            <?php if(!empty($invoice['lease_contract_number'])): ?>
            <tr class="details">
                <td>عقد الإيجار المرتبط</td>
                <td style="text-align:left;"><?php echo esc_html($invoice['lease_contract_number']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if(!empty($invoice['purchase_order_id'])): ?>
            <tr class="details">
                <td>رقم أمر الشراء</td>
                <td style="text-align:left;"><?php echo esc_html($invoice['purchase_order_id']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if(!empty($invoice['contract_id'])): ?> <tr class="details">
                <td>رقم العقد المرجعي</td>
                <td style="text-align:left;"><?php echo esc_html($invoice['contract_id']); ?></td>
            </tr>
            <?php endif; ?>


            <tr class="heading">
                <td>وصف البند</td>
                <td style="text-align:left;">السعر الإفرادي</td>
                </tr>
            <?php $calculated_items_subtotal = 0; ?>
            <?php foreach ($invoice_items as $item):
                // $item_total_before_vat = ($item['quantity'] * $item['unit_price_before_vat']) - ($item['item_discount_amount'] ?? 0);
                // $item_vat = ($item_total_before_vat * $item['item_vat_percentage'] / 100);
                // $item_total_with_vat = $item_total_before_vat + $item_vat;
                // $calculated_items_subtotal += $item_total_before_vat; // Sum of item taxable amounts
            ?>
            <tr class="item <?php echo end($invoice_items) === $item ? 'last' : ''; ?>">
                <td>
                    <?php echo esc_html($item['item_name']); ?><br>
                    <small class="text-muted">
                        الكمية: <?php echo esc_html($item['quantity']); ?>, 
                        السعر: <?php echo number_format($item['unit_price_before_vat'], 2); ?>,
                        خصم البند: <?php echo number_format($item['item_discount_amount'] ?? 0, 2); ?>,
                        ضريبة القيمة المضافة (<?php echo number_format($item['item_vat_percentage'], 2); ?>%): <?php echo number_format($item['item_vat_amount'], 2); ?>
                    </small>
                </td>
                <td style="text-align:left;"><?php echo number_format($item['item_sub_total_with_vat'], 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="total">
                <td></td>
                <td style="text-align:left;"><strong>المجموع الفرعي للبنود (خاضع للضريبة): <?php echo number_format($invoice['sub_total_amount'], 2); ?> ريال</strong></td>
            </tr>
            <?php if (isset($invoice['discount_amount']) && $invoice['discount_amount'] > 0): ?>
            <tr class="total">
                <td></td>
                <td style="text-align:left;">خصم على إجمالي الفاتورة: <?php echo number_format($invoice['discount_amount'], 2); ?> ريال</td>
            </tr>
            <?php endif; ?>
            <tr class="total">
                <td></td>
                <td style="text-align:left;">ضريبة القيمة المضافة (<?php echo number_format($invoice['vat_percentage'], 2); ?>%): <?php echo number_format($invoice['vat_amount'], 2); ?> ريال</td>
            </tr>
            <tr class="total">
                <td></td>
                <td style="text-align:left; font-size: 1.2em;"><strong>الإجمالي المستحق: <?php echo number_format($invoice['total_amount'], 2); ?> ريال</strong></td>
            </tr>
             <tr class="total">
                <td></td>
                <td style="text-align:left;">المدفوع: <?php echo number_format($invoice['paid_amount'], 2); ?> ريال</td>
            </tr>
             <tr class="total">
                <td></td>
                <td style="text-align:left; color: <?php echo ($invoice['total_amount'] - $invoice['paid_amount']) > 0 ? 'red' : 'green'; ?>;">
                    <strong>المتبقي: <?php echo number_format($invoice['total_amount'] - $invoice['paid_amount'], 2); ?> ريال</strong>
                </td>
            </tr>
        </table>
        <hr>
        <?php if (!empty($invoice['description'])): ?>
            <p><strong>وصف الفاتورة:</strong><br><?php echo nl2br(esc_html($invoice['description'])); ?></p>
        <?php endif; ?>
        <?php if (!empty($invoice['notes_zatca'])): ?>
            <p><strong>ملاحظات ZATCA:</strong><br><?php echo nl2br(esc_html($invoice['notes_zatca'])); ?></p>
        <?php endif; ?>

        <?php if ($zatca_qr_image_src): ?>
        <div style="text-align: center; margin-top: 20px;">
            <h5>رمز الاستجابة السريعة (ZATCA)</h5>
            <?php if (strpos($zatca_qr_image_src, 'data:image') === 0): // If it's a data URI ?>
                <img src="<?php echo $zatca_qr_image_src; ?>" alt="ZATCA QR Code" class="qr-code-zatca">
            <?php else: // If it's a path to an image file (not implemented by placeholder) or just raw data ?>
                <p><small>بيانات QR (تحتاج إلى مكتبة لتحويلها لصورة):</small></p>
                <div style="word-break: break-all; font-size: 0.7em; max-height: 100px; overflow-y: auto; border: 1px solid #ccc; padding: 5px; background-color: #f9f9f9;"><?php echo esc_html($invoice['zatca_qr_code_data']); ?></div>
            <?php endif; ?>
             <br><small>المعرف الفريد (UUID): <?php echo esc_html($invoice['zatca_uuid'] ?: 'N/A'); ?></small>
             <br><small>تجزئة الفاتورة (HASH): <?php echo esc_html($invoice['zatca_invoice_hash'] ?: 'N/A'); ?></small>
        </div>
        <?php endif; ?>
    </div>
</div>

</div> <script>
    // JavaScript to prepare invoice modal for edit when "تعديل هذه الفاتورة" is clicked
    // This assumes the invoiceModal and its item management JS is available (from includes/modals/invoice_modal.php and its script block)
    function prepareEditInvoice(buttonElement) {
        var invoiceId = buttonElement.getAttribute('data-invoice_id');
        if (!invoiceId) return;

        var invoiceModalElement = document.getElementById('invoiceModal');
        if (!invoiceModalElement) return;
        
        var invoiceModalInstance = bootstrap.Modal.getInstance(invoiceModalElement) || new bootstrap.Modal(invoiceModalElement);

        // --- Simulate button click for edit action ---
        // Create a mock button with all necessary data attributes for the modal's 'show.bs.modal' event listener
        var mockButton = document.createElement('button');
        mockButton.setAttribute('data-action', 'edit_invoice'); // This is key
        mockButton.setAttribute('data-invoice_id', invoiceId);

        // Fetch full invoice data (header + items) via a separate request or ensure it's available in JS
        // For this example, we'll assume you'd populate the modal form fields directly here
        // after fetching data. The existing modal's 'show.bs.modal' listener needs to be adapted
        // or this function needs to do more comprehensive population.

        // Populate header (example - get data from the displayed invoice on this page)
        var modalTitle = invoiceModalElement.querySelector('.modal-title');
        var invoiceForm = invoiceModalElement.querySelector('#invoiceForm');
        var invoiceIdInput = invoiceModalElement.querySelector('#invoice_id_modal');
        var formActionInput = invoiceModalElement.querySelector('#invoice_form_action_modal');
        var submitButtonText = invoiceModalElement.querySelector('#invoiceSubmitButtonText');
        
        if(invoiceForm) invoiceForm.reset();
        invoiceModalElement.querySelectorAll('#invoiceItemsContainerModal .invoice-item-row-modal:not(#invoiceItemTemplateModal)').forEach(row => row.remove());


        if(modalTitle) modalTitle.textContent = 'تعديل بيانات الفاتورة';
        if(formActionInput) formActionInput.value = 'edit_invoice';
        if(submitButtonText) submitButtonText.textContent = 'حفظ التعديلات';
        if(invoiceForm) invoiceForm.action = '<?php echo base_url('invoices/invoice_actions.php'); ?>';
        if(invoiceIdInput) invoiceIdInput.value = invoiceId;

        // Populate header fields from the $invoice PHP variable available on this page
        // This is a simplified approach. A robust solution might use a JS object or AJAX.
        <?php if (isset($invoice) && is_array($invoice)): ?>
        if(document.getElementById('invoice_number_modal')) document.getElementById('invoice_number_modal').value = '<?php echo esc_js($invoice['invoice_number']); ?>';
        if(document.getElementById('invoice_sequence_number_modal')) document.getElementById('invoice_sequence_number_modal').value = '<?php echo esc_js($invoice['invoice_sequence_number']); ?>';
        if(document.getElementById('invoice_date_modal')) document.getElementById('invoice_date_modal').value = '<?php echo esc_js($invoice['invoice_date']); ?>';
        if(document.getElementById('invoice_time_modal')) document.getElementById('invoice_time_modal').value = '<?php echo esc_js($invoice['invoice_time']); ?>';
        if(document.getElementById('due_date_modal')) document.getElementById('due_date_modal').value = '<?php echo esc_js($invoice['due_date']); ?>';
        if(document.getElementById('invoice_type_zatca_modal')) document.getElementById('invoice_type_zatca_modal').value = '<?php echo esc_js($invoice['invoice_type_zatca']); ?>';
        if(document.getElementById('transaction_type_code_modal')) document.getElementById('transaction_type_code_modal').value = '<?php echo esc_js($invoice['transaction_type_code']); ?>';
        if(document.getElementById('invoice_status_modal')) document.getElementById('invoice_status_modal').value = '<?php echo esc_js($invoice['status']); ?>';
        if(document.getElementById('lease_id_modal')) document.getElementById('lease_id_modal').value = '<?php echo esc_js($invoice['lease_id'] ?: ''); ?>';
        if(document.getElementById('tenant_id_invoice_modal')) {
            document.getElementById('tenant_id_invoice_modal').value = '<?php echo esc_js($invoice['tenant_id'] ?: ''); ?>';
            if('<?php echo esc_js($invoice['lease_id'] ?: ''); ?>' !== ''){ // If lease selected, disable tenant direct select
                document.getElementById('tenant_id_invoice_modal').setAttribute('disabled', 'disabled');
            } else {
                 document.getElementById('tenant_id_invoice_modal').removeAttribute('disabled');
            }
        }
        if(document.getElementById('purchase_order_id_modal')) document.getElementById('purchase_order_id_modal').value = '<?php echo esc_js($invoice['purchase_order_id']); ?>';
        if(document.getElementById('contract_id_modal_invoice')) document.getElementById('contract_id_modal_invoice').value = '<?php echo esc_js($invoice['contract_id']); ?>';
        if(document.getElementById('invoice_description_modal')) document.getElementById('invoice_description_modal').value = '<?php echo esc_js($invoice['description']); ?>';
        if(document.getElementById('zatca_notes_modal')) document.getElementById('zatca_notes_modal').value = '<?php echo esc_js($invoice['notes_zatca']); ?>';
        if(document.getElementById('invoice_total_discount_modal')) document.getElementById('invoice_total_discount_modal').value = '<?php echo esc_js(number_format($invoice['discount_amount'], 2, '.', '')); ?>';
        if(document.getElementById('invoice_vat_percentage_modal_header')) document.getElementById('invoice_vat_percentage_modal_header').value = '<?php echo esc_js(number_format($invoice['vat_percentage'], 2, '.', '')); ?>';
        if(document.getElementById('invoice_paid_amount_modal')) document.getElementById('invoice_paid_amount_modal').value = '<?php echo esc_js(number_format($invoice['paid_amount'], 2, '.', '')); ?>';
        <?php endif; ?>

        // Populate items (this requires the items data to be available in JS)
        var itemsContainerModal = document.getElementById('invoiceItemsContainerModal');
        var addItemButtonModal = document.getElementById('addItemBtnModal'); // From modal's own JS
        <?php if (isset($invoice_items) && !empty($invoice_items)): ?>
        <?php foreach ($invoice_items as $item_js): ?>
            if (addItemButtonModal) { // Ensure addItemBtnModal's click handler is set up in modal's JS
                // Directly create and populate row or rely on addItemBtnModal's functionality
                // For now, let's assume addItemBtnModal creates a blank row, then we populate it.
                addItemButtonModal.click(); // This adds a new blank row
                var lastItemRow = itemsContainerModal.querySelector('.invoice-item-row-modal:not(#invoiceItemTemplateModal):last-child');
                if (lastItemRow) {
                    if(lastItemRow.querySelector('.item-name')) lastItemRow.querySelector('.item-name').value = '<?php echo esc_js($item_js['item_name']); ?>';
                    if(lastItemRow.querySelector('.item-quantity')) lastItemRow.querySelector('.item-quantity').value = '<?php echo esc_js($item_js['quantity']); ?>';
                    if(lastItemRow.querySelector('.item-unit-price')) lastItemRow.querySelector('.item-unit-price').value = '<?php echo esc_js($item_js['unit_price_before_vat']); ?>';
                    if(lastItemRow.querySelector('.item-vat-category')) lastItemRow.querySelector('.item-vat-category').value = '<?php echo esc_js($item_js['item_vat_category_code']); ?>';
                    if(lastItemRow.querySelector('.item-vat-percentage')) lastItemRow.querySelector('.item-vat-percentage').value = '<?php echo esc_js($item_js['item_vat_percentage']); ?>';
                    if(lastItemRow.querySelector('.item-discount')) lastItemRow.querySelector('.item-discount').value = '<?php echo esc_js($item_js['item_discount_amount'] ?? '0.00'); ?>';
                }
            }
        <?php endforeach; ?>
        <?php else: ?>
            // if (addItemButtonModal) addItemButtonModal.click(); // Add one blank if no items
        <?php endif; ?>
        
        // Trigger calculations
        if(document.getElementById('invoice_total_discount_modal')) document.getElementById('invoice_total_discount_modal').dispatchEvent(new Event('change'));


        invoiceModalInstance.show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>