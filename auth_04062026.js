/**
 * auth.js — CSC RO VIII IMIS Login Authentication
 * ─────────────────────────────────────────────────────────────────────────────
 * Handles all client-side login UI interactions.
 */

$(document).ready(function () {
  'use strict';

  // ============================================================
  // 1. STATE
  // ============================================================

  let isLoggingIn = false;
  let lockoutInterval = null;
  const MAX_FAILED_ATTEMPTS = 5;
  const LOCKOUT_SECONDS = 30;
  const ATTEMPT_KEY = 'imis_failed_attempts';
  const LOCKOUT_UNTIL_KEY = 'imis_lockout_until';


  // ============================================================
  // 2. DOM REFERENCES
  // ============================================================

  const $loginBtn = $('#loginBtn');
  const $loginStatus = $('#loginStatus');
  const $usernameInput = $('#username');
  const $passwordInput = $('#password');
  const $lockoutNotice = $('#lockoutNotice');
  const $lockoutTimer = $('#lockoutTimer');
  const $toggleModeBtn = $('#toggleModeBtn');


  // ============================================================
  // 3. INLINE FIELD VALIDATION HELPERS
  // ============================================================

  function showFieldError($shell, errorId, message) {
    $shell.addClass('is-error');
    $('#' + errorId).find('.err-text').text(message).end().addClass('show');
    $shell.find('.input-field').attr('aria-invalid', 'true');
  }

  function clearFieldError($shell, errorId) {
    $shell.removeClass('is-error');
    $('#' + errorId).find('.err-text').text('').end().removeClass('show');
    $shell.find('.input-field').attr('aria-invalid', 'false');
  }

  /**
   * Validates format/presence of all fields.
   */
  function validateForm(username, password) {
    let valid = true;

    if (!username) {
      showFieldError($('#usernameShell'), 'usernameErr', 'Username is required.');
      valid = false;
    }

    if (!password) {
      showFieldError($('#passwordShell'), 'passwordErr', 'Password is required.');
      valid = false;
    }

    if (!valid) {
      $('#loginForm [aria-invalid="true"]').first().focus();
    }

    return valid;
  }


// ============================================================
// 4. LOGIN BUTTON STATE
// ============================================================

function setButtonLoading() {
    const $btn = $('#loginBtn');
    
    // Disable the button to prevent double-clicks
    $btn.addClass('loading').prop('disabled', true);
    
    // Hide the arrow icon, show the spinner
    $btn.find('.btn-icon').addClass('d-none');
    $btn.find('.btn-spinner').removeClass('d-none');
    
    // Change the text (it will stay perfectly beside the newly visible spinner)
    $btn.find('.btn-text').text('Logging in, please wait...');
}

function resetButton() {
    isLoggingIn = false;
    const $btn = $('#loginBtn');
    
    // Re-enable the button
    $btn.removeClass('loading').prop('disabled', false);
    
    // Hide the spinner, show the arrow icon
    $btn.find('.btn-spinner').addClass('d-none');
    $btn.find('.btn-icon').removeClass('d-none');
    
    // Change the text back to default
    $btn.find('.btn-text').text('Login');
}


  // ============================================================
  // 5. SAFE sessionStorage HELPERS
  // ============================================================

  function ssGet(key) { try { return sessionStorage.getItem(key); } catch { return null; } }
  function ssSet(key, val) { try { sessionStorage.setItem(key, val); } catch { /* fail silently */ } }
  function ssRemove(key) { try { sessionStorage.removeItem(key); } catch { } }

  function getFailedAttempts() {
    return parseInt(ssGet(ATTEMPT_KEY) || '0', 10);
  }

  function incrementFailedAttempts() {
    const next = getFailedAttempts() + 1;
    ssSet(ATTEMPT_KEY, String(next));
    return next;
  }

  function clearFailedAttempts() {
    ssRemove(ATTEMPT_KEY);
    ssRemove(LOCKOUT_UNTIL_KEY);
  }

  function isLockedOut() {
    const until = parseInt(ssGet(LOCKOUT_UNTIL_KEY) || '0', 10);
    return Date.now() < until;
  }

  function startLockout(seconds) {
    ssSet(LOCKOUT_UNTIL_KEY, String(Date.now() + seconds * 1000));

    let remaining = seconds;
    $lockoutTimer.text(remaining);
    $lockoutNotice.addClass('show');
    $loginBtn.prop('disabled', true);

    clearInterval(lockoutInterval);
    lockoutInterval = setInterval(() => {
      remaining -= 1;
      $lockoutTimer.text(remaining);
      if (remaining <= 0) {
        clearInterval(lockoutInterval);
        $lockoutNotice.removeClass('show');
        $loginBtn.prop('disabled', false);
      }
    }, 1000);
  }

  window.showLockout = startLockout;

  function resumeLockoutIfActive() {
    if (!isLockedOut()) return;
    const remaining = Math.ceil(
      (parseInt(ssGet(LOCKOUT_UNTIL_KEY) || '0') - Date.now()) / 1000
    );
    if (remaining > 0) startLockout(remaining);
  }


  // ============================================================
  // 6. ERROR HANDLER
  // ============================================================

  function handleLoginError(response) {
    const msg = response?.message || 'Login failed. Please try again.';
    
    if (msg === 'ACCOUNT_INACTIVE') {
      Swal.fire({
        icon: 'error',
        title: 'Account Deactivated',
        html: 'Your account has been <strong>deactivated</strong>.<br>Please contact the system administrator for assistance.',
        confirmButtonText: 'OK',
        confirmButtonColor: '#1a56a0',
      });
      resetButton();
      return;
    }

    if (msg === 'UNAUTHORIZED') {
      Swal.fire({
        icon: 'error',
        title: 'Unauthorized',
        html: 'You are not authorized to access the Superadmin area.<br>Please contact your system administrator.',
        confirmButtonText: 'OK',
        confirmButtonColor: '#1a56a0',
      });
      resetButton();
      return;
    }

    const attempts = incrementFailedAttempts();

    Swal.fire({
      icon: 'error',
      title: 'Login Failed',
      text: msg,
      confirmButtonText: 'Try Again',
      confirmButtonColor: '#1a56a0',
    });

    if (attempts >= MAX_FAILED_ATTEMPTS) {
      startLockout(LOCKOUT_SECONDS);
    }

    resetButton();
  }


  // ============================================================
  // 7. POST-LOGIN SUCCESS
  // ============================================================

  function handleLoginSuccess(response) {
    clearFailedAttempts();

    const isSuperadmin = response.login_mode === 'superadmin';
    const name = response.name || 'User';

    Swal.fire({
      title: isSuperadmin ? 'Login Successful' : 'Welcome!',
      html: isSuperadmin
        ? `Welcome, <span class="text-primary fw-bold">${name}</span>.<br>
           You have successfully logged in as a <strong>Super Administrator</strong>.<br>
           Please click below to proceed.`
        : `Welcome to the <strong>CSC RO VIII – IMIS</strong>,<br>
           <span class="text-primary fw-bold">${name}</span>.<br>
           Please click below to proceed.`,
      icon: 'success',
      confirmButtonText: '<i class="bi bi-box-arrow-in-right"></i>&nbsp; Proceed',
      confirmButtonColor: isSuperadmin ? '#6d28d9' : '#1a56a0',
      allowOutsideClick: false,
    }).then(result => {
      if (result.isConfirmed) {
        window.location.href = response.redirect;
      }
    });

    resetButton();
  }


  // ============================================================
  // 8. PROGRESSIVE FIELD VALIDATION
  // ============================================================

  $usernameInput.on('blur', function () {
    $(this).val().trim()
      ? clearFieldError($('#usernameShell'), 'usernameErr')
      : showFieldError($('#usernameShell'), 'usernameErr', 'Username is required.');
  });

  $passwordInput.on('blur', function () {
    $(this).val()
      ? clearFieldError($('#passwordShell'), 'passwordErr')
      : showFieldError($('#passwordShell'), 'passwordErr', 'Password is required.');
  });

  $usernameInput.on('input', () => clearFieldError($('#usernameShell'), 'usernameErr'));
  $passwordInput.on('input', () => clearFieldError($('#passwordShell'), 'passwordErr'));


  // ============================================================
  // 9. FORM SUBMIT
  // ============================================================

  $('#loginForm').on('submit', function (e) {
    e.preventDefault();

    if (isLoggingIn) return;
    if (isLockedOut()) { resumeLockoutIfActive(); return; }

    // Using a fallback `|| ''` prevents the .trim() error from ever happening again
    // even if the HTML changes in the future.
    const username = ($usernameInput.val() || '').trim();
    const password = $passwordInput.val() || ''; 
    const loginMode = $toggleModeBtn.prop('checked') ? 'superadmin' : 'admin';

    if (!validateForm(username, password)) return;

    isLoggingIn = true;
    setButtonLoading();

    $.ajax({
      url: '/auth/login',
      method: 'POST',
      dataType: 'json',
      timeout: 15000,                           
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      data: {
          username,
          password,
          login_mode: loginMode,
          cf_turnstile_response: $('#cfTurnstileToken').val(),
      },

      success(response) {
        if (response?.success) {
          handleLoginSuccess(response);
        } else {
          handleLoginError(response);
        }
      },

      error(xhr, textStatus) {
        const serverResponse = xhr.responseJSON || {};
        serverResponse.message =
          textStatus === 'timeout'
            ? 'Request timed out. Check your connection and try again.'
            : (serverResponse.message || 'A network error occurred. Please try again.');
        handleLoginError(serverResponse);
      },

      complete() {
        if (isLoggingIn) resetButton();
      },
    });
  });

  // ============================================================
  // 10. INITIALISE
  // ============================================================

  resumeLockoutIfActive();

}); // END $(document).ready