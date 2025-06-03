<?php
// dashboard.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = "[FATAL DASHBOARD] {$error['message']} in {$error['file']} on line {$error['line']}";
        error_log(date('[Y-m-d H:i:s] ') . $msg . "\n", 3, __DIR__ . '/error.log');
    }
});

$page_title = "لوحة القيادة";
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/session_manager.php';
require_login();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header_resources.php';
require_once __DIR__ . '/includes/navigation.php';

// --- إعدادات الفترة الزمنية للتقارير ---
$selected_period = $_GET['period'] ?? 'current_month';
$date_filter_clause_invoices = "";
$date_filter_clause_payments = "";
$period_params = [];
$period_types = "";
$period_display_name = "";

switch ($selected_period) {
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('first day of last month'));
        $end_date = date('Y-m-t', strtotime('last day of last month'));
        $period_display_name = "الشهر الماضي (" . format_date_custom($start_date, 'M Y') . ")";
        break;
    case 'current_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        $period_display_name = "السنة الحالية (" . date('Y') . ")";
        break;
    case 'all_time':
        $period_display_name = "كل الأوقات";
        break;
    case 'current_month':
    default:
        $selected_period = 'current_month';
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $period_display_name = "الشهر الحالي (" . format_date_custom($start_date, 'M Y') . ")";
        break;
}

if ($selected_period !== 'all_time') {
    $date_filter_clause_invoices = " AND i.invoice_date BETWEEN ? AND ? ";
    $date_filter_clause_payments = " AND p.payment_date BETWEEN ? AND ? ";
    $period_params = [$start_date, $end_date];
    $period_types = "ss";
}

// --- جلب البيانات للإحصائيات ---

// 1. عدد الملاك
$result_owners = $mysqli->query("SELECT COUNT(*) as total_owners FROM owners");
$total_owners = ($result_owners && $result_owners->num_rows > 0) ? $result_owners->fetch_assoc()['total_owners'] : 0;
if ($result_owners) $result_owners->free();

// 2. عدد العقارات
$result_properties = $mysqli->query("SELECT COUNT(*) as total_properties FROM properties");
$total_properties = ($result_properties && $result_properties->num_rows > 0) ? $result_properties->fetch_assoc()['total_properties'] : 0;
if ($result_properties) $result_properties->free();

// 3. عدد الوحدات
$result_units_total = $mysqli->query("SELECT COUNT(*) as total_units FROM units");
$total_units = ($result_units_total && $result_units_total->num_rows > 0) ? $result_units_total->fetch_assoc()['total_units'] : 0;
if ($result_units_total) $result_units_total->free();

$result_units_occupied = $mysqli->query("SELECT COUNT(*) as occupied_units FROM units WHERE status = 'Occupied'");
$occupied_units = ($result_units_occupied && $result_units_occupied->num_rows > 0) ? $result_units_occupied->fetch_assoc()['occupied_units'] : 0;
if ($result_units_occupied) $result_units_occupied->free();
$vacant_units_count = $total_units - $occupied_units;

// 4. عدد المستأجرين النشطين
$result_active_tenants = $mysqli->query("SELECT COUNT(DISTINCT t.id) as total_active_tenants FROM tenants t JOIN leases l ON t.id = l.tenant_id WHERE l.status = 'Active'");
$total_active_tenants = ($result_active_tenants && $result_active_tenants->num_rows > 0) ? $result_active_tenants->fetch_assoc()['total_active_tenants'] : 0;
if ($result_active_tenants) $result_active_tenants->free();

// 5. عدد عقود الإيجار النشطة
$result_active_leases = $mysqli->query("SELECT COUNT(*) as total_active_leases FROM leases WHERE status = 'Active'");
$total_active_leases = ($result_active_leases && $result_active_leases->num_rows > 0) ? $result_active_leases->fetch_assoc()['total_active_leases'] : 0;
if ($result_active_leases) $result_active_leases->free();

// 6. إجمالي الدخل المستلم (للفترة المحددة، بناءً على تاريخ الدفعة)
$sql_total_income = "SELECT SUM(p.amount_paid) as total_income 
                     FROM payments p 
                     WHERE 1=1";
