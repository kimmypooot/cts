<?php
// ============================================================
// Enhanced Security Headers
// ============================================================
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.jsdelivr.net/npm/sweetalert2@11 https://challenges.cloudflare.com; script-src-elem 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.jsdelivr.net/npm/sweetalert2@11 https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com; connect-src 'self' https://challenges.cloudflare.com https://imis.cscro8.com;");

// ============================================================
// Session Bootstrap
// ============================================================
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   '1');   // ← always '1'; match all auth files
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// Redirect if already logged in
// ============================================================
if (
    isset($_SESSION['username']) && !empty($_SESSION['username']) &&
    isset($_SESSION['role']) && in_array($_SESSION['role'], ['user', 'admin', 'superadmin'], true)
) {
    header('Location: /dashboard');
    exit();
}

include_once('includes/connect.php');

// ============================================================
// Logout Message Handling
// ============================================================
$logout_message = null;
if (isset($_SESSION['logout_message'])) {
    $logout_message = htmlspecialchars($_SESSION['logout_message'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['logout_message']);
}
if (!$logout_message && isset($_GET['logout'])) {
    $allowed_logout_msgs = ['idle', 'manual'];
    $raw = $_GET['logout'];
    if (in_array($raw, $allowed_logout_msgs, true)) {
        $logout_message = $raw;
    }
}

// ============================================================
// Cloudflare Turnstile – Site Key
// ============================================================
define('TURNSTILE_SITE_KEY', '0x4AAAAAACxEHlX3Y5cF3sjz'); // ← REPLACE THIS
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, height=device-height, viewport-fit=cover">
    <meta name="description" content="CSC RO VIII Integrated Management Information System – Secure Employee Login">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <link href="assets/img/favicon.png" rel="icon">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link rel="preload" as="image" href="login_img/csclogo.png">
    <link rel="preload" as="image" href="login_img/CSC-IMIS.png">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet" media="print" onload="this.media='all'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    </noscript>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
        integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd"
        crossorigin="anonymous">
    
    <style>
        <?php include 'assets/css/preloader.css'; ?>
    </style>
    <link href="assets/css/imis_login.css?v=<?php echo time(); ?>" rel="stylesheet">

    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <title>CSC RO VIII – IMIS Login</title>
</head>

<body>
    <div class="bg-orbs" aria-hidden="true">
        <div class="bg-orb bg-orb-1"></div>
        <div class="bg-orb bg-orb-2"></div>
        <div class="bg-orb bg-orb-3"></div>
    </div>
    <div class="bg-grid" aria-hidden="true"></div>

    <div id="preloader"
        role="status"
        aria-live="polite"
        aria-busy="true"
        aria-label="IMIS is loading. Please wait.">
        <div class="preloader-accent" aria-hidden="true"></div>
        <div class="preloader-logo-wrap" aria-hidden="true">
            <img src="login_img/CSC-IMIS-dark.png" class="preloader-logo" alt="" draggable="false">
        </div>
        <div class="preloader-agency">
            <span class="preloader-agency-main">Civil Service Commission</span>
            <span class="preloader-agency-sub">Regional Office VIII &bull;</span>
        </div>
        <div class="preloader-divider" aria-hidden="true"></div>
        <p class="preloader-label">
            <span>Loading</span>
            <span class="preloader-dots" aria-hidden="true">
                <span></span><span></span><span></span>
            </span>
        </p>
        <div class="preloader-bar-wrap" aria-hidden="true">
            <div class="preloader-bar"></div>
        </div>
    </div>

    <div class="login-wrapper" id="loginWrapper">

        <div class="hero-panel" aria-hidden="true">
            <img src="login_img/bg.png" class="hero-illustration" alt="">
            <p class="hero-tagline">
                <strong>CSC RO VIII – Integrated Management Information System</strong>
                is the centralized digital hub for CSC Regional Office VIII. It streamlines regional operations by integrating human resources, examinations, and technical support into a single, seamless platform.
            </p>
            <div class="hero-dots">
                <div class="hero-dot active"></div>
                <div class="hero-dot"></div>
                <div class="hero-dot"></div>
            </div>
        </div>

        <main id="loginMain" aria-label="Login">

            <div id="securityCheckCard" class="login-card security-check-card" role="region" aria-label="Security verification">

                <div class="logo-row">
                    <img src="login_img/csclogo.png" class="logo-img" alt="Civil Service Commission logo" width="72" height="72">
                    <div class="logo-divider" aria-hidden="true"></div>
                    <img src="login_img/CSC-IMIS.png" class="logo-img logo-wordmark" alt="CSC IMIS wordmark" width="80" height="72" style="cursor:default">
                </div>

                <div class="security-shield" aria-hidden="true">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>

                <h1 class="card-heading security-heading">Security Check</h1>

                <p class="security-subtext">
                    Please verify you are human before signing in.
                </p>

                <div class="turnstile-wrap" id="turnstileWrap">
                    <div class="cf-turnstile"
                        data-sitekey="<?= htmlspecialchars(TURNSTILE_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>"
                        data-callback="onTurnstileSuccess"
                        data-error-callback="onTurnstileError"
                        data-expired-callback="onTurnstileExpired"
                        data-theme="light"
                        aria-label="Cloudflare Turnstile security check">
                    </div>
                </div>

                <div id="turnstileError" class="turnstile-error" role="alert" aria-live="assertive">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <span id="turnstileErrorText">Verification failed. Please try again.</span>
                </div>

                <p class="card-footer-note">
                    CSC RO VIII &copy; <?= date('Y') ?> &nbsp;|&nbsp; IMIS v2.0 &nbsp;|&nbsp;
                    <a href="mailto:ro08@csc.gov.ph">ro08@csc.gov.ph</a>
                </p>

            </div>
            <div class="login-card" id="loginCard" aria-hidden="true" style="display:none;">

                <div class="logo-row">
                    <img src="login_img/csclogo.png" class="logo-img" alt="Civil Service Commission logo" width="72" height="72">
                    <div class="logo-divider" aria-hidden="true"></div>
                    <img src="login_img/CSC-IMIS.png"
                        id="toggleTriggerImg"
                        class="logo-img logo-wordmark"
                        alt="CSC IMIS wordmark – click to toggle superadmin login"
                        role="button"
                        tabindex="0"
                        aria-expanded="false"
                        aria-controls="toggleContainer"
                        title="Click to access superadmin login"
                        width="80" height="72">
                </div>

                <h1 class="card-heading" id="cardHeading">
                    Welcome to CSC RO VIII<br>Integrated Management Information System
                </h1>

                <div class="text-center">
                    <span class="role-badge" id="roleBadge" aria-live="polite">
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        <span id="roleBadgeText">Login as CSC RO VIII Employee</span>
                    </span>
                </div>

                <div class="lockout-notice" id="lockoutNotice" role="alert" aria-live="assertive">
                    <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                    <span>
                        Too many failed attempts. Please wait
                        <strong id="lockoutTimer">30</strong> seconds before trying again.
                    </span>
                </div>

                <form
                    id="loginForm"
                    method="POST"
                    action="/auth/login"
                    novalidate
                    autocomplete="on"
                    aria-labelledby="cardHeading">

                    <input type="hidden" id="cfTurnstileToken" name="cf_turnstile_response" value="">
                    <input type="hidden" id="loginMode" name="login_mode" value="admin">

                    <div class="field-wrap" id="usernameWrap">
                        <label class="field-label" for="username">Username</label>
                        <div class="input-shell" id="usernameShell">
                            <span class="input-icon" aria-hidden="true"><i class="bi bi-person"></i></span>
                            <input
                                type="text"
                                class="input-field"
                                id="username"
                                name="username"
                                placeholder="Enter your username"
                                autocomplete="username"
                                spellcheck="false"
                                autocorrect="off"
                                autocapitalize="none"
                                required
                                aria-required="true"
                                aria-describedby="usernameErr"
                                aria-invalid="false"
                                maxlength="100">
                        </div>
                        <div class="field-error-msg" id="usernameErr" role="alert" aria-live="polite">
                            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                            <span class="err-text"></span>
                        </div>
                    </div>

                    <div class="field-wrap" id="passwordWrap">
                        <label class="field-label" for="password">Password</label>
                        <div class="input-shell" id="passwordShell">
                            <span class="input-icon" aria-hidden="true"><i class="bi bi-lock"></i></span>
                            <input
                                type="password"
                                class="input-field"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                                aria-required="true"
                                aria-describedby="passwordErr pwVisibleWarn"
                                aria-invalid="false"
                                maxlength="255">
                            <button
                                type="button"
                                class="input-action"
                                id="togglePassword"
                                aria-label="Show password"
                                aria-pressed="false"
                                title="Toggle password visibility">
                                <i class="bi bi-eye-slash" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="pw-visible-warn" id="pwVisibleWarn" role="status" aria-live="polite">
                            <i class="bi bi-eye-fill" aria-hidden="true"></i>
                            Password is currently visible
                        </div>
                        <div class="field-error-msg" id="passwordErr" role="alert" aria-live="polite">
                            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                            <span class="err-text"></span>
                        </div>
                    </div>

                    <button type="submit" class="login-btn btn btn-primary w-100" id="loginBtn">
                        <span class="btn-inner d-flex align-items-center justify-content-center gap-2">

                            <span class="btn-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>

                            <i class="bi bi-arrow-right-circle btn-icon" aria-hidden="true"></i>

                            <span class="btn-text">Login</span>

                        </span>
                    </button>

                    <div id="loginStatus" class="visually-hidden"></div>

                </form>
                <div id="toggleContainer" role="region" aria-label="Login mode switch">
                    <div class="role-switch-panel">
                        <span class="role-switch-label" id="toggleLabel">
                            Switch to Superadmin Login
                        </span>
                        <label class="toggle-switch" aria-label="Toggle superadmin login mode">
                            <input type="checkbox" id="toggleModeBtn" aria-describedby="toggleLabel">
                            <span class="toggle-track"></span>
                        </label>
                    </div>
                </div>

                <p class="card-footer-note">
                    CSC RO VIII &copy; <?= date('Y') ?> &nbsp;|&nbsp; IMIS v2.0 &nbsp;|&nbsp;
                    <a href="mailto:ro8@csc.gov.ph">ro8@csc.gov.ph</a>
                </p>

            </div>
        </main>

    </div>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
        defer></script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/preloader.js"></script>
    <script src="auth.js?v=<?php echo time(); ?>"></script>

    <script>
        /* ============================================================
           TURNSTILE CALLBACKS
        ============================================================ */
        var _turnstileVerified = false;
        var _turnstileErrorTimer = null;

        function _showLoginCard() {
            var secCard = document.getElementById('securityCheckCard');
            var loginCard = document.getElementById('loginCard');
            var verifyEl = document.getElementById('turnstileVerifying');

            if (verifyEl) verifyEl.style.display = 'none';
            secCard.classList.add('security-card-exit');

            setTimeout(function() {
                secCard.style.display = 'none';
                loginCard.removeAttribute('aria-hidden');
                loginCard.style.display = 'block';

                loginCard.style.animation = 'none';
                void loginCard.offsetHeight;
                loginCard.style.animation = '';

                var usernameInput = document.getElementById('username');
                if (usernameInput) usernameInput.focus();
            }, 450);
        }

        function _showTurnstileError(msg) {
            var errEl = document.getElementById('turnstileError');
            var errText = document.getElementById('turnstileErrorText');
            var verifyEl = document.getElementById('turnstileVerifying');

            if (verifyEl) verifyEl.style.display = 'none';
            if (errEl && errText) {
                errText.textContent = msg || 'Verification failed. Please try again.';
                errEl.classList.add('show');
            }
        }

        function onTurnstileSuccess(token) {
            _turnstileVerified = true;
            if (_turnstileErrorTimer) {
                clearTimeout(_turnstileErrorTimer);
                _turnstileErrorTimer = null;
            }

            document.getElementById('cfTurnstileToken').value = token;

            var errEl = document.getElementById('turnstileError');
            if (errEl) errEl.classList.remove('show');

            var verifyEl = document.getElementById('turnstileVerifying');
            if (verifyEl) verifyEl.style.display = 'flex';

            fetch('/auth/turnstile/verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: 'token=' + encodeURIComponent(token),
                })
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(data) {
                    if (data && data.success) {
                        _showLoginCard();
                    } else {
                        _turnstileVerified = false;
                        document.getElementById('cfTurnstileToken').value = '';

                        _showTurnstileError(
                            (data && data.message) ?
                            data.message :
                            'Verification could not be confirmed. Please try the check again.'
                        );

                        if (window.turnstile) {
                            window.turnstile.reset();
                        }
                    }
                })
                .catch(function() {
                    _turnstileVerified = false;
                    document.getElementById('cfTurnstileToken').value = '';
                    _showTurnstileError('Network error during verification. Please check your connection and reload the page.');
                });
        }

        function onTurnstileError() {
            if (_turnstileVerified) return;

            _turnstileErrorTimer = setTimeout(function() {
                if (_turnstileVerified) return;
                _showTurnstileError('Verification error. Please check your connection and refresh the page.');
            }, 800);
        }

        function onTurnstileExpired() {
            _turnstileVerified = false;
            document.getElementById('cfTurnstileToken').value = '';

            _showTurnstileError('Verification expired. Please complete the check again.');
        }


        /* ============================================================
           MAIN SCRIPT
        ============================================================ */
        (function() {
            'use strict';

            var card = document.getElementById('loginCard');
            var loginModeInput = document.getElementById('loginMode');
            var passwordInput = document.getElementById('password');
            var togglePassBtn = document.getElementById('togglePassword');
            var pwVisibleWarn = document.getElementById('pwVisibleWarn');
            var toggleImg = document.getElementById('toggleTriggerImg');
            var toggleContainer = document.getElementById('toggleContainer');
            var toggleModeBtn = document.getElementById('toggleModeBtn');
            var roleBadgeText = document.getElementById('roleBadgeText');
            var toggleLabel = document.getElementById('toggleLabel');

            if (togglePassBtn) {
                togglePassBtn.addEventListener('click', function() {
                    var isHidden = passwordInput.type === 'password';
                    passwordInput.type = isHidden ? 'text' : 'password';

                    var icon = this.querySelector('i');
                    icon.classList.toggle('bi-eye-slash', !isHidden);
                    icon.classList.toggle('bi-eye', isHidden);

                    this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                    this.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                    pwVisibleWarn.classList.toggle('show', isHidden);
                });
            }

            function applyDarkMode(isDark) {
                card.classList.toggle('is-dark', isDark);
                roleBadgeText.textContent = isDark ?
                    'Login as CSC RO VIII Superadmin' :
                    'Login as CSC RO VIII Employee';
                toggleLabel.textContent = isDark ?
                    'Switch Back to Employee Login' :
                    'Switch to Superadmin Login';
                loginModeInput.value = isDark ? 'superadmin' : 'admin';

                toggleImg.src = isDark ? 'login_img/CSC-IMIS-dark.png' : 'login_img/CSC-IMIS.png';
                toggleImg.setAttribute('aria-expanded', String(isDark));
            }

            if (toggleModeBtn) {
                toggleModeBtn.addEventListener('change', function() {
                    applyDarkMode(this.checked);
                });
            }

            function toggleSuperadminPanel() {
                var isOpen = toggleContainer.classList.toggle('open');
                toggleImg.setAttribute('aria-expanded', String(isOpen));
                if (!isOpen && toggleModeBtn.checked) {
                    toggleModeBtn.checked = false;
                    applyDarkMode(false);
                }
            }

            if (toggleImg) {
                toggleImg.addEventListener('click', toggleSuperadminPanel);
                toggleImg.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleSuperadminPanel();
                    }
                });
            }

            var Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                didOpen: function(toast) {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                },
            });

            var urlParams = new URLSearchParams(window.location.search);
            var logoutReason = urlParams.get('logout');

            var clearLogoutParam = function() {
                var url = new URL(window.location);
                url.searchParams.delete('logout');
                window.history.replaceState({}, document.title, url.toString());
            };

            if (logoutReason === 'idle') {
                Toast.fire({
                    icon: 'info',
                    title: 'Session Expired',
                    text: 'Logged out due to inactivity.'
                });
                clearLogoutParam();
            } else if (logoutReason === 'manual') {
                Toast.fire({
                    icon: 'success',
                    title: 'Logged out successfully'
                });
                clearLogoutParam();
            }

        }()); // END IIFE
    </script>

</body>

</html>