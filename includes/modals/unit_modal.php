<?php
// includes/modals/unit_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : '';
}

// --- جلب قائمة أنواع الوحدات ---
$unit_types_list_for_modal = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $utypes_query_modal = "SELECT id, display_name_ar FROM unit_types ORDER BY display_name_ar ASC";
    $utypes_result_modal = $mysqli->query($utypes_query_modal);
    if ($utypes_result_modal) {
        while ($utype_row_modal = $utypes_result_modal->fetch_assoc()) {
            $unit_types_list_for_modal[] = $utype_row_modal;
        }
        $utypes_result_modal->free();
    } else {
        error_log("Unit Modal: Failed to fetch unit types: " . $mysqli->error);
    }
}

// حالات الوحدة للنافذة المنبثقة
$unit_statuses_for_unit_modal_dropdown = [ // تم تغيير الاسم ليكون فريداً
    'Vacant' => 'شاغرة',
    'Occupied' => 'مشغولة',
    'Under Maintenance' => 'تحت الصيانة',
    'Reserved' => 'محجوزة'
];
?>
<div class="modal fade" id="unitModal" tabindex="-1" aria-labelledby="unitModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="unitFormModal"> <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="unit_id" id="unit_id_modal_units_page" value=""> <input type="hidden" name="property_id_for_unit" id="property_id_for_unit_modal_page" value=""> <input type="hidden" name="action" id="unit_form_action_modal_page" value=""> <div class="modal-header">
                    <h5 class="modal-title" id="unitModalLabel_page">بيانات الوحدة</h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small py-2">
                        العقار: <strong id="property_name_for_unit_modal_display_page">[سيتم عرض اسم العقار هنا]</strong> </div>
                    <hr class="mt-0">
                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="unit_number_modal_page" class="form-label">رقم/اسم الوحدة <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="unit_number_modal_page" name="unit_number" required placeholder="مثال: شقة 101، محل رقم 5">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="unit_type_id_modal_page" class="form-label">نوع الوحدة</label>
                            <select class="form-select form-select-sm" id="unit_type_id_modal_page" name="unit_type_id">
                                <option value="">-- اختر نوع الوحدة --</option>
                                <?php foreach ($unit_types_list_for_modal as $utype_item_modal): ?>
                                    <option value="<?php echo $utype_item_modal['id']; ?>"><?php echo esc_html($utype_item_modal['display_name_ar']); ?></option>
                                <?php endforeach; ?>
                            </select>
                             <small><a href="<?php echo base_url('unit_types/index.php'); // افترض وجود صفحة لإدارة أنواع الوحدات ?>" target="_blank" class="text-decoration-none">إدارة أنواع الوحدات</a></small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="unit_status_modal_page" class="form-label">حالة الوحدة <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="unit_status_modal_page" name="unit_status" required>
                                <?php foreach ($unit_statuses_for_unit_modal_dropdown as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-3 mb-3">
                            <label for="floor_number_modal_page" class="form-label">رقم الطابق</label>
                            <input type="number" class="form-control form-control-sm" id="floor_number_modal_page" name="floor_number" placeholder="0 للدور الأرضي">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="size_sqm_modal_page" class="form-label">المساحة (م²)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="size_sqm_modal_page" name="size_sqm" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="bedrooms_modal_page" class="form-label">عدد غرف النوم</label>
                            <input type="number" class="form-control form-control-sm" id="bedrooms_modal_page" name="bedrooms" min="0" placeholder="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="bathrooms_modal_page" class="form-label">عدد دورات المياه</label>
                            <input type="number" class="form-control form-control-sm" id="bathrooms_modal_page" name="bathrooms" min="0" placeholder="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="base_rent_price_modal_page" class="form-label">سعر الإيجار الأساسي المقترح</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="base_rent_price_modal_page" name="base_rent_price" min="0" placeholder="0.00">
                    </div>

                    <div class="mb-3">
                        <label for="unit_features_modal_page" class="form-label">ميزات الوحدة</label>
                        <textarea class="form-control form-control-sm" id="unit_features_modal_page" name="unit_features" rows="2" placeholder="مثال: مكيفة بالكامل، مطبخ مجهز..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="unit_notes_modal_page" class="form-label">ملاحظات على الوحدة</label>
                        <textarea class="form-control form-control-sm" id="unit_notes_modal_page" name="unit_notes" rows="2" placeholder="أي تفاصيل أو ملاحظات أخرى خاصة بالوحدة"></textarea>
                    </div>
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="unitSubmitButtonTextModalPage">حفظ البيانات</span> </button>
                </div>
            </form>
        </div>
    </div>
</div>