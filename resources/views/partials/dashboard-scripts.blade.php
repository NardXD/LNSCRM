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

    // App notifications (inbox mentions, etc.)
    (function () {
        const btn = document.getElementById('headerNotificationsBtn');
        const badge = document.getElementById('headerNotificationsBadge');
        const dropdown = document.getElementById('headerNotificationsDropdown');
        const list = document.getElementById('headerNotificationsList');
        const markAll = document.getElementById('headerNotificationsMarkAll');
        if (!btn || !dropdown || !list) return;

        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
        }

        function setBadge(total) {
            if (!badge) return;
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : String(total);
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
                badge.textContent = '';
            }
        }

        function renderItems(items) {
            if (!items.length) {
                list.innerHTML = '<div class="header-notifications-empty">No notifications yet</div>';
                return;
            }
            list.innerHTML = items.map(item => {
                const data = item.data || {};
                const isMention = !!(data.is_mention || data.type === 'inbox_comment_mention');
                const isWhatsApp = data.type === 'whatsapp_message' || data.channel === 'whatsapp';
                const isViber = data.type === 'viber_message' || data.channel === 'viber';
                const isSms = data.type === 'sms_message' || data.channel === 'sms';
                const isFacebook = data.type === 'facebook_message' || data.channel === 'messenger' || data.channel === 'instagram' || data.channel === 'facebook';
                let title;
                if (isWhatsApp) {
                    title = data.summary || `New WhatsApp message from ${data.contact_name || 'a contact'}`;
                } else if (isViber) {
                    title = data.summary || `New Viber message from ${data.contact_name || 'a contact'}`;
                } else if (isSms) {
                    title = data.summary || `New SMS from ${data.contact_name || 'a contact'}`;
                } else if (isFacebook) {
                    title = data.summary || `New message from ${data.contact_name || 'a contact'}`;
                } else if (isMention) {
                    title = `${data.author_name || 'Someone'} mentioned you`;
                } else {
                    title = data.summary || `${data.author_name || 'Someone'} updated a conversation`;
                }
                const snippet = data.snippet || data.subject || data.contact_name || '';
                const unread = !item.read_at;
                return `
                    <button type="button" class="header-notification-item ${unread ? 'unread' : ''}"
                        data-notification-id="${escapeHtml(item.id)}"
                        data-notification-url="${escapeHtml(data.url || '')}">
                        <span class="header-notification-title">${escapeHtml(title)}</span>
                        <span class="header-notification-snippet">${escapeHtml(snippet)}</span>
                        <span class="header-notification-time">${escapeHtml(item.created_at_human || '')}</span>
                    </button>
                `;
            }).join('');
        }

        window.updateHeaderNotificationsBadge = function () {
            fetch('{{ url("api/notifications/unread-count") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) setBadge(Number(data.data.total || 0));
                    else setBadge(0);
                })
                .catch(() => setBadge(0));
        };

        async function loadNotifications() {
            list.innerHTML = '<div class="header-notifications-empty">Loading…</div>';
            try {
                const res = await fetch('{{ url("api/notifications") }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                const items = data?.data?.notifications || [];
                renderItems(items);
                setBadge(Number(data?.data?.unread_count || 0));
            } catch (_) {
                list.innerHTML = '<div class="header-notifications-empty">Could not load notifications</div>';
            }
        }

        function closeDropdown() {
            dropdown.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = dropdown.hidden;
            dropdown.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) loadNotifications();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#headerNotifications')) closeDropdown();
        });

        markAll?.addEventListener('click', async (e) => {
            e.stopPropagation();
            try {
                const res = await fetch('{{ url("api/notifications/read-all") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf(),
                    },
                });
                const data = await res.json();
                setBadge(Number(data?.data?.unread_count || 0));
            } catch (_) {}
            loadNotifications();
        });

        list.addEventListener('click', async (e) => {
            const item = e.target.closest('[data-notification-id]');
            if (!item) return;
            const id = item.dataset.notificationId;
            const url = item.dataset.notificationUrl;
            try {
                await fetch(`{{ url("api/notifications") }}/${encodeURIComponent(id)}/read`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf(),
                    },
                });
            } catch (_) {}
            closeDropdown();
            window.updateHeaderNotificationsBadge?.();
            if (url) window.location = url;
        });

        window.updateHeaderNotificationsBadge();
        setInterval(window.updateHeaderNotificationsBadge, 30000);
    })();
</script>

