<?php
// includes/modals/payment_method_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : 'fallback_csrf_pm_modal';
}
?>
<div class="modal fade" id="paymentMethodModal" tabindex="-1" aria-labelledby="paymentMethodModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="paymentMethodFormModal">
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="payment_method_id" id="payment_method_id_modal_pmethods" value="">
                <input type="hidden" name="action" id="payment_method_form_action_modal_pmethods" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="paymentMethodModalLabel_pmethods">تفاصيل طريقة الدفع</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="method_name_modal_pmethods" class="form-label">المعرف (بالانجليزية - بدون مسافات) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="method_name_modal_pmethods" name="method_name" required placeholder="مثال: Cash, BankTransfer">
                        <small class="form-text text-muted">يستخدم داخلياً في النظام، يجب أن يكون فريداً (أحرف إنجليزية بدون مسافات).</small>
                    </div>
                    <div class="mb-3">
                        <label for="display_name_ar_modal_pmethods" class="form-label">الاسم المعروض (بالعربية) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="display_name_ar_modal_pmethods" name="display_name_ar" required placeholder="مثال: نقداً، تحويل بنكي">
                    </div>
                    <div class="mb-3">
                        <label for="zatca_code_modal_pmethods" class="form-label">رمز ZATCA (اختياري)</label>
                        <input type="text" class="form-control form-control-sm" id="zatca_code_modal_pmethods" name="zatca_code" maxlength="2" placeholder="مثال: 10 (لنقد)، 42 (تحويل)">
                        <small class="form-text text-muted">الرمز المعتمد من ZATCA لهذه الطريقة (مثل 10, 30, 42, 48).</small>
                    </div>
                     <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="is_active_modal_pmethods" name="is_active" checked>
                        <label class="form-check-label" for="is_active_modal_pmethods">
                            فعالة (متاحة للاستخدام)
                        </label>
                    </div>
                    
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="paymentMethodSubmitButtonTextModalPmethods">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>