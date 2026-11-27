<?php // Included by settings.php after loading the authenticated account. ?>
<dialog class="account-dialog" id="personalDialog" aria-labelledby="personalTitle">
  <form method="post" action="settings.php">
    <h3 id="personalTitle">Edit Personal Information</h3>
    <?php accountCsrf(); ?><input type="hidden" name="action" value="personal">
    <label for="accountName">Full Name</label><input id="accountName" name="full_name" maxlength="100" required autocomplete="name" value="<?= settingsEscape($account['full_name']) ?>">
    <label for="accountEmail">Email Address</label><input id="accountEmail" type="email" name="email" maxlength="150" required autocomplete="email" value="<?= settingsEscape($account['email']) ?>">
    <label for="accountPhone">Phone</label><input id="accountPhone" type="tel" name="phone" maxlength="30" autocomplete="tel" value="<?= settingsEscape($account['phone']) ?>">
    <label for="emailPassword">Current Password (required if changing email)</label><input id="emailPassword" type="password" name="current_password" autocomplete="current-password">
    <p>Changing your email changes your login address.</p>
    <div class="account-actions"><button type="button" class="btn-change-pass" data-account-close>Cancel</button><button type="submit" class="btn-edit">Save Changes</button></div>
  </form>
</dialog>
<dialog class="account-dialog" id="addressDialog" aria-labelledby="addressTitle">
  <form method="post" action="settings.php">
    <h3 id="addressTitle">Edit Address</h3>
    <?php accountCsrf(); ?><input type="hidden" name="action" value="address">
    <label for="accountCountry">Country</label><input id="accountCountry" name="country" maxlength="100" autocomplete="country-name" value="<?= settingsEscape($account['country']) ?>">
    <label for="accountCity">City/State</label><input id="accountCity" name="city" maxlength="100" autocomplete="address-level2" value="<?= settingsEscape($account['city']) ?>">
    <label for="accountPostal">Postal Code</label><input id="accountPostal" name="postal_code" maxlength="20" autocomplete="postal-code" value="<?= settingsEscape($account['postal_code']) ?>">
    <div class="account-actions"><button type="button" class="btn-change-pass" data-account-close>Cancel</button><button type="submit" class="btn-edit">Save Changes</button></div>
  </form>
</dialog>
<dialog class="account-dialog" id="passwordDialog" aria-labelledby="passwordTitle">
  <form method="post" action="settings.php">
    <h3 id="passwordTitle">Change Password</h3>
    <?php accountCsrf(); ?><input type="hidden" name="action" value="password">
    <label for="currentPassword">Current Password</label><input id="currentPassword" type="password" name="current_password" required autocomplete="current-password">
    <label for="newPassword">New Password</label><input id="newPassword" type="password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
    <label for="confirmPassword">Confirm New Password</label><input id="confirmPassword" type="password" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password">
    <p>Use at least 8 characters. Existing password reset links will be invalidated.</p>
    <div class="account-actions"><button type="button" class="btn-change-pass" data-account-close>Cancel</button><button type="submit" class="btn-edit">Change Password</button></div>
  </form>
</dialog>
<?php if ($photoEnabled): ?>
<dialog class="account-dialog" id="photoDialog" aria-labelledby="photoTitle">
  <h3 id="photoTitle">Profile Photo</h3>
  <form method="post" action="settings.php" enctype="multipart/form-data">
    <?php accountCsrf(); ?><input type="hidden" name="action" value="photo">
    <label for="accountPhoto">Choose Photo</label><input id="accountPhoto" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required>
    <p>JPG, PNG or WebP, up to 2 MB.</p>
    <div class="account-actions"><button type="button" class="btn-change-pass" data-account-close>Cancel</button><button type="submit" class="btn-edit">Save Photo</button></div>
  </form>
  <?php if ($hasPhoto): ?><form method="post" action="settings.php" class="account-actions"><?php accountCsrf(); ?><input type="hidden" name="action" value="remove_photo"><button type="submit" class="btn-change-pass">Remove Photo</button></form><?php endif; ?>
</dialog>
<?php endif; ?>
<script>
(() => {
  document.querySelectorAll('[data-account-dialog]').forEach(button => button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.accountDialog);
    if (dialog && !dialog.open) dialog.showModal();
  }));
  document.querySelectorAll('[data-account-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
  document.querySelectorAll('.account-dialog').forEach(dialog => dialog.addEventListener('close', () => {
    dialog.querySelectorAll('form').forEach(form => form.reset());
  }));
  const password = document.getElementById('newPassword');
  const confirm = document.getElementById('confirmPassword');
  function checkPassword() { confirm.setCustomValidity(confirm.value && confirm.value !== password.value ? 'New passwords do not match.' : ''); }
  password.addEventListener('input', checkPassword); confirm.addEventListener('input', checkPassword);
  document.getElementById('passwordDialog').addEventListener('close', () => confirm.setCustomValidity(''));
})();
</script>
