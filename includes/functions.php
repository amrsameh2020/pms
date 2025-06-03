<?php
// includes/functions.php

// التأكد من بدء الجلسة إذا لم تكن قد بدأت بالفعل (مهم لدوال set_message و CSRF)
if (session_status() == PHP_SESSION_NONE) {
    // لا تقم بـ session_set_cookie_params هنا، هذا يجب أن يكون في session_manager.php
    session_start();
}

/**
 * تنقية بيانات الإدخال لمنع هجمات XSS.
 * @param string|array $data بيانات الإدخال (نص أو مصفوفة).
 * @return string|array البيانات المنقاة.
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(stripslashes(trim((string)$data)), ENT_QUOTES, 'UTF-8');
}

/**
 * تعيين رسالة جلسة ليتم عرضها للمستخدم.
 * @param string $message نص الرسالة.
 * @param string $type نوع الرسالة (مثل 'success', 'danger', 'warning', 'info') - يتوافق مع كلاسات Bootstrap alert.
 */
function set_message($message, $type = 'info') {
    $_SESSION['flash_message'] = [ // تم تغيير اسم مفتاح الجلسة ليكون أوضح
        'message' => $message,
        'type' => $type
    ];
}

/**
 * عرض ثم مسح رسالة الجلسة.
 * يتم الآن التعامل مع العرض الأساسي في header_resources.php.
 * هذه الدالة يمكن استخدامها إذا احتجت لعرض رسالة في مكان محدد يدويًا.
 * @return string HTML لكود التنبيه، أو سلسلة فارغة إذا لم تكن هناك رسالة.
 */
function display_message_manual() {
    if (isset($_SESSION['flash_message'])) {
        $message_data = $_SESSION['flash_message'];
        if (is_array($message_data) && isset($message_data['message']) && isset($message_data['type'])) {
            $message = htmlspecialchars($message_data['message'], ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars($message_data['type'], ENT_QUOTES, 'UTF-8');
            $alert_html = '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
            $alert_html .= $message;
            $alert_html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $alert_html .= '</div>';

            unset($_SESSION['flash_message']);
            return $alert_html;
        }
        // إذا كانت البيانات غير متوقعة، قم بإزالتها
        unset($_SESSION['flash_message']);
    }
    return '';
}

/**
 * إعادة التوجيه إلى URL محدد.
 * @param string $url الـ URL المراد إعادة التوجيه إليه.
 */
function redirect($url) {
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
        if (function_exists('base_url')) {
            $url = base_url($url);
        } else {
            // fallback if base_url is not available (should not happen if config is loaded)
            $fallback_base = (defined('APP_BASE_URL') ? APP_BASE_URL : '');
            $url = rtrim($fallback_base, '/') . '/' . ltrim($url, '/');
        }
    }
    header("Location: " . $url);
    exit();
}


/**
 * تنسيق سلسلة تاريخ إلى تنسيق أكثر قابلية للقراءة.
 * @param string $date_string سلسلة التاريخ (مثل YYYY-MM-DD HH:MM:SS أو YYYY-MM-DD).
 * @param string $format تنسيق الإخراج المطلوب (سلسلة تنسيق تاريخ PHP).
 * @return string سلسلة التاريخ المنسقة، أو '-' إذا كان الإدخال غير صالح.
 */
function format_date_custom($date_string, $format = 'd M, Y H:i A') {
    if (empty($date_string) || $date_string === '0000-00-00' || $date_string === '0000-00-00 00:00:00') {
        return '-';
    }
    try {
        $date = new DateTime($date_string);
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Error formatting date: " . $e->getMessage() . " for string: " . $date_string);
        return $date_string; // Return original on error
    }
}

/**
 * إنشاء توكن CSRF وتخزينه في الجلسة.
 * @return string توكن CSRF.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * التحقق من توكن CSRF المرسل مع النموذج.
 * @param string $token التوكن المرسل مع النموذج.
 * @return bool True إذا كان التوكن صالحًا، False بخلاف ذلك.
 */
