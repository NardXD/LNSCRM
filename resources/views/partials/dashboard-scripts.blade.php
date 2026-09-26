<script>
    const SIDEBAR_STORAGE_KEY = 'crm.sidebar.collapsed';

    function isSidebarCollapsedPreferred() {
        try {
            const saved = localStorage.getItem(SIDEBAR_STORAGE_KEY);
            if (saved === null) return true; // default closed
            return saved === '1';
        } catch (e) {
            return true;
        }
    }

    function setSidebarCollapsedPreference(collapsed) {
        try {
            localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
        } catch (e) {
            // ignore
        }
    }

    function syncSidebarUiState() {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const desktopToggle = document.getElementById('desktopSidebarToggle');
        if (!sidebar) return;

        const collapsed = sidebar.classList.contains('collapsed');
        document.body.classList.toggle('sidebar-is-collapsed', collapsed && window.innerWidth > 768);

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggleBtn.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
            toggleBtn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }

        if (desktopToggle) {
            desktopToggle.hidden = !collapsed || window.innerWidth <= 768;
            desktopToggle.title = 'Open sidebar';
        }
    }

    function applySidebarCollapsed(collapsed) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar || window.innerWidth <= 768) return;

        sidebar.classList.toggle('collapsed', collapsed);
        setSidebarCollapsedPreference(collapsed);
        syncSidebarUiState();
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        // On mobile, close the sidebar
        if (window.innerWidth <= 768) {
            closeMobileSidebar();
        } else {
            // On desktop, toggle collapse
            const nextCollapsed = !sidebar.classList.contains('collapsed');
            applySidebarCollapsed(nextCollapsed);
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

    // Tooltips for collapsed icon rail (fallback when not hovering full peek)
    function refreshSidebarNavTitles() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        const collapsed = sidebar.classList.contains('collapsed');
        sidebar.querySelectorAll('.nav-item, .nav-subitem').forEach(item => {
            const label = item.querySelector('.nav-text')?.textContent?.trim();
            if (!label) return;
            if (collapsed) {
                item.setAttribute('title', label);
            } else {
                item.removeAttribute('title');
            }
        });
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (window.innerWidth > 768) {
            // Desktop: remove mobile classes and restore preference
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            applySidebarCollapsed(isSidebarCollapsedPreferred());
        } else {
            // Mobile: ensure sidebar is closed by default
            if (!sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
            document.body.classList.remove('sidebar-is-collapsed');
        }
        refreshSidebarNavTitles();
    });

    // Initialize sidebar state (default closed on desktop)
    (function initSidebarState() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        if (window.innerWidth <= 768) {
            sidebar.classList.remove('open');
            document.body.classList.remove('sidebar-is-collapsed');
        } else {
            applySidebarCollapsed(isSidebarCollapsedPreferred());
        }
        refreshSidebarNavTitles();
    })();

    // Keep titles in sync when toggling
    const sidebarEl = document.getElementById('sidebar');
    if (sidebarEl) {
        const observer = new MutationObserver(refreshSidebarNavTitles);
        observer.observe(sidebarEl, { attributes: true, attributeFilter: ['class'] });
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

    // Sidebar channel unread badges (messaging, viber, whatsapp, sms)
    window.updateSidebarUnreadBadges = function() {
        const badges = document.querySelectorAll('.nav-unread-badge[data-channel]');
        if (!badges.length) return;

        fetch('{{ url("api/notifications/channel-unread-counts") }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data) return;
                badges.forEach(badge => {
                    const channel = badge.getAttribute('data-channel');
                    const total = Number(data.data[channel] || 0);
                    if (total > 0) {
                        badge.textContent = total > 99 ? '99+' : String(total);
                        badge.style.display = '';
                        badge.setAttribute('aria-label', total + ' unread');
                        badge.removeAttribute('aria-hidden');
                    } else {
                        badge.textContent = '';
                        badge.style.display = 'none';
                        badge.setAttribute('aria-hidden', 'true');
                        badge.removeAttribute('aria-label');
                    }
                });
            })
            .catch(() => {});
    };
    if (document.querySelector('.nav-unread-badge[data-channel]')) {
        window.updateSidebarUnreadBadges();
        setInterval(window.updateSidebarUnreadBadges, 30000);
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
                } else if (data.type === 'lead_assigned') {
                    title = data.summary || (data.event === 'created'
                        ? `New lead assigned to you: ${data.contact_name || 'a lead'}`
                        : `${data.contact_name || 'A lead'} was assigned to you`);
                } else if (data.type === 'lead_rule') {
                    title = data.summary || `Lead rule: ${data.contact_name || 'a lead'}`;
                } else if (isMention || data.type === 'messaging_mention') {
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

