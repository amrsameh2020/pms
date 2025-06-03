<?php
// includes/modals/tenant_modal.php

if (!isset($csrf_token)) {
    $csrf_token = (function_exists('generate_csrf_token')) ? generate_csrf_token() : '';
}

// --- جلب قائمة أنواع المستأجرين ---
$tenant_types_list_for_modal = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $ttypes_query_modal = "SELECT id, display_name_ar FROM tenant_types ORDER BY display_name_ar ASC";
    $ttypes_result_modal = $mysqli->query($ttypes_query_modal);
    if ($ttypes_result_modal) {
        while ($ttype_row_modal = $ttypes_result_modal->fetch_assoc()) {
            $tenant_types_list_for_modal[] = $ttype_row_modal;
        }
        $ttypes_result_modal->free();
    } else {
        error_log("Tenant Modal: Failed to fetch tenant types: " . $mysqli->error);
    }
}

$gender_options_modal = [
    '' => '-- اختر النوع --',
    'Male' => 'ذكر',
    'Female' => 'أنثى',
    'Other' => 'آخر'
];

?>
<div class="modal fade" id="tenantModal" tabindex="-1" aria-labelledby="tenantModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <form id="tenantFormModal"> <input type="hidden" name="csrf_token" value="<?php echo esc_attr($csrf_token); ?>">
                <input type="hidden" name="tenant_id" id="tenant_id_modal_tenants" value=""> <input type="hidden" name="action" id="tenant_form_action_modal_tenants" value=""> <div class="modal-header">
                    <h5 class="modal-title" id="tenantModalLabel_tenants">بيانات المستأجر</h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <nav>
                        <div class="nav nav-tabs mb-3" id="nav-tab-tenant-modal" role="tablist">
                            <button class="nav-link active" id="nav-basic-info-tab-modal" data-bs-toggle="tab" data-bs-target="#nav-basic-info-modal" type="button" role="tab" aria-controls="nav-basic-info-modal" aria-selected="true">المعلومات الأساسية</button>
                            <button class="nav-link" id="nav-zatca-buyer-info-tab-modal" data-bs-toggle="tab" data-bs-target="#nav-zatca-buyer-info-modal" type="button" role="tab" aria-controls="nav-zatca-buyer-info-modal" aria-selected="false">بيانات المشتري (ZATCA)</button>
                            <button class="nav-link" id="nav-emergency-contact-tab-modal" data-bs-toggle="tab" data-bs-target="#nav-emergency-contact-modal" type="button" role="tab" aria-controls="nav-emergency-contact-modal" aria-selected="false">جهة الاتصال في الطوارئ</button>
                        </div>
                    </nav>
                    <div class="tab-content" id="nav-tabContentTenantModal">
                        <div class="tab-pane fade show active" id="nav-basic-info-modal" role="tabpanel" aria-labelledby="nav-basic-info-tab-modal">
                            <h6 class="mb-3 text-primary">المعلومات الشخصية والاتصال:</h6>
                            <div class="row gx-3">
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_full_name_modal_tenants" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_full_name_modal_tenants" name="tenant_full_name" required placeholder="مثال: عبدالله محمد الأحمد">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_national_id_iqama_modal_tenants" class="form-label">رقم الهوية/الإقامة <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_national_id_iqama_modal_tenants" name="tenant_national_id_iqama" required placeholder="1XXXXXXXXX أو 2XXXXXXXXX">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_type_id_modal_tenants" class="form-label">نوع المستأجر</label>
                                    <select class="form-select form-select-sm" id="tenant_type_id_modal_tenants" name="tenant_type_id">
                                        <option value="">-- اختر نوع المستأجر --</option>
                                        <?php foreach ($tenant_types_list_for_modal as $ttype_item_modal): ?>
                                            <option value="<?php echo $ttype_item_modal['id']; ?>"><?php echo esc_html($ttype_item_modal['display_name_ar']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row gx-3">
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_phone_primary_modal_tenants" class="form-label">رقم الجوال الأساسي <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control form-control-sm" id="tenant_phone_primary_modal_tenants" name="tenant_phone_primary" required placeholder="05XXXXXXXX">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_phone_secondary_modal_tenants" class="form-label">رقم جوال إضافي</label>
                                    <input type="tel" class="form-control form-control-sm" id="tenant_phone_secondary_modal_tenants" name="tenant_phone_secondary" placeholder="05XXXXXXXX">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_email_modal_tenants" class="form-label">البريد الإلكتروني</label>
                                    <input type="email" class="form-control form-control-sm" id="tenant_email_modal_tenants" name="tenant_email" placeholder="example@domain.com">
                                </div>
                            </div>
                            <div class="row gx-3">
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_gender_modal_tenants" class="form-label">الجنس</label>
                                    <select class="form-select form-select-sm" id="tenant_gender_modal_tenants" name="gender">
                                        <?php foreach($gender_options_modal as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo esc_html($value); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_dob_modal_tenants" class="form-label">تاريخ الميلاد</label>
                                    <input type="date" class="form-control form-control-sm" id="tenant_dob_modal_tenants" name="date_of_birth">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_nationality_modal_tenants" class="form-label">الجنسية</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_nationality_modal_tenants" name="tenant_nationality" placeholder="مثال: سعودي">
                                </div>
                            </div>
                             <div class="row gx-3">
                                 <div class="col-md-6 mb-3">
                                    <label for="tenant_occupation_modal_tenants" class="form-label">المهنة</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_occupation_modal_tenants" name="tenant_occupation" placeholder="مثال: مهندس، طبيب">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="tenant_current_address_modal_tenants" class="form-label">العنوان الحالي للمستأجر</label>
                                <textarea class="form-control form-control-sm" id="tenant_current_address_modal_tenants" name="tenant_current_address" rows="2" placeholder="العنوان الحالي إذا كان مختلفًا عن الوحدة المستأجرة"></textarea>
                            </div>
                             <div class="mb-3">
                                <label for="tenant_notes_modal_tenants" class="form-label">ملاحظات عامة عن المستأجر</label>
                                <textarea class="form-control form-control-sm" id="tenant_notes_modal_tenants" name="tenant_notes" rows="2" placeholder="أي ملاحظات إضافية"></textarea>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="nav-zatca-buyer-info-modal" role="tabpanel" aria-labelledby="nav-zatca-buyer-info-tab-modal">
                            <h6 class="mb-3 text-primary">بيانات المشتري لأغراض الفوترة الإلكترونية (ZATCA):</h6>
                            <p class="text-muted small">تملأ هذه البيانات إذا كان المستأجر هو الطرف الذي ستصدر باسمه الفاتورة الإلكترونية (المشتري). إذا كانت الفاتورة لمؤسسة، أدخل بيانات المؤسسة هنا.</p>
                            <div class="row gx-3">
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_buyer_vat_number_modal_tenants" class="form-label">رقم تسجيل ضريبة القيمة المضافة للمشتري</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_buyer_vat_number_modal_tenants" name="tenant_buyer_vat_number" placeholder="3XXXXXXXXXXXXX (15 رقم)">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_buyer_street_name_modal_tenants" class="form-label">اسم الشارع (للمشتري)</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_buyer_street_name_modal_tenants" name="tenant_buyer_street_name" placeholder="مثال: شارع الملك عبد العزيز">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_buyer_building_no_modal_tenants" class="form-label">رقم المبنى (للمشتري)</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_buyer_building_no_modal_tenants" name="tenant_buyer_building_no" placeholder="مثال: 1234">
                                </div>
                            </div>
                            <div class="row gx-3">
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_buyer_additional_no_modal_tenants" class="form-label">الرقم الإضافي (للمشتري)</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_buyer_additional_no_modal_tenants" name="tenant_buyer_additional_no" placeholder="مثال: 5678">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_buyer_district_name_modal_tenants" class="form-label">الحي (للمشتري)</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_buyer_district_name_modal_tenants" name="tenant_buyer_district_name" placeholder="مثال: حي العليا">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_buyer_city_name_modal_tenants" class="form-label">المدينة (للمشتري)</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_buyer_city_name_modal_tenants" name="tenant_buyer_city_name" placeholder="مثال: الرياض">
                                </div>
                            </div>
                             <div class="row gx-3">
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_buyer_postal_code_modal_tenants" class="form-label">الرمز البريدي (للمشتري)</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_buyer_postal_code_modal_tenants" name="tenant_buyer_postal_code" placeholder="مثال: 12345">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tenant_buyer_country_code_modal_tenants" class="form-label">رمز الدولة (للمشتري)</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_buyer_country_code_modal_tenants" name="tenant_buyer_country_code" value="SA" placeholder="SA" maxlength="2">
                                    </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="nav-emergency-contact-modal" role="tabpanel" aria-labelledby="nav-emergency-contact-tab-modal">
                             <h6 class="mb-3 text-primary">بيانات جهة الاتصال في حالة الطوارئ:</h6>
                             <div class="row gx-3">
                                <div class="col-md-6 mb-3">
                                    <label for="tenant_emergency_contact_name_modal_tenants" class="form-label">اسم جهة الاتصال</label>
                                    <input type="text" class="form-control form-control-sm" id="tenant_emergency_contact_name_modal_tenants" name="tenant_emergency_contact_name" placeholder="الاسم الكامل لجهة الاتصال">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tenant_emergency_contact_phone_modal_tenants" class="form-label">رقم هاتف جهة الاتصال</label>
                                    <input type="tel" class="form-control form-control-sm" id="tenant_emergency_contact_phone_modal_tenants" name="tenant_emergency_contact_phone" placeholder="05XXXXXXXX">
                                </div>
                            </div>
                        </div>
                    </div>
                    <small class="text-danger">* حقول مطلوبة في تبويب "المعلومات الأساسية"</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> <span id="tenantSubmitButtonTextModal">حفظ البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>