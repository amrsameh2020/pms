<?php
$page_title = "إعدادات التطبيق";
require_once __DIR__ . '/db_connect.php'; // For $mysqli and APP_NAME
require_once __DIR__ . '/includes/session_manager.php';
require_login();
require_role('admin'); // Only admins can access settings
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit_log_functions.php'; // تضمين ملف سجل التدقيق


$current_settings = [];
$settings_keys = [
    'APP_NAME', 'ITEMS_PER_PAGE', 'VAT_PERCENTAGE',
    'ZATCA_API_URL_PRODUCTION_CLEARANCE', 'ZATCA_API_URL_PRODUCTION_REPORTING', 'ZATCA_API_URL_PRODUCTION_COMPLIANCE_INVOICE',
    'ZATCA_API_URL_SIMULATION_CLEARANCE', 'ZATCA_API_URL_SIMULATION_REPORTING', 'ZATCA_API_URL_SIMULATION_COMPLIANCE_INVOICE',
    'ZATCA_API_URL_SANDBOX_PORTAL',
    'ZATCA_CERTIFICATE_PATH', 'ZATCA_PRIVATE_KEY_PATH', 'ZATCA_PRIVATE_KEY_PASSWORD',
    'ZATCA_CLIENT_ID', 'ZATCA_CLIENT_SECRET', 'ZATCA_COMPLIANCE_OTP',
    'ZATCA_SELLER_NAME', 'ZATCA_SELLER_VAT_NUMBER', 'ZATCA_SELLER_STREET_NAME', 'ZATCA_SELLER_BUILDING_NO',
    'ZATCA_SELLER_ADDITIONAL_NO', 'ZATCA_SELLER_DISTRICT_NAME', 'ZATCA_SELLER_CITY_NAME',
    'ZATCA_SELLER_POSTAL_CODE', 'ZATCA_SELLER_COUNTRY_CODE',
    'ZATCA_SOLUTION_NAME',
    'ZATCA_INVOICE_TYPE_CODE_SIMPLIFIED', 'ZATCA_INVOICE_TYPE_CODE_STANDARD',
    'ZATCA_PAYMENT_MEANS_CODE_CASH', 'ZATCA_PAYMENT_MEANS_CODE_CARD', 'ZATCA_PAYMENT_MEANS_CODE_BANK'
];

