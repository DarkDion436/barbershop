/**
 * Sidebar Toggle Logic
 * Handles opening/closing sidebar on mobile devices
 */

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const mainContent = document.querySelector('.main-content'); // Assuming you have a main wrapper

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });
    }

    // Close sidebar when clicking outside of it on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
            sidebar.classList.remove('open');
        }
    });

    // Auto-close alerts
    const alerts = document.querySelectorAll('.alert-message');
    if (alerts.length > 0) {
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.animation = 'fadeOut 0.5s ease-out forwards';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            }, 4000); // 4 seconds
        });
    }
});