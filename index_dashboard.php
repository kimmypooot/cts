<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/connect.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ANTI-FOUC GUARD — Must be absolute first in <head> -->
    <style id="fouc-guard">html { visibility: hidden !important; opacity: 0; }</style>
    <script>
        (function () {
            var revealed = false;
            function reveal() {
                if (revealed) return;
                revealed = true;
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        var html = document.documentElement;
                        html.style.cssText = 'visibility:visible !important; opacity:0; transition:opacity 0.25s ease;';
                        requestAnimationFrame(function () {
                            html.style.opacity = '1';
                            setTimeout(function () {
                                html.style.cssText = '';
                                var g = document.getElementById('fouc-guard');
                                if (g) g.parentNode.removeChild(g);
                            }, 300);
                        });
                    });
                });
            }
            document.addEventListener('DOMContentLoaded', reveal);
            window.addEventListener('load', reveal);
            setTimeout(reveal, 3000);
        }());
    </script>

    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
    <title>CSC RO VIII - IMIS Dashboard</title>
    <meta name="description" content="Integrated Management Information System Dashboard">
    <meta name="keywords" content="CSC, IMIS, Dashboard, Management System">

    <?php include 'vendor_css.html'; ?>
    <link href="assets/css/imis_dashboard_style.css" rel="stylesheet">
