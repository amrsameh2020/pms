<?php
// includes/modals/tenant_type_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : 'fallback_csrf_ttype_modal';
}
?>
<div class="modal fade" id="tenantTypeModal" tabindex="-1" aria-labelledby="tenantTypeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="tenantTypeFormModal">
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="tenant_type_id" id="tenant_type_id_modal_ttypes" value="">
                <input type="hidden" name="action" id="tenant_type_form_action_modal_ttypes" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="tenantTypeModalLabel_ttypes">تفاصيل نوع المستأجر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tenant_type_name_modal_ttypes" class="form-label">المعرف (بالانجليزية - بدون مسافات) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="tenant_type_name_modal_ttypes" name="type_name" required placeholder="مثال: individual, company">
                        <small class="form-text text-muted">يستخدم داخلياً في النظام، يجب أن يكون فريداً (أحرف إنجليزية، أرقام، وشرطة سفلية).</small>
                    </div>
                    <div class="mb-3">
                        <label for="tenant_type_display_name_ar_modal_ttypes" class="form-label">الاسم المعروض (بالعربية) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="tenant_type_display_name_ar_modal_ttypes" name="display_name_ar" required placeholder="مثال: فرد، شركة">
                    </div>
                    
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="tenantTypeSubmitButtonTextModalTtypes">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>