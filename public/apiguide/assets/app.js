(function () {
    var baseUrl = window.location.origin;

    document.querySelectorAll('[data-base-url]').forEach(function (el) {
        el.textContent = el.textContent.replace(/\{\{BASE_URL\}\}/g, baseUrl);
    });

    document.querySelectorAll('.copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var code = btn.closest('.code-wrap').querySelector('code');
            navigator.clipboard.writeText(code.innerText).then(function () {
                var original = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = original; }, 1500);
            });
        });
    });

    document.querySelectorAll('.code-tabs').forEach(function (group) {
        var tabs = group.querySelectorAll('.tab-bar button');
        var panels = group.querySelectorAll('.code-panel');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var lang = tab.getAttribute('data-lang');
                tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
                panels.forEach(function (p) {
                    p.classList.toggle('active', p.getAttribute('data-lang') === lang);
                });
            });
        });
        if (tabs.length) {
            tabs[0].classList.add('active');
            panels[0].classList.add('active');
        }
    });

    var toggle = document.querySelector('.menu-toggle');
    var sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () { sidebar.classList.remove('open'); });
        });
    }

    var sections = Array.prototype.slice.call(document.querySelectorAll('section[id]'));
    var navLinks = Array.prototype.slice.call(document.querySelectorAll('.nav-group a[href^="#"]'));

    function onScroll() {
        var pos = window.scrollY + 120;
        var current = sections[0];
        sections.forEach(function (s) {
            if (s.offsetTop <= pos && !s.classList.contains('hidden-by-search')) {
                current = s;
            }
        });
        navLinks.forEach(function (l) {
            l.classList.toggle('active', l.getAttribute('href') === '#' + current.id);
        });
    }

    window.addEventListener('scroll', onScroll);
    onScroll();

    var searchIndex = sections.map(function (section) {
        return {
            id: section.id,
            title: (section.querySelector('h2') || {}).textContent || section.id,
            text: section.textContent.toLowerCase(),
        };
    });

    var sidebarSearch = document.getElementById('sidebar-search');
    var searchModal = document.getElementById('search-modal');
    var searchModalInput = document.getElementById('search-modal-input');
    var searchResults = document.getElementById('search-results');

    function runSearch(query) {
        var q = (query || '').trim().toLowerCase();
        if (!q) {
            sections.forEach(function (s) { s.classList.remove('hidden-by-search'); });
            navLinks.forEach(function (l) { l.classList.remove('hidden-by-search'); });
            if (searchResults) {
                searchResults.innerHTML = '';
            }
            onScroll();
            return;
        }

        var matches = searchIndex.filter(function (item) {
            return item.text.indexOf(q) !== -1 || item.id.indexOf(q) !== -1;
        });

        var matchIds = matches.map(function (m) { return m.id; });

        sections.forEach(function (s) {
            s.classList.toggle('hidden-by-search', matchIds.indexOf(s.id) === -1);
        });

        navLinks.forEach(function (l) {
            var id = (l.getAttribute('href') || '').replace('#', '');
            l.classList.toggle('hidden-by-search', matchIds.indexOf(id) === -1);
        });

        if (searchResults) {
            searchResults.innerHTML = matches.length
                ? matches.map(function (m) {
                    return '<a class="search-result" href="#' + m.id + '">' +
                        '<div class="title">' + m.title + '</div>' +
                        '<div class="snippet">#' + m.id + '</div></a>';
                }).join('')
                : '<div style="padding:16px;color:var(--text-muted)">No results found.</div>';

            searchResults.querySelectorAll('.search-result').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (searchModal) {
                        searchModal.classList.remove('open');
                    }
                    sidebar.classList.remove('open');
                });
            });
        }

        onScroll();
    }

    if (sidebarSearch) {
        sidebarSearch.addEventListener('input', function () {
            runSearch(sidebarSearch.value);
        });
    }

    if (searchModal && searchModalInput) {
        document.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                searchModal.classList.add('open');
                searchModalInput.focus();
                searchModalInput.select();
            }
            if (e.key === 'Escape') {
                searchModal.classList.remove('open');
            }
        });

        searchModal.addEventListener('click', function (e) {
            if (e.target === searchModal) {
                searchModal.classList.remove('open');
            }
        });

        searchModalInput.addEventListener('input', function () {
            runSearch(searchModalInput.value);
        });
    }
})();
