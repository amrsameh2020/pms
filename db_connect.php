<?php
// db_connect.php

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

$mysqli = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($mysqli->connect_errno) {
    error_log("فشل الاتصال بـ MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
    die("فشل الاتصال بقاعدة البيانات. يرجى مراجعة المسؤول.");
}

if (!$mysqli->set_charset(DB_CHARSET)) {
    error_log("خطأ في تحميل مجموعة الحروف " . DB_CHARSET . ": " . $mysqli->error);
}

// تحميل إعدادات التطبيق من جدول app_settings وتعريفها كثوابت
$settings_sql = "SELECT `setting_key`, `setting_value` FROM `app_settings`";
$settings_result = $mysqli->query($settings_sql);

if ($settings_result) {
    while ($setting_row = $settings_result->fetch_assoc()) {
        $constant_name = strtoupper(str_replace('-', '_', $setting_row['setting_key']));
        if (!defined($constant_name)) {
            define($constant_name, $setting_row['setting_value']);
        }
    }
    $settings_result->free();
} else {
    error_log("فشل تحميل إعدادات التطبيق من قاعدة البيانات: " . $mysqli->error);
    die("فشل تحميل إعدادات التطبيق الأساسية. يرجى مراجعة مسؤول النظام.");
}

// التحقق من تعريف الثوابت الأساسية مع قيم افتراضية إذا لم يتم تحميلها
if (!defined('APP_NAME')) {
    define('APP_NAME', 'نظام إدارة العقارات (افتراضي)');
}
if (!defined('ITEMS_PER_PAGE')) {
    define('ITEMS_PER_PAGE', 10);
} else {
    // Ensure ITEMS_PER_PAGE is an integer
    if (!filter_var(ITEMS_PER_PAGE, FILTER_VALIDATE_INT)) {
        define('ITEMS_PER_PAGE_OVERRIDDEN_AS_INT', 10); // Use a different name if redefining is problematic
        error_log("Warning: ITEMS_PER_PAGE from database is not a valid integer. Using default 10.");
    } else {
         // If you want to ensure it's an int type, you could re-define or use a new constant
         // For simplicity, we assume it will be cast to int when used in calculations.
    }
}
// Add similar checks for critical ZATCA constants if their absence would break core functionality
// For example: ZATCA_SELLER_VAT_NUMBER
if (!defined('ZATCA_SELLER_VAT_NUMBER') || empty(ZATCA_SELLER_VAT_NUMBER)) {
    error_log("Critical ZATCA Setting Missing: ZATCA_SELLER_VAT_NUMBER is not defined in app_settings.");
    // Depending on how strictly you want to enforce this, you could die() here too.
}

?>