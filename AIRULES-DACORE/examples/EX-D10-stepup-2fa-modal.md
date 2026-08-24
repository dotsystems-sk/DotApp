# EX-D10 — Step-up 2FA modal (DACore plugin-installer chrome)

Rules: [32](../32-DACORE-RIGHTS.md) §6, [09](../09-DOTAPP-JS-AND-BRIDGE.md) §3 (`twoFactor`), [08](../08-FORMS-AND-SECURITY.md) (PHP is the gate), [33](../33-DACORE-PAGES-AND-UI.md) (search DACore first), [45](../45-MODULE-PLANNING.md).

**Open this file only after the user named a second 2FA prompt in the plan.** Login 2FA is already on. Ordinary settings Save does **not** get this modal.

Canonical look (read-only): `app/modules/DACore/views/layouts/plugins/_gate.layout.php` + `plugins-admin.js` (`$dotapp(OTP).twoFactor(..., { autoSubmit: true })`). Copy that chrome into **your** module. **MUST NOT** patch DACore.

---

## When this example applies

| Plan answer | What you ship |
|-------------|----------------|
| User did not ask / said no / no answer | **No** step-up. Rights + CRC + PHP checks. |
| User named actions (install package, wipe, grant `dotapp.root`, change a jail, restore) | This modal **only** on those actions |

**MUST NOT** invent step-up on every settings card “to be safe.”

---

## Chrome (**MUST** — one look)

1. Centered Bootstrap modal (`$dotapp(el).modal()`), `data-bs-backdrop="static"`.
2. Title + one sentence why this action needs a code.
3. Method hint (authenticator / email / SMS).
4. **Send confirmation code** only when the operator’s method is email/SMS.
5. Six square boxes: `dacore-otp two-fa-inputs`, `type="password"`, `inputmode="numeric"`, `maxlength="1"`. First box `autocomplete="one-time-code"`, the rest `autocomplete="off"`.
6. `$dotapp("#… input").twoFactor(submitFn, { length: 6, autoSubmit: true, allowLetters: false, invalidClass: "is-invalid" })`.
7. Paste of six digits **or** the sixth box filled → **immediately** POST. A Confirm button is fallback only.
8. Overlay `.modal-content` while the request is in flight (Notiflix on admin). Toast success **and** fail. No `location.reload()` while staying on the page.
9. PHP verifies the code **before** persist. Skipping the modal **MUST** still fail.

**MUST NOT:** one `<input maxlength="6">` on the settings card; OTP boxes inline under Save; a custom OTP widget; `autoSubmit: false` on an unlock/confirm step-up; `Auth::confirmTwoFactor` (login stage 2 only); a second look per module.

Load DACore OTP sizing (search DACore first — do not fork `users.css` into your module):

```php
$css = [
    '/assets/modules/DACore/css/pages/dotapp-ui/users.css',
    '/assets/modules/Shop/css/admin.css',
];
```

---

## Layout (your module)

