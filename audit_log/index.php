<?php
$page_title = "سجل التدقيق";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_role('admin'); // سجل التدقيق عادة ما يكون للمسؤولين فقط
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// Pagination variables
$current_page_audit = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_audit = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 25; // Show more items for logs
$offset_audit = ($current_page_audit - 1) * $items_per_page_audit;

// Filtering
$filter_user_id_audit = isset($_GET['user_id']) && filter_var($_GET['user_id'], FILTER_VALIDATE_INT) ? (int)$_GET['user_id'] : '';
$filter_action_type_audit = isset($_GET['action_type']) ? sanitize_input($_GET['action_type']) : '';
$filter_target_table_audit = isset($_GET['target_table']) ? sanitize_input($_GET['target_table']) : '';
$filter_date_from_audit = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$filter_date_to_audit = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

$where_clauses_audit = [];
$params_for_count_audit = []; $types_for_count_audit = "";
$params_for_data_audit = [];  $types_for_data_audit = "";

if (!empty($filter_user_id_audit)) {
    $where_clauses_audit[] = "al.user_id = ?";
    $params_for_count_audit[] = $filter_user_id_audit; $types_for_count_audit .= "i";
    $params_for_data_audit[] = $filter_user_id_audit;  $types_for_data_audit .= "i";
}
if (!empty($filter_action_type_audit)) {
    $where_clauses_audit[] = "al.action_type = ?";
    $params_for_count_audit[] = $filter_action_type_audit; $types_for_count_audit .= "s";
    $params_for_data_audit[] = $filter_action_type_audit;  $types_for_data_audit .= "s";
}
if (!empty($filter_target_table_audit)) {
    $where_clauses_audit[] = "al.target_table = ?";
    $params_for_count_audit[] = $filter_target_table_audit; $types_for_count_audit .= "s";
    $params_for_data_audit[] = $filter_target_table_audit;  $types_for_data_audit .= "s";
}
if (!empty($filter_date_from_audit)) {
    $where_clauses_audit[] = "DATE(al.timestamp) >= ?";
    $params_for_count_audit[] = $filter_date_from_audit; $types_for_count_audit .= "s";
    $params_for_data_audit[] = $filter_date_from_audit;  $types_for_data_audit .= "s";
}
if (!empty($filter_date_to_audit)) {
    $where_clauses_audit[] = "DATE(al.timestamp) <= ?";
    $params_for_count_audit[] = $filter_date_to_audit; $types_for_count_audit .= "s";
    $params_for_data_audit[] = $filter_date_to_audit;  $types_for_data_audit .= "s";
}

$where_sql_audit = "";
if (!empty($where_clauses_audit)) {
    $where_sql_audit = " WHERE " . implode(" AND ", $where_clauses_audit);
}

// Get total number of audit log entries
$total_sql_audit = "SELECT COUNT(al.id) as total FROM audit_log al" . $where_sql_audit;
$stmt_total_audit = $mysqli->prepare($total_sql_audit);
$total_audit_logs = 0;
if ($stmt_total_audit) {
    if (!empty($params_for_count_audit)) $stmt_total_audit->bind_param($types_for_count_audit, ...$params_for_count_audit);
    $stmt_total_audit->execute();
    $total_result_audit = $stmt_total_audit->get_result();
    $total_audit_logs = ($total_result_audit && $total_result_audit->num_rows > 0) ? $total_result_audit->fetch_assoc()['total'] : 0;
    $stmt_total_audit->close();
} else { error_log("SQL Prepare Error counting audit logs: " . $mysqli->error); }
$total_pages_audit = ceil($total_audit_logs / $items_per_page_audit);

// Fetch audit logs for the current page
$sql_audit = "SELECT al.*, u.full_name as user_full_name, u.username 
              FROM audit_log al 
              LEFT JOIN users u ON al.user_id = u.id" 
             . $where_sql_audit . " ORDER BY al.timestamp DESC LIMIT ? OFFSET ?";

$current_data_params_audit = $params_for_data_audit;
$current_data_params_audit[] = $items_per_page_audit;
$current_data_params_audit[] = $offset_audit;
$current_data_types_audit = $types_for_data_audit . 'ii';

