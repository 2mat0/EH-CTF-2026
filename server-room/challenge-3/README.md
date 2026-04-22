# Challenge 3: The Warden's Notes

**Category:** Forensics  
**Difficulty:** Moderate (250 pts)  
**Prerequisites:** Solve Challenge 2 first (you need SSH access as `inmate`)

---

## The Story

You broke out of your cell. You bypassed the door locks. You're loose in the
prison's computer system as a low-privilege inmate account.

Word in the yard is that the warden has been keeping notes about something
serious — a security flaw, a hidden room, maybe even a way out. He thought
he covered his tracks, but the system has a long memory.

Find the warden's secret. The flag follows the format `PrisonCTF{...}`.

---

## Tools You Need

Just the commands available in any Linux shell:
- `ls`, `cd`, `cat` — basic file operations
- `find`, `grep` — searching

You're on the system as user `michael`. You have limited privileges.

---

## Hints With Point Costs (escalating)

| Cost | Hint |
|------|------|
| 50   | Look at the inmate user's command history. Linux saves it automatically. |
| 50   | The warden tried to delete a log file, but he renamed it to look like a system cache. Check `/var/backups/`. |
| 50   | The notes file is encoded. Try `base64 -d`. |
