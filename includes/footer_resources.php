<?php
// includes/footer_resources.php
?>
    </div> <?php /* Closes main-content div from header_resources.php */ ?>
    </div> <?php /* Closes wrapper div from header_resources.php */ ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tooltips (if still used for other elements)
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Sidebar Toggle Functionality
        var sidebar = document.getElementById('sidebar');
        var sidebarToggler = document.getElementById('sidebarToggle');
        if (sidebar && sidebarToggler) {
            sidebarToggler.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }

        // SweetAlert for flash messages from session
        <?php
        if (isset($_SESSION['flash_message']) && isset($_SESSION['flash_message']['message']) && isset($_SESSION['flash_message']['type'])) {
            $message = $_SESSION['flash_message']['message'];
            $type = $_SESSION['flash_message']['type'];
            
            $swal_icon = 'info'; // default icon
            if ($type === 'success') $swal_icon = 'success';
            else if ($type === 'danger') $swal_icon = 'error';
            else if ($type === 'warning') $swal_icon = 'warning';

            // Determine title based on type for better context
            $swal_title = ucfirst($swal_icon); // Default title e.g. "Success", "Error"
            if ($swal_icon === 'error') $swal_title = 'خطأ!';
            else if ($swal_icon === 'success') $swal_title = 'نجاح!';
            else if ($swal_icon === 'warning') $swal_title = 'تحذير!';
            else if ($swal_icon === 'info') $swal_title = 'معلومة!';

            // Using addslashes to escape characters for JavaScript string
            echo "Swal.fire({ 
                title: '" . addslashes($swal_title) . "', 
                text: '" . addslashes($message) . "', 
                icon: '" . $swal_icon . "', 
                confirmButtonText: 'حسنًا',
                customClass: {
                    popup: 'fs-6', // Smaller font size for popup
                    title: 'fs-5', // Smaller font size for title
                    htmlContainer: 'fs-6' // Smaller font for text
                }
            });";
            unset($_SESSION['flash_message']); // Clear the message after displaying
        }
        ?>

        // Global SweetAlert for delete confirmations
        document.body.addEventListener('click', function(event) {
            const targetButton = event.target.closest('.sweet-delete-btn'); // Use closest to handle clicks on icons inside button
            if (targetButton) {
                event.preventDefault(); // Prevent any default button action if it's a link or form button
                const deleteUrl = targetButton.getAttribute('data-delete-url');
                const itemName = targetButton.getAttribute('data-name') || 'هذا العنصر'; // Fallback item name
                const additionalMessage = targetButton.getAttribute('data-additional-message') || 'لا يمكن التراجع عن هذا الإجراء.';

                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    html: `هل تريد حقًا حذف "<b>${itemName}</b>"؟<br><small>${additionalMessage}</small>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33', // Red for delete
                    cancelButtonColor: '#6c757d', // Bootstrap secondary color
                    confirmButtonText: 'نعم، قم بالحذف!',
                    cancelButtonText: 'إلغاء',
                    customClass: { // Optional: for consistent font sizes
                        popup: 'fs-6',
                        title: 'fs-5',
                        htmlContainer: 'fs-6'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (deleteUrl) {
                            window.location.href = deleteUrl;
                        } else {
                            Swal.fire('خطأ!', 'رابط الحذف غير موجود أو غير صالح.', 'error');
                        }
                    }
                });
            }
        });
    });
</script>

</body>
</html>