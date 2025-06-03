<?php
// includes/modals/invoice_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : '';
}

// --- جلب قائمة عقود الإيجار النشطة لاختيارها ---
$active_leases_list_for_modal = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $leases_query_modal = "SELECT l.id as lease_id, l.lease_contract_number, t.full_name as tenant_name, p.name as property_name, u.unit_number
                           FROM leases l
                           JOIN tenants t ON l.tenant_id = t.id
                           JOIN units u ON l.unit_id = u.id
                           JOIN properties p ON u.property_id = p.id
                           WHERE l.status = 'Active' OR l.status = 'Pending'
                           ORDER BY t.full_name ASC, l.lease_start_date DESC";
    $leases_result_modal = $mysqli->query($leases_query_modal);
    if ($leases_result_modal) {
        while ($lease_row_modal = $leases_result_modal->fetch_assoc()) {
            $active_leases_list_for_modal[] = $lease_row_modal;
        }
        $leases_result_modal->free();
    }
}
// --- جلب قائمة المستأجرين (إذا كانت الفاتورة لا ترتبط بعقد محدد) ---
$tenants_list_for_invoice_modal = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $tenants_q_inv_modal = "SELECT id, full_name, national_id_iqama FROM tenants ORDER BY full_name ASC";
    if($tenants_r_inv_modal = $mysqli->query($tenants_q_inv_modal)){
        while($tenant_r_inv_modal = $tenants_r_inv_modal->fetch_assoc()){ $tenants_list_for_invoice_modal[] = $tenant_r_inv_modal; }
        $tenants_r_inv_modal->free();
    }
}


$invoice_types_zatca_display = [ // لأنواع الفواتير حسب ZATCA
    'SimplifiedInvoice' => 'فاتورة ضريبية مبسطة (B2C)',
    'Invoice' => 'فاتورة ضريبية (B2B)',
    'DebitNote' => 'إشعار مدين',
    'CreditNote' => 'إشعار دائن'
];

$invoice_statuses_display = [ // حالات الفاتورة الداخلية
    'Draft' => 'مسودة', 'Unpaid' => 'غير مدفوعة', 'Partially Paid' => 'مدفوعة جزئياً',
    'Paid' => 'مدفوعة', 'Overdue' => 'متأخرة', 'Cancelled' => 'ملغاة', 'Void' => 'لاغية'
];

$default_vat_percentage = defined('VAT_PERCENTAGE') ? VAT_PERCENTAGE : 15.00;

$zatca_vat_categories = [ // حسب دليل مطوري ZATCA
    'S' => 'خاضع للنسبة الأساسية (Standard rated)',
    'Z' => 'خاضع لنسبة الصفر (Zero rated goods/services)',
    'E' => 'معفى من الضريبة (Exempt from VAT)',
    'O' => 'خارج نطاق الضريبة (Services outside scope of VAT / Not subject to VAT)'
];

