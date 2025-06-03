<?php
$page_title = "إدارة طرق الدفع";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
// require_role('admin'); // يمكنك إضافة هذا إذا كانت هذه الصفحة للمسؤول فقط
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// متغيرات التصفح
$current_page_pm = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$items_per_page_pm = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : 10;
$offset_pm = ($current_page_pm - 1) * $items_per_page_pm;

// وظيفة البحث
$search_term_pm = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query_part_pm = '';
$params_for_count_pm = [];
$types_for_count_pm = "";
$params_for_data_pm = [];
$types_for_data_pm = "";

if (!empty($search_term_pm)) {
    $search_query_part_pm = " WHERE method_name LIKE ? OR display_name_ar LIKE ? OR zatca_code LIKE ?";
    $search_like_pm = "%" . $search_term_pm . "%";
    $params_for_count_pm = [$search_like_pm, $search_like_pm, $search_like_pm];
    $types_for_count_pm = "sss";
    $params_for_data_pm = $params_for_count_pm;
    $types_for_data_pm = $types_for_count_pm;
}

// الحصول على العدد الإجمالي لطرق الدفع
$total_sql_pm = "SELECT COUNT(*) as total FROM payment_methods" . $search_query_part_pm;
$stmt_total_pm = $mysqli->prepare($total_sql_pm);
$total_payment_methods = 0;
if ($stmt_total_pm) {
    if (!empty($params_for_count_pm)) {
        $stmt_total_pm->bind_param($types_for_count_pm, ...$params_for_count_pm);
    }
    $stmt_total_pm->execute();
    $total_result_pm = $stmt_total_pm->get_result();
    $total_payment_methods = ($total_result_pm && $total_result_pm->num_rows > 0) ? $total_result_pm->fetch_assoc()['total'] : 0;
    $stmt_total_pm->close();
} else {
    error_log("SQL Prepare Error for counting payment methods: " . $mysqli->error);
}
$total_pages_pm = ceil($total_payment_methods / $items_per_page_pm);

// جلب طرق الدفع للصفحة الحالية
$sql_pm = "SELECT * FROM payment_methods" . $search_query_part_pm . " ORDER BY display_name_ar ASC LIMIT ? OFFSET ?";
$current_data_params_pm = $params_for_data_pm;
$current_data_params_pm[] = $items_per_page_pm;
$current_data_params_pm[] = $offset_pm;
$current_data_types_pm = $types_for_data_pm . 'ii';

$payment_methods_list_page = [];
$stmt_pm = $mysqli->prepare($sql_pm);
if ($stmt_pm) {
    if (!empty($current_data_params_pm)) {
        $stmt_pm->bind_param($current_data_types_pm, ...$current_data_params_pm);
    } else {
        $stmt_pm->bind_param('ii', $items_per_page_pm, $offset_pm);
    }
    $stmt_pm->execute();
    $result_pm = $stmt_pm->get_result();
    $payment_methods_list_page = ($result_pm && $result_pm->num_rows > 0) ? $result_pm->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_pm->close();
} else {
    error_log("SQL Prepare Error for fetching payment methods: " . $mysqli->error);
}

