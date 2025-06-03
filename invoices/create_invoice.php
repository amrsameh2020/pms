<?php
$page_title = "إنشاء فاتورة جديدة";
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header_resources.php';
require_once __DIR__ . '/../includes/navigation.php';

// لا يوجد منطق خاص هنا سوى عرض المعلومات والتوجيه
?>

<div class="container-fluid">
    <div class="content-header">
        <h1><i class="bi bi-receipt-cutoff"></i> <?php echo esc_html($page_title); ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title">بدء عملية إنشاء فاتورة</h5>
            <p class="card-text">
                لإنشاء فاتورة جديدة، يرجى الانتقال إلى صفحة <a href="<?php echo base_url('invoices/index.php'); ?>">قائمة الفواتير</a> والضغط على زر "إنشاء فاتورة جديدة".
            </p>
            <p class="card-text">
                سيتم فتح نافذة منبثقة تتيح لك إدخال جميع تفاصيل الفاتورة المطلوبة، بما في ذلك:
            </p>
            <ul>
                <li>المعلومات الأساسية للفاتورة (الرقم، التاريخ، العميل، إلخ).</li>
                <li>بنود الفاتورة التفصيلية (الكميات، الأسعار، الضرائب).</li>
                <li>أي خصومات أو مبالغ إضافية.</li>
            </ul>

            <div class="mt-4">
                <a href="<?php echo base_url('invoices/index.php?action=open_add_modal'); ?>" class="btn btn-primary btn-lg">
                    <i class="bi bi-card-list"></i> الانتقال إلى قائمة الفواتير وبدء فاتورة جديدة
                </a>
            </div>
        </div>
    </div>
</div>

</div> <?php require_once __DIR__ . '/../includes/footer_resources.php'; ?>