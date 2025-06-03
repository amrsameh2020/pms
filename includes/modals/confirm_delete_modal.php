<?php
// includes/modals/confirm_delete_modal.php
?>
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmDeleteModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i> تأكيد عملية الحذف</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <p class="modal-body-text lead">هل أنت متأكد أنك تريد حذف هذا العنصر؟</p>
                <p class="text-muted small">لا يمكن التراجع عن هذا الإجراء بعد التأكيد.</p>
                <div id="additionalDeleteInfo" class="mt-2 text-muted small"></div> </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> إلغاء
                </button>
                <a href="#" id="confirmDeleteButton" class="btn btn-sm btn-danger">
                    <i class="bi bi-trash-fill"></i> نعم، قم بالحذف
                </a>
            </div>
        </div>
    </div>
</div>