function verify_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        // For one-time tokens per form load, regenerate it after successful verification.
        // For now, we'll keep it same for the session unless explicitly regenerated.
        // unset($_SESSION['csrf_token']); // Uncomment for one-time tokens.
        return true;
    }
    error_log("CSRF token mismatch. Expected: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . " - Received: " . $token);
    return false;
}

/**
 * دالة مساعدة للحصول على APP_BASE_URL لتكوين الروابط.
 * يفترض أن APP_BASE_URL معرف في config.php.
 * @param string $path المسار الاختياري لإضافته إلى الـ URL الأساسي.
 * @return string الـ URL الكامل.
 */
function base_url($path = '') {
    if (!defined('APP_BASE_URL')) {
        error_log("CRITICAL: APP_BASE_URL is not defined when base_url() was called. Path: " . $path);
        // Basic fallback, may not be accurate for all setups
        $protocol_fn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host_fn = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_dir_fn = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // Assumes functions.php is in includes/
        $fallback_base_fn = $protocol_fn . $host_fn . $script_dir_fn;
        return rtrim($fallback_base_fn, '/') . '/' . ltrim($path, '/');
    }
    return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/');
}


/**
 * تنقية المخرجات لسمات HTML لمنع XSS.
 * @param string $string السلسلة المراد تنقيتها.
 * @return string السلسلة المنقاة.
 */