if ($selected_period !== 'all_time') {
    $sql_total_income .= $date_filter_clause_payments;
}
$stmt_total_income = $mysqli->prepare($sql_total_income);
$total_income = 0;
if($stmt_total_income){
    if ($selected_period !== 'all_time') {
        $stmt_total_income->bind_param($period_types, ...$period_params);
    }
    $stmt_total_income->execute();
    $result_total_income = $stmt_total_income->get_result();
    $total_income_row = $result_total_income->fetch_assoc();
    $total_income = $total_income_row ? (float)$total_income_row['total_income'] : 0;
    $stmt_total_income->close();
} else { error_log("Dashboard: Failed to prepare total_income query: " . $mysqli->error); }


// 7. إجمالي المبالغ المستحقة للفواتير
$sql_total_due = "SELECT SUM(i.total_amount - i.paid_amount) as total_due
                  FROM invoices i
                  WHERE i.status IN ('Unpaid', 'Partially Paid', 'Overdue')";
if ($selected_period !== 'all_time') {
    $sql_total_due .= $date_filter_clause_invoices;
}
$stmt_total_due = $mysqli->prepare($sql_total_due);
$total_due_amount = 0;
if($stmt_total_due){
    if ($selected_period !== 'all_time') {
        $stmt_total_due->bind_param($period_types, ...$period_params);
    }
    $stmt_total_due->execute();
    $result_total_due = $stmt_total_due->get_result();
    $total_due_row = $result_total_due->fetch_assoc();
    $total_due_amount = $total_due_row ? (float)$total_due_row['total_due'] : 0;
    $stmt_total_due->close();
} else { error_log("Dashboard: Failed to prepare total_due_amount query: " . $mysqli->error); }


// 8. الوحدات الشاغرة
$vacant_units_sql = "SELECT u.id as unit_id_link, u.unit_number, u.base_rent_price, 
                            ut.display_name_ar as unit_type_name, 
                            p.name as property_name, p.property_code, p.address as property_address, p.id as property_id_link
                     FROM units u
                     JOIN properties p ON u.property_id = p.id
                     LEFT JOIN unit_types ut ON u.unit_type_id = ut.id
                     WHERE u.status = 'Vacant'
                     ORDER BY p.name, u.unit_number LIMIT 5";
$result_vacant_units = $mysqli->query($vacant_units_sql);
$vacant_units_list = [];
if ($result_vacant_units && $result_vacant_units->num_rows > 0) {
    $vacant_units_list = $result_vacant_units->fetch_all(MYSQLI_ASSOC);
    $result_vacant_units->free();
}

// بيانات للرسوم البيانية
$sql_chart_data = "SELECT 
                        COALESCE(SUM(i.total_amount), 0) as total_billed_period,
                        COALESCE(SUM(i.paid_amount), 0) as total_paid_period
                   FROM invoices i WHERE 1=1 ";
$params_chart_data = [];
$types_chart_data = "";
if ($selected_period !== 'all_time') {
    $sql_chart_data .= $date_filter_clause_invoices;
    $params_chart_data = $period_params;
    $types_chart_data = $period_types;
}

$stmt_chart_data = $mysqli->prepare($sql_chart_data);
$invoice_chart_data = ['total_billed_period' => 0, 'total_paid_period' => 0, 'total_pending_period' => 0];
if($stmt_chart_data){
    if(!empty($params_chart_data)) $stmt_chart_data->bind_param($types_chart_data, ...$params_chart_data);
    $stmt_chart_data->execute();
    $result_chart_data = $stmt_chart_data->get_result();
    if($result_chart_data && $result_chart_data->num_rows > 0){
        $fetched_chart_data = $result_chart_data->fetch_assoc();
        $invoice_chart_data['total_billed_period'] = (float)($fetched_chart_data['total_billed_period'] ?? 0);
        $invoice_chart_data['total_paid_period'] = (float)($fetched_chart_data['total_paid_period'] ?? 0);
    }
    $stmt_chart_data->close();
} else { error_log("Dashboard: Failed to prepare invoice_chart_data query: " . $mysqli->error); }

