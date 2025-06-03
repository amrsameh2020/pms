<?php
// includes/modals/role_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : 'fallback_csrf_role_modal';
}
?>
<div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="roleFormModal">
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="role_id" id="role_id_modal_roles_page" value="">
                <input type="hidden" name="action" id="role_form_action_modal_roles_page" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="roleModalLabel_roles_page">تفاصيل الدور</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="role_name_modal_roles_page" class="form-label">المعرف (بالانجليزية - بدون مسافات) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="role_name_modal_roles_page" name="role_name" required placeholder="مثال: admin, staff, accountant">
                        <small class="form-text text-muted">يستخدم داخلياً في النظام، يجب أن يكون فريداً (أحرف إنجليزية، أرقام، وشرطة سفلية).</small>
                    </div>
                    <div class="mb-3">
                        <label for="role_display_name_ar_modal_roles_page" class="form-label">الاسم المعروض (بالعربية) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="role_display_name_ar_modal_roles_page" name="display_name_ar" required placeholder="مثال: مسؤول نظام، موظف، محاسب">
                    </div>
                    <div class="mb-3">
                        <label for="role_description_modal_roles_page" class="form-label">الوصف (اختياري)</label>
                        <textarea class="form-control form-control-sm" id="role_description_modal_roles_page" name="description" rows="3" placeholder="وصف موجز لصلاحيات هذا الدور"></textarea>
                    </div>
                    
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="roleSubmitButtonTextModalRolesPage">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>