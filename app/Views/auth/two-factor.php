<?php
/**
 * Two-Factor Authentication form.
 *
 * Shown when login credentials are valid but the user has 2FA enabled.
 * Expects: $appName (from config), $viewsPath (for layout)
 */
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card shadow-sm mt-4">
        <div class="card-body p-4">
          <h2 class="h5 mb-2">Two-Factor Authentication</h2>
          <p class="text-muted mb-3">Enter the code from your authenticator app.</p>

          <form id="tf-form" method="post" action="/auth/2fa">
            <div class="mb-3">
              <label for="code" class="form-label">Authentication code</label>
              <input type="text" class="form-control text-center" id="code" name="code"
                     placeholder="000000" maxlength="6" required
                     autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*">
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
              <label class="form-check-label" for="remember">Remember this device</label>
            </div>

            <div class="mb-3">
              <a href="#" id="toggle-recovery" class="small text-decoration-none">Use a recovery code instead</a>
            </div>

            <div id="recovery-section" class="mb-3 d-none">
              <label for="type" class="form-label">Recovery code</label>
              <input type="hidden" name="type" value="recovery" id="recovery-type">
              <input type="text" class="form-control" id="recovery-code" name="code"
                     placeholder="XXXXX-XXXXX" maxlength="11" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Verify</button>
          </form>

          <div id="error-msg" class="alert alert-danger mt-3 d-none" role="alert"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var form = document.getElementById('tf-form');
  var toggle = document.getElementById('toggle-recovery');
  var recoverySection = document.getElementById('recovery-section');
  var codeInput = document.getElementById('code');
  var recoveryCodeInput = document.getElementById('recovery-code');
  var recoveryType = document.getElementById('recovery-type');
  var errorMsg = document.getElementById('error-msg');

  toggle.addEventListener('click', function(e) {
    e.preventDefault();
    var isRecovery = recoverySection.classList.contains('d-none');
    recoverySection.classList.toggle('d-none');
    toggle.textContent = isRecovery ? 'Use a verification code instead' : 'Use a recovery code instead';
    codeInput.classList.toggle('d-none', isRecovery);
    recoveryCodeInput.required = isRecovery;
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;

    var data = new FormData(form);

    fetch('/auth/2fa', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: data
    }).then(function(r) {
      return r.json().then(function(j) {
        if (r.ok && j.success) {
          window.location.href = '/';
        } else {
          errorMsg.textContent = j.error || 'Invalid code. Please try again.';
          errorMsg.classList.remove('d-none');
          btn.disabled = false;
        }
      });
    });
  });
})();
</script>

<?php require $viewsPath . '/layouts/blank.php'; ?>
