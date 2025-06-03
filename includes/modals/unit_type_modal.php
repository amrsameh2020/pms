<?php
// includes/modals/unit_type_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : 'fallback_csrf_unit_type_modal';
}
?>
<div class="modal fade" id="unitTypeModal" tabindex="-1" aria-labelledby="unitTypeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="unitTypeFormModal">
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="unit_type_id" id="unit_type_id_modal_utypes" value="">
                <input type="hidden" name="action" id="unit_type_form_action_modal_utypes" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="unitTypeModalLabel_utypes">تفاصيل نوع الوحدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="unit_type_name_modal_utypes" class="form-label">المعرف (بالانجليزية - بدون مسافات) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="unit_type_name_modal_utypes" name="type_name" required placeholder="مثال: apartment, villa">
                        <small class="form-text text-muted">يستخدم داخلياً في النظام، يجب أن يكون فريداً.</small>
                    </div>
                    <div class="mb-3">
                        <label for="unit_type_display_name_ar_modal_utypes" class="form-label">الاسم المعروض (بالعربية) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="unit_type_display_name_ar_modal_utypes" name="display_name_ar" required placeholder="مثال: شقة، فيلا">
                    </div>
                    
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="unitTypeSubmitButtonTextModalUtypes">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>