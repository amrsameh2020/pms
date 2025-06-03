<?php
// includes/footer_resources.php
?>
    </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-dismiss Bootstrap alerts
        var alerts = document.querySelectorAll('.alert-container .alert.alert-dismissible.show');
        alerts.forEach(function(alert) {
            if (!alert.classList.contains('no-auto-dismiss')) {
                setTimeout(function() {
                    var bsAlertInstance = bootstrap.Alert.getInstance(alert);
                    if (bsAlertInstance) {
                        bsAlertInstance.close();
                    }
                }, 7000); 
            }
        });

        // Initialize Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Sidebar Toggle Functionality
        var sidebar = document.getElementById('sidebar');
        var sidebarToggler = document.getElementById('sidebarToggle');
        // var mainContent = document.getElementById('mainContent'); // Not needed for overlay toggle

        if (sidebar && sidebarToggler) {
            sidebarToggler.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                // If you want an overlay effect, you might add a class to body to show a backdrop
                // document.body.classList.toggle('sidebar-open-overlay');
            });

            // Optional: Close sidebar when clicking outside of it on small screens
            // This requires a backdrop or careful event handling on the main content area.
            // document.addEventListener('click', function(event) {
            //    if (sidebar.classList.contains('active') && 
            //        !sidebar.contains(event.target) && 
            //        !sidebarToggler.contains(event.target)) {
            //        sidebar.classList.remove('active');
            //    }
            // });
        }
    });
</script>

</body>
</html>
