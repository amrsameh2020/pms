<?php
$page_title = "إدارة أصحاب العقارات";
// تضمين db_connect أولاً لأنه يقوم بإعداد الاتصال بقاعدة البيانات وتحميل الإعدادات الهامة
require_once __DIR__ . '/../db_connect.php'; // هذا الملف يتضمن config.php ويحمّل الإعدادات
require_once __DIR__ . '/../includes/session_manager.php';
require_login(); // يتطلب تسجيل الدخول للوصول لهذه الصفحة
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php'; // يبدأ الجلسة إذا لم تكن قد بدأت
require_once __DIR__ . '/../includes/navigation.php';

// متغيرات التصفح (Pagination)
// التأكد من أن رقم الصفحة هو عدد صحيح موجب
$current_page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
// استخدام الثابت المعرف لعدد العناصر في كل صفحة، مع قيمة افتراضية إذا لم يتم تعريفه
$items_per_page = defined('ITEMS_PER_PAGE_INT') ? ITEMS_PER_PAGE_INT : (defined('ITEMS_PER_PAGE') && filter_var(ITEMS_PER_PAGE, FILTER_VALIDATE_INT) ? (int)ITEMS_PER_PAGE : 10);
$offset = ($current_page - 1) * $items_per_page;

// وظيفة البحث
$search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query_part = '';
$params_for_count = []; // معلمات لـ COUNT query
$params_for_data = [];  // معلمات لـ data query
$types_for_count = "";  // أنواع المعلمات لـ COUNT
$types_for_data = "";   // أنواع المعلمات لـ data

if (!empty($search_term)) {
    $search_query_part = " WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR national_id_iqama LIKE ?";
    $search_like = "%" . $search_term . "%";
    // معلمات استعلام العد
    $params_for_count = [$search_like, $search_like, $search_like, $search_like];
    $types_for_count = "ssss"; // 4 سلاسل نصية
    // معلمات استعلام البيانات (سيتم إضافة LIMIT و OFFSET لاحقًا)
    $params_for_data = $params_for_count;
    $types_for_data = $types_for_count;
}

// الحصول على العدد الإجمالي لأصحاب العقارات للتصفح
$total_sql = "SELECT COUNT(*) as total FROM owners" . $search_query_part;
$stmt_total = $mysqli->prepare($total_sql);
if (!empty($search_term)) {
    $stmt_total->bind_param($types_for_count, ...$params_for_count);
}
$stmt_total->execute();
$total_result = $stmt_total->get_result();
$total_owners = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_owners / $items_per_page);
$stmt_total->close();

// جلب أصحاب العقارات للصفحة الحالية
$sql = "SELECT * FROM owners" . $search_query_part . " ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($sql);

// إضافة LIMIT و OFFSET إلى المعلمات والأنواع لاستعلام البيانات
$current_data_params = $params_for_data; // ابدأ بمعلمات البحث
$current_data_params[] = $items_per_page;
$current_data_params[] = $offset;
$current_data_types = $types_for_data . 'ii'; // إضافة 'ii' لأنواع LIMIT و OFFSET الصحيحة

