<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        // On mobile, close the sidebar
        if (window.innerWidth <= 768) {
            closeMobileSidebar();
        } else {
            // On desktop, toggle collapse
            sidebar.classList.toggle('collapsed');
        }
    }

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        
        // Prevent body scroll when sidebar is open
        if (sidebar.classList.contains('open')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    function closeMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    function toggleUserMenu() {
        const menu = document.getElementById('userMenu');
        menu.classList.toggle('open');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const userMenu = document.getElementById('userMenu');
        if (userMenu && !userMenu.contains(event.target)) {
            userMenu.classList.remove('open');
        }
    });

    // Close mobile sidebar when clicking on nav items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (window.innerWidth > 768) {
            // Desktop: remove mobile classes
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        } else {
            // Mobile: ensure sidebar is closed by default
            if (!sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        }
    });

    // Initialize: close sidebar on mobile by default
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.remove('open');
        }
    }

    // Submenu toggle function
    function toggleSubmenu(submenuId) {
        const submenu = document.getElementById(submenuId);
        if (!submenu) return;
        
        const parent = submenu.closest('.nav-item-parent');
        if (!parent) return;
        
        if (submenu.style.display === 'none' || submenu.style.display === '') {
            submenu.style.display = 'block';
            parent.classList.add('active');
        } else {
            submenu.style.display = 'none';
            parent.classList.remove('active');
        }
    }

    // Header messaging unread badge: fetch and update, poll periodically
    window.updateHeaderMessagingBadge = function() {
        const badge = document.getElementById('headerMessagingBadge');
        if (!badge) return;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('{{ url("api/messaging/unread-count") }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data && data.data.total > 0) {
                    badge.textContent = data.data.total > 99 ? '99+' : data.data.total;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                    badge.textContent = '';
                }
            })
            .catch(() => { if (badge) badge.style.display = 'none'; });
    };
    if (document.getElementById('headerMessagingBadge')) {
        window.updateHeaderMessagingBadge();
        setInterval(window.updateHeaderMessagingBadge, 30000);
    }

</script>