function esc_attr($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * تنقية المخرجات لمحتوى HTML العام لمنع XSS.
 * @param string $string السلسلة المراد تنقيتها.
 * @return string السلسلة المنقاة.
 */
function esc_html($string) {
    return htmlspecialchars((string)$string, ENT_NOQUOTES, 'UTF-8');
}

/**
 * دالة مبدئية لتوليد بيانات رمز الاستجابة السريعة لـ ZATCA (placeholder).
 */
function generate_zatca_qr_code_data_string($invoiceData) {
    // ... (الكود المبدئي كما هو، يتطلب تنفيذًا حقيقيًا لـ ZATCA)
    $qr_content = "اسم البائع: " . (isset($invoiceData['seller_name']) ? $invoiceData['seller_name'] : (defined('ZATCA_SELLER_NAME') ? ZATCA_SELLER_NAME : '')) . "\n";
    $qr_content .= "رقم الضريبة: " . (isset($invoiceData['vat_number']) ? $invoiceData['vat_number'] : (defined('ZATCA_SELLER_VAT_NUMBER') ? ZATCA_SELLER_VAT_NUMBER : '')) . "\n";
    $qr_content .= "الوقت: " . (isset($invoiceData['timestamp']) ? $invoiceData['timestamp'] : date('Y-m-d\TH:i:s\Z')) . "\n";
    $qr_content .= "الإجمالي: " . (isset($invoiceData['total_amount']) ? $invoiceData['total_amount'] : '0.00') . " ريال\n";
    $qr_content .= "الضريبة: " . (isset($invoiceData['vat_amount']) ? $invoiceData['vat_amount'] : '0.00') . " ريال\n";
    $qr_content .= "تجزئة الفاتورة: " . (isset($invoiceData['invoice_hash']) ? substr($invoiceData['invoice_hash'], 0, 10)."..." : 'N/A');
    return base64_encode($qr_content); 
}

/**
 * إنشاء روابط التصفح (pagination).
 */
function generate_pagination_links($current_page, $total_pages, $base_url_path, $params = []) {
    // ... (الكود كما هو، يفترض أنه يعمل بشكل صحيح)
    if ($total_pages <= 1) {
        return '';
    }
    $pagination_html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center pagination-sm">';
    $full_base_url = base_url($base_url_path);
    $query_params = $params;

    if ($current_page > 1) {
        $query_params['page'] = $current_page - 1;
        $pagination_html .= '<li class="page-item"><a class="page-link" href="' . $full_base_url . '?' . http_build_query($query_params) . '">السابق</a></li>';
    } else {
        $pagination_html .= '<li class="page-item disabled"><span class="page-link">السابق</span></li>';
    }

    $num_links_to_show = 5;
    $start_page = max(1, $current_page - floor($num_links_to_show / 2));
    $end_page = min($total_pages, $start_page + $num_links_to_show - 1);
    $start_page = max(1, $end_page - $num_links_to_show + 1);

    if ($start_page > 1) {
        $query_params['page'] = 1;
        $pagination_html .= '<li class="page-item"><a class="page-link" href="' . $full_base_url . '?' . http_build_query($query_params) . '">1</a></li>';
        if ($start_page > 2) {
            $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start_page; $i <= $end_page; $i++) {
        $query_params['page'] = $i;
        if ($i == $current_page) {
            $pagination_html .= '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pagination_html .= '<li class="page-item"><a class="page-link" href="' . $full_base_url . '?' . http_build_query($query_params) . '">' . $i . '</a></li>';
        }
    }

    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $query_params['page'] = $total_pages;
        $pagination_html .= '<li class="page-item"><a class="page-link" href="' . $full_base_url . '?' . http_build_query($query_params) . '">' . $total_pages . '</a></li>';
    }

    if ($current_page < $total_pages) {
        $query_params['page'] = $current_page + 1;
        $pagination_html .= '<li class="page-item"><a class="page-link" href="' . $full_base_url . '?' . http_build_query($query_params) . '">التالي</a></li>';
    } else {
        $pagination_html .= '<li class="page-item disabled"><span class="page-link">التالي</span></li>';
    }
    $pagination_html .= '</ul></nav>';
    return $pagination_html;
}

// دالة لتحديث حالة الفاتورة بناءً على الدفعات (إذا لم تكن موجودة بالفعل)
if (!function_exists('update_invoice_status')) {
    function update_invoice_status(mysqli $mysqli, int $invoice_id): bool {
        if (empty($invoice_id)) {
            return false;
        }

        $stmt_sum = $mysqli->prepare("SELECT SUM(amount_paid) as total_paid FROM payments WHERE invoice_id = ? AND status = 'Completed'");
        if (!$stmt_sum) return false;
        $stmt_sum->bind_param("i", $invoice_id);
        $stmt_sum->execute();
        $result_sum = $stmt_sum->get_result();
        $total_paid = 0;
        if ($result_sum->num_rows > 0) {
            $total_paid = (float)($result_sum->fetch_assoc()['total_paid'] ?? 0);
        }
        $stmt_sum->close();

        $stmt_inv = $mysqli->prepare("SELECT total_amount, due_date, status as current_status FROM invoices WHERE id = ?");
        if (!$stmt_inv) return false;
        $stmt_inv->bind_param("i", $invoice_id);
        $stmt_inv->execute();
        $result_inv = $stmt_inv->get_result();
        if ($result_inv->num_rows === 0) { $stmt_inv->close(); return false; }
        $invoice = $result_inv->fetch_assoc();
        $stmt_inv->close();

        $invoice_total_amount = (float)$invoice['total_amount'];
        $new_status = $invoice['current_status']; // Default to current status

        if (abs($total_paid - $invoice_total_amount) < 0.005) { // Compare floats with tolerance
            $new_status = 'Paid';
        } elseif ($total_paid > 0 && $total_paid < $invoice_total_amount) {
            $new_status = 'Partially Paid';
        } elseif ($total_paid <= 0) {
             // Only change to Unpaid if not already Cancelled/Void etc.
            if (!in_array($invoice['current_status'], ['Cancelled', 'Void', 'Draft'])) {
                $new_status = 'Unpaid';
            }
        }
        
        if ($new_status !== 'Paid' && $new_status !== 'Cancelled' && $new_status !== 'Void' && $new_status !== 'Draft' && strtotime($invoice['due_date']) < time()) {
            $new_status = 'Overdue';
        }

        // Only update if status has changed or paid_amount has changed
        if ($new_status !== $invoice['current_status'] || abs((float)$invoice['paid_amount'] - $total_paid) >= 0.005 ) {
            $stmt_update = $mysqli->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ?");
            if (!$stmt_update) return false;
            $stmt_update->bind_param("dsi", $total_paid, $new_status, $invoice_id);
            $success = $stmt_update->execute();
            $stmt_update->close();
            return $success;
        }
        return true; // No change needed, considered success
    }
}

?>