// ربط المعلمات فقط إذا كانت المصفوفة ليست فارغة (لتجنب خطأ bind_param مع 0 معلمات)
if (!empty($current_data_params)) {
    $stmt->bind_param($current_data_types, ...$current_data_params);
} else {
    // حالة عدم وجود بحث (فقط LIMIT و OFFSET)
    $stmt->bind_param('ii', $items_per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$owners = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf_token = generate_csrf_token(); // لنماذج النوافذ المنبثقة
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-people-fill"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة أصحاب العقارات (<?php echo $total_owners; ?>)</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#ownerModal" data-action="add">
                    <i class="bi bi-plus-circle"></i> إضافة مالك جديد
                </button>
            </div>
            <hr class="my-2">
            <form method="GET" action="<?php echo base_url('owners/index.php'); ?>" class="row gx-2 gy-2 align-items-center">
                <div class="col-md-10 col-sm-8">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="ابحث بالاسم, البريد الإلكتروني, رقم الهاتف, رقم الهوية..." value="<?php echo esc_attr($search_term); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> بحث</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($owners) && !empty($search_term)): ?>
                <div class="alert alert-warning text-center">لا توجد نتائج بحث تطابق "<?php echo esc_html($search_term); ?>".</div>
            <?php elseif (empty($owners) && empty($search_term)): ?>
                <div class="alert alert-info text-center">لا يوجد أصحاب عقارات مسجلين حالياً. يمكنك <a href="#" data-bs-toggle="modal" data-bs-target="#ownerModal" data-action="add">إضافة مالك جديد</a>.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">الاسم</th>
                            <th scope="col">البريد الإلكتروني</th>
                            <th scope="col">رقم الهاتف</th>
                            <th scope="col">رقم الهوية/الإقامة</th>
                            <th scope="col">العنوان</th>
                            <th scope="col" class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = ($current_page - 1) * $items_per_page + 1; ?>
                        <?php foreach ($owners as $owner): ?>
                        <tr>
                            <td><?php echo $row_num++; ?></td>
                            <td><?php echo esc_html($owner['name']); ?></td>
                            <td><?php echo esc_html($owner['email'] ?: '-'); ?></td>
                            <td><?php echo esc_html($owner['phone'] ?: '-'); ?></td>
                            <td><?php echo esc_html($owner['national_id_iqama'] ?: '-'); ?></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr($owner['address']); ?>"><?php echo esc_html($owner['address'] ?: '-'); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-warning edit-owner-btn"
                                        data-bs-toggle="modal" data-bs-target="#ownerModal"
                                        data-id="<?php echo $owner['id']; ?>"
                                        data-name="<?php echo esc_attr($owner['name']); ?>"
                                        data-email="<?php echo esc_attr($owner['email']); ?>"
                                        data-phone="<?php echo esc_attr($owner['phone']); ?>"
                                        data-national_id="<?php echo esc_attr($owner['national_id_iqama']); ?>"
                                        data-address="<?php echo esc_attr($owner['address']); ?>"
                                        title="تعديل بيانات المالك">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-owner-btn"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                        data-id="<?php echo $owner['id']; ?>"
                                        data-name="<?php echo esc_attr($owner['name']); ?>"
                                        data-delete-url="<?php echo base_url('owners/owner_actions.php?action=delete&id=' . $owner['id'] . '&csrf_token=' . $csrf_token); ?>"
                                        title="حذف المالك">
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
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-light">
            <?php
            $pagination_params = [];
            if (!empty($search_term)) {
                $pagination_params['search'] = $search_term;
            }
            // المسار الأساسي لروابط التصفح يجب أن يكون نسبيًا من جذر التطبيق
            echo generate_pagination_links($current_page, $total_pages, 'owners/index.php', $pagination_params);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// تضمين نافذة إضافة/تعديل المالك
require_once __DIR__ . '/../includes/modals/owner_modal.php';
// تضمين نافذة تأكيد الحذف
require_once __DIR__ . '/../includes/modals/confirm_delete_modal.php';
?>

</div> <script>
document.addEventListener('DOMContentLoaded', function () {
    // التعامل مع نافذة المالك للإضافة والتعديل
    var ownerModal = document.getElementById('ownerModal');
    if (ownerModal) {
        ownerModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // الزر الذي قام بتشغيل النافذة
            var action = button.getAttribute('data-action'); // 'add' أو يكون ضمنيًا 'edit'
            var modalTitle = ownerModal.querySelector('.modal-title');
            var ownerForm = ownerModal.querySelector('#ownerForm');
            var ownerIdInput = ownerModal.querySelector('#owner_id');
            var formActionInput = ownerModal.querySelector('#form_action'); // حقل الإجراء المخفي
            var submitButton = ownerModal.querySelector('button[type="submit"]');

            ownerForm.reset(); // إعادة تعيين حقول النموذج
            ownerIdInput.value = ''; // مسح معرّف المالك

            var form_url = '<?php echo base_url('owners/owner_actions.php'); ?>'; // رابط معالجة النموذج

            if (action === 'add') {
                modalTitle.textContent = 'إضافة مالك جديد';
                formActionInput.value = 'add_owner';
                submitButton.textContent = 'إضافة المالك';
                ownerForm.action = form_url;
            } else { // إجراء التعديل
                modalTitle.textContent = 'تعديل بيانات المالك';
                formActionInput.value = 'edit_owner';
                submitButton.textContent = 'حفظ التعديلات';
                ownerForm.action = form_url;

                // ملء حقول النموذج ببيانات المالك للتعديل
                ownerIdInput.value = button.getAttribute('data-id');
                ownerModal.querySelector('#owner_name').value = button.getAttribute('data-name');
                ownerModal.querySelector('#owner_email').value = button.getAttribute('data-email');
                ownerModal.querySelector('#owner_phone').value = button.getAttribute('data-phone');
                ownerModal.querySelector('#owner_national_id').value = button.getAttribute('data-national_id');
                ownerModal.querySelector('#owner_address').value = button.getAttribute('data-address');
            }
        });
    }

    // التعامل مع نافذة تأكيد الحذف
    var confirmDeleteModal = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModal) {
        confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // الزر الذي قام بتشغيل النافذة
            var itemName = button.getAttribute('data-name'); // اسم العنصر المراد حذفه
            var deleteUrl = button.getAttribute('data-delete-url'); // رابط الحذف من الزر

            var modalBodyText = confirmDeleteModal.querySelector('.modal-body-text');
            if (modalBodyText) {
                 modalBodyText.textContent = 'هل أنت متأكد أنك تريد حذف "' + itemName + '"؟ لا يمكن التراجع عن هذا الإجراء.';
            }

            var confirmDeleteButton = confirmDeleteModal.querySelector('#confirmDeleteButton');
            if (confirmDeleteButton) {
                confirmDeleteButton.setAttribute('href', deleteUrl); // تعيين رابط الحذف لزر التأكيد
            }
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer_resources.php'; // لتضمين أي سكربتات في نهاية الصفحة ?>