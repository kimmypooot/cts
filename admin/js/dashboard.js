/**
 * js/dashboard.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Super Administrator Dashboard — data loading, chart rendering, and UI logic.
 *
 * Dependencies (loaded by the parent page):
 *   jQuery 3.x · Bootstrap 5 · Chart.js · SweetAlert2
 * ─────────────────────────────────────────────────────────────────────────────
 */

'use strict';

(function ($) {

    /* ── Chart instances (kept for destroy-on-refresh) ─────────────────── */
    const charts = {};

    /* ── NiceAdmin primary palette ──────────────────────────────────────── */
    const PALETTE = {
        primary   : '#4154f1',
        success   : '#2eca6a',
        warning   : '#ff771d',
        danger    : '#f82b2b',
        info      : '#0dcaf0',
        muted     : '#899bbd',
        light     : '#f6f9ff',
        grid      : 'rgba(137,155,189,0.15)',
    };

    /* Extended colour array for multi-series charts */
    const CHART_COLORS = [
        '#4154f1','#2eca6a','#ff771d','#0dcaf0','#f82b2b',
        '#6f42c1','#fd7e14','#20c997','#e83e8c','#ffc107',
        '#17a2b8','#6610f2',
    ];

    /* ── Bootstrap ──────────────────────────────────────────────────────── */
    $(document).ready(function () {
        loadDashboard();
        bindRefreshBtn();
    });

    /* ══════════════════════════════════════════════════════════════════════
       DATA LOADING
       ══════════════════════════════════════════════════════════════════════ */
    function loadDashboard() {
        setLoadingState(true);

        $.ajax({
            url     : 'backend/fetch/fetch_dashboard_stats.php',
            type    : 'GET',
            dataType: 'json',
            success : function (res) {
                if (!res.success) {
                    showFetchError(res.message || 'Failed to load dashboard data.');
                    return;
                }
                renderAll(res);
                $('#generatedAt').text('Last updated: ' + formatDateTime(res.generated_at));
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Server error. Please try again.';
                showFetchError(msg);
            },
            complete: function () {
                setLoadingState(false);
            }
        });
    }

    function bindRefreshBtn() {
        $('#refreshDashboardBtn').on('click', function () {
            const $icon = $(this).find('i');
            $icon.addClass('spin');
            loadDashboard();
            setTimeout(() => $icon.removeClass('spin'), 800);
        });
    }

    /* ══════════════════════════════════════════════════════════════════════
       RENDER ALL WIDGETS
       ══════════════════════════════════════════════════════════════════════ */
    function renderAll(res) {
        renderSummaryCards(res.summary);
        renderLoginTrendChart(res.login_trend);
        renderDivisionChart(res.users_by_division);
        renderAccessByProjectChart(res.access_by_project);
        renderRoleDonut(res.role_distribution);
        renderTopSystemsTable(res.top_systems);
        renderRecentLoginsTable(res.recent_logins);
        renderNoAccessTable(res.users_no_access);
        animateCounters();
    }

    /* ══════════════════════════════════════════════════════════════════════
       SUMMARY CARDS
       ══════════════════════════════════════════════════════════════════════ */
    function renderSummaryCards(s) {
        /* Total Users */
        setCounter('#cardTotalUsers',    s.total_users);
        setCounter('#cardActiveUsers',   s.active_users);
        setCounter('#cardInactiveUsers', s.inactive_users);

        /* Systems */
        setCounter('#cardTotalSystems',  s.total_projects);
        setCounter('#cardActiveSystems', s.active_projects);

        /* Access */
        setCounter('#cardAccessGrants',       s.total_access_grants);
        setCounter('#cardUsersWithAccess',    s.users_with_access);
        setCounter('#cardUsersWithoutAccess', s.users_without_access);

        /* Logins */
        setCounter('#cardLoginsToday',   s.logins_today);
        setCounter('#cardActiveSessions', s.active_sessions);

        /* Progress bars */
        const accessPct = s.active_users > 0
            ? Math.round((s.users_with_access / s.active_users) * 100)
            : 0;
        $('#accessProgressBar')
            .css('width', accessPct + '%')
            .attr('aria-valuenow', accessPct)
            .text(accessPct + '%');

        const activePct = s.total_projects > 0
            ? Math.round((s.active_projects / s.total_projects) * 100)
            : 0;
        $('#systemsProgressBar')
            .css('width', activePct + '%')
            .attr('aria-valuenow', activePct);
    }

    /* ══════════════════════════════════════════════════════════════════════
       CHARTS
       ══════════════════════════════════════════════════════════════════════ */

    /* ── Login trend (14-day area line) ───────────────────────────────── */
    function renderLoginTrendChart(data) {
        destroyChart('loginTrend');
        const ctx = document.getElementById('loginTrendChart');
        if (!ctx) return;

        const labels = data.map(d => formatShortDate(d.date));
        const counts = data.map(d => d.count);
        const maxVal = Math.max(...counts, 1);

        charts.loginTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label      : 'Logins',
                    data       : counts,
                    fill       : true,
                    tension    : 0.4,
                    borderColor: PALETTE.primary,
                    borderWidth: 2.5,
                    pointBackgroundColor: PALETTE.primary,
                    pointRadius : 4,
                    pointHoverRadius: 6,
                    backgroundColor: createGradient(ctx, PALETTE.primary),
                }]
            },
            options: baseLineOptions({
                yMax  : maxVal + Math.ceil(maxVal * 0.2),
                title : 'Login Activity — Last 14 Days',
            })
        });
    }

    /* ── Users by division (horizontal bar) ───────────────────────────── */
    function renderDivisionChart(data) {
        destroyChart('divisionChart');
        const ctx = document.getElementById('divisionChart');
        if (!ctx) return;

        const labels = data.map(d => abbreviate(d.division, 30));
        const counts = data.map(d => d.count);

        charts.divisionChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label          : 'Active Users',
                    data           : counts,
                    backgroundColor: CHART_COLORS.map(c => c + 'cc'),
                    borderColor    : CHART_COLORS,
                    borderWidth    : 1.5,
                    borderRadius   : 4,
                }]
            },
            options: {
                indexAxis : 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend : { display: false },
                    tooltip: tooltipStyle(),
                },
                scales: {
                    x: gridAxis({ title: 'Users' }),
                    y: { ...tickAxis(), ticks: { font: { size: 11 } } },
                }
            }
        });
    }

    /* ── Access by project (vertical bar) ────────────────────────────── */
    function renderAccessByProjectChart(data) {
        destroyChart('accessProject');
        const ctx = document.getElementById('accessProjectChart');
        if (!ctx) return;

        const labels = data.map(d => abbreviate(d.project_name, 20));
        const counts = data.map(d => d.count);

        charts.accessProject = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label          : 'Assigned Users',
                    data           : counts,
                    backgroundColor: PALETTE.primary + 'bb',
                    borderColor    : PALETTE.primary,
                    borderWidth    : 1.5,
                    borderRadius   : 4,
                    hoverBackgroundColor: PALETTE.primary,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend : { display: false },
                    tooltip: tooltipStyle(),
                },
                scales: {
                    x: { ...tickAxis(), ticks: { font: { size: 10 }, maxRotation: 35 } },
                    y: gridAxis({ title: 'Users Assigned' }),
                }
            }
        });
    }

    /* ── IMIS Role donut ──────────────────────────────────────────────── */
    function renderRoleDonut(data) {
        destroyChart('roleDonut');
        const ctx = document.getElementById('roleDonutChart');
        if (!ctx) return;

        const roleColors = {
            superadmin: PALETTE.danger,
            admin      : PALETTE.warning,
            user       : PALETTE.primary,
            none       : PALETTE.muted,
        };

        const labels = data.map(d => capitalize(d.role));
        const counts = data.map(d => d.count);
        const colors = data.map(d => roleColors[d.role] ?? PALETTE.muted);

        charts.roleDonut = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data           : counts,
                    backgroundColor: colors,
                    borderColor    : '#fff',
                    borderWidth    : 3,
                    hoverOffset    : 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding   : 12,
                            font      : { size: 12 },
                            usePointStyle: true,
                        }
                    },
                    tooltip: tooltipStyle(),
                }
            }
        });
    }

    /* ══════════════════════════════════════════════════════════════════════
       TABLES
       ══════════════════════════════════════════════════════════════════════ */

    function renderTopSystemsTable(data) {
        const $tbody = $('#topSystemsTbody').empty();
        if (!data.length) {
            $tbody.html('<tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr>');
            return;
        }
        const maxCount = data[0]?.user_count || 1;
        data.forEach((row, i) => {
            const pct = Math.round((row.user_count / maxCount) * 100);
            const badge = row.is_active
                ? '<span class="badge bg-success-light text-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';

            $tbody.append(`
                <tr>
                    <td class="pe-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rank-pill">${i + 1}</span>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate" style="max-width:200px;font-size:13px">
                                    ${escHtml(row.project_name)}
                                </div>
                                <code class="text-primary" style="font-size:11px">${escHtml(row.code_name)}</code>
                            </div>
                        </div>
                    </td>
                    <td class="align-middle text-center">${badge}</td>
                    <td class="align-middle" style="min-width:120px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px;border-radius:3px">
                                <div class="progress-bar bg-primary" style="width:${pct}%"></div>
                            </div>
                            <span class="text-muted small fw-semibold">${row.user_count}</span>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function renderRecentLoginsTable(data) {
        const $tbody = $('#recentLoginsTbody').empty();
        if (!data.length) {
            $tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">No recent login activity.</td></tr>');
            return;
        }
        data.forEach(row => {
            const statusBadge = row.status === 'active'
                ? '<span class="badge rounded-pill" style="background:#d1fae5;color:#065f46;font-size:10px">●&nbsp;Online</span>'
                : row.status === 'timeout'
                    ? '<span class="badge rounded-pill bg-warning text-dark" style="font-size:10px">Timeout</span>'
                    : '<span class="badge rounded-pill bg-light text-muted border" style="font-size:10px">Logged Out</span>';

            $tbody.append(`
                <tr>
                    <td>
                        <div class="fw-semibold" style="font-size:13px">${escHtml(row.name || row.username)}</div>
                        <div class="text-muted" style="font-size:11px">@${escHtml(row.username)}</div>
                    </td>
                    <td class="text-center">
                        <code style="font-size:11px">${escHtml(row.ip_address)}</code>
                    </td>
                    <td style="font-size:12px">${formatDateTime(row.login_time)}</td>
                    <td style="font-size:12px">${row.logout_time ? formatDateTime(row.logout_time) : '<span class="text-muted">—</span>'}</td>
                    <td class="text-center">${statusBadge}</td>
                </tr>
            `);
        });
    }

    function renderNoAccessTable(data) {
        const $tbody = $('#noAccessTbody').empty();
        const $badge = $('#noAccessCount');

        $badge.text(data.length > 0 ? data.length + '+' : '0');

        if (!data.length) {
            $tbody.html('<tr><td colspan="3" class="text-center text-muted py-3"><i class="bi bi-shield-check me-1"></i>All active users have system access.</td></tr>');
            return;
        }
        data.forEach(row => {
            const roleBadge = `<span class="badge bg-secondary text-capitalize" style="font-size:10px">${escHtml(row.role)}</span>`;
            $tbody.append(`
                <tr>
                    <td>
                        <div class="fw-semibold" style="font-size:13px">${escHtml(row.name)}</div>
                        <div class="text-muted" style="font-size:11px">${escHtml(row.position)}</div>
                    </td>
                    <td style="font-size:12px">${escHtml(row.fo_rsu)}</td>
                    <td class="text-center">${roleBadge}</td>
                </tr>
            `);
        });
    }

    /* ══════════════════════════════════════════════════════════════════════
       CHART HELPERS
       ══════════════════════════════════════════════════════════════════════ */
    function createGradient(ctx, color) {
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0,   color + '44');
        gradient.addColorStop(1,   color + '00');
        return gradient;
    }

    function baseLineOptions({ yMax, title }) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend : { display: false },
                tooltip: tooltipStyle(),
            },
            scales: {
                x: tickAxis(),
                y: {
                    ...gridAxis({ title }),
                    suggestedMax: yMax,
                    min: 0,
                    ticks: { precision: 0 },
                }
            }
        };
    }

    function gridAxis({ title = '' } = {}) {
        return {
            grid: { color: PALETTE.grid, drawBorder: false },
            ticks: { font: { size: 11 }, color: PALETTE.muted },
            title: title
                ? { display: true, text: title, font: { size: 11 }, color: PALETTE.muted }
                : { display: false },
        };
    }

    function tickAxis() {
        return {
            grid: { display: false },
            ticks: { font: { size: 11 }, color: PALETTE.muted },
        };
    }

    function tooltipStyle() {
        return {
            backgroundColor: 'rgba(1,41,112,0.88)',
            titleColor     : '#fff',
            bodyColor      : '#cdd9f5',
            padding        : 10,
            cornerRadius   : 6,
            displayColors  : true,
            boxPadding     : 4,
        };
    }

    function destroyChart(key) {
        if (charts[key]) { charts[key].destroy(); delete charts[key]; }
    }

    /* ══════════════════════════════════════════════════════════════════════
       UI HELPERS
       ══════════════════════════════════════════════════════════════════════ */
    function setCounter(selector, value) {
        $(selector).attr('data-target', value).text('0');
    }

    function animateCounters() {
        $('[data-target]').each(function () {
            const $el    = $(this);
            const target = parseInt($el.attr('data-target'), 10) || 0;
            const duration = 900;
            const step = Math.ceil(target / (duration / 16));
            let current = 0;
            const timer = setInterval(() => {
                current = Math.min(current + step, target);
                $el.text(current.toLocaleString());
                if (current >= target) clearInterval(timer);
            }, 16);
        });
    }

    function setLoadingState(loading) {
        if (loading) {
            $('#dashboardContent').addClass('opacity-50 pe-none');
            $('#dashboardSpinner').removeClass('d-none');
        } else {
            $('#dashboardContent').removeClass('opacity-50 pe-none');
            $('#dashboardSpinner').addClass('d-none');
        }
    }

    function showFetchError(msg) {
        Swal.fire({
            icon : 'error',
            title: 'Dashboard Error',
            text : msg,
            confirmButtonColor: '#4154f1',
        });
    }

    /* ── String / date helpers ──────────────────────────────────────────── */
    function formatDateTime(dt) {
        if (!dt) return '—';
        const d = new Date(dt.replace(' ', 'T'));
        if (isNaN(d)) return dt;
        return d.toLocaleString('en-PH', {
            month : 'short', day: '2-digit',
            hour  : '2-digit', minute: '2-digit', hour12: true
        });
    }

    function formatShortDate(dt) {
        const d = new Date(dt);
        return d.toLocaleString('en-PH', { month: 'short', day: '2-digit' });
    }

    function abbreviate(str, max) {
        if (!str) return '';
        return str.length > max ? str.substring(0, max - 1) + '…' : str;
    }

    function capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function escHtml(text) {
        if (text == null) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

}(jQuery));