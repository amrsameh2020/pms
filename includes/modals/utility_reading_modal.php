<?php
// includes/modals/utility_reading_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : 'fallback_csrf_util_read_modal';
}

// --- جلب قائمة أنواع الخدمات/المرافق ---
$utility_types_list_for_modal_ur = []; // تم تغيير الاسم
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $utypes_query_modal_ur = "SELECT id, name FROM utility_types ORDER BY name ASC";
    $utypes_result_modal_ur = $mysqli->query($utypes_query_modal_ur);
    if ($utypes_result_modal_ur) {
        while ($utype_row_modal_ur = $utypes_result_modal_ur->fetch_assoc()) {
            $utility_types_list_for_modal_ur[] = $utype_row_modal_ur;
        }
        $utypes_result_modal_ur->free();
    } else {
        error_log("Utility Reading Modal: Failed to fetch utility types: " . $mysqli->error);
    }
}

// حالات الفوترة للقراءة
$billed_statuses_for_ur_modal = [
    'Pending' => 'معلقة (لم تتم فوترتها)',
    'Billed' => 'تمت فوترتها',
    'Paid' => 'مدفوعة (جزء من فاتورة مدفوعة)'
];

// يتم افتراض أن $property_id_for_utility_page و $property_name_for_utility_page يتم تمريرهما من الصفحة الرئيسية (utilities/index.php)
// $units_for_property_ur_modal = [];
// if (isset($mysqli) && $mysqli instanceof mysqli && isset($property_id_for_utility_page) && $property_id_for_utility_page > 0) {
//     $stmt_units_ur = $mysqli->prepare("SELECT id, unit_number FROM units WHERE property_id = ? ORDER BY unit_number ASC");
//     if ($stmt_units_ur) {
//         $stmt_units_ur->bind_param("i", $property_id_for_utility_page);
//         $stmt_units_ur->execute();
//         $result_units_ur = $stmt_units_ur->get_result();
//         while ($row_unit_ur = $result_units_ur->fetch_assoc()) {
//             $units_for_property_ur_modal[] = $row_unit_ur;
//         }
//         $stmt_units_ur->close();
//     } else {
//         error_log("Utility Reading Modal: Failed to prepare statement for units: " . $mysqli->error);
//     }
// }
// تم نقل منطق جلب الوحدات إلى utilities/index.php ليتم تمريره إلى JavaScript

?>
<div class="modal fade" id="utilityReadingModal" tabindex="-1" aria-labelledby="utilityReadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="utilityReadingFormModal">
                <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="reading_id" id="reading_id_modal_ur" value="">
                <input type="hidden" name="property_id_for_reading" id="property_id_for_reading_modal_ur" value=""> 
                <input type="hidden" name="action" id="utility_reading_form_action_modal_ur" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="utilityReadingModalLabel_ur">تسجيل قراءة عداد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small py-2">
                        العقار: <strong id="property_name_for_ur_modal_display">[اسم العقار]</strong>
                    </div>
                    <hr class="mt-0">

                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="unit_id_for_reading_modal_ur" class="form-label">الوحدة <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="unit_id_for_reading_modal_ur" name="unit_id_for_reading" required>
                                <option value="">-- اختر الوحدة --</option>
                                </select>
                             <small class="form-text text-muted">يتم عرض وحدات العقار المحدد فقط.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="utility_type_id_modal_ur" class="form-label">نوع الخدمة/المرفق <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="utility_type_id_modal_ur" name="utility_type_id" required>
                                <option value="">-- اختر نوع الخدمة --</option>
                                <?php foreach ($utility_types_list_for_modal_ur as $utype_item_ur): ?>
                                    <option value="<?php echo $utype_item_ur['id']; ?>"><?php echo esc_html($utype_item_ur['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="reading_date_modal_ur" class="form-label">تاريخ القراءة <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="reading_date_modal_ur" name="reading_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="previous_reading_value_modal_ur" class="form-label">القراءة السابقة</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="previous_reading_value_modal_ur" name="previous_reading_value" placeholder="0.00" readonly>
                             <small class="form-text text-muted">تُجلب تلقائياً بناءً على آخر قراءة لنفس الوحدة والخدمة.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="current_reading_value_modal_ur" class="form-label">القراءة الحالية <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="current_reading_value_modal_ur" name="current_reading_value" required placeholder="0.00" min="0">
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label for="rate_per_unit_modal_ur" class="form-label">سعر الوحدة (ريال)</label>
                            <input type="number" step="0.0001" class="form-control form-control-sm" id="rate_per_unit_modal_ur" name="rate_per_unit" placeholder="0.0000">
                            <small class="form-text text-muted">سعر الكيلوواط/المتر المكعب. إذا ترك فارغًا، قد يستخدم سعر افتراضي من إعدادات الخدمة (إذا وُجد).</small>
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="billed_status_modal_ur" class="form-label">حالة الفوترة</label>
                            <select class="form-select form-select-sm" id="billed_status_modal_ur" name="billed_status">
                                <?php foreach($billed_statuses_for_ur_modal as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">تتغير إلى "تمت فوترتها" عند إنشاء فاتورة لهذه القراءة.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes_reading_modal_ur" class="form-label">ملاحظات</label>
                        <textarea class="form-control form-control-sm" id="notes_reading_modal_ur" name="notes_reading" rows="2" placeholder="أي ملاحظات إضافية على هذه القراءة"></textarea>
                    </div>
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="utilityReadingSubmitButtonTextModalUr">حفظ القراءة</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Script to fetch previous reading when unit and utility type are selected
document.addEventListener('DOMContentLoaded', function() {
    const unitSelectModalUr = document.getElementById('unit_id_for_reading_modal_ur');
    const utilityTypeSelectModalUr = document.getElementById('utility_type_id_modal_ur');
    const previousReadingInputModalUr = document.getElementById('previous_reading_value_modal_ur');
    const currentReadingInputModalUr = document.getElementById('current_reading_value_modal_ur');

    function fetchPreviousReading() {
        const unitId = unitSelectModalUr.value;
        const utilityTypeId = utilityTypeSelectModalUr.value;

        if (unitId && utilityTypeId) {
            // AJAX call to a new PHP script to get the last reading
            // Example: fetch('get_last_reading.php?unit_id=' + unitId + '&utility_type_id=' + utilityTypeId)
            // For now, we'll just clear it and set min for current_reading
            
            // AJAX request to fetch last reading
            fetch(`<?php echo base_url('utilities/ajax_get_last_reading.php'); ?>?unit_id=${unitId}&utility_type_id=${utilityTypeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.last_reading !== null) {
                        previousReadingInputModalUr.value = parseFloat(data.last_reading).toFixed(2);
                        currentReadingInputModalUr.min = parseFloat(data.last_reading).toFixed(2);
                    } else {
                        previousReadingInputModalUr.value = '0.00';
                        currentReadingInputModalUr.min = '0.00';
                        if(data.message) console.log(data.message); // Optional: log message
                    }
                })
                .catch(error => {
                    console.error('Error fetching last reading:', error);
                    previousReadingInputModalUr.value = '0.00';
                    currentReadingInputModalUr.min = '0.00';
                });
        } else {
            previousReadingInputModalUr.value = '0.00';
            currentReadingInputModalUr.min = '0.00';
        }
    }

    if(unitSelectModalUr) unitSelectModalUr.addEventListener('change', fetchPreviousReading);
    if(utilityTypeSelectModalUr) utilityTypeSelectModalUr.addEventListener('change', fetchPreviousReading);
});
</script>