$audit_logs_list = [];
$stmt_audit = $mysqli->prepare($sql_audit);
if ($stmt_audit) {
    if (!empty($current_data_params_audit) && $current_data_types_audit !== '') $stmt_audit->bind_param($current_data_types_audit, ...$current_data_params_audit);
    else $stmt_audit->bind_param('ii', $items_per_page_audit, $offset_audit);
    $stmt_audit->execute();
    $result_audit = $stmt_audit->get_result();
    $audit_logs_list = ($result_audit && $result_audit->num_rows > 0) ? $result_audit->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_audit->close();
} else { error_log("SQL Prepare Error fetching audit logs: " . $mysqli->error); }

// For filters: Get distinct users, action_types, target_tables
$distinct_users_audit = $mysqli->query("SELECT DISTINCT u.id, u.full_name, u.username FROM audit_log al JOIN users u ON al.user_id = u.id WHERE al.user_id IS NOT NULL ORDER BY u.full_name ASC")->fetch_all(MYSQLI_ASSOC);
$distinct_actions_audit = $mysqli->query("SELECT DISTINCT action_type FROM audit_log ORDER BY action_type ASC")->fetch_all(MYSQLI_ASSOC);
$distinct_tables_audit = $mysqli->query("SELECT DISTINCT target_table FROM audit_log WHERE target_table IS NOT NULL AND target_table != '' ORDER BY target_table ASC")->fetch_all(MYSQLI_ASSOC);