// Fetch current settings
$sql_fetch_settings = "SELECT setting_key, setting_value, description FROM app_settings WHERE setting_key IN ('" . implode("','", $settings_keys) . "')";
$result_settings = $mysqli->query($sql_fetch_settings);
if ($result_settings) {
    while ($row = $result_settings->fetch_assoc()) {
        $current_settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'description' => $row['description']
        ];
    }
} else {
    error_log("Failed to fetch app settings: " . $mysqli->error);
    set_message("حدث خطأ أثناء جلب الإعدادات الحالية.", "danger");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('خطأ في التحقق (CSRF). يرجى المحاولة مرة أخرى.', 'danger');
    } else {
        $mysqli->begin_transaction();
        $all_updates_successful = true;
        $updated_settings_log = []; // لتسجيل ما تم تغييره

        foreach ($settings_keys as $key) {
            if (isset($_POST[$key])) {
                $new_value = sanitize_input(trim($_POST[$key]));
                
                // Validation for specific fields
                if ($key === 'ITEMS_PER_PAGE' && (!filter_var($new_value, FILTER_VALIDATE_INT) || (int)$new_value <= 0)) {
                    set_message("قيمة 'عدد العناصر بالصفحة' يجب أن تكون رقمًا صحيحًا أكبر من صفر.", "warning");
                    $all_updates_successful = false; // Mark as not fully successful to prevent success message if other fields are fine
                    continue; // Skip this update
                }
                if ($key === 'VAT_PERCENTAGE' && (!filter_var($new_value, FILTER_VALIDATE_FLOAT) || (float)$new_value < 0 || (float)$new_value > 100)) {
                    set_message("قيمة 'نسبة الضريبة' يجب أن تكون رقمًا بين 0 و 100.", "warning");
                    $all_updates_successful = false;
                    continue;
                }
                 // Ensure ZATCA codes are numeric if not empty
                $zatca_code_keys = [
                    'ZATCA_INVOICE_TYPE_CODE_SIMPLIFIED', 'ZATCA_INVOICE_TYPE_CODE_STANDARD',
                    'ZATCA_PAYMENT_MEANS_CODE_CASH', 'ZATCA_PAYMENT_MEANS_CODE_CARD', 'ZATCA_PAYMENT_MEANS_CODE_BANK'
                ];
                if (in_array($key, $zatca_code_keys) && !empty($new_value) && !ctype_digit($new_value)) {
                    set_message("قيمة '" . ($current_settings[$key]['description'] ?? $key) . "' يجب أن تكون رقمية.", "warning");
                    $all_updates_successful = false;
                    continue;
                }


                // Check if the value actually changed
                $old_value = $current_settings[$key]['value'] ?? null;
                if ($new_value !== $old_value) {
                    $updated_settings_log[$key] = ['old' => $old_value, 'new' => $new_value];
                }

                $stmt = $mysqli->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $new_value, $key);
                    if (!$stmt->execute()) {
                        $all_updates_successful = false;
                        set_message("حدث خطأ أثناء تحديث الإعداد: " . esc_html($key) . " - " . $stmt->error, "danger");
                        error_log("Failed to update setting " . $key . ": " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    $all_updates_successful = false;
                    set_message("خطأ في تجهيز الاستعلام للإعداد: " . esc_html($key) . " - " . $mysqli->error, "danger");
                    error_log("Failed to prepare statement for setting " . $key . ": " . $mysqli->error);
                }
            }
        }

        if ($all_updates_successful) {
            if (!empty($updated_settings_log)) {
                log_audit_action($mysqli, AUDIT_UPDATE_APP_SETTINGS, null, 'app_settings', $updated_settings_log);
                set_message('تم تحديث الإعدادات بنجاح!', 'success');
            } else {
                set_message('لم يتم إجراء أي تغييرات على الإعدادات.', 'info');
            }
            $mysqli->commit();
        } else {
            $mysqli->rollback();
            // Messages should have been set for specific errors or a general one if needed.
            if (empty($_SESSION['flash_message'])) { // if no specific error was set during loop
                set_message('فشل تحديث بعض الإعدادات. يرجى مراجعة المدخلات.', 'danger');
            }
        }
        // Re-fetch settings to display updated values
        $current_settings = []; // Clear old values
        $result_settings = $mysqli->query($sql_fetch_settings); // Re-run the query
        if ($result_settings) {
            while ($row = $result_settings->fetch_assoc()) {
                $current_settings[$row['setting_key']] = [
                    'value' => $row['setting_value'],
                    'description' => $row['description']
                ];
            }
        }
        redirect(base_url('settings.php')); // Redirect to refresh and clear POST
    }
}

require_once __DIR__ . '/includes/header_resources.php';
require_once __DIR__ . '/includes/navigation.php';

$csrf_token = generate_csrf_token(); // Generate new token for the form

// Function to safely get setting value
function get_setting_value($settings_array, $key, $default = '') {
    return isset($settings_array[$key]['value']) ? esc_attr($settings_array[$key]['value']) : $default;
}
function get_setting_description($settings_array, $key, $default_key_name = '') {
    return isset($settings_array[$key]['description']) ? esc_html($settings_array[$key]['description']) : esc_html($default_key_name);
}