$invoice_chart_data['total_pending_period'] = max(0, $invoice_chart_data['total_billed_period'] - $invoice_chart_data['total_paid_period']);
$paid_percentage_chart = ($invoice_chart_data['total_billed_period'] > 0) ? round(($invoice_chart_data['total_paid_period'] / $invoice_chart_data['total_billed_period']) * 100) : 0;
$pending_percentage_chart = 100 - $paid_percentage_chart;

?>
<style>
    .stat-card { border-right: 5px solid var(--bs-primary); transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15)!important; }
    .stat-card .card-body { padding: 1.5rem; }
    .stat-card i { font-size: 2.8rem; opacity: 0.6; }
    .stat-card h5 { font-size: 0.95rem; font-weight: 600; color: #6c757d; margin-bottom: 0.25rem; }
    .stat-card .display-6 { font-weight: 700; color: var(--bs-primary-text-emphasis); }
    .chart-placeholder { height: 200px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; border-radius: 0.25rem; text-align: center; color: #6c757d; border: 1px dashed #dee2e6;}
    .progress-bar-custom { height: 20px; font-size: 0.85rem; }
    .dashboard-filter .form-select, .dashboard-filter .btn { font-size: 0.875rem; }
</style>

<div class="container-fluid">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1><i class="bi bi-grid-1x2-fill"></i> <?php echo esc_html($page_title); ?></h1>
            <form method="GET" action="<?php echo base_url('dashboard.php'); ?>" class="d-flex dashboard-filter">
                <select name="period" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                    <option value="current_month" <?php if ($selected_period == 'current_month') echo 'selected'; ?>>هذا الشهر</option>
                    <option value="last_month" <?php if ($selected_period == 'last_month') echo 'selected'; ?>>الشهر الماضي</option>
                    <option value="current_year" <?php if ($selected_period == 'current_year') echo 'selected'; ?>>هذه السنة</option>
                    <option value="all_time" <?php if ($selected_period == 'all_time') echo 'selected'; ?>>كل الأوقات</option>
                </select>
                <noscript><button type="submit" class="btn btn-sm btn-outline-primary">تطبيق</button></noscript>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <a href="<?php echo base_url('properties/index.php'); ?>" class="text-decoration-none">
            <div class="card shadow-sm stat-card border-primary h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h5>إجمالي العقارات</h5><p class="display-6 mb-0"><?php echo $total_properties; ?></p></div>
                    <i class="bi bi-buildings text-primary"></i>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="<?php echo base_url('units/index.php'); ?>" class="text-decoration-none">
            <div class="card shadow-sm stat-card border-success h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h5>إجمالي الوحدات</h5><p class="display-6 mb-0"><?php echo $total_units; ?></p>
                        <small class="text-muted"><?php echo $occupied_units; ?> مشغولة / <?php echo $vacant_units_count; ?> شاغرة</small>
                    </div>
                    <i class="bi bi-door-open text-success"></i>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="<?php echo base_url('leases/index.php?status=Active'); ?>" class="text-decoration-none">
            <div class="card shadow-sm stat-card border-info h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h5>العقود النشطة</h5><p class="display-6 mb-0"><?php echo $total_active_leases; ?></p></div>
                    <i class="bi bi-file-earmark-text text-info"></i>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="<?php echo base_url('tenants/index.php'); ?>" class="text-decoration-none">
            <div class="card shadow-sm stat-card border-warning h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h5>المستأجرون النشطون</h5><p class="display-6 mb-0"><?php echo $total_active_tenants; ?></p></div>
                    <i class="bi bi-person-check text-warning"></i>
                </div>
            </div>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4 col-md-6">
             <a href="<?php echo base_url('payments/index.php?status=Completed&period='.$selected_period); ?>" class="text-decoration-none">
            <div class="card shadow-sm stat-card border-danger h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h5>الدخل المستلم (<?php echo esc_html($period_display_name); ?>)</h5>
                        <p class="display-6 mb-0"><?php echo number_format($total_income, 2); ?> <small>ريال</small></p>
                    </div>
                    <i class="bi bi-cash-coin text-danger"></i>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-4 col-md-6">
            <a href="<?php echo base_url('invoices/index.php?status_filter[]=Unpaid&status_filter[]=Partially Paid&status_filter[]=Overdue&period='.$selected_period); ?>" class="text-decoration-none">
            <div class="card shadow-sm stat-card border-secondary h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h5>المبالغ المستحقة (<?php echo esc_html($period_display_name); ?>)</h5>
                        <p class="display-6 mb-0"><?php echo number_format($total_due_amount, 2); ?> <small>ريال</small></p>
                    </div>
                    <i class="bi bi-hourglass-split text-secondary"></i>
                </div>
            </div>
            </a>
        </div>
         <div class="col-xl-4 col-md-6">
            <a href="<?php echo base_url('owners/index.php'); ?>" class="text-decoration-none">
            <div class="card shadow-sm stat-card border-dark h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><h5>إجمالي الملاك</h5><p class="display-6 mb-0"><?php echo $total_owners; ?></p></div>
                    <i class="bi bi-person-rolodex text-dark"></i>
                </div>
            </div>
            </a>
        </div>
    </div>


    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="bi bi-bar-chart-line-fill me-2"></i>نظرة عامة على الفواتير (<?php echo esc_html($period_display_name); ?>)</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1">إجمالي الفواتير الصادرة: <strong class="text-primary"><?php echo number_format($invoice_chart_data['total_billed_period'], 2); ?> ريال</strong></p>
                    <div class="progress mb-2" style="height: 25px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $paid_percentage_chart; ?>%" aria-valuenow="<?php echo $paid_percentage_chart; ?>" aria-valuemin="0" aria-valuemax="100" title="المدفوع: <?php echo $paid_percentage_chart; ?>%">
                           <?php if($paid_percentage_chart > 15) echo 'مدفوع: ' . number_format($invoice_chart_data['total_paid_period'], 0) . ' (' . $paid_percentage_chart . '%)'; ?>
                        </div>
                        <div class="progress-bar bg-warning text-dark progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $pending_percentage_chart; ?>%" aria-valuenow="<?php echo $pending_percentage_chart; ?>" aria-valuemin="0" aria-valuemax="100" title="المتبقي: <?php echo $pending_percentage_chart; ?>%">
                           <?php if($pending_percentage_chart > 15) echo 'متبقي: ' . number_format($invoice_chart_data['total_pending_period'], 0) . ' (' . $pending_percentage_chart . '%)'; ?>
                        </div>
                    </div>
                     <div class="d-flex justify-content-between small mb-3">
                        <span>المدفوع: <strong class="text-success"><?php echo number_format($invoice_chart_data['total_paid_period'], 2); ?> ريال</strong></span>
                        <span>المتبقي: <strong class="text-danger"><?php echo number_format($invoice_chart_data['total_pending_period'], 2); ?> ريال</strong></span>
                    </div>
                    <div class="chart-placeholder mt-3" id="monthlyIncomeChartPlaceholder">
                        <canvas id="incomeExpenseChart"></canvas>
                        <small class="d-none" id="noChartDataMessage">لا توجد بيانات كافية لعرض الرسم البياني لهذه الفترة.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="bi bi-house-door me-2"></i>أحدث الوحدات الشاغرة (إجمالي: <?php echo $vacant_units_count; ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($vacant_units_list)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>العقار</th>
                                    <th>الوحدة</th>
                                    <th>النوع</th>
                                    <th>الإيجار المقترح</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vacant_units_list as $unit): ?>
                                <tr>
                                    <td><a href="<?php echo base_url('properties/index.php?search=' . rawurlencode($unit['property_code'] ?? '')); ?>" title="<?php echo esc_attr($unit['property_address']); ?>"><?php echo esc_html($unit['property_name']); ?></a></td>
                                    <td><a href="<?php echo base_url('units/index.php?property_id=' . $unit['property_id_link'] . '&search_unit=' . rawurlencode($unit['unit_number'] ?? '')); ?>"><?php echo esc_html($unit['unit_number']); ?></a></td>
                                    <td><?php echo esc_html($unit['unit_type_name'] ?: '-'); ?></td>
                                    <td><?php echo $unit['base_rent_price'] ? number_format($unit['base_rent_price'], 2) . ' <small>ريال</small>' : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($vacant_units_count > 5): ?>
                        <div class="card-footer text-center py-2">
                            <a href="<?php echo base_url('units/index.php?status=Vacant'); ?>">عرض جميع الوحدات الشاغرة (<?php echo $vacant_units_count; ?>)</a>
                        </div>
                    <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-success m-3 text-center">لا توجد وحدات شاغرة حالياً.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
<script src="<?php echo base_url('assets/js/chart.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const incomeDataForChart = <?php
        $income_chart_data_values_js = [];
        $income_chart_labels_js = [];
        for ($i = 5; $i >= 0; $i--) {
            $month_loop_start_js = date('Y-m-01', strtotime("-$i month"));
            $month_loop_end_js = date('Y-m-t', strtotime("-$i month"));
            $month_loop_label_js = format_date_custom($month_loop_start_js, 'M Y');
            $income_chart_labels_js[] = $month_loop_label_js;
            
            $sql_income_m_loop_js = "SELECT SUM(p.amount_paid) as total_income_month 
                                     FROM payments p 
                                     WHERE p.payment_date BETWEEN ? AND ?"; // تم إزالة شرط p.status
            $stmt_income_m_loop_js = $mysqli->prepare($sql_income_m_loop_js);
            if ($stmt_income_m_loop_js) {
                $stmt_income_m_loop_js->bind_param("ss", $month_loop_start_js, $month_loop_end_js);
                $stmt_income_m_loop_js->execute();
                $res_income_m_loop_js = $stmt_income_m_loop_js->get_result()->fetch_assoc();
                $income_chart_data_values_js[] = (float)($res_income_m_loop_js['total_income_month'] ?? 0);
                $stmt_income_m_loop_js->close();
            } else {
                 $income_chart_data_values_js[] = 0;
                 error_log("Dashboard Chart Data Error (JS block): Failed to prepare income query for month " . $month_loop_label_js . ": " . $mysqli->error);
            }
        }
        echo json_encode($income_chart_data_values_js);
    ?>;
    const monthLabelsForChart = <?php echo json_encode($income_chart_labels_js); ?>;

    var ctx = document.getElementById('incomeExpenseChart')?.getContext('2d');
    var noChartDataMsg = document.getElementById('noChartDataMessage');
    var chartCanvas = document.getElementById('incomeExpenseChart');

    if (ctx && incomeDataForChart.some(val => val > 0)) {
        if(noChartDataMsg) noChartDataMsg.classList.add('d-none');
        if(chartCanvas) chartCanvas.classList.remove('d-none');
        if(document.getElementById('monthlyIncomeChartPlaceholder')) document.getElementById('monthlyIncomeChartPlaceholder').classList.remove('d-flex');


        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthLabelsForChart,
                datasets: [{
                    label: 'الدخل المستلم شهريًا',
                    data: incomeDataForChart,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1,
                    borderRadius: 5,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return value.toLocaleString('ar-SA', { style: 'currency', currency: 'SAR', minimumFractionDigits: 0, maximumFractionDigits: 0 }); } } }, x: { grid: { display: false } } },
                plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: function(context) { let label = context.dataset.label || ''; if (label) label += ': '; if (context.parsed.y !== null) { label += context.parsed.y.toLocaleString('ar-SA', { style: 'currency', currency: 'SAR' }); } return label; } } } }
            }
        });
    } else {
        if(noChartDataMsg) noChartDataMsg.classList.remove('d-none');
        if(chartCanvas) chartCanvas.classList.add('d-none');
        if(document.getElementById('monthlyIncomeChartPlaceholder')) document.getElementById('monthlyIncomeChartPlaceholder').classList.add('d-flex');
    }
});
</script>
<?php require_once __DIR__ . '/includes/footer_resources.php'; ?>