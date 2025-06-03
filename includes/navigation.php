<?php
// includes/navigation.php

// Fallback for APP_BASE_URL (should ideally be defined in config.php via db_connect.php)
if (!defined('APP_BASE_URL')) {
    $protocol_nav = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host_nav = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script_path_nav = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
    $fallback_base_dir_nav = '';
    if (!empty($script_path_nav)) {
        $path_parts = explode('/', trim(dirname($script_path_nav), '/\\')); // Get directory parts
        if (!empty($path_parts[0]) && $path_parts[0] !== '.' && $path_parts[0] !== '') {
            $fallback_base_dir_nav = '/' . $path_parts[0]; // Assume first part is project folder if not root
        }
    }
    define('APP_BASE_URL', rtrim($protocol_nav . $host_nav . $fallback_base_dir_nav, '/'));
    error_log("WARNING: APP_BASE_URL was not defined prior to navigation.php. Fallback used: " . APP_BASE_URL);
}
$app_display_name_nav = defined('APP_NAME') ? APP_NAME : 'نظام الإدارة';

// Determine active navigation link
$current_script_path_nav = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
$app_base_path_nav = defined('APP_BASE_URL') ? (string)parse_url(APP_BASE_URL, PHP_URL_PATH) : '';
$relative_uri_nav = '';

if (!empty($app_base_path_nav) && strpos($_SERVER['REQUEST_URI'], $app_base_path_nav) === 0) {
    $relative_uri_nav = substr($_SERVER['REQUEST_URI'], strlen(rtrim($app_base_path_nav, '/')));
} else {
    // Fallback if APP_BASE_URL is not part of REQUEST_URI (e.g. root deployment)
    $relative_uri_nav = $_SERVER['REQUEST_URI'];
}
// Remove query string from relative URI for segment matching
$relative_uri_nav = strtok($relative_uri_nav, '?');
$uri_segments_nav = explode('/', trim($relative_uri_nav, '/'));
$current_module_segment_nav = isset($uri_segments_nav[0]) ? $uri_segments_nav[0] : '';
$current_sub_module_segment_nav = isset($uri_segments_nav[1]) ? $uri_segments_nav[1] : '';


// If the first segment is empty and the script is index.php or dashboard.php at root, identify it
if (empty($current_module_segment_nav) && in_array(basename($current_script_path_nav), ['index.php', 'dashboard.php'])) {
    $current_module_segment_nav = 'dashboard.php'; // Default to dashboard for root index/dashboard
}


function is_nav_active_final($module_identifier, $sub_module_identifier = null) {
    global $current_module_segment_nav, $current_sub_module_segment_nav, $current_script_path_nav;
    $current_script_basename = basename($current_script_path_nav);

    // Check for specific PHP files like dashboard.php, settings.php
    if (strpos($module_identifier, '.php') !== false) {
        return ($current_script_basename === $module_identifier &&
               ($current_module_segment_nav === $module_identifier ||
                empty($current_module_segment_nav) ||
                $current_module_segment_nav === basename(defined('APP_BASE_URL') ? (string)parse_url(APP_BASE_URL, PHP_URL_PATH) : '')
               )
        );
    }
    // Check for module directories
    if ($sub_module_identifier !== null) {
        return ($current_module_segment_nav === $module_identifier && $current_sub_module_segment_nav === $sub_module_identifier);
    }
    return ($current_module_segment_nav === $module_identifier);
}

$system_types_active = is_nav_active_final('property_types') || is_nav_active_final('unit_types') || is_nav_active_final('tenant_types') || is_nav_active_final('lease_types') || is_nav_active_final('payment_methods') || is_nav_active_final('utility_types');