$csrf_token = generate_csrf_token(); 
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-card-list"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">سجلات التدقيق (<?php echo $total_audit_logs; ?>)</h5>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('audit_log/index.php'); ?>" class="row gx-2 gy-2 align-items-end">
                <div class="col-md-2">
                    <label for="filter_user_id_audit_page" class="form-label form-label-sm">المستخدم</label>
                    <select id="filter_user_id_audit_page" name="user_id" class="form-select form-select-sm">
                        <option value="">-- كل المستخدمين --</option>
                        <?php foreach($distinct_users_audit as $user_filter): ?>
                            <option value="<?php echo $user_filter['id']; ?>" <?php echo ($filter_user_id_audit == $user_filter['id']) ? 'selected' : ''; ?>><?php echo esc_html($user_filter['full_name'] . ' (' . $user_filter['username'] . ')'); ?></option>
                        <?php endforeach; ?>
                         <option value="NULL" <?php echo ($filter_user_id_audit === 'NULL') ? 'selected' : ''; ?>>نظام/غير مسجل</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_action_type_audit_page" class="form-label form-label-sm">نوع الإجراء</label>
                    <select id="filter_action_type_audit_page" name="action_type" class="form-select form-select-sm">
                        <option value="">-- كل الإجراءات --</option>
                        <?php foreach($distinct_actions_audit as $action_filter): ?>
                            <option value="<?php echo esc_attr($action_filter['action_type']); ?>" <?php echo ($filter_action_type_audit == $action_filter['action_type']) ? 'selected' : ''; ?>><?php echo esc_html($action_filter['action_type']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-2">
                    <label for="filter_target_table_audit_page" class="form-label form-label-sm">الجدول المستهدف</label>
                    <select id="filter_target_table_audit_page" name="target_table" class="form-select form-select-sm">
                        <option value="">-- كل الجداول --</option>
                        <?php foreach($distinct_tables_audit as $table_filter): ?>
                            <option value="<?php echo esc_attr($table_filter['target_table']); ?>" <?php echo ($filter_target_table_audit == $table_filter['target_table']) ? 'selected' : ''; ?>><?php echo esc_html($table_filter['target_table']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_date_from_audit_page" class="form-label form-label-sm">من تاريخ</label>
                    <input type="date" id="filter_date_from_audit_page" name="date_from" class="form-control form-control-sm" value="<?php echo esc_attr($filter_date_from_audit); ?>">
                </div>
                <div class="col-md-2">
                     <label for="filter_date_to_audit_page" class="form-label form-label-sm">إلى تاريخ</label>
                    <input type="date" id="filter_date_to_audit_page" name="date_to" class="form-control form-control-sm" value="<?php echo esc_attr($filter_date_to_audit); ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill"></i></button>
                </div>
                 <div class="col-md-1">
                     <a href="<?php echo base_url('audit_log/index.php'); ?>" class="btn btn-outline-secondary btn-sm w-100" title="مسح الفلاتر"><i class="bi bi-eraser-fill"></i></a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($audit_logs_list) && (!empty($filter_user_id_audit) || !empty($filter_action_type_audit) || !empty($filter_target_table_audit) || !empty($filter_date_from_audit) || !empty($filter_date_to_audit))): ?>
                <div class="alert alert-warning text-center">لا توجد سجلات تدقيق تطابق معايير الفلترة.</div>
            <?php elseif (empty($audit_logs_list)): ?>
                <div class="alert alert-info text-center">لا توجد سجلات تدقيق متاحة حالياً.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>التاريخ والوقت</th>
                            <th>المستخدم</th>
                            <th>نوع الإجراء</th>
                            <th>الجدول</th>
                            <th>معرف الهدف</th>
                            <th>عنوان IP</th>
                            <th>التفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_audit = ($current_page_audit - 1) * $items_per_page_audit + 1; ?>
                        <?php foreach ($audit_logs_list as $log_item): ?>
                        <tr>
                            <td><?php echo $row_num_audit++; ?></td>
                            <td><?php echo format_date_custom($log_item['timestamp'], 'Y-m-d H:i:s'); ?></td>
                            <td><?php echo esc_html($log_item['user_full_name'] ? ($log_item['user_full_name'] . ' (' . $log_item['username'] . ')') : ($log_item['user_id'] ? 'مستخدم محذوف (ID: ' . $log_item['user_id'] . ')' : 'نظام/غير مسجل')); ?></td>
                            <td><span class="badge bg-info text-dark"><?php echo esc_html($log_item['action_type']); ?></span></td>
                            <td><?php echo esc_html($log_item['target_table'] ?: '-'); ?></td>
                            <td><?php echo esc_html($log_item['target_id'] ?: '-'); ?></td>
                            <td><?php echo esc_html($log_item['ip_address'] ?: '-'); ?></td>
                            <td>
                                <?php if (!empty($log_item['details'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#auditDetailsModal_<?php echo $log_item['id']; ?>">
                                    <i class="bi bi-eye"></i> عرض
                                </button>
                                <div class="modal fade" id="auditDetailsModal_<?php echo $log_item['id']; ?>" tabindex="-1" aria-labelledby="auditDetailsModalLabel_<?php echo $log_item['id']; ?>" aria-hidden="true">
                                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="auditDetailsModalLabel_<?php echo $log_item['id']; ?>">تفاصيل السجل #<?php echo $log_item['id']; ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <div class="modal-body">
                                        <pre style="white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; border-radius: 0.25rem;"><?php 
                                            $details_array = json_decode($log_item['details'], true);
                                            if (json_last_error() === JSON_ERROR_NONE) {
                                                echo esc_html(json_encode($details_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                            } else {
                                                echo esc_html($log_item['details']); // Show raw if not valid JSON
                                            }
                                        ?></pre>
                                      </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إغلاق</button>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <?php else: echo '-'; endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages_audit > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_audit = [];
            if (!empty($filter_user_id_audit)) $pagination_params_audit['user_id'] = $filter_user_id_audit;
            if (!empty($filter_action_type_audit)) $pagination_params_audit['action_type'] = $filter_action_type_audit;
            if (!empty($filter_target_table_audit)) $pagination_params_audit['target_table'] = $filter_target_table_audit;
            if (!empty($filter_date_from_audit)) $pagination_params_audit['date_from'] = $filter_date_from_audit;
            if (!empty($filter_date_to_audit)) $pagination_params_audit['date_to'] = $filter_date_to_audit;
            echo generate_pagination_links($current_page_audit, $total_pages_audit, 'audit_log/index.php', $pagination_params_audit);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</div> <?php /* This was an unclosed div in original, now handled by footer_resources.php */ ?>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>