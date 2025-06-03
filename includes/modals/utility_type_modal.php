<?php
// includes/modals/utility_type_modal.php
if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : 'fallback_csrf_utility_type_modal';
}
?>
<div class="modal fade" id="utilityTypeModal" tabindex="-1" aria-labelledby="utilityTypeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="utilityTypeFormModal">
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="utility_type_id" id="utility_type_id_modal_utypes_page" value="">
                <input type="hidden" name="action" id="utility_type_form_action_modal_utypes_page" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="utilityTypeModalLabel">تفاصيل نوع المرفق</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="utility_type_name_modal" class="form-label">اسم نوع المرفق <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="utility_type_name_modal" name="utility_type_name_modal" required placeholder="مثال: كهرباء, ماء, غاز">
                    </div>
                    <div class="mb-3">
                        <label for="unit_of_measure_modal" class="form-label">وحدة القياس <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="unit_of_measure_modal" name="unit_of_measure_modal" required placeholder="مثال: kWh, m³, وحدة">
                    </div>
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="utilityTypeSubmitButtonText">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>