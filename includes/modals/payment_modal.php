<?php
// includes/modals/payment_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : 'fallback_csrf_payment_modal';
}

// --- جلب قائمة الفواتير (غير المدفوعة أو المدفوعة جزئياً) ---
$invoices_list_for_payment_modal = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    // جلب الفواتير مع المبلغ المتبقي للدفع
    $invoices_query_pay_modal = "
        SELECT i.id, i.invoice_number, i.total_amount, i.due_date, t.full_name as tenant_name,
               (i.total_amount - COALESCE(SUM(p.amount_paid), 0)) as remaining_amount
        FROM invoices i
        JOIN tenants t ON i.tenant_id = t.id
        LEFT JOIN payments p ON i.id = p.invoice_id AND p.status = 'Completed' -- فقط المدفوعات المكتملة تؤثر على المتبقي
        WHERE i.status IN ('Unpaid', 'Partially Paid')
        GROUP BY i.id, i.invoice_number, i.total_amount, i.due_date, t.full_name
        HAVING remaining_amount > 0
        ORDER BY i.due_date ASC, i.invoice_number ASC";
        
    $invoices_result_pay_modal = $mysqli->query($invoices_query_pay_modal);
    if ($invoices_result_pay_modal) {
        while ($invoice_row_pay_modal = $invoices_result_pay_modal->fetch_assoc()) {
            $invoices_list_for_payment_modal[] = $invoice_row_pay_modal;
        }
        $invoices_result_pay_modal->free();
    } else {
        error_log("Payment Modal: Failed to fetch invoices: " . $mysqli->error);
    }
}

// --- جلب قائمة طرق الدفع النشطة ---
$payment_methods_list_for_payment_modal_dd = []; // تم تغيير الاسم ليكون أكثر تحديداً
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $pm_query_modal = "SELECT id, display_name_ar, method_name FROM payment_methods WHERE is_active = 1 ORDER BY display_name_ar ASC";
    $pm_result_modal = $mysqli->query($pm_query_modal);
    if ($pm_result_modal) {
        while ($pm_row_modal = $pm_result_modal->fetch_assoc()) {
            $payment_methods_list_for_payment_modal_dd[] = $pm_row_modal;
        }
        $pm_result_modal->free();
    } else {
        error_log("Payment Modal: Failed to fetch payment methods: " . $mysqli->error);
    }
}


$payment_statuses_modal_options = [ // تم تغيير الاسم
    'Pending' => 'معلقة',
    'Completed' => 'مكتملة',
    'Failed' => 'فشلت',
    'Cancelled' => 'ملغاة',
    'Refunded' => 'مستردة'
];
?>
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="paymentFormModal" enctype="multipart/form-data"> <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="payment_id" id="payment_id_modal_payments_page" value=""> <input type="hidden" name="action" id="payment_form_action_modal_payments_page" value=""> <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel_payments_page">تسجيل دفعة جديدة</h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="invoice_id_modal_payments_page" class="form-label">الفاتورة المرتبطة <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="invoice_id_modal_payments_page" name="invoice_id" required>
                                <option value="">-- اختر الفاتورة --</option>
                                <?php foreach ($invoices_list_for_payment_modal as $invoice_item_pay): ?>
                                    <option value="<?php echo $invoice_item_pay['id']; ?>" 
                                            data-total-amount="<?php echo esc_attr($invoice_item_pay['total_amount']); ?>"
                                            data-remaining-amount="<?php echo esc_attr($invoice_item_pay['remaining_amount']); ?>">
                                        <?php echo esc_html('رقم: ' . $invoice_item_pay['invoice_number'] . ' - مستأجر: ' . $invoice_item_pay['tenant_name'] . ' (الإجمالي: ' . number_format($invoice_item_pay['total_amount'], 2) . '، المتبقي: ' . number_format($invoice_item_pay['remaining_amount'], 2) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="invoice_details_text_payments_modal" class="form-text text-muted"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount_paid_modal_payments_page" class="form-label">المبلغ المدفوع <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amount_paid_modal_payments_page" name="amount_paid" required min="0.01" placeholder="0.00">
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="payment_date_modal_payments_page" class="form-label">تاريخ الدفع <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="payment_date_modal_payments_page" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="payment_method_id_modal_payments_page" class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="payment_method_id_modal_payments_page" name="payment_method_id" required>
                                <option value="">-- اختر طريقة الدفع --</option>
                                <?php foreach ($payment_methods_list_for_payment_modal_dd as $pm_item_dd): ?>
                                    <option value="<?php echo $pm_item_dd['id']; ?>"><?php echo esc_html($pm_item_dd['display_name_ar']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="payment_status_modal_payments_page" class="form-label">حالة الدفع <span class="text-danger">*</span></label>
                             <select class="form-select form-select-sm" id="payment_status_modal_payments_page" name="payment_status" required>
                                <?php foreach ($payment_statuses_modal_options as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($key === 'Completed') ? 'selected' : '';?>><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="receipt_number_modal_payments_page" class="form-label">رقم الإيصال/المرجع</label>
                            <input type="text" class="form-control form-control-sm" id="receipt_number_modal_payments_page" name="receipt_number" placeholder="مثال: INV-2025-001-P1">
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="payment_attachment_modal_payments_page" class="form-label">مرفق (إيصال، صورة تحويل)</label>
                            <input type="file" class="form-control form-control-sm" id="payment_attachment_modal_payments_page" name="payment_attachment" accept=".pdf,.jpg,.jpeg,.png,.gif">
                            <small id="current_payment_attachment_text_modal" class="form-text text-muted"></small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="payment_notes_modal_payments_page" class="form-label">ملاحظات على الدفعة</label>
                        <textarea class="form-control form-control-sm" id="payment_notes_modal_payments_page" name="payment_notes" rows="2" placeholder="أي تفاصيل إضافية"></textarea>
                    </div>
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="paymentSubmitButtonTextModalPaymentsPage">حفظ الدفعة</span> </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Script to handle invoice selection and display remaining amount in payment modal
document.addEventListener('DOMContentLoaded', function() {
    var invoiceSelectPaymentModal = document.getElementById('invoice_id_modal_payments_page'); // تم تغيير ID
    var amountPaidInputPaymentModal = document.getElementById('amount_paid_modal_payments_page'); // تم تغيير ID
    var invoiceDetailsTextPaymentModal = document.getElementById('invoice_details_text_payments_modal'); // تم تغيير ID

    if (invoiceSelectPaymentModal && amountPaidInputPaymentModal && invoiceDetailsTextPaymentModal) {
        invoiceSelectPaymentModal.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.value !== '') {
                var remainingAmount = parseFloat(selectedOption.getAttribute('data-remaining-amount')).toFixed(2);
                amountPaidInputPaymentModal.value = remainingAmount; // Set amount to remaining by default
                amountPaidInputPaymentModal.max = remainingAmount; // Set max to remaining
                invoiceDetailsTextPaymentModal.textContent = 'المبلغ المتبقي لهذه الفاتورة: ' + remainingAmount + ' ريال.';
            } else {
                amountPaidInputPaymentModal.value = '';
                amountPaidInputPaymentModal.max = '';
                invoiceDetailsTextPaymentModal.textContent = '';
            }
        });
    }
});
</script>