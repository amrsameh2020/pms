<?php
// includes/modals/user_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : '';
}

// --- جلب قائمة الأدوار ---
$roles_list_for_modal = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $roles_query_modal = "SELECT id, display_name_ar FROM roles ORDER BY display_name_ar ASC";
    $roles_result_modal = $mysqli->query($roles_query_modal);
    if ($roles_result_modal) {
        while ($role_row_modal = $roles_result_modal->fetch_assoc()) {
            $roles_list_for_modal[] = $role_row_modal;
        }
        $roles_result_modal->free();
    } else {
        // خطأ في جلب الأدوار، يمكن تسجيله أو عرض رسالة
        error_log("User Modal: Failed to fetch roles: " . $mysqli->error);
    }
}


$user_is_active_options_modal = [ // تم تغيير الاسم ليكون فريداً داخل نطاق النافذة المنبثقة
    '1' => 'نشط',
    '0' => 'غير نشط (معطل)'
];
?>
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="userFormModal"> <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="user_id" id="user_id_modal_users" value=""> <input type="hidden" name="action" id="user_form_action_modal" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">بيانات المستخدم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="user_full_name_modal" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="user_full_name_modal" name="user_full_name" required placeholder="مثال: أحمد خالد المحمد">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="user_username_modal" class="form-label">اسم المستخدم (للدخول) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="user_username_modal" name="user_username" required placeholder="english_letters_numbers_underscores">
                            <small class="form-text text-muted">أحرف إنجليزية، أرقام، وشرطة سفلية (_) فقط، بدون مسافات.</small>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="user_email_modal" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm" id="user_email_modal" name="user_email" required placeholder="user@example.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="user_role_id_modal" class="form-label">دور المستخدم <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="user_role_id_modal" name="role_id" required>
                                <option value="">-- اختر الدور --</option>
                                <?php foreach ($roles_list_for_modal as $role_item): ?>
                                    <option value="<?php echo $role_item['id']; ?>"><?php echo esc_html($role_item['display_name_ar']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="user_password_modal" class="form-label">كلمة المرور</label>
                            <input type="password" class="form-control form-control-sm" id="user_password_modal" name="user_password" placeholder="اتركه فارغًا لعدم التغيير عند التعديل">
                            <small class="form-text text-muted" id="passwordHelpBlock_modal">عند إضافة مستخدم جديد، هذا الحقل مطلوب. عند التعديل، إذا تركته فارغًا، لن تتغير كلمة المرور الحالية.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="user_confirm_password_modal" class="form-label">تأكيد كلمة المرور</label>
                            <input type="password" class="form-control form-control-sm" id="user_confirm_password_modal" name="user_confirm_password" placeholder="أعد كتابة كلمة المرور">
                        </div>
                    </div>
                     <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                             <label for="user_is_active_modal" class="form-label">حالة الحساب <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="user_is_active_modal" name="is_active" required>
                                <?php foreach ($user_is_active_options_modal as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <small class="text-danger">* حقول مطلوبة</small><br>
                    <small class="text-info">يجب أن تتكون كلمة المرور من 8 أحرف على الأقل، وتحتوي على حرف كبير، حرف صغير، رقم، ورمز خاص.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="userSubmitButtonTextModal">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>