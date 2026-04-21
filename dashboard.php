<?php
require_once __DIR__ . '/includes/session.php';
include_once 'imis_include.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- ANTI-FOUC GUARD — Must be absolute first in <head> -->
    <style id="fouc-guard">
        html {
            visibility: hidden !important;
            opacity: 0;
        }
    </style>
    <script>
        (function() {
            var revealed = false;

            function reveal() {
                if (revealed) return;
                revealed = true;
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        var html = document.documentElement;
                        html.style.cssText = 'visibility:visible !important; opacity:0; transition:opacity 0.25s ease;';
                        requestAnimationFrame(function() {
                            html.style.opacity = '1';
                            setTimeout(function() {
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
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['_csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <?php include 'vendor_css.html'; ?>
    <link href="assets/css/imis_dashboard_style.css" rel="stylesheet">

    <style>
        /* ── Category Section Labels ───────────────────────────────── */
        .category-section {
            margin-bottom: 2.75rem;
        }

        .category-section.search-hidden {
            display: none;
        }

        .category-section .division-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 0;
            margin-bottom: 0;
        }

        .category-section-label {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .category-section-label .label-icon {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .category-section-label.cat-rsu .label-icon {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .category-section-label.cat-committee .label-icon {
            background: #fef3c7;
            color: #d97706;
        }

        .category-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .category-count-badge {
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.1rem 0.5rem;
            border-radius: 50px;
            letter-spacing: 0.02em;
            flex-shrink: 0;
        }

        .cat-rsu .category-count-badge {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .cat-committee .category-count-badge {
            background: #fef3c7;
            color: #d97706;
        }

        .dashboard-toolbar .toolbar-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            min-width: 0;
        }

        @media (max-width: 768px) {
            .category-section .division-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .category-section .division-grid {
                gap: 0.75rem;
            }
        }
    </style>
</head>

<body>
    <?php imis_include('header'); ?>

    <!--
        Read-only session values for JS display purposes only.
        These are NEVER written back — the server owns all session state.
    -->
    <input type="hidden" id="userId" value="<?= htmlspecialchars($_SESSION['id']   ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" id="userName" value="<?= htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <?php
    /* ──────────────────────────────────────────────────────────────
       SYSTEM DEFINITIONS
       $categories drives the two-section HTML rendering.
       $directories is the flat map serialised into JS as systemsData.
    ────────────────────────────────────────────────────────────── */
    $allDivisions = [
        'Human Resource Division (HRD)' => [
            'systems' => [
                ['id' => 'OTRS',  'name' => 'ONLINE TRAINING REGISTRATION SYSTEM',                   'icon' => 'bi bi-person-vcard',      'description' => 'Online registration for employee training programs'],
                ['id' => 'ORS',   'name' => 'ONLINE RECRUITMENT SYSTEM',                              'icon' => 'bi bi-person-badge',      'description' => 'Digital recruitment and hiring process management'],
                ['id' => 'IIS',   'name' => 'INTERN INFORMATION SYSTEM',                              'icon' => 'bi bi-person-lines-fill', 'description' => 'Intern data management and tracking system'],
                ['id' => 'LMS',   'name' => 'LEARNING AND DEVELOPMENT DATABASE FOR EMPLOYEE RECORDS', 'icon' => 'bi bi-book',              'description' => 'Employee learning and development management'],
                ['id' => 'ROOMS', 'name' => 'REGIONAL OFFICE ORDERS AND MEMORANDA MANAGEMENT SYSTEM', 'icon' => 'bi bi-journals',          'description' => 'Manage and track office orders and memoranda'],
            ],
            'icon'        => 'bi bi-people-fill',
            'description' => 'HR systems for employee management and training',
        ],
        'Examination Services Division (ESD)' => [
            'systems' => [
                ['id' => 'ERIS',     'name' => 'EXAMINATION-RELATED INFORMATION SYSTEM',                'icon' => 'bi bi-journal-text', 'description' => 'Manage civil service examination data and results'],
                ['id' => 'COMEXAMS', 'name' => 'COMPUTERIZED EXAMINATION APPLICATION MANAGEMENT SYSTEM', 'icon' => 'bi bi-pc-display',   'description' => 'Manage computerized examination applications'],
            ],
            'icon'        => 'bi bi-file-text-fill',
            'description' => 'Civil service examination management',
        ],
        'Public Assistance and Liaison Division (PALD)' => [
            'systems' => [
                ['id' => 'CDL',     'name' => 'CLIENT DAILY LOGSHEET',  'icon' => 'bi bi-calendar-check', 'description' => 'Daily tracking of client visits and services'],
                ['id' => 'JPORTAL', 'name' => 'CSC RO VIII JOB PORTAL', 'icon' => 'bi bi-briefcase',      'description' => 'Platform for posting and accessing government job vacancies within CSC Region VIII'],
            ],
            'icon'        => 'bi bi-person-check-fill',
            'description' => 'Client service, publication posting and assistance',
        ],
        'Management Services Division (MSD)' => [
            'systems' => [
                ['id' => 'RFCS',      'name' => 'FUEL CONSUMPTION REPORT SYSTEM', 'icon' => 'bi bi-fuel-pump',          'description' => 'Monitor and report vehicle fuel consumption'],
                ['id' => 'DVS',       'name' => 'DISBURSEMENT VOUCHER SYSTEM',     'icon' => 'bi bi-cash-stack',         'description' => 'Process and track financial disbursements'],
                ['id' => 'MSDESERVE', 'name' => 'MsDeServe',                       'icon' => 'bi bi-box-arrow-in-right', 'description' => 'Management services and delivery system'],
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
                ['id' => 'PROCUREMENT', 'name' => 'BID AND PROCUREMENT PORTAL', 'icon' => 'bi bi-briefcase', 'description' => 'Procurement documents'],
            ],
            'icon'        => 'bi bi-briefcase',
            'description' => 'Procurement',
        ],
        'Performance Management Team (PMT)' => [
            'systems' => [
                ['id' => 'PMS', 'name' => 'INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR) SYSTEM', 'icon' => 'bi bi-graph-up-arrow', 'description' => 'Manage and track employee performance commitments and reviews'],
            ],
            'icon'        => 'bi bi-graph-up-arrow',
            'description' => 'Employee performance management and tracking',
        ],
    ];

    $categories = [
        'Regional Support Units' => [
            'icon' => 'bi-building',
            'type' => 'rsu',
            'keys' => [
                'Human Resource Division (HRD)',
                'Examination Services Division (ESD)',
                'Public Assistance and Liaison Division (PALD)',
                'Management Services Division (MSD)',
                'Policies and Systems Evaluation Division (PSED)',
                'Legal Services Division (LSD)',
            ],
        ],
        'Committees / Groups' => [
            'icon' => 'bi-diagram-3',
            'type' => 'committee',
            'keys' => [
                'Information Technology Group (ITG)',
                'Gender and Development',
                'Bids and Awards Committee (BAC)',
                'Performance Management Team (PMT)',
            ],
        ],
    ];

    $directories = $allDivisions;
    ?>

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

                <div id="recentlyAccessedSection" class="recently-accessed" style="display:none;">
                    <div class="section-label">
                        <i class="bi bi-clock-history" aria-hidden="true"></i> Recently Accessed
                    </div>
                    <div class="recent-strip" id="recentStrip"></div>
                </div>

                <div class="dashboard-toolbar">
                    <div class="search-wrapper">
                        <i class="bi bi-search search-icon-left" aria-hidden="true"></i>
                        <input
                            type="search"
                            id="divisionSearch"
                            class="search-input"
                            placeholder="Search divisions or systems… (Ctrl+/)"
                            aria-label="Search divisions and systems"
                            autocomplete="off">
                        <button class="search-clear" id="divSearchClear" aria-label="Clear search" tabindex="-1">
                            <i class="bi bi-x" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <!-- ── Category Sections ───────────────────────── -->
                <div id="divisionGrid">
                    <?php foreach ($categories as $catName => $catMeta): ?>
                        <?php
                        $catDivisions = array_intersect_key($allDivisions, array_flip($catMeta['keys']));
                        $catDivisions = array_merge(array_flip($catMeta['keys']), $catDivisions);
                        $catDivisions = array_filter($catDivisions, 'is_array');
                        $totalSystems = array_sum(array_map(fn($d) => count($d['systems']), $catDivisions));
                        ?>
                        <div class="category-section" data-category="<?= htmlspecialchars($catName) ?>">

                            <div class="category-section-label cat-<?= $catMeta['type'] ?>">
                                <span class="label-icon" aria-hidden="true">
                                    <i class="bi <?= htmlspecialchars($catMeta['icon']) ?>"></i>
                                </span>
                                <?= htmlspecialchars($catName) ?>
                                <?php if ($catMeta['type'] !== 'committee'): ?>
                                    <span class="category-count-badge">
                                        <?= count($catDivisions) ?> division<?= count($catDivisions) !== 1 ? 's' : '' ?>
                                        &middot;
                                        <?= $totalSystems ?> system<?= $totalSystems !== 1 ? 's' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="division-grid">
                                <?php foreach ($catDivisions as $division => $data): ?>
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
                    <?php endforeach; ?>
                </div>

            </div><!-- /#directoryView -->

            <!-- ── Subdirectory View ──────────────────────────── -->
            <div id="subdirectoryView" class="d-none">

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
                            autocomplete="off">
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

    <script>
        $(function() {

            /* ============================================================
               DATA & CONSTANTS
               ============================================================ */
            const systemsData = <?= json_encode($directories, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
            const CACHE_DURATION = 5 * 60 * 1000; // 5 min
            const RETRY_ATTEMPTS = 3;
            const RETRY_DELAY = 1000;
            const RECENT_KEY = 'imis_recently_accessed';
            const RECENT_MAX = 5;

            /*
             * FIX [Bug 2]: Read the session userId once at startup and scope all
             * "access_acknowledged" keys to this specific user.
             * Previously the key was just `access_acknowledged_${systemId}`, which
             * persisted across user switches on the same browser tab.
             */
            const SESSION_USER_ID = ($('#userId').val() || '').trim();

            /* ── DOM references ──────────────────────────────────────── */
            const $directoryView = $('#directoryView');
            const $subdirectoryView = $('#subdirectoryView');
            const $systemGrid = $('#systemGrid');
            const $divisionGrid = $('#divisionGrid');
            const $backBtn = $('#backToDirectory');
            const $bcDivision = $('#bcDivision');
            const $bcHome = $('#bcHome');
            const $divSearch = $('#divisionSearch');
            const $divSearchClear = $('#divSearchClear');
            const $sysSearch = $('#systemSearch');
            const $sysSearchClear = $('#sysSearchClear');

            let currentDivision = null;

            /* ── Access data cache ───────────────────────────────────── */
            /*
             * FIX [Bug 1]: Added a `loaded` boolean flag that is set to true
             * whenever set() is called, regardless of whether the access map is
             * empty or not.
             *
             * Previously ok() checked `this.data !== null`. When the server
             * returned `access: null` (old API behaviour for users with no role
             * assignments), set(null) stored null and ok() returned false on every
             * subsequent call — permanently bypassing the cache and re-fetching
             * permissions on every division open.
             *
             * With the parallel fix in api/user-access.php (always returning an
             * object cast {}), `data` will always be a plain object now. The
             * `loaded` flag makes the cache logic explicit and immune to any future
             * falsy-value edge cases from the API.
             */
            const accessCache = {
                data: null,
                ts: 0,
                loaded: false,
                ok() {
                    return this.loaded && (Date.now() - this.ts) < CACHE_DURATION;
                },
                set(d) {
                    this.data = d;
                    this.ts = Date.now();
                    this.loaded = true; // set regardless of whether d is empty
                },
                clear() {
                    this.data = null;
                    this.ts = 0;
                    this.loaded = false;
                },
            };

            /* ============================================================
               UTILITIES
               ============================================================ */
            function debounce(fn, ms) {
                let t;
                return function(...a) {
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(this, a), ms);
                };
            }

            function retry(op, n = RETRY_ATTEMPTS) {
                return new Promise((resolve, reject) => {
                    let attempts = 0;

                    function attempt() {
                        attempts++;
                        Promise.resolve(op())
                            .then(resolve)
                            .catch(err => {
                                if (attempts >= n) return reject(err);
                                setTimeout(attempt, RETRY_DELAY * attempts);
                            });
                    }
                    attempt();
                });
            }

            /* ============================================================
               AJAX HELPERS
               ============================================================ */

            /**
             * Fetches the current user's system permissions from the server.
             * Used only for UI rendering (enabled / disabled badge display).
             * Returns the raw access row object.
             *
             * FIX [Bug 3]: Previously resolved with `response.access` which could
             * be null (old API). A null resolution was treated as success by retry(),
             * making the retry wrapper ineffective. Now we explicitly reject if
             * `response.access` is undefined (i.e. the shape is unexpected), so
             * retry() can actually retry a malformed response.
             */
            function fetchUserAccess() {
                return $.ajax({
                    url: '/api/user-access.php',
                    method: 'GET',
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: false,
                }).then(response => {
                    // access must be present (may be an empty object {})
                    if (response?.success && response.access !== undefined) {
                        return response.access;
                    }
                    return Promise.reject(
                        new Error(response?.error || 'Access fetch failed')
                    );
                });
            }

            /**
             * Asks the server to resolve a system redirect.
             * The server looks up the user's role, performs any required session
             * setup (CTS / RFCS / ICTSRTS), and returns the destination URL.
             *
             * Returns the full response object so callers can inspect `external`.
             */
            function fetchSystemRedirect(system) {
                return $.ajax({
                    url: '/api/system-redirect.php',
                    method: 'POST',
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    data: {
                        system
                    },
                }).then(response => {
                    if (response?.success && response?.redirect) return response;
                    return Promise.reject(
                        new Error(response?.message || 'Redirect failed')
                    );
                });
            }

            /* ============================================================
               GREETING
               ============================================================ */
            (function() {
                const name = ($('#userName').val() || '').trim().split(' ')[0];
                const h = new Date().getHours();
                const part = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
                document.getElementById('welcomeGreeting').textContent =
                    name ? `${part}, ${name}! 👋` : `${part}!`;
            })();

            /* ============================================================
               TOAST SYSTEM
               ============================================================ */
            const TOAST_ICONS = {
                success: 'bi-check-circle-fill',
                error: 'bi-x-circle-fill',
                warning: 'bi-exclamation-triangle-fill',
                info: 'bi-info-circle-fill',
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
                try {
                    return JSON.parse(sessionStorage.getItem(RECENT_KEY) || '[]');
                } catch {
                    return [];
                }
            }

            function saveRecent(systemId, systemName, divisionName) {
                let r = getRecent().filter(x => x.id !== systemId);
                r.unshift({
                    id: systemId,
                    name: systemName,
                    division: divisionName
                });
                sessionStorage.setItem(RECENT_KEY, JSON.stringify(r.slice(0, RECENT_MAX)));
            }

            function renderRecentStrip() {
                const recent = getRecent();
                const $section = $('#recentlyAccessedSection');
                const $strip = $('#recentStrip');
                if (!recent.length) {
                    $section.hide();
                    return;
                }

                $strip.empty();
                recent.forEach(item => {
                    let icon = 'bi-app';
                    for (const [, div] of Object.entries(systemsData)) {
                        const s = div.systems.find(s => s.id === item.id);
                        if (s) {
                            icon = s.icon;
                            break;
                        }
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
                $('.division-card').each(function() {
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
               SEARCH — Divisions (category-aware)
               ============================================================ */
            function filterDivisions(q) {
                q = q.trim().toLowerCase();
                let totalVisible = 0;
                $divisionGrid.find('> .empty-state').remove();

                $('.category-section').each(function() {
                    const $section = $(this);
                    let sectionVisible = 0;

                    $section.find('.division-card').each(function() {
                        const divName = $(this).data('division').toLowerCase();
                        const desc = $(this).find('.division-subtitle').text().toLowerCase();
                        const divData = systemsData[$(this).data('division')];
                        const sysNames = (divData?.systems || []).map(s => s.name.toLowerCase()).join(' ');
                        const match = !q || divName.includes(q) || desc.includes(q) || sysNames.includes(q);

                        $(this)
                            .toggleClass('search-hidden', !match)
                            .toggleClass('search-match', !!q && match);
                        if (match) {
                            sectionVisible++;
                            totalVisible++;
                        }
                    });

                    $section.toggleClass('search-hidden', sectionVisible === 0 && !!q);
                });

                if (totalVisible === 0 && q) {
                    if (!$divisionGrid.find('> .empty-state').length) {
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
                    $divisionGrid.find('> .empty-state').remove();
                    if (!q) $('.division-card').removeClass('search-match');
                }
            }

            $divSearch.on('input', debounce(function() {
                const v = $(this).val();
                $divSearchClear.toggleClass('visible', !!v);
                filterDivisions(v);
            }, 200));

            $divSearchClear.on('click', () => $divSearch.val('').trigger('input').focus());

            /* ── Search — Systems ────────────────────────────────────── */
            function filterSystems(q) {
                q = q.trim().toLowerCase();
                let visible = 0;
                $('#systemGrid .system-card').each(function() {
                    const name = $(this).find('.system-title').text().toLowerCase();
                    const desc = $(this).find('.system-description').text().toLowerCase();
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

            $sysSearch.on('input', debounce(function() {
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
                if (!divData?.systems) {
                    showToast('Error', 'Division data not found.', 'error');
                    return;
                }

                currentDivision = division;
                $bcDivision.text(division.replace(/ \(.*\)/, '')).attr('title', division);
                $sysSearch.val('');
                $sysSearchClear.removeClass('visible');

                renderSkeletons(divData.systems.length);
                $directoryView.addClass('d-none');
                $subdirectoryView.removeClass('d-none');

                fetchAndRenderSystems(divData.systems);
            }

            $divisionGrid.on('click keydown', '.division-card', function(e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
                if (e.type === 'keydown') e.preventDefault();
                openDivision($(this).data('division'));
            });

            function goBack() {
                $subdirectoryView.addClass('d-none');
                $directoryView.removeClass('d-none');
                currentDivision = null;
                $divSearch.val('').trigger('input');
                renderRecentStrip();
                markRecentCards();
            }

            $backBtn.on('click', goBack);
            $bcHome.on('click keydown', function(e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
                goBack();
            });

            /* ============================================================
               SYSTEM CARDS — render after access data resolves
               ============================================================ */

            /**
             * Determines the visual state of a system button.
             * Role is used only for the UI badge — routing is always server-side.
             * ERIS is always enabled (None role → public guest page on the server).
             *
             * FIX [Bug 2]: The `acknowledged` key is now scoped to SESSION_USER_ID
             * so a modal dismissal by User A does not silently disable the button
             * for User B who logs in on the same browser tab.
             */
            function resolveState(systemId, accessRow) {
                const key = systemId.toUpperCase();
                const role = accessRow?.[key] ?? 'None';
                const ackKey = `access_ack_${SESSION_USER_ID}_${systemId}`;
                const acknowledged = sessionStorage.getItem(ackKey);

                // ERIS is always enabled — None role maps to public guest page server-side
                if (systemId === 'ERIS') {
                    return {
                        cls: 'enabled',
                        content: buildBtn(role),
                        disabled: false,
                        role
                    };
                }

                if (role === 'None') {
                    return {
                        cls: 'disabled',
                        content: '<i class="bi bi-lock-fill" aria-hidden="true"></i> No Access',
                        disabled: !!acknowledged,
                        role: 'None',
                    };
                }

                return {
                    cls: 'enabled',
                    content: buildBtn(role),
                    disabled: false,
                    role
                };
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
                    const tip = state.cls === 'disabled' && !state.disabled ?
                        'data-imis-tip="You do not have access. Contact your administrator."' :
                        '';

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

                setTimeout(() => {
                    $('.system-card').each(function() {
                        const $btn = $(this).find('.manage-btn');
                        const $inf = $(this).find('.system-info');
                        if (!$btn.hasClass('disabled') && !$inf.find('.status-indicator').length) {
                            $inf.prepend('<span class="status-indicator online" title="System Online" aria-label="System Online"></span>');
                        }
                    });
                    $('.fade-in').each(function() {
                        observer.observe(this);
                    });
                }, 800);
            }

            /* ============================================================
               FETCH USER ACCESS (for UI rendering only)
               ============================================================ */
            async function fetchAndRenderSystems(systems) {
                if (accessCache.ok()) {
                    renderSystemCards(systems, accessCache.data);
                    showToast('Access Loaded', 'Your permissions have been retrieved.', 'success', 2500);
                    return;
                }

                try {
                    const accessRow = await retry(fetchUserAccess);
                    accessCache.set(accessRow);
                    renderSystemCards(systems, accessRow);
                    showToast('Access Loaded', 'Your permissions have been retrieved.', 'success', 2500);
                } catch (err) {
                    console.error('[dashboard] fetchAndRenderSystems error:', err);
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

            /*
             * FIX [Bug 2]: The acknowledged key now includes SESSION_USER_ID so
             * the dismissal state is scoped per-user on a shared browser tab.
             * Previously `access_acknowledged_${id}` bled across user sessions
             * within the same tab's sessionStorage lifetime.
             */
            $subdirectoryView.on('click', '.manage-btn.disabled:not([disabled])', function() {
                const id = $(this).data('id');
                const name = $(this).data('sysname');
                const $btn = $(this);
                const ackKey = `access_ack_${SESSION_USER_ID}_${id}`;

                Swal.fire({
                    title: 'Restricted System Access',
                    html: `<div class="text-center">
                               <p class="mb-2">You do not have access to <strong>${name}</strong>.</p>
                               <p class="mb-2">This system requires authorization from your administrator.</p>
                               <p class="text-muted small mb-0">Contact ITG or your division head to request access.</p>
                           </div>`,
                    icon: 'warning',
                    confirmButtonText: 'Understood',
                    confirmButtonColor: '#0077b6',
                    allowOutsideClick: false,
                }).then(r => {
                    if (r.isConfirmed) {
                        sessionStorage.setItem(ackKey, 'true'); // scoped to this user
                        $btn.prop('disabled', true).removeAttr('data-imis-tip');
                    }
                });
            });

            /* ============================================================
               SYSTEM ACCESS — redirect via server
               ─────────────────────────────────────────────────────────────
               The client posts only the system ID. All routing logic lives in
               api/system-redirect.php — there is no client-side switch here.
               ============================================================ */
            $subdirectoryView.on('click', '.manage-btn.enabled', debounce(async function() {
                const $btn = $(this);
                const system = $btn.data('id');
                const sysName = $btn.data('sysname');
                const orig = $btn.html();

                if (currentDivision) saveRecent(system, sysName, currentDivision);

                $btn.prop('disabled', true).html('<div class="loading-spinner"></div> Accessing…');

                try {
                    await redirectToSystem(system);
                } catch (err) {
                    console.error('[dashboard] redirectToSystem error:', err);
                    $btn.prop('disabled', false).html(orig);
                    showToast(
                        'Error',
                        navigator.onLine ?
                        (err?.message || 'An unexpected error occurred.') :
                        'You appear to be offline.',
                        'error'
                    );
                }
            }, 500));

            /**
             * Resolves the redirect URL from the server and navigates.
             * External systems (MSDESERVE, COMEXAMS) open in a new tab.
             * The server signals this via `response.external = true`.
             */
            async function redirectToSystem(system) {
                const result = await fetchSystemRedirect(system);

                if (result.external) {
                    window.open(result.redirect, '_blank', 'noopener,noreferrer');
                } else {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 100);
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
            }, {
                threshold: 0.08,
                rootMargin: '50px'
            });

            $('.fade-in').each(function() {
                observer.observe(this);
            });

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
            $(document).on('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                    e.preventDefault();
                    ($directoryView.is(':visible') ? $divSearch : $sysSearch).focus().select();
                }
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