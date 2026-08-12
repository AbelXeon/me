<?php
/**
 * Shared layout footer — Social Media Manager
 * Closes .main and .app-shell and renders the mobile sidebar toggle script.
 * Page-specific scripts should be included BEFORE this file.
 */
?>
        </main>
    </div>

    <!-- ===== Sidebar toggle (mobile) ===== -->
    <script>
        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sidebarOverlay').classList.add('open');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('open');
        }
    </script>
</body>
</html>