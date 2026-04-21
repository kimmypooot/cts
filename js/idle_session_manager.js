/**
 * imis/js/idle_session_manager.js
 *
 * Idle Session Manager — monitors user activity and handles automatic logout.
 *
 * FIX HISTORY
 * -----------
 * v1 bug: handleActivity() set this.warningShown = false then immediately
 *   checked `if (this.warningShown && ...)`, which was always false — the
 *   open warning modal was never closed when the user resumed activity.
 *
 * v1 bug: performLogout() had fragile relative-path arithmetic that broke
 *   in deep subdirectories (e.g. /cts/admin/index).  Replaced with a single
 *   absolute path that works regardless of subdirectory depth.
 *
 * v1 bug: startMonitoring() could spawn a second interval if called while one
 *   was already running (e.g. after updateConfig()).
 */
class IdleSessionManager {

    /**
     * @param {object} options
     * @param {number} [options.idleTime=3600000]      ms before forced logout (default 60 min)
     * @param {number} [options.warningTime=3420000]   ms before warning shown (default 57 min)
     * @param {number} [options.checkInterval=5000]    polling interval in ms
     * @param {number} [options.throttleDelay=200]     activity-event throttle in ms
     * @param {string} [options.logoutUrl='/inc/logout?reason=idle']  absolute logout path
     */
    constructor(options = {}) {
        this.idleTime = options.idleTime ?? 3_600_000;
        this.warningTime = options.warningTime ?? 3_420_000;
        this.checkInterval = options.checkInterval ?? 5_000;
        this.throttleDelay = options.throttleDelay ?? 200;
        this.logoutUrl = options.logoutUrl ?? '/inc/logout?reason=idle';

        this.lastActivity = Date.now();
        this.warningShown = false;
        this.isIdle = false;
        this.isDestroyed = false;

        this.intervalId = null;
        this.throttleTimeoutId = null;

        // Pre-bind so we can remove the same references in destroy()
        this._onActivity = this._throttle.bind(this);

        this._init();
    }

    // ── Initialisation ──────────────────────────────────────────────────────

    _init() {
        if (this.isDestroyed) return;

        const passive = { passive: true, capture: true };
        const active = { capture: true };

        document.addEventListener('mousedown', this._onActivity, active);
        document.addEventListener('keydown', this._onActivity, active);
        document.addEventListener('touchstart', this._onActivity, passive);
        document.addEventListener('scroll', this._onActivity, passive);
        document.addEventListener('click', this._onActivity, active);

        this._startMonitoring();
    }

    // ── Activity tracking ───────────────────────────────────────────────────

    /**
     * Throttled wrapper — prevents hundreds of calls per second during scroll/type.
     */
    _throttle() {
        if (this.throttleTimeoutId !== null) return;

        this.throttleTimeoutId = setTimeout(() => {
            this.throttleTimeoutId = null;
            this._recordActivity();
        }, this.throttleDelay);
    }

    /**
     * Record that the user is active.
     *
     * FIX: Previously the modal-close check happened AFTER warningShown was set
     * to false, so Swal.isVisible() was tested but the `if (this.warningShown)`
     * guard was never true.  Now we snapshot the pre-reset state first.
     */
    _recordActivity() {
        if (this.isDestroyed || this.isIdle) return;

        const wasWarning = this.warningShown;  // snapshot BEFORE reset

        this.lastActivity = Date.now();
        this.warningShown = false;

        // Close the warning modal if it was open when the user resumed activity
        if (wasWarning && this._isModalVisible()) {
            const title = Swal.getTitle();
            if (title?.textContent?.includes('Session Timeout Warning')) {
                Swal.close();
            }
        }
    }

    // ── Monitoring loop ─────────────────────────────────────────────────────

    _startMonitoring() {
        // Guard: never spawn a second interval
        if (this.intervalId !== null || this.isDestroyed) return;

        this.intervalId = setInterval(() => {
            if (this.isDestroyed || this.isIdle) {
                this._stopMonitoring();
                return;
            }

            const idleFor = Date.now() - this.lastActivity;

            if (idleFor >= this.idleTime) {
                // Hard timeout — log out regardless of modal state
                this._logout();
                return;
            }

            if (idleFor >= this.warningTime && !this.warningShown) {
                this._showWarning();
            }
        }, this.checkInterval);
    }

    _stopMonitoring() {
        if (this.intervalId !== null) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        if (this.throttleTimeoutId !== null) {
            clearTimeout(this.throttleTimeoutId);
            this.throttleTimeoutId = null;
        }
    }

    // ── Warning modal ───────────────────────────────────────────────────────

