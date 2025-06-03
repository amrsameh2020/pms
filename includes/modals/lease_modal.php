<?php
// includes/modals/lease_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : '';
}

// --- جلب قائمة الوحدات ---
$units_list_for_lease_modal = []; // تم تغيير الاسم
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $units_query_lease_modal = "SELECT u.id as unit_id, u.unit_number, u.status as unit_status, p.name as property_name, p.property_code
                          FROM units u
                          JOIN properties p ON u.property_id = p.id
                          ORDER BY p.name ASC, u.unit_number ASC";
    $units_result_lease_modal = $mysqli->query($units_query_lease_modal);
    if ($units_result_lease_modal) {
        while ($unit_row_lease_modal = $units_result_lease_modal->fetch_assoc()) {
            $units_list_for_lease_modal[] = $unit_row_lease_modal;
        }
        $units_result_lease_modal->free();
    } else {
        error_log("Lease Modal: Failed to fetch units: " . $mysqli->error);
    }
}

// --- جلب قائمة المستأجرين ---
$tenants_list_for_lease_modal = []; // تم تغيير الاسم
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $tenants_query_lease_modal = "SELECT id, full_name, national_id_iqama FROM tenants ORDER BY full_name ASC";
    $tenants_result_lease_modal = $mysqli->query($tenants_query_lease_modal);
    if ($tenants_result_lease_modal) {
        while ($tenant_row_lease_modal = $tenants_result_lease_modal->fetch_assoc()) {
            $tenants_list_for_lease_modal[] = $tenant_row_lease_modal;
        }
        $tenants_result_lease_modal->free();
    } else {
        error_log("Lease Modal: Failed to fetch tenants: " . $mysqli->error);
    }
}

// --- جلب قائمة أنواع عقود الإيجار ---
$lease_types_list_for_modal = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $ltypes_query_modal = "SELECT id, display_name_ar FROM lease_types ORDER BY display_name_ar ASC";
    $ltypes_result_modal = $mysqli->query($ltypes_query_modal);
    if ($ltypes_result_modal) {
        while ($ltype_row_modal = $ltypes_result_modal->fetch_assoc()) {
            $lease_types_list_for_modal[] = $ltype_row_modal;
        }
        $ltypes_result_modal->free();
    } else {
        error_log("Lease Modal: Failed to fetch lease types: " . $mysqli->error);
    }
}


$payment_frequencies_lease_modal_options = [ // تم تغيير الاسم
    'Monthly' => 'شهري',
    'Quarterly' => 'ربع سنوي',
    'Semi-Annually' => 'نصف سنوي',
    'Annually' => 'سنوي',
    'Custom' => 'مخصص'
];

$lease_statuses_lease_modal_options = [ // تم تغيير الاسم
    'Pending' => 'معلق',
    'Active' => 'نشط',
    'Expired' => 'منتهي الصلاحية',
    'Terminated' => 'ملغي',
    'Draft' => 'مسودة'
];