</head>
<body>
    <?php imis_include('header'); ?>

    <!-- Hidden session data for JS -->
    <input type="hidden" id="userId"   value="<?= htmlspecialchars($_SESSION['id'] ?? '') ?>">
    <input type="hidden" id="userName" value="<?= htmlspecialchars($_SESSION['name'] ?? '') ?>">

    <?php
    $directories = [
        'Human Resource Division (HRD)' => [
            'systems' => [
                ['id' => 'OTRS',  'name' => 'ONLINE TRAINING REGISTRATION SYSTEM',                   'icon' => 'bi bi-person-vcard',     'description' => 'Online registration for employee training programs'],
                ['id' => 'ORS',   'name' => 'ONLINE RECRUITMENT SYSTEM',                              'icon' => 'bi bi-person-badge',     'description' => 'Digital recruitment and hiring process management'],
                ['id' => 'IIS',   'name' => 'INTERN INFORMATION SYSTEM',                              'icon' => 'bi bi-person-lines-fill','description' => 'Intern data management and tracking system'],
                ['id' => 'LMS',   'name' => 'LEARNING AND DEVELOPMENT DATABASE FOR EMPLOYEE RECORDS', 'icon' => 'bi bi-book',             'description' => 'Employee learning and development management'],
                ['id' => 'ROOMS', 'name' => 'REGIONAL OFFICE ORDERS AND MEMORANDA MANAGEMENT SYSTEM', 'icon' => 'bi bi-journals',         'description' => 'Manage and track office orders and memoranda'],
            ],
            'icon'        => 'bi bi-people-fill',
            'description' => 'HR systems for employee management and training',
        ],
        'Examination Services Division (ESD)' => [
            'systems' => [
                ['id' => 'ERIS',     'name' => 'EXAMINATION-RELATED INFORMATION SYSTEM',                'icon' => 'bi bi-journal-text', 'description' => 'Manage civil service examination data and results'],
                ['id' => 'COMEXAMS', 'name' => 'COMPUTERIZED EXAMINATION APPLICATION MANAGEMENT SYSTEM', 'icon' => 'bi bi-pc-display',   'description' => 'Manage computerized examination applications', 'url' => 'https://comexams.cscro8.com', 'target' => '_blank'],
            ],
            'icon'        => 'bi bi-file-text-fill',
            'description' => 'Civil service examination management',
        ],
        'Public Assistance and Liaison Division (PALD)' => [
            'systems' => [
                ['id' => 'CDL',     'name' => 'CLIENT DAILY LOGSHEET',  'icon' => 'bi bi-calendar-check', 'description' => 'Daily tracking of client visits and services'],
                ['id' => 'JPortal', 'name' => 'CSC RO VIII JOB PORTAL', 'icon' => 'bi bi-briefcase',      'description' => 'Platform for posting and accessing government job vacancies within CSC Region VIII'],
            ],
            'icon'        => 'bi bi-person-check-fill',
            'description' => 'Client service, publication posting and assistance',
        ],
        'Management Services Division (MSD)' => [
            'systems' => [
                ['id' => 'RFCS',      'name' => 'FUEL CONSUMPTION REPORT SYSTEM', 'icon' => 'bi bi-fuel-pump',        'description' => 'Monitor and report vehicle fuel consumption'],
                ['id' => 'DVS',       'name' => 'DISBURSEMENT VOUCHER SYSTEM',     'icon' => 'bi bi-cash-stack',       'description' => 'Process and track financial disbursements'],
                ['id' => 'MSDESERVE', 'name' => 'MsDeServe',                       'icon' => 'bi bi-box-arrow-in-right','description' => 'Management services and delivery system', 'url' => 'https://msdeserve.cscro8.com', 'target' => '_blank'],
                ['id' => 'PROCURE',   'name' => 'BID AND PROCUREMENT PORTAL',      'icon' => 'bi bi-clipboard-check',  'description' => 'Post bids and procurements for the CSC RO VIII'],
            ],
            'icon'        => 'bi bi-gear-fill',
            'description' => 'Financial and administrative support systems',
        ],
        'Policies and Systems Evaluation Division (PSED)' => [
            'systems' => [
                ['id' => 'PSED', 'name' => 'PRIMARY SYSTEM OF ELECTRONIC BASED DOCUMENTS', 'icon' => 'bi bi-file-earmark-check', 'description' => 'Electronic document management and policy evaluation system'],
            ],
            'icon'        => 'bi bi-clipboard-data-fill',
            'description' => 'Evaluates HR systems and policy compliance',
        ],
        'Legal Services Division (LSD)' => [
            'systems' => [
                ['id' => 'CTS', 'name' => 'CASE TRACKING SYSTEM', 'icon' => 'bi bi-search', 'description' => 'Track and monitor legal cases and proceedings'],
            ],
            'icon'        => 'bi bi-shield-fill-check',
            'description' => 'Legal case monitoring and management',
        ],
        'Information Technology Group (ITG)' => [
            'systems' => [
                ['id' => 'ICTSRTS', 'name' => 'ICT SERVICE REQUEST TICKETING SYSTEM', 'icon' => 'bi bi-motherboard', 'description' => 'Submit and track ICT service requests and technical support tickets'],
            ],
            'icon'        => 'bi bi-cpu-fill',
            'description' => 'IT service requests and technical support',
        ],
        'Gender and Development' => [
            'systems' => [
                ['id' => 'GAD-CORNER', 'name' => 'GENDER AND DEVELOPMENT (GAD) CORNER', 'icon' => 'bi bi-gender-ambiguous', 'description' => 'Gender and development programs, resources and initiatives'],
            ],
            'icon'        => 'bi bi-gender-ambiguous',
            'description' => 'Gender and development programs and initiatives',
        ],
        'Bids and Awards Committee (BAC)' => [
            'systems' => [
                ['id' => 'PROCUREMENT', 'name' => 'Procurement Archives', 'icon' => 'bi bi-briefcase', 'description' => 'Procurement documents'],
            ],
            'icon'        => 'bi bi-briefcase',
            'description' => 'Procurement',
        ],
    ];
    ?>

    <!-- Toast container -->
    <div id="toastContainer" aria-live="polite" aria-atomic="false"></div>

    <main class="main">
        <div class="container-fluid px-4">

            <!-- ── Welcome Banner ─────────────────────────────── -->
            <div class="welcome-banner">
                <div class="orbit-circle-1"></div>
                <div class="orbit-circle-2"></div>
                <div class="orbit-circle-3"></div>
                <div class="orbit-circle-4"></div>
                <div class="orbit-circle-5"></div>
                <div class="orbit-circle-6"></div>
                <div class="welcome-content text-center">
                    <p class="welcome-greeting" id="welcomeGreeting"></p>
                    <h1 class="welcome-title">
                        CSC RO VIII &mdash; Integrated Management Information System
                    </h1>
                    <p class="welcome-subtitle">
                        Enhancing public service delivery through innovation and digital transformation.
                    </p>
                </div>
            </div>

            <!-- ── Directory View ─────────────────────────────── -->
            <div id="directoryView">

                <!-- Recently Accessed strip (hidden until populated) -->
                <div id="recentlyAccessedSection" class="recently-accessed" style="display:none;">
                    <div class="section-label">
                        <i class="bi bi-clock-history" aria-hidden="true"></i> Recently Accessed
                    </div>
                    <div class="recent-strip" id="recentStrip"></div>
                </div>

                <!-- Toolbar: section label + search -->
                <div class="dashboard-toolbar">
                    <div class="section-label" style="margin-bottom:0; flex:none;">
                        <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Select a Division
                    </div>
                    <div class="search-wrapper">
                        <i class="bi bi-search search-icon-left" aria-hidden="true"></i>
                        <input
                            type="search"
                            id="divisionSearch"
                            class="search-input"
                            placeholder="Search divisions or systems… (Ctrl+/)"
                            aria-label="Search divisions and systems"
                            autocomplete="off"
                        >
                        <button class="search-clear" id="divSearchClear" aria-label="Clear search" tabindex="-1">
                            <i class="bi bi-x" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="division-grid" id="divisionGrid">
                    <?php foreach ($directories as $division => $data): ?>
                        <div class="division-card fade-in"
                             data-division="<?= htmlspecialchars($division) ?>"
                             tabindex="0"
                             role="button"
                             aria-label="Open <?= htmlspecialchars($division) ?>">
                            <div class="division-content">
                                <div class="division-icon" aria-hidden="true">
                                    <i class="<?= htmlspecialchars($data['icon']) ?>"></i>
                                </div>
                                <div class="division-info">
                                    <div>
                                        <h3 class="division-title"><?= htmlspecialchars($division) ?></h3>
                                        <p class="division-subtitle"><?= htmlspecialchars($data['description']) ?></p>
                                    </div>
                                    <button class="view-systems-btn" tabindex="-1" aria-hidden="true">
                                        <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                                        View Systems (<?= count($data['systems']) ?>)
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Subdirectory View ──────────────────────────── -->
            <div id="subdirectoryView" class="d-none">

                <!-- Toolbar: back button + breadcrumb + search -->
                <div class="dashboard-toolbar">
                    <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; min-width:0; flex:1;">
                        <button id="backToDirectory" class="back-btn">
                            <i class="bi bi-arrow-left-circle" aria-hidden="true"></i>
                            Back
                        </button>
                        <nav class="imis-breadcrumb" aria-label="Page breadcrumb">
                            <span class="bc-item bc-link" id="bcHome" tabindex="0" role="link" aria-label="Go to Dashboard">
                                <i class="bi bi-house-door" aria-hidden="true"></i> Dashboard
                            </span>
                            <i class="bi bi-chevron-right bc-separator" aria-hidden="true"></i>
                            <span class="bc-item bc-current" id="bcDivision" aria-current="page"></span>
                        </nav>
                    </div>
                    <div class="search-wrapper">
                        <i class="bi bi-search search-icon-left" aria-hidden="true"></i>
                        <input
                            type="search"
                            id="systemSearch"
                            class="search-input"
                            placeholder="Search systems… (Ctrl+/)"
                            aria-label="Search systems"
                            autocomplete="off"
                        >
                        <button class="search-clear" id="sysSearchClear" aria-label="Clear system search" tabindex="-1">
                            <i class="bi bi-x" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="system-grid" id="systemGrid"></div>
            </div>

        </div>
    </main>

    <?php include 'vendor_js.html'; ?>
    <script src="LumaFramework/LumaFramework.js"></script>
    <script>
    $(function () {

        /* ============================================================
           DATA & CONSTANTS
           ============================================================ */
        const systemsData    = <?= json_encode($directories) ?>;
        const CACHE_DURATION = 5 * 60 * 1000;   // 5 min
        const RETRY_ATTEMPTS = 3;
        const RETRY_DELAY    = 1000;
        const RECENT_KEY     = 'imis_recently_accessed';
        const RECENT_MAX     = 5;

        /* ── DOM references ──────────────────────────────────────── */
        const $directoryView    = $('#directoryView');
        const $subdirectoryView = $('#subdirectoryView');
        const $systemGrid       = $('#systemGrid');
        const $divisionGrid     = $('#divisionGrid');
        const $backBtn          = $('#backToDirectory');
        const $bcDivision       = $('#bcDivision');
        const $bcHome           = $('#bcHome');
        const $divSearch        = $('#divisionSearch');
        const $divSearchClear   = $('#divSearchClear');
        const $sysSearch        = $('#systemSearch');
        const $sysSearchClear   = $('#sysSearchClear');

        let currentDivision = null;

        /* ── Access data cache ───────────────────────────────────── */
        const accessCache = {
            data: null, ts: 0,
            ok()    { return this.data && (Date.now() - this.ts) < CACHE_DURATION; },
            set(d)  { this.data = d; this.ts = Date.now(); },
            clear() { this.data = null; this.ts = 0; }
        };

        /* ============================================================
           UTILITIES
           ============================================================ */
        function debounce(fn, ms) {
            let t;
            return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), ms); };
        }

        async function retry(op, n = RETRY_ATTEMPTS) {
            for (let i = 0; i < n; i++) {
                try { return await op(); }
                catch (e) {
                    if (i === n - 1) throw e;
                    await new Promise(r => setTimeout(r, RETRY_DELAY * (i + 1)));
                }
            }
        }

        /* ============================================================
           GREETING
           ============================================================ */
        (function () {
            const name = ($('#userName').val() || '').trim().split(' ')[0];
            const h    = new Date().getHours();
            const part = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
            document.getElementById('welcomeGreeting').textContent =
                name ? `${part}, ${name}! 👋` : `${part}!`;
        })();

        /* ============================================================
           TOAST SYSTEM
           ============================================================ */
        const TOAST_ICONS = {
            success: 'bi-check-circle-fill',
            error:   'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info:    'bi-info-circle-fill',
        };

        function showToast(title, message = '', type = 'info', duration = 4000) {
            const icon = TOAST_ICONS[type] || TOAST_ICONS.info;
            const $t = $(`
                <div class="imis-toast toast-${type}" role="alert">
                    <i class="bi ${icon} toast-icon" aria-hidden="true"></i>
                    <div class="toast-body">
                        <p class="toast-title">${title}</p>
                        ${message ? `<p class="toast-message">${message}</p>` : ''}
                    </div>
                    <button class="toast-close" aria-label="Dismiss">
                        <i class="bi bi-x" aria-hidden="true"></i>
                    </button>
                    <div class="toast-progress" style="animation-duration:${duration}ms;"></div>
                </div>
            `);
            $('#toastContainer').append($t);
            requestAnimationFrame(() => requestAnimationFrame(() => $t.addClass('toast-show')));

            function dismiss() {
                $t.addClass('toast-hide');
                setTimeout(() => $t.remove(), 420);
            }
            $t.find('.toast-close').on('click', dismiss);
            setTimeout(dismiss, duration);
        }

        /* ============================================================
           RECENTLY ACCESSED
           ============================================================ */
        function getRecent() {
            try { return JSON.parse(sessionStorage.getItem(RECENT_KEY) || '[]'); }
            catch { return []; }
        }

        function saveRecent(systemId, systemName, divisionName) {
            let r = getRecent().filter(x => x.id !== systemId);
            r.unshift({ id: systemId, name: systemName, division: divisionName });
            sessionStorage.setItem(RECENT_KEY, JSON.stringify(r.slice(0, RECENT_MAX)));
        }

        function renderRecentStrip() {
            const recent   = getRecent();
            const $section = $('#recentlyAccessedSection');
            const $strip   = $('#recentStrip');
            if (!recent.length) { $section.hide(); return; }

            $strip.empty();
            recent.forEach(item => {
                let icon = 'bi-app';
                for (const [, div] of Object.entries(systemsData)) {
                    const s = div.systems.find(s => s.id === item.id);
                    if (s) { icon = s.icon; break; }
                }
                const shortDiv = item.division.replace(/ \(.*\)/, '');
                const $chip = $(`
                    <button class="recent-chip"
                            data-system-id="${item.id}"
                            data-division="${item.division}"
                            aria-label="Open ${item.name}"
                            title="${item.name} — ${item.division}">
                        <i class="${icon}" aria-hidden="true"></i>
                        <span class="chip-label">${item.name}</span>
                        <span class="chip-division">· ${shortDiv}</span>
                    </button>
                `);
                $chip.on('click', () => openDivision(item.division));
                $strip.append($chip);
            });
            $section.show();
        }

        function markRecentCards() {
            const divs = [...new Set(getRecent().map(r => r.division))];
            $('.division-card').each(function () {
                const d = $(this).data('division');
                if (divs.includes(d) && !$(this).find('.recently-used-badge').length) {
                    $(this).addClass('recently-used').find('.division-content').prepend(`
                        <div class="recently-used-badge" aria-hidden="true">
                            <i class="bi bi-clock-history"></i> Recent
                        </div>
                    `);
                }
            });
        }

        /* ============================================================
           SKELETON LOADER
           ============================================================ */
        function renderSkeletons(count) {
            $systemGrid.empty();
            for (let i = 0; i < count; i++) {
                $systemGrid.append(`
                    <div class="skeleton-card" aria-hidden="true">
                        <div class="skeleton-icon-panel"></div>
                        <div class="skeleton-body">
                            <div class="skeleton skeleton-line w-80"></div>
                            <div class="skeleton skeleton-line w-60"></div>
                            <div class="skeleton skeleton-line w-100"></div>
                            <div class="skeleton skeleton-line h-btn w-40"></div>
                        </div>
                    </div>
                `);
            }
        }

        /* ============================================================
           SEARCH — Divisions
           ============================================================ */
        function filterDivisions(q) {
            q = q.trim().toLowerCase();
            let visible = 0;

            $('.division-card').each(function () {
                const divName  = $(this).data('division').toLowerCase();
                const desc     = $(this).find('.division-subtitle').text().toLowerCase();
                const divData  = systemsData[$(this).data('division')];
                const sysNames = (divData?.systems || []).map(s => s.name.toLowerCase()).join(' ');
                const match    = !q || divName.includes(q) || desc.includes(q) || sysNames.includes(q);

                $(this).toggleClass('search-hidden', !match).toggleClass('search-match', !!q && match);
                if (match) visible++;
            });

            // Empty state
            let $e = $divisionGrid.find('.empty-state');
            if (visible === 0 && q) {
                if (!$e.length) {
                    $divisionGrid.append(`
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-search"></i></div>
                            <h5>No results found</h5>
                            <p>Nothing matched "<strong>${q}</strong>". Try a different keyword.</p>
                            <button class="empty-state-btn" id="clearDivSearch">
                                <i class="bi bi-arrow-counterclockwise"></i> Clear Search
                            </button>
                        </div>
                    `);
                    $('#clearDivSearch').on('click', () => $divSearch.val('').trigger('input').focus());
                }
            } else {
                $e.remove();
                if (!q) $('.division-card').removeClass('search-match');
            }
        }

        $divSearch.on('input', debounce(function () {
            const v = $(this).val();
            $divSearchClear.toggleClass('visible', !!v);
            filterDivisions(v);
        }, 200));

        $divSearchClear.on('click', () => $divSearch.val('').trigger('input').focus());

        /* ── Search — Systems ────────────────────────────────────── */
        function filterSystems(q) {
            q = q.trim().toLowerCase();
            let visible = 0;
            $('#systemGrid .system-card').each(function () {
                const name  = $(this).find('.system-title').text().toLowerCase();
                const desc  = $(this).find('.system-description').text().toLowerCase();
                const match = !q || name.includes(q) || desc.includes(q);
                $(this).toggleClass('search-hidden', !match);
                if (match) visible++;
            });

            let $e = $systemGrid.find('.empty-state');
            if (visible === 0 && q) {
                if (!$e.length) {
                    $systemGrid.append(`
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-folder-x"></i></div>
                            <h5>No systems found</h5>
                            <p>Nothing matched "<strong>${q}</strong>" in this division.</p>
                            <button class="empty-state-btn" id="clearSysSearch">
                                <i class="bi bi-arrow-counterclockwise"></i> Clear Search
                            </button>
                        </div>
                    `);
                    $('#clearSysSearch').on('click', () => $sysSearch.val('').trigger('input').focus());
                }
            } else {
                $e.remove();
            }
        }

        $sysSearch.on('input', debounce(function () {
            const v = $(this).val();
            $sysSearchClear.toggleClass('visible', !!v);
            filterSystems(v);
        }, 200));

        $sysSearchClear.on('click', () => $sysSearch.val('').trigger('input').focus());

        /* ============================================================
           OPEN DIVISION
           ============================================================ */
        function openDivision(division) {
            const divData = systemsData[division];
            if (!divData?.systems) { showToast('Error', 'Division data not found.', 'error'); return; }

            currentDivision = division;
            $bcDivision.text(division.replace(/ \(.*\)/, '')).attr('title', division);

            // Reset system search
            $sysSearch.val('');
            $sysSearchClear.removeClass('visible');

            // Show skeletons immediately for visual feedback
            renderSkeletons(divData.systems.length);
            $directoryView.addClass('d-none');
            $subdirectoryView.removeClass('d-none');

            fetchAndRenderSystems(divData.systems);
        }

        /* ── Division card click / keydown ───────────────────────── */
        $divisionGrid.on('click keydown', '.division-card', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
            if (e.type === 'keydown') e.preventDefault();
            openDivision($(this).data('division'));
        });

        /* ── Back ────────────────────────────────────────────────── */
        function goBack() {
            $subdirectoryView.addClass('d-none');
            $directoryView.removeClass('d-none');
            currentDivision = null;
            $divSearch.val('').trigger('input');
            renderRecentStrip();
            markRecentCards();
        }

        $backBtn.on('click', goBack);
        $bcHome.on('click keydown', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
            goBack();
        });

        /* ============================================================
           SYSTEM CARDS — render after access data resolves
           ============================================================ */
        function resolveState(systemId, accessRow) {
            const role         = accessRow ? (accessRow[systemId.toLowerCase()] || 'None') : 'None';
            const acknowledged = sessionStorage.getItem(`access_acknowledged_${systemId}`);

            if (systemId === 'ERIS') {
                return { cls: 'enabled', content: buildBtn(role), disabled: false, role };
            }

            if (!accessRow || role === 'None') {
                return acknowledged
                    ? { cls: 'disabled', content: '<i class="bi bi-lock-fill" aria-hidden="true"></i> No Access', disabled: true,  role: 'None' }
                    : { cls: 'disabled', content: '<i class="bi bi-lock-fill" aria-hidden="true"></i> No Access', disabled: false, role: 'None' };
            }

            return { cls: 'enabled', content: buildBtn(role), disabled: false, role };
        }

        function buildBtn(role) {
            const label = role === 'None' ? 'Guest' : role;
            return `<i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    Access System
                    <span class="role-badge">${label}</span>`;
        }

        function renderSystemCards(systems, accessRow) {
            $systemGrid.empty();
            const frag = document.createDocumentFragment();

            systems.forEach((sys, i) => {
                const delay = Math.min(0.05 * (i + 1), 0.5);
                const state = resolveState(sys.id, accessRow);
                const tip   = state.cls === 'disabled' && !state.disabled
                    ? 'data-imis-tip="You do not have access. Contact your administrator."'
                    : '';

                const $card = $(`
                    <div class="system-card fade-in" style="animation-delay:${delay}s" data-system-id="${sys.id}">
                        <div class="system-content">
                            <div class="system-icon" aria-hidden="true">
                                <i class="${sys.icon}"></i>
                            </div>
                            <div class="system-info">
                                <div>
                                    <h4 class="system-title">${sys.name}</h4>
                                    ${sys.description
                                        ? `<small class="system-description">${sys.description}</small>`
                                        : ''}
                                </div>
                                <button class="system-btn ${state.cls} manage-btn"
                                        data-id="${sys.id}"
                                        data-sysname="${sys.name}"
                                        data-role="${state.role}"
                                        data-user="${accessRow?.user ?? ''}"
                                        aria-label="${state.cls === 'disabled' ? 'No access to ' + sys.name : 'Access ' + sys.name}"
                                        ${state.disabled ? 'disabled' : ''}
                                        ${tip}>
                                    ${state.content}
                                </button>
                            </div>
                        </div>
                    </div>
                `)[0];

                frag.appendChild($card);
            });

            $systemGrid.append(frag);

            // Inject status indicators after a short delay
            setTimeout(() => {
                $('.system-card').each(function () {
                    const $btn = $(this).find('.manage-btn');
                    const $inf = $(this).find('.system-info');
                    if (!$btn.hasClass('disabled') && !$inf.find('.status-indicator').length) {
                        $inf.prepend('<span class="status-indicator online" title="System Online" aria-label="System Online"></span>');
                    }
                });
                // Re-observe new cards
                $('.fade-in').each(function () { observer.observe(this); });
            }, 800);
        }

        /* ============================================================
           FETCH USER ACCESS
           ============================================================ */
        async function fetchAndRenderSystems(systems) {
            if (accessCache.ok()) {
                renderSystemCards(systems, accessCache.data[0]);
                showToast('Access Loaded', 'Your permissions have been retrieved.', 'success', 2500);
                return;
            }

            try {
                const userId = $('#userId').val();
                if (!userId) throw new Error('User ID not found');

                const data = await retry(() => Luma.FetchData({
                    query:  'SELECT * FROM system_access WHERE user = :user',
                    params: { user: userId },
                }));

                if (!data || !data.length) {
                    renderSystemCards(systems, null);
                    showToast('No Access Records', 'No permissions found for your account.', 'warning');
                    return;
                }

                accessCache.set(data);
                renderSystemCards(systems, data[0]);
                showToast('Access Loaded', 'Your permissions have been retrieved.', 'success', 2500);

            } catch (err) {
                console.error('fetchAndRenderSystems:', err);
                renderError();
                showToast('Access Error', 'Could not load permissions. Please try again.', 'error');
            }
        }

        function renderError() {
            $systemGrid.empty().append(`
                <div class="empty-state" style="grid-column:1/-1">
                    <div class="empty-state-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <h5>Failed to load access data</h5>
                    <p>There was a problem retrieving your permissions. Check your connection and try again.</p>
                    <button class="empty-state-btn" id="retryAccess">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                </div>
            `);
            $('#retryAccess').one('click', () => {
                accessCache.clear();
                if (currentDivision) openDivision(currentDivision);
            });
        }

        /* ============================================================
           ACCESS DENIED MODAL
           ============================================================ */
        $subdirectoryView.on('click', '.manage-btn.disabled:not([disabled])', function () {
            const id   = $(this).data('id');
            const name = $(this).data('sysname');
            const $btn = $(this);

            Swal.fire({
                title:             'Restricted System Access',
                html:              `<div class="text-center">
                                        <p class="mb-2">You do not have access to <strong>${name}</strong>.</p>
                                        <p class="mb-2">This system requires authorization from your administrator.</p>
                                        <p class="text-muted small mb-0">Contact ITG or your division head to request access.</p>
                                    </div>`,
                icon:              'warning',
                confirmButtonText: 'Understood',
                confirmButtonColor:'#0077b6',
                allowOutsideClick: false,
            }).then(r => {
                if (r.isConfirmed) {
                    sessionStorage.setItem(`access_acknowledged_${id}`, 'true');
                    $btn.prop('disabled', true).removeAttr('data-imis-tip');
                }
            });
        });

        /* ============================================================
           SYSTEM ACCESS CLICK
           ============================================================ */
        $subdirectoryView.on('click', '.manage-btn.enabled', debounce(async function () {
            const $btn    = $(this);
            const system  = $btn.data('id');
            const role    = $btn.data('role');
            const userId  = $btn.data('user');
            const sysName = $btn.data('sysname');

            // Save to recently accessed before navigating
            if (currentDivision) saveRecent(system, sysName, currentDivision);

            const orig = $btn.html();
            $btn.prop('disabled', true).html('<div class="loading-spinner"></div> Accessing…');

            try {
                await redirectToSystem(system, role, userId);
            } catch (err) {
                console.error('System access:', err);
                $btn.prop('disabled', false).html(orig);

                if (!navigator.onLine) {
                    showToast('Offline', 'You appear to be offline.', 'error');
                } else if (err?.status >= 500) {
                    showToast('Server Error', 'Server temporarily unavailable.', 'error');
                } else {
                    showToast('Error', err?.message || 'An unexpected error occurred.', 'error');
                }
            }
        }, 500));

        /* ============================================================
           REDIRECT LOGIC
           ============================================================ */
        async function redirectToSystem(sys, role, userId) {
            const isArr = a => Array.isArray(a) && a.length > 0;
            const go    = url => setTimeout(() => { window.location.href = url; }, 100);
            const timer = new Promise((_, rej) => setTimeout(() => rej(new Error('Operation timed out')), 15000));

            await Promise.race([doIt(), timer]);

            async function doIt() {
                switch (sys) {
                    case 'ERIS': {
                        const m = { Admin: 'eris/esd/index', User: 'eris/index', None: 'eris/db_onsa' };
                        if (m[role]) go(m[role]); else throw new Error('Invalid role for ERIS');
                        break;
                    }
                    case 'ORS': {
                        const m = { Admin: 'ors/admin/index', User: 'ors/index' };
                        if (m[role]) go(m[role]); else throw new Error('Invalid role for ORS');
                        break;
                    }
                    case 'CTS': {
                        const d = await retry(() => Luma.FetchData({
                            query: 'SELECT * FROM cts_manage_users WHERE user = :user', params: { user: userId }
                        }));
                        if (isArr(d)) {
                            const ok = await Luma.SetSession({ userId: d[0].id, ao_number: d[0].ao_number }, false);
                            if (!ok) throw new Error('Failed to set CTS session');
                            const m = { Admin: 'cts/admin/index_dashboard', Superadmin: 'cts/superadmin/index_dashboard', User: 'cts/users/index_my_dashboard' };
                            if (m[role]) go(m[role]); else throw new Error('Invalid role for CTS');
                        } else {
                            await Luma.Alert('Restricted Access', 'Access is limited to Legal Services Division personnel only.', 'error');
                        }
                        break;
                    }
                    case 'CDL':   go('cdl/index_clients'); break;
                    case 'PSED':
                        if (role === 'Admin' || role === 'User') go('psed/index');
                        else throw new Error('Insufficient privileges for PSED');
                        break;
                    case 'RFCS':
                        if (role === 'Admin') { go('fts/admin/index_dashboard'); break; }
                        if (role === 'User') {
                            const d = await retry(() => Luma.FetchData({
                                query: "SELECT * FROM trip_drivers WHERE user = :user AND status != 'Inactive'",
                                params: { user: userId }
                            }));
                            if (isArr(d)) {
                                const ok = await Luma.SetSession({ driver_id: d[0].id }, false);
                                if (ok) go('fts/user/index_dashboard'); else throw new Error('Failed to set driver session');
                            } else throw new Error('No active driver account found');
                        } else throw new Error('Invalid role for RFCS');
                        break;
                    case 'LMS': {
                        const m = { Admin: 'lms/hrd/ld_index', User: 'lms/ld_index' };
                        if (m[role]) go(m[role]); else throw new Error('Invalid role for LMS');
                        break;
                    }
                    case 'OTRS':
                        if (role === 'Admin') go('otrs/hrd/index');
                        else if (role === 'User') go(`otrs/index?location=<?= htmlspecialchars($_SESSION['type'] ?? '') ?>`);
                        else throw new Error('Invalid role for OTRS');
                        break;
                    case 'LCMMS': go('lcms/index'); break;
                    case 'JPortal':
                        if (role === 'Admin') go('jportal/pald/index');
                        else throw new Error('Invalid role for Job Portal');
                        break;
                    case 'IIS':
                        if (role === 'Admin') go('iis/index_dashboard');
                        else throw new Error('Insufficient privileges for IIS');
                        break;
                    case 'ROOMS':
                        if (role === 'Admin' || role === 'User') go('rooms/index');
                        else throw new Error('Invalid role for ROOMS');
                        break;
                    case 'DVS':
                        if (role === 'Admin') go('dvs/admin/index_dashboard');
                        else throw new Error('Insufficient privileges for DVS');
                        break;
                    case 'MSDESERVE':
                        if (role === 'Admin' || role === 'User') window.open('https://msdeserve.cscro8.com', '_blank');
                        else throw new Error('Invalid role for MsDeServe');
                        break;
                    case 'PROCURE':
                        if (role === 'Admin') go('procure/index');
                        else throw new Error('Insufficient privileges for PROCURE');
                        break;
                    case 'COMEXAMS':
                        if (role === 'Admin' || role === 'User') window.open('https://comexams.cscro8.com', '_blank');
                        else throw new Error('Invalid role for COMEXAMS');
                        break;
                    case 'ICTSRTS': {
                        try {
                            const d = await retry(() => Luma.FetchData({
                                query: 'SELECT id FROM itg_tbl WHERE id = :userId', params: { userId }
                            }));
                            const isITG = isArr(d);
                            await Luma.SetSession({ is_itg_member: isITG, login_user: userId }, false);
                            go(isITG ? 'ict-srts/admin/index' : 'ict-srts/user/index');
                        } catch {
                            go(role === 'Admin' ? 'ict-srts/admin/index' : 'ict-srts/user/index');
                        }
                        break;
                    }
                        case 'GAD-CORNER':
                            if (role === 'Admin' || role === 'User') go('gad-corner/index');
                            else throw new Error('Invalid role for GAD');
                            break;
                        default:
                            await Luma.Alert('Info', 'System not configured for redirect yet.', 'info');
                        
                        case 'PROCUREMENT':
                            if (role === 'Admin') go('procurement/index');
                            else throw new Error('Invalid role for PROCUREMENT');
                            break;

                        case 'PMS':
                            if (role === 'Admin') go('ipcrf/auth/login.php');
                            else throw new Error('Invalid role for PMS');
                            break;
                    }
                }
            }

        /* ============================================================
           INTERSECTION OBSERVER (fade-in)
           ============================================================ */
        const observer = new IntersectionObserver(entries => {
            requestAnimationFrame(() => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        e.target.style.opacity = '1';
                        e.target.style.transform = 'translateY(0)';
                        observer.unobserve(e.target);
                    }
                });
            });
        }, { threshold: 0.08, rootMargin: '50px' });

        $('.fade-in').each(function () { observer.observe(this); });

        /* ============================================================
           NETWORK MONITORING
           ============================================================ */
        window.addEventListener('offline', () =>
            showToast('Connection Lost', 'You are now offline. Some features may not work.', 'warning', 6000)
        );
        window.addEventListener('online', () => {
            showToast('Back Online', 'Your connection has been restored.', 'success', 3000);
            accessCache.clear();
        });

        /* ============================================================
           AJAX ERROR HANDLER
           ============================================================ */
        $(document).ajaxError((event, xhr) => {
            if (xhr.status === 401 || xhr.status === 403) {
                accessCache.clear();
                sessionStorage.clear();
            }
        });

        /* ============================================================
           KEYBOARD SHORTCUTS
           ============================================================ */
        $(document).on('keydown', function (e) {
            // Ctrl+/ — focus active search bar
            if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                e.preventDefault();
                ($directoryView.is(':visible') ? $divSearch : $sysSearch).focus().select();
            }
            // Escape — go back from subdirectory view
            if (e.key === 'Escape' && $subdirectoryView.is(':visible')) {
                goBack();
            }
        });

        /* ============================================================
           INITIALISE
           ============================================================ */
        renderRecentStrip();
        markRecentCards();

        $(window).on('beforeunload', () => observer.disconnect());
    });
    </script>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center" aria-label="Back to top">
        <i class="bi bi-arrow-up-short"></i>
    </a>
</body>
</html>