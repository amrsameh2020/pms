<?php
// includes/modals/property_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : '';
}

// --- جلب قائمة الملاك ---
$owners_list_for_property_modal = []; // تم تغيير الاسم لتمييزه
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $owners_query_prop_modal = "SELECT id, name FROM owners ORDER BY name ASC";
    $owners_result_prop_modal = $mysqli->query($owners_query_prop_modal);
    if ($owners_result_prop_modal) {
        while ($owner_row_prop_modal = $owners_result_prop_modal->fetch_assoc()) {
            $owners_list_for_property_modal[] = $owner_row_prop_modal;
        }
        $owners_result_prop_modal->free();
    } else {
        error_log("Property Modal: Failed to fetch owners: " . $mysqli->error);
    }
}

// --- جلب قائمة أنواع العقارات ---
$property_types_list_for_modal = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $ptypes_query_modal = "SELECT id, display_name_ar FROM property_types ORDER BY display_name_ar ASC";
    $ptypes_result_modal = $mysqli->query($ptypes_query_modal);
    if ($ptypes_result_modal) {
        while ($ptype_row_modal = $ptypes_result_modal->fetch_assoc()) {
            $property_types_list_for_modal[] = $ptype_row_modal;
        }
        $ptypes_result_modal->free();
    } else {
        error_log("Property Modal: Failed to fetch property types: " . $mysqli->error);
    }
}
?>
<div class="modal fade" id="propertyModal" tabindex="-1" aria-labelledby="propertyModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="propertyFormModal"> <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="property_id" id="property_id_modal_properties" value=""> <input type="hidden" name="action" id="property_form_action_modal" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="propertyModalLabel">بيانات العقار</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="property_code_modal_prop" class="form-label">كود العقار <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="property_code_modal_prop" name="property_code" required placeholder="مثال: BLD001">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="property_name_modal_prop" class="form-label">اسم العقار <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="property_name_modal_prop" name="property_name" required placeholder="مثال: برج النخيل السكني">
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="owner_id_modal_prop" class="form-label">المالك <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="owner_id_modal_prop" name="owner_id" required>
                                <option value="">-- اختر المالك --</option>
                                <?php foreach ($owners_list_for_property_modal as $owner_item_prop): ?>
                                    <option value="<?php echo $owner_item_prop['id']; ?>"><?php echo esc_html($owner_item_prop['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small><a href="<?php echo base_url('owners/index.php'); ?>" target="_blank" class="text-decoration-none">إدارة الملاك</a></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="property_type_id_modal_prop" class="form-label">نوع العقار</label>
                            <select class="form-select form-select-sm" id="property_type_id_modal_prop" name="property_type_id">
                                <option value="">-- اختر نوع العقار --</option>
                                <?php foreach ($property_types_list_for_modal as $ptype_item_prop): ?>
                                    <option value="<?php echo $ptype_item_prop['id']; ?>"><?php echo esc_html($ptype_item_prop['display_name_ar']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small><a href="<?php echo base_url('property_types/index.php'); // افترض وجود صفحة لإدارة أنواع العقارات ?>" target="_blank" class="text-decoration-none">إدارة أنواع العقارات</a></small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="property_address_modal_prop" class="form-label">العنوان التفصيلي <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="property_address_modal_prop" name="property_address" rows="2" required placeholder="مثال: الرياض، حي الملك عبدالله، شارع الأمير محمد"></textarea>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="property_city_modal_prop" class="form-label">المدينة</label>
                            <input type="text" class="form-control form-control-sm" id="property_city_modal_prop" name="property_city" placeholder="مثال: الرياض">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="number_of_units_modal_prop" class="form-label">عدد الوحدات <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="number_of_units_modal_prop" name="number_of_units" required min="0" placeholder="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="construction_year_modal_prop" class="form-label">سنة الإنشاء</label>
                            <input type="number" class="form-control form-control-sm" id="construction_year_modal_prop" name="construction_year" min="1800" max="<?php echo date('Y') + 5; ?>" placeholder="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                     <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="land_area_sqm_modal_prop" class="form-label">مساحة الأرض (م²)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="land_area_sqm_modal_prop" name="land_area_sqm" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="latitude_modal_prop" class="form-label">خط العرض (Latitude)</label>
                            <input type="text" class="form-control form-control-sm" id="latitude_modal_prop" name="latitude" placeholder="مثال: 24.7136">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="longitude_modal_prop" class="form-label">خط الطول (Longitude)</label>
                            <input type="text" class="form-control form-control-sm" id="longitude_modal_prop" name="longitude" placeholder="مثال: 46.6753">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="property_notes_modal_prop" class="form-label">ملاحظات إضافية</label>
                        <textarea class="form-control form-control-sm" id="property_notes_modal_prop" name="property_notes" rows="3" placeholder="أي تفاصيل أو ملاحظات أخرى حول العقار"></textarea>
                    </div>
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="propertySubmitButtonTextModal">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>