?>
<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-gear-fill"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="card-title mb-0">تعديل إعدادات النظام</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo base_url('settings.php'); ?>" id="settingsForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <nav>
                    <div class="nav nav-tabs" id="nav-tab-settings" role="tablist">
                        <button class="nav-link active" id="nav-general-tab" data-bs-toggle="tab" data-bs-target="#nav-general" type="button" role="tab" aria-controls="nav-general" aria-selected="true">إعدادات عامة</button>
                        <button class="nav-link" id="nav-zatca-seller-tab" data-bs-toggle="tab" data-bs-target="#nav-zatca-seller" type="button" role="tab" aria-controls="nav-zatca-seller" aria-selected="false">بيانات البائع (ZATCA)</button>
                        <button class="nav-link" id="nav-zatca-api-tab" data-bs-toggle="tab" data-bs-target="#nav-zatca-api" type="button" role="tab" aria-controls="nav-zatca-api" aria-selected="false">روابط ZATCA API</button>
                        <button class="nav-link" id="nav-zatca-integration-tab" data-bs-toggle="tab" data-bs-target="#nav-zatca-integration" type="button" role="tab" aria-controls="nav-zatca-integration" aria-selected="false">تكامل ZATCA</button>
                        <button class="nav-link" id="nav-zatca-codes-tab" data-bs-toggle="tab" data-bs-target="#nav-zatca-codes" type="button" role="tab" aria-controls="nav-zatca-codes" aria-selected="false">أكواد ZATCA</button>
                    </div>
                </nav>

                <div class="tab-content pt-3" id="nav-tabContentSettings">
                    <div class="tab-pane fade show active" id="nav-general" role="tabpanel" aria-labelledby="nav-general-tab">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="APP_NAME" class="form-label"><?php echo get_setting_description($current_settings, 'APP_NAME', 'APP_NAME'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="APP_NAME" name="APP_NAME" value="<?php echo get_setting_value($current_settings, 'APP_NAME'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ITEMS_PER_PAGE" class="form-label"><?php echo get_setting_description($current_settings, 'ITEMS_PER_PAGE', 'ITEMS_PER_PAGE'); ?></label>
                                <input type="number" class="form-control form-control-sm" id="ITEMS_PER_PAGE" name="ITEMS_PER_PAGE" value="<?php echo get_setting_value($current_settings, 'ITEMS_PER_PAGE', '10'); ?>" min="1">
                            </div>
                        </div>
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="VAT_PERCENTAGE" class="form-label"><?php echo get_setting_description($current_settings, 'VAT_PERCENTAGE', 'VAT_PERCENTAGE'); ?></label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="VAT_PERCENTAGE" name="VAT_PERCENTAGE" value="<?php echo get_setting_value($current_settings, 'VAT_PERCENTAGE', '15.00'); ?>" min="0" max="100">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="nav-zatca-seller" role="tabpanel" aria-labelledby="nav-zatca-seller-tab">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ZATCA_SELLER_NAME" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SELLER_NAME', 'ZATCA_SELLER_NAME'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SELLER_NAME" name="ZATCA_SELLER_NAME" value="<?php echo get_setting_value($current_settings, 'ZATCA_SELLER_NAME'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ZATCA_SELLER_VAT_NUMBER" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SELLER_VAT_NUMBER', 'ZATCA_SELLER_VAT_NUMBER'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SELLER_VAT_NUMBER" name="ZATCA_SELLER_VAT_NUMBER" value="<?php echo get_setting_value($current_settings, 'ZATCA_SELLER_VAT_NUMBER'); ?>">
                            </div>
                        </div>
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ZATCA_SELLER_STREET_NAME" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SELLER_STREET_NAME', 'ZATCA_SELLER_STREET_NAME'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SELLER_STREET_NAME" name="ZATCA_SELLER_STREET_NAME" value="<?php echo get_setting_value($current_settings, 'ZATCA_SELLER_STREET_NAME'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ZATCA_SELLER_BUILDING_NO" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SELLER_BUILDING_NO', 'ZATCA_SELLER_BUILDING_NO'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SELLER_BUILDING_NO" name="ZATCA_SELLER_BUILDING_NO" value="<?php echo get_setting_value($current_settings, 'ZATCA_SELLER_BUILDING_NO'); ?>">
                            </div>
                        </div>
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ZATCA_SELLER_ADDITIONAL_NO" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SELLER_ADDITIONAL_NO', 'ZATCA_SELLER_ADDITIONAL_NO'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SELLER_ADDITIONAL_NO" name="ZATCA_SELLER_ADDITIONAL_NO" value="<?php echo get_setting_value($current_settings, 'ZATCA_SELLER_ADDITIONAL_NO'); ?>">
                            </div>
                             <div class="col-md-6 mb-3">
                                <label for="ZATCA_SELLER_DISTRICT_NAME" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SELLER_DISTRICT_NAME', 'ZATCA_SELLER_DISTRICT_NAME'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SELLER_DISTRICT_NAME" name="ZATCA_SELLER_DISTRICT_NAME" value="<?php echo get_setting_value($current_settings, 'ZATCA_SELLER_DISTRICT_NAME'); ?>">
                            </div>
                        </div>
                         <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="ZATCA_SELLER_CITY_NAME" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SELLER_CITY_NAME', 'ZATCA_SELLER_CITY_NAME'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SELLER_CITY_NAME" name="ZATCA_SELLER_CITY_NAME" value="<?php echo get_setting_value($current_settings, 'ZATCA_SELLER_CITY_NAME'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ZATCA_SELLER_POSTAL_CODE" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SELLER_POSTAL_CODE', 'ZATCA_SELLER_POSTAL_CODE'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SELLER_POSTAL_CODE" name="ZATCA_SELLER_POSTAL_CODE" value="<?php echo get_setting_value($current_settings, 'ZATCA_SELLER_POSTAL_CODE'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ZATCA_SELLER_COUNTRY_CODE" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SELLER_COUNTRY_CODE', 'ZATCA_SELLER_COUNTRY_CODE'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SELLER_COUNTRY_CODE" name="ZATCA_SELLER_COUNTRY_CODE" value="<?php echo get_setting_value($current_settings, 'ZATCA_SELLER_COUNTRY_CODE', 'SA'); ?>" maxlength="2">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="nav-zatca-api" role="tabpanel" aria-labelledby="nav-zatca-api-tab">
                        <h6 class="text-muted">روابط بيئة الإنتاج</h6>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="ZATCA_API_URL_PRODUCTION_CLEARANCE" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_API_URL_PRODUCTION_CLEARANCE'); ?></label>
                                <input type="url" class="form-control form-control-sm" id="ZATCA_API_URL_PRODUCTION_CLEARANCE" name="ZATCA_API_URL_PRODUCTION_CLEARANCE" value="<?php echo get_setting_value($current_settings, 'ZATCA_API_URL_PRODUCTION_CLEARANCE'); ?>">
                            </div>
                             <div class="col-md-12 mb-3">
                                <label for="ZATCA_API_URL_PRODUCTION_REPORTING" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_API_URL_PRODUCTION_REPORTING'); ?></label>
                                <input type="url" class="form-control form-control-sm" id="ZATCA_API_URL_PRODUCTION_REPORTING" name="ZATCA_API_URL_PRODUCTION_REPORTING" value="<?php echo get_setting_value($current_settings, 'ZATCA_API_URL_PRODUCTION_REPORTING'); ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="ZATCA_API_URL_PRODUCTION_COMPLIANCE_INVOICE" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_API_URL_PRODUCTION_COMPLIANCE_INVOICE'); ?></label>
                                <input type="url" class="form-control form-control-sm" id="ZATCA_API_URL_PRODUCTION_COMPLIANCE_INVOICE" name="ZATCA_API_URL_PRODUCTION_COMPLIANCE_INVOICE" value="<?php echo get_setting_value($current_settings, 'ZATCA_API_URL_PRODUCTION_COMPLIANCE_INVOICE'); ?>">
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-muted">روابط بيئة المحاكاة/التجربة</h6>
                         <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="ZATCA_API_URL_SIMULATION_CLEARANCE" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_API_URL_SIMULATION_CLEARANCE'); ?></label>
                                <input type="url" class="form-control form-control-sm" id="ZATCA_API_URL_SIMULATION_CLEARANCE" name="ZATCA_API_URL_SIMULATION_CLEARANCE" value="<?php echo get_setting_value($current_settings, 'ZATCA_API_URL_SIMULATION_CLEARANCE'); ?>">
                            </div>
                             <div class="col-md-12 mb-3">
                                <label for="ZATCA_API_URL_SIMULATION_REPORTING" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_API_URL_SIMULATION_REPORTING'); ?></label>
                                <input type="url" class="form-control form-control-sm" id="ZATCA_API_URL_SIMULATION_REPORTING" name="ZATCA_API_URL_SIMULATION_REPORTING" value="<?php echo get_setting_value($current_settings, 'ZATCA_API_URL_SIMULATION_REPORTING'); ?>">
                            </div>
                             <div class="col-md-12 mb-3">
                                <label for="ZATCA_API_URL_SIMULATION_COMPLIANCE_INVOICE" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_API_URL_SIMULATION_COMPLIANCE_INVOICE'); ?></label>
                                <input type="url" class="form-control form-control-sm" id="ZATCA_API_URL_SIMULATION_COMPLIANCE_INVOICE" name="ZATCA_API_URL_SIMULATION_COMPLIANCE_INVOICE" value="<?php echo get_setting_value($current_settings, 'ZATCA_API_URL_SIMULATION_COMPLIANCE_INVOICE'); ?>">
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-muted">رابط بوابة المطورين</h6>
                        <div class="row">
                             <div class="col-md-12 mb-3">
                                <label for="ZATCA_API_URL_SANDBOX_PORTAL" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_API_URL_SANDBOX_PORTAL'); ?></label>
                                <input type="url" class="form-control form-control-sm" id="ZATCA_API_URL_SANDBOX_PORTAL" name="ZATCA_API_URL_SANDBOX_PORTAL" value="<?php echo get_setting_value($current_settings, 'ZATCA_API_URL_SANDBOX_PORTAL'); ?>">
                            </div>
                        </div>
                    </div>

                     <div class="tab-pane fade" id="nav-zatca-integration" role="tabpanel" aria-labelledby="nav-zatca-integration-tab">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ZATCA_CERTIFICATE_PATH" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_CERTIFICATE_PATH'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_CERTIFICATE_PATH" name="ZATCA_CERTIFICATE_PATH" value="<?php echo get_setting_value($current_settings, 'ZATCA_CERTIFICATE_PATH'); ?>">
                                <small class="form-text text-muted">المسار الكامل على الخادم لملف الشهادة.</small>
                            </div>
                             <div class="col-md-6 mb-3">
                                <label for="ZATCA_PRIVATE_KEY_PATH" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_PRIVATE_KEY_PATH'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_PRIVATE_KEY_PATH" name="ZATCA_PRIVATE_KEY_PATH" value="<?php echo get_setting_value($current_settings, 'ZATCA_PRIVATE_KEY_PATH'); ?>">
                                 <small class="form-text text-muted">المسار الكامل على الخادم لملف المفتاح الخاص.</small>
                            </div>
                        </div>
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ZATCA_PRIVATE_KEY_PASSWORD" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_PRIVATE_KEY_PASSWORD'); ?></label>
                                <input type="password" class="form-control form-control-sm" id="ZATCA_PRIVATE_KEY_PASSWORD" name="ZATCA_PRIVATE_KEY_PASSWORD" value="<?php echo get_setting_value($current_settings, 'ZATCA_PRIVATE_KEY_PASSWORD'); ?>">
                                <small class="form-text text-muted">اتركه فارغًا إذا لم يكن المفتاح الخاص محميًا بكلمة مرور.</small>
                            </div>
                             <div class="col-md-6 mb-3">
                                <label for="ZATCA_CLIENT_ID" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_CLIENT_ID'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_CLIENT_ID" name="ZATCA_CLIENT_ID" value="<?php echo get_setting_value($current_settings, 'ZATCA_CLIENT_ID'); ?>">
                            </div>
                        </div>
                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <label for="ZATCA_CLIENT_SECRET" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_CLIENT_SECRET'); ?></label>
                                <input type="password" class="form-control form-control-sm" id="ZATCA_CLIENT_SECRET" name="ZATCA_CLIENT_SECRET" value="<?php echo get_setting_value($current_settings, 'ZATCA_CLIENT_SECRET'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ZATCA_COMPLIANCE_OTP" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_COMPLIANCE_OTP'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_COMPLIANCE_OTP" name="ZATCA_COMPLIANCE_OTP" value="<?php echo get_setting_value($current_settings, 'ZATCA_COMPLIANCE_OTP'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="ZATCA_SOLUTION_NAME" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_SOLUTION_NAME'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_SOLUTION_NAME" name="ZATCA_SOLUTION_NAME" value="<?php echo get_setting_value($current_settings, 'ZATCA_SOLUTION_NAME'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="nav-zatca-codes" role="tabpanel" aria-labelledby="nav-zatca-codes-tab">
                        <h6 class="text-muted">أكواد أنواع الفواتير</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ZATCA_INVOICE_TYPE_CODE_SIMPLIFIED" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_INVOICE_TYPE_CODE_SIMPLIFIED'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_INVOICE_TYPE_CODE_SIMPLIFIED" name="ZATCA_INVOICE_TYPE_CODE_SIMPLIFIED" value="<?php echo get_setting_value($current_settings, 'ZATCA_INVOICE_TYPE_CODE_SIMPLIFIED', '388'); ?>">
                            </div>
                             <div class="col-md-6 mb-3">
                                <label for="ZATCA_INVOICE_TYPE_CODE_STANDARD" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_INVOICE_TYPE_CODE_STANDARD'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_INVOICE_TYPE_CODE_STANDARD" name="ZATCA_INVOICE_TYPE_CODE_STANDARD" value="<?php echo get_setting_value($current_settings, 'ZATCA_INVOICE_TYPE_CODE_STANDARD', '388'); ?>">
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-muted">أكواد وسائل الدفع</h6>
                         <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="ZATCA_PAYMENT_MEANS_CODE_CASH" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_PAYMENT_MEANS_CODE_CASH'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_PAYMENT_MEANS_CODE_CASH" name="ZATCA_PAYMENT_MEANS_CODE_CASH" value="<?php echo get_setting_value($current_settings, 'ZATCA_PAYMENT_MEANS_CODE_CASH', '10'); ?>">
                            </div>
                             <div class="col-md-4 mb-3">
                                <label for="ZATCA_PAYMENT_MEANS_CODE_CARD" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_PAYMENT_MEANS_CODE_CARD'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_PAYMENT_MEANS_CODE_CARD" name="ZATCA_PAYMENT_MEANS_CODE_CARD" value="<?php echo get_setting_value($current_settings, 'ZATCA_PAYMENT_MEANS_CODE_CARD', '48'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ZATCA_PAYMENT_MEANS_CODE_BANK" class="form-label"><?php echo get_setting_description($current_settings, 'ZATCA_PAYMENT_MEANS_CODE_BANK'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="ZATCA_PAYMENT_MEANS_CODE_BANK" name="ZATCA_PAYMENT_MEANS_CODE_BANK" value="<?php echo get_setting_value($current_settings, 'ZATCA_PAYMENT_MEANS_CODE_BANK', '42'); ?>">
                            </div>
                        </div>
                    </div>

                </div> <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> حفظ الإعدادات</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div> <?php require_once __DIR__ . '/includes/footer_resources.php'; ?>