<?php
// includes/modals/lease_type_modal.php

if (!isset($csrf_token)) {
    // عادةً ما يتم تضمين هذا الملف في سياق حيث $csrf_token معرف بالفعل.
    // إذا لم يكن كذلك، يجب على الصفحة المتضمنة تعريفه.
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : 'fallback_csrf_lease_type_modal';
    if ($csrf_token === 'fallback_csrf_lease_type_modal') {
        error_log("Warning: CSRF token was not set by the parent page for lease_type_modal.php.");
    }
}
?>
<div class="modal fade" id="leaseTypeModal" tabindex="-1" aria-labelledby="leaseTypeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="leaseTypeFormModal"> <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="lease_type_id" id="lease_type_id_modal_ltypes" value=""> <input type="hidden" name="action" id="lease_type_form_action_modal_ltypes" value=""> <div class="modal-header">
                    <h5 class="modal-title" id="leaseTypeModalLabel_ltypes">تفاصيل نوع عقد الإيجار</h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="lease_type_name_modal_ltypes" class="form-label">المعرف (بالانجليزية - بدون مسافات) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="lease_type_name_modal_ltypes" name="type_name" required placeholder="مثال: residential, commercial">
                        <small class="form-text text-muted">يستخدم داخلياً في النظام، يجب أن يكون فريداً.</small>
                    </div>
                    <div class="mb-3">
                        <label for="lease_type_display_name_ar_modal_ltypes" class="form-label">الاسم المعروض (بالعربية) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="lease_type_display_name_ar_modal_ltypes" name="display_name_ar" required placeholder="مثال: عقد سكني، عقد تجاري">
                    </div>
                    
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="leaseTypeSubmitButtonTextModalLtypes">حفظ البيانات</span> </button>
                </div>
            </form>
        </div>
    </div>
</div>