?>
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <form id="invoiceForm" method="POST" action=""> <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="invoice_id" id="invoice_id_modal" value="">
                <input type="hidden" name="action" id="invoice_form_action_modal" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceModalLabel">تفاصيل الفاتورة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-3 text-primary">المعلومات الأساسية للفاتورة:</h6>
                    <div class="row gx-3">
                        <div class="col-md-3 mb-3">
                            <label for="invoice_number_modal" class="form-label">رقم الفاتورة (داخلي) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="invoice_number_modal" name="invoice_number" required placeholder="مثال: INV-2025-001">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_sequence_number_modal" class="form-label">رقم تسلسل الفاتورة (ICV) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="invoice_sequence_number_modal" name="invoice_sequence_number" required min="1" placeholder="لـ ZATCA">
                             <small class="form-text text-muted">يجب أن يكون متسلسلاً لكل جهاز/نظام.</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_date_modal" class="form-label">تاريخ إصدار الفاتورة <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="invoice_date_modal" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_time_modal" class="form-label">وقت إصدار الفاتورة <span class="text-danger">*</span></label>
                            <input type="time" class="form-control form-control-sm" id="invoice_time_modal" name="invoice_time" value="<?php echo date('H:i:s'); ?>" required>
                        </div>
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-3 mb-3">
                            <label for="due_date_modal" class="form-label">تاريخ الاستحقاق <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="due_date_modal" name="due_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_type_zatca_modal" class="form-label">نوع الفاتورة (ZATCA) <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="invoice_type_zatca_modal" name="invoice_type_zatca" required>
                                <?php foreach ($invoice_types_zatca_display as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($key === 'SimplifiedInvoice') ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="col-md-3 mb-3">
                            <label for="transaction_type_code_modal" class="form-label">رمز نوع المعاملة (ZATCA BT-3)</label>
                            <input type="text" class="form-control form-control-sm" id="transaction_type_code_modal" name="transaction_type_code" value="388" placeholder="388">
                        </div>
                         <div class="col-md-3 mb-3">
                            <label for="invoice_status_modal" class="form-label">حالة الفاتورة (داخلي) <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="invoice_status_modal" name="invoice_status" required>
                                <?php foreach ($invoice_statuses_display as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($key === 'Unpaid') ? 'selected' : ''; ?>><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                     <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="lease_id_modal" class="form-label">مرتبطة بعقد إيجار (اختياري)</label>
                            <select class="form-select form-select-sm" id="lease_id_modal" name="lease_id">
                                <option value="">-- لا يوجد عقد محدد --</option>
                                <?php foreach ($active_leases_list_for_modal as $lease_item): ?>
                                    <option value="<?php echo $lease_item['lease_id']; ?>" data-tenant-id="<?php echo $lease_item['tenant_id_for_lease_in_invoice_modal'] ?? ''; /* JS will need to populate this tenant id */ ?>">
                                        <?php echo esc_html($lease_item['lease_contract_number'] . ' (' . $lease_item['tenant_name'] . ' - ' . $lease_item['property_name'] . '/' . $lease_item['unit_number'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tenant_id_invoice_modal" class="form-label">المستأجر/العميل (إذا لم يتم اختيار عقد) <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="tenant_id_invoice_modal" name="tenant_id_invoice_direct" required>
                                <option value="">-- اختر المستأجر/العميل --</option>
                                 <?php foreach ($tenants_list_for_invoice_modal as $tenant_inv_item): ?>
                                    <option value="<?php echo $tenant_inv_item['id']; ?>">
                                        <?php echo esc_html($tenant_inv_item['full_name'] . ' (هوية: ' . $tenant_inv_item['national_id_iqama'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                     <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="purchase_order_id_modal" class="form-label">رقم أمر الشراء (ZATCA BT-13)</label>
                            <input type="text" class="form-control form-control-sm" id="purchase_order_id_modal" name="purchase_order_id" placeholder="PO12345">
                        </div>
                        <div class="col-md-6 mb-3">
                             <label for="contract_id_modal_invoice" class="form-label">رقم العقد المرجعي (ZATCA BT-12)</label>
                            <input type="text" class="form-control form-control-sm" id="contract_id_modal_invoice" name="contract_id_invoice" placeholder="CON987">
                        </div>
                    </div>
                     <div class="mb-3">
                        <label for="invoice_description_modal" class="form-label">وصف عام للفاتورة</label>
                        <textarea class="form-control form-control-sm" id="invoice_description_modal" name="invoice_description" rows="2" placeholder="مثال: فاتورة إيجار شهر مايو، خدمات صيانة"></textarea>
                    </div>
                     <div class="mb-3">
                        <label for="zatca_notes_modal" class="form-label">ملاحظات ZATCA (لإشعارات المدين/الدائن)</label>
                        <textarea class="form-control form-control-sm" id="zatca_notes_modal" name="zatca_notes" rows="2" placeholder="سبب إصدار الإشعار المدين أو الدائن"></textarea>
                    </div>


                    <hr class="my-4">
                    <h6 class="mb-3 text-primary">بنود الفاتورة:</h6>
                    <div id="invoiceItemsContainerModal">
                        <div class="row gx-2 gy-2 align-items-center invoice-item-row-modal mb-2" style="display:none;" id="invoiceItemTemplateModal">
                            <div class="col-md-3">
                                <input type="text" name="item_name[]" class="form-control form-control-sm item-name" placeholder="اسم/وصف البند" >
                            </div>
                            <div class="col-md-1">
                                <input type="number" name="item_quantity[]" class="form-control form-control-sm item-quantity" value="1" min="0.01" step="0.01" >
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="item_unit_price[]" class="form-control form-control-sm item-unit-price" value="0.00" min="0" step="0.01" >
                            </div>
                            <div class="col-md-2">
                                <select name="item_vat_category_code[]" class="form-select form-select-sm item-vat-category">
                                    <?php foreach($zatca_vat_categories as $code => $desc): ?>
                                    <option value="<?php echo $code; ?>" <?php echo ($code === 'S') ? 'selected' : ''; ?>><?php echo esc_html($desc); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             <div class="col-md-1">
                                <input type="number" name="item_vat_percentage[]" class="form-control form-control-sm item-vat-percentage" value="<?php echo $default_vat_percentage; ?>" min="0" max="100" step="0.01" >
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="item_discount_amount[]" class="form-control form-control-sm item-discount" value="0.00" min="0" step="0.01" placeholder="خصم البند">
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger removeItemBtnModal"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="addItemBtnModal" class="btn btn-sm btn-outline-success mt-2"><i class="bi bi-plus-circle"></i> إضافة بند جديد</button>

                    <hr class="my-4">
                    <h6 class="mb-3 text-primary">ملخص الفاتورة:</h6>
                     <div class="row gx-3">
                        <div class="col-md-3 mb-3">
                            <label for="invoice_sub_total_amount_modal" class="form-label">المجموع الفرعي (قبل الضريبة والخصم الكلي)</label>
                            <input type="text" class="form-control form-control-sm" id="invoice_sub_total_amount_modal" name="invoice_sub_total_amount" value="0.00" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_total_discount_modal" class="form-label">إجمالي الخصم على الفاتورة</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="invoice_total_discount_modal" name="invoice_total_discount" value="0.00" min="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_vat_percentage_modal_header" class="form-label">نسبة ضريبة القيمة المضافة (%) (للفاتورة ككل)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="invoice_vat_percentage_modal_header" name="invoice_vat_percentage_header" value="<?php echo $default_vat_percentage; ?>" min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_total_vat_amount_modal" class="form-label">إجمالي ضريبة القيمة المضافة</label>
                            <input type="text" class="form-control form-control-sm" id="invoice_total_vat_amount_modal" name="invoice_total_vat_amount" value="0.00" readonly>
                        </div>
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="invoice_total_amount_modal" class="form-label fw-bold fs-5">المبلغ الإجمالي المستحق</label>
                            <input type="text" class="form-control form-control-sm fw-bold fs-5" id="invoice_total_amount_modal" name="invoice_total_amount" value="0.00" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="invoice_paid_amount_modal" class="form-label">المبلغ المدفوع مسبقاً (إذا وجد)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="invoice_paid_amount_modal" name="invoice_paid_amount" value="0.00" min="0">
                        </div>
                    </div>

                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="invoiceSubmitButtonText">حفظ الفاتورة</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript for Invoice Modal (Item Management & Calculations)
document.addEventListener('DOMContentLoaded', function() {
    var invoiceModal = document.getElementById('invoiceModal');
    if (!invoiceModal) return;

    var itemsContainer = invoiceModal.querySelector('#invoiceItemsContainerModal');
    var addItemBtn = invoiceModal.querySelector('#addItemBtnModal');
    var itemTemplate = invoiceModal.querySelector('#invoiceItemTemplateModal');

    // Function to add a new item row
    function addNewItemRow() {
        if (!itemTemplate) return;
        var newItemRow = itemTemplate.cloneNode(true);
        newItemRow.removeAttribute('id');
        newItemRow.style.display = 'flex'; // or 'grid' or 'block' depending on your row layout

        // Clear input values in the new row (template might have default values)
        newItemRow.querySelectorAll('input[type="text"], input[type="number"]').forEach(function(input) {
            if (!input.classList.contains('item-quantity') && !input.classList.contains('item-vat-percentage') && !input.classList.contains('item-discount')) {
                 input.value = '';
            }
            if (input.classList.contains('item-quantity')) input.value = '1';
            if (input.classList.contains('item-vat-percentage')) input.value = '<?php echo $default_vat_percentage; ?>';
            if (input.classList.contains('item-discount')) input.value = '0.00';

        });
        newItemRow.querySelectorAll('select.item-vat-category option[value="S"]').forEach(function(opt){ opt.selected = true; });


        // Add event listener to the new remove button
        var removeBtn = newItemRow.querySelector('.removeItemBtnModal');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                this.closest('.invoice-item-row-modal').remove();
                calculateInvoiceTotals();
            });
        }
        // Add event listeners for calculation on change
        newItemRow.querySelectorAll('.item-quantity, .item-unit-price, .item-vat-percentage, .item-discount').forEach(function(input) {
            input.addEventListener('change', calculateInvoiceTotals);
            input.addEventListener('keyup', calculateInvoiceTotals);
        });

        if (itemsContainer) {
            itemsContainer.appendChild(newItemRow);
        }
        calculateInvoiceTotals(); // Recalculate after adding
    }

    if (addItemBtn) {
        addItemBtn.addEventListener('click', addNewItemRow);
    }

    // Function to calculate totals
    function calculateInvoiceTotals() {
        if (!invoiceModal) return;
        var itemRows = itemsContainer.querySelectorAll('.invoice-item-row-modal:not(#invoiceItemTemplateModal)'); // Exclude template
        var subTotal = 0;
        var totalVAT = 0;

        itemRows.forEach(function(row) {
            var quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
            var unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
            var itemDiscount = parseFloat(row.querySelector('.item-discount').value) || 0;
            var vatPercentage = parseFloat(row.querySelector('.item-vat-percentage').value) || 0;

            var itemSubTotalBeforeDiscount = quantity * unitPrice;
            var itemTaxableAmount = itemSubTotalBeforeDiscount - itemDiscount;
            if(itemTaxableAmount < 0) itemTaxableAmount = 0; // Cannot be negative

            var itemVAT = (itemTaxableAmount * vatPercentage) / 100;

            subTotal += itemTaxableAmount; // This subtotal is after item discounts, before invoice-level discount and VAT
            totalVAT += itemVAT;
        });

        var invoiceSubTotalInput = invoiceModal.querySelector('#invoice_sub_total_amount_modal');
        var invoiceTotalDiscountInput = invoiceModal.querySelector('#invoice_total_discount_modal');
        var invoiceHeaderVatPercentageInput = invoiceModal.querySelector('#invoice_vat_percentage_modal_header');
        var invoiceTotalVATInput = invoiceModal.querySelector('#invoice_total_vat_amount_modal');
        var invoiceTotalAmountInput = invoiceModal.querySelector('#invoice_total_amount_modal');

        if (invoiceSubTotalInput) invoiceSubTotalInput.value = subTotal.toFixed(2);

        var invoiceLevelDiscount = parseFloat(invoiceTotalDiscountInput.value) || 0;
        var finalTaxableAmount = subTotal - invoiceLevelDiscount;
        if(finalTaxableAmount < 0) finalTaxableAmount = 0;

        // VAT calculation preference:
        // 1. Sum of item-level calculated VATs (more accurate for ZATCA if items have different rates)
        // 2. Or, calculate based on invoice-level subtotal (after discount) and header VAT rate.
        // For ZATCA, item-level is usually preferred. Let's use sum of item VATs.
        var finalTotalVAT = totalVAT; // Using sum of item VATs.

        // If you prefer to use header VAT rate on the net amount after invoice discount:
        // var headerVatRate = parseFloat(invoiceHeaderVatPercentageInput.value) || <?php echo $default_vat_percentage; ?>;
        // finalTotalVAT = (finalTaxableAmount * headerVatRate) / 100;


        if (invoiceTotalVATInput) invoiceTotalVATInput.value = finalTotalVAT.toFixed(2);
        if (invoiceTotalAmountInput) invoiceTotalAmountInput.value = (finalTaxableAmount + finalTotalVAT).toFixed(2);
    }

    // Event listeners for invoice-level discount and VAT rate changes
    var invDiscountInput = invoiceModal.querySelector('#invoice_total_discount_modal');
    var invVatHeaderInput = invoiceModal.querySelector('#invoice_vat_percentage_modal_header');
    if(invDiscountInput) {
        invDiscountInput.addEventListener('change', calculateInvoiceTotals);
        invDiscountInput.addEventListener('keyup', calculateInvoiceTotals);
    }
    if(invVatHeaderInput) {
        // If using header VAT to recalculate total VAT, uncomment these.
        // invVatHeaderInput.addEventListener('change', calculateInvoiceTotals);
        // invVatHeaderInput.addEventListener('keyup', calculateInvoiceTotals);
    }


    // Initial calculation when modal is shown (especially for edit)
    invoiceModal.addEventListener('shown.bs.modal', function () {
        // If in edit mode, item rows should be populated by server-side PHP/JS when modal data is set.
        // After populating, call calculateInvoiceTotals().
        // For now, if items are dynamically added, calculate.
        var existingItemRows = itemsContainer.querySelectorAll('.invoice-item-row-modal:not(#invoiceItemTemplateModal)');
        existingItemRows.forEach(function(row) {
             row.querySelectorAll('.item-quantity, .item-unit-price, .item-vat-percentage, .item-discount').forEach(function(input) {
                input.addEventListener('change', calculateInvoiceTotals);
                input.addEventListener('keyup', calculateInvoiceTotals);
            });
            row.querySelector('.removeItemBtnModal').addEventListener('click', function() {
                this.closest('.invoice-item-row-modal').remove();
                calculateInvoiceTotals();
            });
        });
        calculateInvoiceTotals();
    });

    // Handle lease selection to auto-populate tenant if possible
    var leaseSelect = invoiceModal.querySelector('#lease_id_modal');
    var tenantDirectSelect = invoiceModal.querySelector('#tenant_id_invoice_modal');
    if(leaseSelect && tenantDirectSelect){
        leaseSelect.addEventListener('change', function(){
            if(this.value !== ""){
                var selectedLeaseOption = this.options[this.selectedIndex];
                var tenantIdForLease = selectedLeaseOption.getAttribute('data-tenant-id');
                if(tenantIdForLease){
                    tenantDirectSelect.value = tenantIdForLease;
                    tenantDirectSelect.setAttribute('disabled', 'disabled'); // Disable direct tenant selection
                } else {
                     tenantDirectSelect.removeAttribute('disabled');
                }
            } else {
                tenantDirectSelect.removeAttribute('disabled');
                tenantDirectSelect.value = ""; // Clear tenant if lease is unselected
            }
        });
    }


    // Add at least one item row when the modal is prepared for 'add_invoice'
    // This is typically done when the 'Add Invoice' button is clicked and modal 'show.bs.modal' is triggered
    // See the part in index.php that handles modal show for 'add_invoice'
});
</script>