    _showWarning() {
        if (this.warningShown || this.isDestroyed) return;
        this.warningShown = true;

        const remainingMs = this.idleTime - (Date.now() - this.lastActivity);
        const initialSecs = Math.max(1, Math.ceil(remainingMs / 1000));

        let timerInterval;

        Swal.fire({
            title: 'Session Timeout Warning',
            html: `Your session will expire in <strong>${this._formatTime(initialSecs)}</strong>`
                + ` due to inactivity.<br><br>Click <em>Stay Logged In</em> to continue.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-circle"></i> Stay Logged In',
            cancelButtonText: '<i class="bi bi-box-arrow-right"></i> Logout Now',
            allowOutsideClick: false,
            allowEscapeKey: false,
            timer: Math.max(remainingMs, 1000),
            timerProgressBar: true,
            customClass: { popup: 'idle-warning-popup' },

            didOpen: () => {
                timerInterval = setInterval(() => {
                    const left = Swal.getTimerLeft() ?? 0;
                    const el = Swal.getHtmlContainer()?.querySelector('strong');
                    if (el && left > 0) {
                        el.textContent = this._formatTime(Math.ceil(left / 1000));
                    }
                }, 1_000);
            },

            willClose: () => clearInterval(timerInterval),

        }).then(result => {
            if (this.isDestroyed) return;

            if (result.isConfirmed) {
                this._resetTimer();
            } else {
                // Cancelled by user or timer expired
                this._logout();
            }
        }).catch(() => {
            // Swal was closed programmatically (e.g. _recordActivity above)
            this._resetTimer();
        });
    }

    // ── Logout ──────────────────────────────────────────────────────────────

    _logout() {
        if (this.isIdle || this.isDestroyed) return;

        this.isIdle = true;
        this._stopMonitoring();

        if (this._isModalVisible()) Swal.close();

        let timerInterval;

        Swal.fire({
            title: 'Session Expired',
            html: 'You have been logged out due to inactivity.'
                + '<br><br>Redirecting in <strong>3</strong> seconds.',
            icon: 'info',
            timer: 3_000,
            timerProgressBar: true,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            customClass: { popup: 'idle-logout-popup' },

            didOpen: () => {
                Swal.showLoading();
                timerInterval = setInterval(() => {
                    const left = Swal.getTimerLeft() ?? 0;
                    const el = Swal.getHtmlContainer()?.querySelector('strong');
                    if (el) el.textContent = Math.max(1, Math.ceil(left / 1000));
                }, 500);
            },

            willClose: () => {
                clearInterval(timerInterval);
                this._redirect();
            },
        });
    }

    /**
     * FIX: Use a single absolute path instead of fragile relative-path arithmetic.
     *
     * '/inc/logout?reason=idle' works from every page in every subdirectory
     * on the same origin without any path calculation.
     */
    _redirect() {
        try {
            window.location.replace(this.logoutUrl);
        } catch {
            // Last-resort fallback
            window.location.href = this.logoutUrl;
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    _resetTimer() {
        if (this.isDestroyed) return;
        this.lastActivity = Date.now();
        this.warningShown = false;
        this.isIdle = false;
    }

    _isModalVisible() {
        return typeof Swal !== 'undefined' && Swal.isVisible();
    }

    _formatTime(seconds) {
        if (seconds < 60) {
            return `${seconds} second${seconds !== 1 ? 's' : ''}`;
        }
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        const ms = `${m} minute${m !== 1 ? 's' : ''}`;
        return s > 0 ? `${ms} and ${s} second${s !== 1 ? 's' : ''}` : ms;
    }

    isUserLoggedIn() {
        return !!(
            document.querySelector('.nav-profile') ||
            document.getElementById('header')
        );
    }

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Update timing configuration and restart the monitoring loop.
     *
     * @param {object} opts  Same keys as constructor options.
     */
    updateConfig(opts = {}) {
        if (this.isDestroyed) return;

        if (opts.idleTime) this.idleTime = opts.idleTime;
        if (opts.warningTime) this.warningTime = opts.warningTime;
        if (opts.checkInterval) this.checkInterval = opts.checkInterval;
        if (opts.logoutUrl) this.logoutUrl = opts.logoutUrl;

        this._stopMonitoring();
        this._resetTimer();
        this._startMonitoring();
    }

    /**
     * Remove all listeners and timers.  Call when navigating away via SPA router.
     */
    destroy() {
        this.isDestroyed = true;
        this._stopMonitoring();

        document.removeEventListener('mousedown', this._onActivity, true);
        document.removeEventListener('keydown', this._onActivity, true);
        document.removeEventListener('touchstart', this._onActivity, true);
        document.removeEventListener('scroll', this._onActivity, true);
        document.removeEventListener('click', this._onActivity, true);

        if (this._isModalVisible()) Swal.close();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────────────────

function initializeIdleManager() {
    if (window.idleManager) return; // already initialised

    const manager = new IdleSessionManager({
        idleTime: 3_600_000, // 60 minutes
        warningTime: 3_420_000, // 57 minutes (3-minute warning window)
        checkInterval: 5_000,     // poll every 5 s
        throttleDelay: 200,       // coalesce rapid events to 200 ms
        logoutUrl: '/inc/logout?reason=idle',
    });

    if (manager.isUserLoggedIn()) {
        window.idleManager = manager;
    } else {
        manager.destroy();
    }
}

// Run after DOM is ready regardless of script placement
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeIdleManager);
} else {
    initializeIdleManager();
}

// Reset the activity clock when the tab becomes visible again (e.g. user
// switches back after a long time away — don't immediately log them out).
document.addEventListener('visibilitychange', () => {
    if (!window.idleManager?.isDestroyed && document.visibilityState === 'visible') {
        window.idleManager._recordActivity();
    }
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = IdleSessionManager;
}