?>
<div class="modal fade" id="leaseModal" tabindex="-1" aria-labelledby="leaseModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="leaseFormModal"> <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="lease_id" id="lease_id_modal_leases" value=""> <input type="hidden" name="action" id="lease_form_action_modal_leases" value=""> <div class="modal-header">
                    <h5 class="modal-title" id="leaseModalLabel_leases">تفاصيل عقد الإيجار</h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="lease_contract_number_modal_leases" class="form-label">رقم عقد الإيجار <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="lease_contract_number_modal_leases" name="lease_contract_number" required placeholder="مثال: LEASE-2025-001">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="unit_id_modal_leases" class="form-label">الوحدة المستأجرة <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="unit_id_modal_leases" name="unit_id" required>
                                <option value="">-- اختر الوحدة --</option>
                                <?php foreach ($units_list_for_lease_modal as $unit_item_lease): ?>
                                    <option value="<?php echo $unit_item_lease['unit_id']; ?>" data-unit-status="<?php echo esc_attr($unit_item_lease['unit_status']); ?>">
                                        <?php echo esc_html($unit_item_lease['property_name'] . ' (كود: ' . $unit_item_lease['property_code'] . ') - وحدة: ' . $unit_item_lease['unit_number'] . ' [' . $unit_item_lease['unit_status'] . ']'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                             <small id="unit_status_warning_modal_leases" class="text-danger d-none">تحذير: هذه الوحدة ليست شاغرة حاليًا.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="tenant_id_modal_leases" class="form-label">المستأجر <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="tenant_id_modal_leases" name="tenant_id" required>
                                <option value="">-- اختر المستأجر --</option>
                                <?php foreach ($tenants_list_for_lease_modal as $tenant_item_lease): ?>
                                    <option value="<?php echo $tenant_item_lease['id']; ?>">
                                        <?php echo esc_html($tenant_item_lease['full_name'] . ' (هوية: ' . $tenant_item_lease['national_id_iqama'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="lease_type_id_modal_leases" class="form-label">نوع العقد</label>
                            <select class="form-select form-select-sm" id="lease_type_id_modal_leases" name="lease_type_id">
                                <option value="">-- اختر نوع العقد --</option>
                                <?php foreach ($lease_types_list_for_modal as $ltype_item_modal): ?>
                                    <option value="<?php echo $ltype_item_modal['id']; ?>"><?php echo esc_html($ltype_item_modal['display_name_ar']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="lease_start_date_modal_leases" class="form-label">تاريخ بدء العقد <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="lease_start_date_modal_leases" name="lease_start_date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="lease_end_date_modal_leases" class="form-label">تاريخ انتهاء العقد <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="lease_end_date_modal_leases" name="lease_end_date" required>
                        </div>
                    </div>
                     <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="rent_amount_modal_leases" class="form-label">مبلغ الإيجار <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="rent_amount_modal_leases" name="rent_amount" required min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="payment_frequency_modal_leases" class="form-label">دورية السداد <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="payment_frequency_modal_leases" name="payment_frequency" required>
                                <?php foreach ($payment_frequencies_lease_modal_options as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="payment_due_day_modal_leases" class="form-label">يوم استحقاق الدفعة</label>
                            <input type="number" class="form-control form-control-sm" id="payment_due_day_modal_leases" name="payment_due_day" min="1" max="31" placeholder="مثال: 1">
                            <small class="form-text text-muted">يوم من الشهر/الفترة تستحق فيه الدفعة.</small>
                        </div>
                    </div>

                    <div class="row gx-3">
                         <div class="col-md-4 mb-3">
                            <label for="deposit_amount_modal_leases" class="form-label">مبلغ التأمين</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="deposit_amount_modal_leases" name="deposit_amount" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="grace_period_days_modal_leases" class="form-label">فترة سماح (أيام)</label>
                            <input type="number" class="form-control form-control-sm" id="grace_period_days_modal_leases" name="grace_period_days" min="0" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="lease_status_modal_leases" class="form-label">حالة العقد <span class="text-danger">*</span></label>
                             <select class="form-select form-select-sm" id="lease_status_modal_leases" name="lease_status" required>
                                <?php foreach ($lease_statuses_lease_modal_options as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label for="next_billing_date_modal_leases" class="form-label">تاريخ الفاتورة التالية</label>
                            <input type="date" class="form-control form-control-sm" id="next_billing_date_modal_leases" name="next_billing_date">
                             <small class="form-text text-muted">اختياري، يمكن تحديثه تلقائياً.</small>
                        </div>
                         <div class="col-md-4 mb-3">
                            <label for="last_billed_on_modal_leases" class="form-label">تاريخ آخر فاتورة صدرت</label>
                            <input type="date" class="form-control form-control-sm" id="last_billed_on_modal_leases" name="last_billed_on">
                             <small class="form-text text-muted">اختياري، يمكن تحديثه تلقائياً.</small>
                        </div>
                         <div class="col-md-4 mb-3">
                            <label for="contract_document_modal_leases" class="form-label">مستند العقد (PDF)</label>
                            <input type="file" class="form-control form-control-sm" id="contract_document_modal_leases" name="contract_document" accept=".pdf">
                            <small id="current_contract_document_modal_leases" class="form-text text-muted"></small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="lease_notes_modal_leases" class="form-label">ملاحظات على العقد</label>
                        <textarea class="form-control form-control-sm" id="lease_notes_modal_leases" name="lease_notes" rows="3" placeholder="شروط خاصة، ملاحظات إضافية..."></textarea>
                    </div>
                    <small class="text-danger">* حقول مطلوبة</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="leaseSubmitButtonTextModalLeases">حفظ البيانات</span> </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Script to handle unit status warning in the lease modal
document.addEventListener('DOMContentLoaded', function() {
    var unitSelectLeaseModal = document.getElementById('unit_id_modal_leases'); // تم تغيير ID
    var unitStatusWarningLeaseModal = document.getElementById('unit_status_warning_modal_leases'); // تم تغيير ID

    if (unitSelectLeaseModal && unitStatusWarningLeaseModal) {
        unitSelectLeaseModal.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            if (selectedOption) { // Ensure an option is actually selected
                var unitStatus = selectedOption.getAttribute('data-unit-status');
                if (unitStatus && unitStatus.toLowerCase() !== 'vacant') {
                    unitStatusWarningLeaseModal.classList.remove('d-none');
                    // يمكنك تحديث النص ليكون أكثر وضوحًا إذا أردت
                    unitStatusWarningLeaseModal.textContent = 'تحذير: هذه الوحدة حاليًا "' + unitStatus + '".'; 
                } else {
                    unitStatusWarningLeaseModal.classList.add('d-none');
                }
            } else {
                unitStatusWarningLeaseModal.classList.add('d-none'); // No option selected, hide warning
            }
        });
    }
});
</script>