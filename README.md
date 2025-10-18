# PHP AjaxWrapper
*A zero-dependency PHP helper for progressive enhancement (AJAX + PRG).*

---

### Overview

`AjaxWrapper` makes any PHP form **work seamlessly with or without JavaScript**:

- **With JavaScript:**  
  Submits via `fetch()` → JSON response (`{ ok, errors, html, payload }`).
- **Without JavaScript:**  
  Classic `POST → Redirect → GET` (PRG) pattern — no reload issues.

It’s designed for **any code style** — closures, class methods, static calls, or even **legacy scripts that echo output**.

---

### ✅ Features

-  Works with or without JS (auto-detects `Accept`/`X-Requested-With`)
-  No framework, no interfaces, no inheritance
-  Handles modern or legacy code
-  Includes helpers for echo-based classes
-  Keeps PRG behavior for non-JS users
-  Simple, composable, dependency-free

---

### 📦 Installation

Just copy [`AjaxWrapper.php`](./AjaxWrapper.php) into your project.

If you use Composer (optional):

```bash
composer require yourname/ajax-wrapper
````

*(You can later publish it on Packagist if you wish.)*

---

### 🧠 Basic concept

```php
$wrapper = new AjaxWrapper();

$wrapper->handle(
  processPost: fn() => ['ok'=>true,'html'=>'<div>Saved!</div>'],
  renderGet: fn() => '<form method="post">...</form>'
);
```

* **With JS:** returns JSON → your JS updates the DOM
* **Without JS:** redirects → shows a static “Thank you” page

---

### JavaScript (progressive enhancement)

Add this snippet once per page:

```html
<script>
(() => {
  const f = document.getElementById('form-a');
  if (!f) return;

  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(f);
    try {
      const res = await fetch('', {
        method: 'POST',
        body: fd,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
      });
      const data = await res.json().catch(() => null);
      if (!data) { f.submit(); return; }

      f.querySelectorAll('.error').forEach(n=>n.remove());
      if (data.ok) {
        const fv = document.getElementById('form-view');
        const tv = document.getElementById('thanks-view');
        if (tv && fv) {
          tv.innerHTML = data.html || '<div class="success"><strong>Saved!</strong></div>';
          fv.classList.add('hide'); tv.classList.remove('hide');
        } else location.reload();
      } else {
        Object.entries(data.errors || {}).forEach(([n,m])=>{
          const i=f.querySelector(`[name="${n}"]`); if(!i)return;
          const d=document.createElement('div');d.className='error';d.textContent=m;
          i.parentElement.appendChild(d);
        });
      }
    } catch { f.submit(); }
  });
})();
</script>
```

---

## Examples

### A) Modern: simple closure

```php
require 'AjaxWrapper.php';

$processPost = function () {
  $name = trim($_POST['name'] ?? '');
  if ($name === '') return ['ok'=>false,'errors'=>['name'=>'Please enter a name.']];
  return ['ok'=>true,'html'=>'<div class="success">Thank you!</div>'];
};

$renderGet = fn() => '
<form id="form-a" method="post">
  <input name="name" placeholder="Your name">
  <button>Send</button>
</form>
<div id="thanks-view" class="hide"></div>
<script src="ajax.js"></script>
';

(new AjaxWrapper())->handle($processPost, $renderGet);
```

---

### B) Instance or static class methods

```php
class ContactController {
  public function post(): array {
    // your business logic here
    return ['ok'=>true,'html'=>'<div class="success">Saved!</div>'];
  }
  public function get(): string {
    return '<form id="form-a" method="post"><button>OK</button></form><div id="thanks-view" class="hide"></div>';
  }
}

$ctrl = new ContactController();
(new AjaxWrapper())->handle([$ctrl,'post'], [$ctrl,'get']);
```

---

### C) Legacy GET renderer (echoes HTML)

```php
class LegacyPage {
  public function render(): void {
    echo '<form id="form-a" method="post"><input name="name"><button>Send</button></form>';
    echo '<div id="thanks-view" class="hide"></div>';
  }
  public function post(): array {
    return ['ok'=>true,'html'=>'<div class="success">OK (legacy GET)</div>'];
  }
}

$legacy = new LegacyPage();
$renderGet   = AjaxWrapper::renderGetFromEcho([$legacy,'render']);
$processPost = [$legacy,'post'];

(new AjaxWrapper())->handle($processPost, $renderGet);
```

---

### D) Fully legacy: both echo

```php
class VeryLegacy {
  public bool $ok = false;
  public array $errors = [];

  public function render(): void {
    echo '<form id="form-a" method="post"><input name="email"><button>Send</button></form>';
  }

  public function post(): void {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->ok = false; $this->errors = ['email'=>'Invalid email'];
      return;
    }
    $this->ok = true; $this->errors = [];
    echo '<div class="success">Thanks!</div>';
  }
}

$legacy = new VeryLegacy();

$renderGet = AjaxWrapper::renderGetFromEcho([$legacy,'render']);
$processPost = AjaxWrapper::processPostFromEcho(
  [$legacy,'post'],
  true,
  fn() => $legacy->ok,
  fn() => $legacy->errors
);

(new AjaxWrapper())->handle($processPost, $renderGet);
```

---

### E) Ignore unwanted echo output

```php
class Noisy {
  public function post(): void { echo "DEBUG..."; }
  public function form(): string { return '<form id="form-a" method="post"><button>Go</button></form>'; }
}

$svc = new Noisy();

$processPost = function () use ($svc) {
  AjaxWrapper::swallow([$svc,'post']); // discard echo
  return ['ok'=>true,'html'=>'<div class="success">Saved without echo.</div>'];
};

$renderGet = [$svc,'form'];

(new AjaxWrapper())->handle($processPost, $renderGet);
```

---

### Minimal CSS

```css
body{font-family:system-ui;margin:2rem}
.hide{display:none}
.success{padding:.75rem;background:#e8f6ec;border:1px solid #b9e2c5;border-radius:10px;margin-top:.75rem}
.error{color:#b00020;font-size:.9rem}
```

---

### Behavior summary

| Context            | Behavior                                            |
| ------------------ | --------------------------------------------------- |
| JS enabled         | AJAX via `fetch()` → JSON → DOM update              |
| JS disabled        | Regular POST + Redirect (PRG)                       |
| Legacy echo code   | Works via `capture()`/`renderGetFromEcho()` helpers |
| Debug echo in POST | Use `swallow()` to suppress it                      |
| Frameworks         | Works in any PHP app (PSR-7, plain, or legacy)      |

---

### Helper methods

| Helper                       | Purpose                                 |
| ---------------------------- | --------------------------------------- |
| `capture(fn)`                | Returns any echoed output as string     |
| `swallow(fn)`                | Executes and discards output            |
| `renderGetFromEcho(fn)`      | Wraps a legacy echo-based GET renderer  |
| `processPostFromEcho(fn, …)` | Wraps a legacy echo-based POST handler  |
| `handle(fn, fn)`             | Core: automatically serves JSON or HTML |


