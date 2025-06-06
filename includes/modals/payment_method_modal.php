<?php
// includes/modals/owner_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : 'fallback_csrf_owner_modal';
}
?>
<div class="modal fade" id="ownerModal" tabindex="-1" aria-labelledby="ownerModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="ownerForm" method="POST" action="<?php echo base_url('owners/owner_actions.php'); // تأكد أن هذا هو المسار الصحيح ?>">
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="owner_id" id="owner_id" value="">
                <input type="hidden" name="action" id="form_action" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="ownerModalLabel">بيانات المالك</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="owner_input_name" class="form-label">اسم المالك <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="owner_input_name" name="name" required placeholder="مثال: شركة العقارات المتحدة"> {/* تم تغيير owner_name إلى name */}
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="owner_email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control form-control-sm" id="owner_email" name="email" placeholder="example@domain.com">
                            <small class="form-text text-muted">اختياري، ولكن يفضل إدخاله للتواصل الرسمي.</small>
                        </div>
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="owner_phone" class="form-label">رقم الهاتف</label>
                            <input type="tel" class="form-control form-control-sm" id="owner_phone" name="phone" placeholder="مثال: 05XXXXXXXX">
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="owner_national_id" class="form-label">رقم الهوية/الإقامة/السجل التجاري</label>
                            <input type="text" class="form-control form-control-sm" id="owner_national_id" name="national_id_iqama" placeholder="1XXXXXXXXX أو 7XXXXXXXXX">
                        </div>
                    </div>
                     <div class="row gx-3"> {/* افترض أن هذا الحقل كان موجودًا أو أضيف حديثًا */}
                        <div class="col-md-6 mb-3">
                            <label for="owner_registration_date" class="form-label">تاريخ التسجيل</label>
                            <input type="date" class="form-control form-control-sm" id="owner_registration_date" name="registration_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="owner_address" class="form-label">العنوان</label>
                        <textarea class="form-control form-control-sm" id="owner_address" name="address" rows="2" placeholder="مثال: الرياض، حي العليا، شارع الملك فهد"></textarea> {/* تم تغيير rows من 3 إلى 2 لتوفير مساحة */}
                    </div>
                     <div class="mb-3"> {/* افترض أن هذا الحقل كان موجودًا أو أضيف حديثًا */}
                        <label for="owner_notes" class="form-label">ملاحظات</label>
                        <textarea class="form-control form-control-sm" id="owner_notes" name="notes" rows="2" placeholder="أية ملاحظات إضافية"></textarea>
                    </div>
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="submitButtonText">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>