```html
<div class="modal fade" id="shopStepupModal" tabindex="-1" aria-hidden="true" aria-labelledby="shopStepupTitle" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <fo-rm method="POST" id="shopStepupForm" action="{{ var: $postAction }}">
        <div class="modal-header">
          <h5 class="modal-title" id="shopStepupTitle">{{_ "Confirm this action" }}</h5>
        </div>
        <div class="modal-body">
          <p class="text-body-secondary">{{_ "Confirm with a two-factor code before installing this package." }}</p>
          <p class="text-body-secondary">{{ var: $tfaHint }}</p>
          {{ if ($tfaNeedSend == 1) }}
          <button type="button" class="btn btn-label-primary mb-4" id="shopStepupSend">{{_ "Send confirmation code" }}</button>
          {{ /if }}
          <div class="dacore-otp two-fa-inputs" id="shopStepupOtp" role="group" aria-label="{{_ "Confirmation code" }}">
            <input class="form-control text-center" type="password" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="{{_ "Digit 1" }}" />
            <input class="form-control text-center" type="password" inputmode="numeric" maxlength="1" autocomplete="off" aria-label="{{_ "Digit 2" }}" />
            <input class="form-control text-center" type="password" inputmode="numeric" maxlength="1" autocomplete="off" aria-label="{{_ "Digit 3" }}" />
            <input class="form-control text-center" type="password" inputmode="numeric" maxlength="1" autocomplete="off" aria-label="{{_ "Digit 4" }}" />
            <input class="form-control text-center" type="password" inputmode="numeric" maxlength="1" autocomplete="off" aria-label="{{_ "Digit 5" }}" />
            <input class="form-control text-center" type="password" inputmode="numeric" maxlength="1" autocomplete="off" aria-label="{{_ "Digit 6" }}" />
          </div>
          <input type="hidden" name="code" id="shopStepupCode" value="" />
          {{ formName(confirmDangerousAction) }}
        </div>
        <div class="modal-footer d-flex justify-content-center gap-2 pb-4">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{_ "Cancel" }}</button>
          <button type="submit" class="btn btn-primary" id="shopStepupConfirm">{{_ "Confirm and continue" }}</button>
        </div>
      </fo-rm>
    </div>
  </div>
</div>
```

A lock-in gate (installer unlock) may omit Cancel. An optional confirm (turn a flag off) **MUST** keep Cancel. Footer buttons **MUST** have padding vs the modal (`pb-4`).

---

## Page JS

```javascript
var OTP = "#shopStepupOtp input";

function otpCode() {
  return $dotapp(OTP).twoFactor() || "";
}

function showStepup() {
  var hidden = document.getElementById("shopStepupCode");
  if (hidden) {
    hidden.value = "";
  }
  $dotapp("#shopStepupModal").modal().show();
}

$dotapp(OTP).twoFactor(
  function () {
    var form = document.getElementById("shopStepupForm");
    var hidden = document.getElementById("shopStepupCode");
    if (hidden) {
      hidden.value = otpCode();
    }
    if (form) {
      $dotapp(form).submit();
    }
  },
  {
    length: 6,
    autoSubmit: true,
    allowLetters: false,
    uppercase: false,
    invalidClass: "is-invalid"
  }
);

$dotapp("#shopStepupForm").form(function (reply) {
  if (window.Notiflix && Notiflix.Block) {
    Notiflix.Block.remove("#shopStepupModal .modal-content");
  }
  if (reply && reply.status == 1) {
    $dotapp("#shopStepupModal").modal().hide();
    Notiflix.Notify.success(reply.message || "");
    return;
  }
  Notiflix.Notify.failure((reply && reply.message) || "Request failed");
});
```

`twoFactor` paste fills all six boxes and fires the callback. Completing the boxes does **not** authorize — PHP still verifies.

---

## PHP (TOTP step-up — operator already logged in)

`Auth::confirmTwoFactor()` **MUST NOT** be used here (`error` 1 when already at stage 1).

```php
$user = Auth::attributes();
$code = preg_replace('/\D+/', '', (string) ($payload['code'] ?? ''));
if (empty($user['tfa_auth']) || empty($user['tfa_auth_secret'])) {
    return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Two-factor authentication is required.']];
}
$expected = \Dotsystems\App\Parts\TOTP::generate($user['tfa_auth_secret']);
if ($code === '' || !hash_equals((string) $expected, $code)) {
    return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Invalid confirmation code']];
}
// then persist
```

SMS/email: issue a new 6-digit code, store it in **your** `DSM::use('Shop')` with a short TTL, send it, compare with `hash_equals`. Never put the code in HTML or a cookie.

---

## Grep this chunk

| Fail | Pass |
|------|------|
| `maxlength="6"` on one settings-card input | Six `maxlength="1"` boxes in a modal |
| No `.twoFactor(` / `autoSubmit: false` on unlock | `twoFactor(..., { autoSubmit: true })` |
| `Auth::confirmTwoFactor` | `TOTP::generate` + `hash_equals` (or your SMS/email challenge) |
| Persist without reading `code` | PHP refuses empty/wrong code |