$csrf_token = generate_csrf_token();
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-credit-card-2-front-fill"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة طرق الدفع (<?php echo $total_payment_methods; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentMethodModal" onclick="preparePaymentMethodModal('add_payment_method')">
                    <i class="bi bi-plus-circle"></i> إضافة طريقة دفع جديدة
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('payment_methods/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-10 col-sm-8">
                     <label for="search_payment_methods_page" class="form-label form-label-sm visually-hidden">بحث</label>
                    <input type="text" id="search_payment_methods_page" name="search" class="form-control form-control-sm" placeholder="ابحث بالمعرف، الاسم المعروض، أو رمز ZATCA..." value="<?php echo esc_attr($search_term_pm); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> بحث</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($payment_methods_list_page) && !empty($search_term_pm)): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج بحث تطابق "<?php echo esc_html($search_term_pm); ?>".</div>
            <?php elseif (empty($payment_methods_list_page)): ?>
                <div class="alert alert-info text-center">لا توجد طرق دفع مسجلة حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#paymentMethodModal" onclick="preparePaymentMethodModal('add_payment_method')">إضافة طريقة دفع جديدة</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>المعرف (<code>method_name</code>)</th>
                            <th>الاسم المعروض (بالعربية)</th>
                            <th>رمز ZATCA</th>
                            <th>الحالة</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num_pm = ($current_page_pm - 1) * $items_per_page_pm + 1; ?>
                        <?php foreach ($payment_methods_list_page as $pm_item): ?>
                        <tr>
                            <td><?php echo $row_num_pm++; ?></td>
                            <td><code><?php echo esc_html($pm_item['method_name']); ?></code></td>
                            <td><?php echo esc_html($pm_item['display_name_ar']); ?></td>
                            <td><?php echo esc_html($pm_item['zatca_code'] ?: '-'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $pm_item['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $pm_item['is_active'] ? 'فعالة' : 'غير فعالة'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="preparePaymentMethodModal('edit_payment_method', <?php echo htmlspecialchars(json_encode($pm_item), ENT_QUOTES, 'UTF-8'); ?>)"
                                        data-bs-toggle="modal" data-bs-target="#paymentMethodModal"
                                        title="تعديل طريقة الدفع">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-payment-method-btn"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                        data-id="<?php echo $pm_item['id']; ?>"
                                        data-name="<?php echo esc_attr($pm_item['display_name_ar']); ?>"
                                        data-delete-url="<?php echo base_url('payment_methods/actions.php?action=delete_payment_method&id=' . $pm_item['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        title="حذف طريقة الدفع">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages_pm > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params_pm = [];
            if (!empty($search_term_pm)) $pagination_params_pm['search'] = $search_term_pm;
            echo generate_pagination_links($current_page_pm, $total_pages_pm, 'payment_methods/index.php', $pagination_params_pm);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// تضمين نافذة إضافة/تعديل طريقة الدفع
require_once __DIR__ . '/../includes/modals/payment_method_modal.php';
// تضمين نافذة تأكيد الحذف
require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <script>
function preparePaymentMethodModal(action, pmData = null) {
    const pmModal = document.getElementById('paymentMethodModal');
    const modalTitle = pmModal.querySelector('#paymentMethodModalLabel_pmethods');
    const pmForm = pmModal.querySelector('#paymentMethodFormModal');
    const pmIdInput = pmModal.querySelector('#payment_method_id_modal_pmethods');
    const actionInput = pmModal.querySelector('#payment_method_form_action_modal_pmethods');
    const submitButton = pmModal.querySelector('#paymentMethodSubmitButtonTextModalPmethods');
    const isActiveCheckbox = pmModal.querySelector('#is_active_modal_pmethods');


    pmForm.reset();
    pmIdInput.value = '';
    actionInput.value = action;
    isActiveCheckbox.checked = true; // Default to active for new

    if (action === 'add_payment_method') {
        modalTitle.textContent = 'إضافة طريقة دفع جديدة';
        submitButton.textContent = 'إضافة الطريقة';
    } else if (action === 'edit_payment_method' && pmData) {
        modalTitle.textContent = 'تعديل طريقة الدفع: ' + pmData.display_name_ar;
        submitButton.textContent = 'حفظ التعديلات';
        pmIdInput.value = pmData.id;
        
        if(document.getElementById('method_name_modal_pmethods')) document.getElementById('method_name_modal_pmethods').value = pmData.method_name || '';
        if(document.getElementById('display_name_ar_modal_pmethods')) document.getElementById('display_name_ar_modal_pmethods').value = pmData.display_name_ar || '';
        if(document.getElementById('zatca_code_modal_pmethods')) document.getElementById('zatca_code_modal_pmethods').value = pmData.zatca_code || '';
        isActiveCheckbox.checked = (pmData.is_active == 1);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var confirmDeleteModalPMethod = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModalPMethod) {
        confirmDeleteModalPMethod.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button.classList.contains('delete-payment-method-btn')) {
                var itemName = button.getAttribute('data-name');
                var deleteUrl = button.getAttribute('data-delete-url');
                var modalBodyText = confirmDeleteModalPMethod.querySelector('.modal-body-text');
                if(modalBodyText) modalBodyText.textContent = 'هل أنت متأكد أنك تريد حذف طريقة الدفع "' + itemName + '"؟';
                
                var additionalInfo = confirmDeleteModalPMethod.querySelector('#additionalDeleteInfo');
                if(additionalInfo) additionalInfo.textContent = 'ملاحظة: لا يمكن حذف طريقة الدفع إذا كانت مستخدمة في أي عمليات دفع. يمكنك تعطيلها بدلاً من ذلك.';

                var confirmDeleteButton = confirmDeleteModalPMethod.querySelector('#confirmDeleteButton');
                if(confirmDeleteButton) {
                    var newConfirmDeleteButtonPMethod = confirmDeleteButton.cloneNode(true);
                    confirmDeleteButton.parentNode.replaceChild(newConfirmDeleteButtonPMethod, confirmDeleteButton);
                    
                    newConfirmDeleteButtonPMethod.setAttribute('data-delete-url', deleteUrl);
                    newConfirmDeleteButtonPMethod.removeAttribute('href');
                    
                    newConfirmDeleteButtonPMethod.addEventListener('click', function(e) {
                        e.preventDefault();
                        const urlToDelete = this.getAttribute('data-delete-url');
                        if(urlToDelete){
                           window.location.href = urlToDelete; // For GET based delete
                        }
                    });
                }
            }
        });
    }

    const pmFormElement = document.getElementById('paymentMethodFormModal');
    if(pmFormElement) {
        pmFormElement.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(pmFormElement);
            const actionUrl = '<?php echo base_url('payment_methods/actions.php'); ?>';

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var pmModalInstance = bootstrap.Modal.getInstance(document.getElementById('paymentMethodModal'));
                    if(pmModalInstance) pmModalInstance.hide();
                    window.location.reload(); 
                } else {
                    alert('خطأ: ' + data.message); 
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ غير متوقع.');
            });
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>