?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3><?php echo htmlspecialchars($app_display_name_nav); ?></h3>
        <?php if (function_exists('get_current_user_fullname') && get_current_user_fullname()): ?>
            <small class="text-muted">مرحباً, <?php echo htmlspecialchars(get_current_user_fullname()); ?></small>
        <?php endif; ?>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('dashboard.php') ? 'active' : ''; ?>" href="<?php echo base_url('dashboard.php'); ?>">
                <i class="bi bi-grid-fill"></i> لوحة القيادة
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('owners') ? 'active' : ''; ?>" href="<?php echo base_url('owners/index.php'); ?>">
                <i class="bi bi-people-fill"></i> أصحاب العقارات
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('properties') ? 'active' : ''; ?>" href="<?php echo base_url('properties/index.php'); ?>">
                <i class="bi bi-building"></i> العقارات
            </a>
        </li>
         <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('units') ? 'active' : ''; ?>" href="<?php echo base_url('units/index.php'); ?>">
                <i class="bi bi-grid-3x3-gap-fill"></i> الوحدات
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('tenants') ? 'active' : ''; ?>" href="<?php echo base_url('tenants/index.php'); ?>">
                <i class="bi bi-person-badge-fill"></i> المستأجرين
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('leases') ? 'active' : ''; ?>" href="<?php echo base_url('leases/index.php'); ?>">
                <i class="bi bi-file-earmark-text-fill"></i> عقود الإيجار
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('invoices') ? 'active' : ''; ?>" href="<?php echo base_url('invoices/index.php'); ?>">
                <i class="bi bi-receipt-cutoff"></i> الفواتير
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('payments') ? 'active' : ''; ?>" href="<?php echo base_url('payments/index.php'); ?>">
                <i class="bi bi-credit-card-fill"></i> المدفوعات
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('utilities') ? 'active' : ''; ?>" href="<?php echo base_url('utilities/index.php'); ?>">
                <i class="bi bi-lightning-charge-fill"></i> المرافق وقراءات العدادات
            </a>
        </li>

        <?php if (function_exists('user_has_role') && user_has_role('admin')): ?>
        <li class="nav-item mt-3 pt-3 border-top" style="border-color: #495057 !important;">
            <span class="nav-link text-muted small text-uppercase" style="pointer-events: none; font-size: 0.8rem; padding-bottom: 0.5rem;">الإدارة والتهيئة</span>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('users') ? 'active' : ''; ?>" href="<?php echo base_url('users/index.php'); ?>">
                <i class="bi bi-person-lines-fill"></i> إدارة المستخدمين
            </a>
        </li>
         <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('roles') ? 'active' : ''; ?>" href="<?php echo base_url('roles/index.php'); ?>">
                <i class="bi bi-person-rolodex"></i> إدارة الأدوار
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $system_types_active ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#systemTypesSubmenu" role="button" aria-expanded="<?php echo $system_types_active ? 'true' : 'false'; ?>" aria-controls="systemTypesSubmenu">
                <i class="bi bi-tags-fill"></i> تعريفات النظام <i class="bi bi-chevron-down float-end"></i>
            </a>
            <ul class="collapse list-unstyled <?php echo $system_types_active ? 'show' : ''; ?>" id="systemTypesSubmenu">
                <li><a class="nav-link <?php echo is_nav_active_final('property_types') ? 'active' : ''; ?>" href="<?php echo base_url('property_types/index.php'); ?>"><small><i class="bi bi-dash"></i> أنواع العقارات</small></a></li>
                <li><a class="nav-link <?php echo is_nav_active_final('unit_types') ? 'active' : ''; ?>" href="<?php echo base_url('unit_types/index.php'); ?>"><small><i class="bi bi-dash"></i> أنواع الوحدات</small></a></li>
                <li><a class="nav-link <?php echo is_nav_active_final('tenant_types') ? 'active' : ''; ?>" href="<?php echo base_url('tenant_types/index.php'); ?>"><small><i class="bi bi-dash"></i> أنواع المستأجرين</small></a></li>
                <li><a class="nav-link <?php echo is_nav_active_final('lease_types') ? 'active' : ''; ?>" href="<?php echo base_url('lease_types/index.php'); ?>"><small><i class="bi bi-dash"></i> أنواع عقود الإيجار</small></a></li>
                <li><a class="nav-link <?php echo is_nav_active_final('payment_methods') ? 'active' : ''; ?>" href="<?php echo base_url('payment_methods/index.php'); ?>"><small><i class="bi bi-dash"></i> طرق الدفع</small></a></li>
                <li><a class="nav-link <?php echo is_nav_active_final('utility_types') ? 'active' : ''; ?>" href="<?php echo base_url('utility_types/index.php'); ?>"><small><i class="bi bi-dash"></i> أنواع المرافق</small></a></li>
            </ul>
        </li>
         <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('audit_log') ? 'active' : ''; ?>" href="<?php echo base_url('audit_log/index.php'); ?>">
                <i class="bi bi-card-list"></i> سجل التدقيق
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo is_nav_active_final('settings.php') ? 'active' : ''; ?>" href="<?php echo base_url('settings.php'); ?>">
                <i class="bi bi-gear-fill"></i> إعدادات التطبيق (ZATCA)
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-auto pt-3 border-top" style="border-color: #495057 !important;">
            <a class="nav-link" href="<?php echo base_url('auth/logout.php'); ?>">
                <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
            </a>
        </li>
    </ul>
</nav>