<?php
/* ============================================================================
   PROJECT_DOCS.php
   Krasniqi Invoicing App — In-Project Documentation Page
   - Drop this file anywhere in your project (e.g., /project_docs.php) and open it.
   - 100% standalone (no DB), prints and works offline.
   - Red theme, sticky sidebar, search, “copy” for code blocks, back-to-top.
   ============================================================================ */
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Krasniqi Invoicing App — Project Documentation</title>

<style>
:root{
  --bg:#f6f7fb; --ink:#0f172a; --muted:#6b7280; --card:#ffffff; --line:#e5e7eb;
  --accent-50:#fef2f2; --accent-100:#fee2e2; --accent-200:#fecaca; --accent-300:#fca5a5;
  --accent-400:#f87171; --accent-500:#ef4444; --accent-600:#dc2626; --accent-700:#b91c1c;
  --accent-800:#991b1b; --accent-900:#7f1d1d; --surface:#fff7f7; --black:#0b0b0c;
  --radius:14px; --shadow:0 10px 30px rgba(0,0,0,.08);
  --code-bg:#0b1020; --code-ink:#e8edf7; --code-muted:#9fb0d1; --code-key:#ffd479; --code-str:#a1ffb1; --code-kw:#79b8ff;
  --toc-w: 320px;
  --content-max: 1100px;
}

/* Layout */
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
a{color:var(--accent-700);text-decoration:none} a:hover{text-decoration:underline}

.header{
  position:sticky; top:0; z-index:30;
  display:flex; align-items:center; justify-content:space-between; gap:12px;
  padding:14px 18px; background:linear-gradient(180deg,#fff, #fff 60%, rgba(255,255,255,.92));
  border-bottom:1px solid var(--line);
}
.brand{display:flex;align-items:center;gap:12px}
.brand .dot{width:14px;height:14px;border-radius:50%;background:linear-gradient(180deg,var(--accent-500),var(--accent-700))}
.brand h1{font-size:16px;margin:0}
.header .updated{color:var(--muted);font-size:12px}

.wrapper{
  display:grid; grid-template-columns: min(100%, var(--toc-w)) 1fr; gap:0;
  max-width: 100%;
  min-height: calc(100vh - 60px);
}

/* TOC sidebar */
.toc{
  position:sticky; top:60px; align-self:start;
  height: calc(100vh - 60px);
  border-right:1px solid var(--line);
  background:#fff; padding:16px; overflow:auto;
}
.toc .search{display:flex; align-items:center; gap:8px; margin-bottom:10px}
.toc input[type="search"]{
  width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;
  outline: none;
}
.toc .section-title{font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:.06em; margin:12px 4px 8px}
.toc nav a{
  display:block; padding:8px 10px; margin:2px 0; border-radius:10px; color:#111; font-weight:500;
}
.toc nav a small{display:block; color:#6b7280; font-weight:400}
.toc nav a:hover{background:var(--accent-50)}
.toc nav a.active{background:var(--accent-600); color:#fff}
.toc .pill{display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:var(--surface); color:#b91c1c; font-size:12px; font-weight:600}

/* Main content */
.main{
  padding:24px clamp(14px, 3vw, 32px);
  max-width: var(--content-max);
}
.main .hero{
  background:linear-gradient(0deg, var(--accent-50) 0%, #fff 70%);
  border:1px solid var(--accent-200);
  padding:18px; border-radius:16px; box-shadow: var(--shadow);
}
.main h2{margin:28px 0 10px; font-size:22px}
.main h3{margin:18px 0 8px; font-size:18px}
.lead{color:#374151}
.card{
  background:#fff; border:1px solid var(--line); border-radius:16px; padding:18px; box-shadow: var(--shadow);
}
.grid{display:grid; gap:16px}
.grid.two{grid-template-columns: 1fr 1fr}
.grid.three{grid-template-columns: repeat(3,1fr)}
@media (max-width: 1050px){ .grid.two,.grid.three{grid-template-columns:1fr} }

/* Buttons */
.btn{border:1px solid var(--accent-300); background:#fff; color:var(--accent-800); padding:10px 14px; border-radius:999px; font-weight:600; cursor:pointer}
.btn:hover{background:var(--accent-50)}
.btn.primary{background:linear-gradient(180deg,var(--accent-600),var(--accent-700)); color:#fff; border-color:var(--accent-700); box-shadow:0 6px 20px rgba(185,28,28,.25)}
.btn.primary:hover{filter:brightness(1.03)}

/* Callouts */
.callout{border-left:4px solid var(--accent-600); background:#fff; padding:12px 14px; border-radius:10px; border:1px solid var(--line)}

/* Code blocks */
pre{position:relative; margin:14px 0; border-radius:12px; overflow:auto; border:1px solid #0b1020; box-shadow:0 12px 30px rgba(11,16,32,.25)}
pre code{display:block; background:var(--code-bg); color:var(--code-ink); padding:14px; font: 13px/1.6 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}
pre .copy{position:absolute; top:8px; right:8px; z-index:5}
code .k{color:var(--code-kw)} .s{color:var(--code-str)} .c{color:var(--code-muted)} .n{color:#e3e9ff} .p{color:#9ad} .key{color:var(--code-key)}

/* Tables */
table{border-collapse:collapse;width:100%;background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden}
th,td{padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:top}
th{background:#fafafa;text-align:left}

/* Checklist */
ol.check{list-style:none;padding-left:0}
ol.check li{padding-left:28px; position:relative}
ol.check li::before{content:"✓"; position:absolute; left:0; top:0; color:#059669; font-weight:900}

/* Toast + Loader (center of page) */
#toastWrap{position:fixed;left:0;right:0;top:24px;display:flex;justify-content:center;pointer-events:none;z-index:9999}
#toast{min-width:260px;max-width:540px;background:#065f46;color:#fff;padding:12px 16px;border-radius:12px;
       box-shadow:0 10px 30px rgba(0,0,0,.2);transform:translateY(-24px);opacity:0;transition:.25s}
#loader{position:fixed;inset:0;background:rgba(255,255,255,.65);display:none;align-items:center;justify-content:center;z-index:9998}
#loader .box{display:flex;gap:12px;align-items:center;background:#fff;padding:12px 14px;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.12)}
#loader .spin{width:16px;height:16px;border-radius:999px;border:2px solid #e5e7eb;border-top-color:#dc2626;animation:sp 1s linear infinite}
@keyframes sp{to{transform:rotate(360deg)}}

/* Back to top */
#topBtn{
  position:fixed; right:18px; bottom:18px; z-index:40;
  display:none; padding:10px 12px; border-radius:999px; border:1px solid var(--accent-300); background:#fff; color:#111; font-weight:600
}
#topBtn.show{display:block}

/* Print */
@media print{
  .header, .toc, #topBtn, #toastWrap, #loader { display:none !important; }
  .wrapper{display:block}
  .main{max-width:none}
  pre{page-break-inside:avoid}
}
</style>
</head>
<body>

<header class="header">
  <div class="brand">
    <div class="dot"></div>
    <h1>Krasniqi Invoicing App — Project Documentation</h1>
  </div>
  <div class="updated">Last updated: <span id="updatedAt">—</span></div>
</header>

<div class="wrapper">
  <!-- TOC -->
  <aside class="toc">
    <div class="search">
      <input id="tocSearch" type="search" placeholder="Search sections…" oninput="filterToc(this.value)">
    </div>

    <div class="section-title">Overview</div>
    <nav id="tocNav">
      <a href="#quick-start">Quick “Where to change X?” <small>Cheat Sheet</small></a>
      <a href="#stack">Tech Stack</a>
      <a href="#layout">File / Folder Layout</a>
      <a href="#db">Database Schema</a>
      <a href="#config">Configuration</a>
      <a href="#pages">Page Responsibilities</a>
      <a href="#style">Style System</a>
      <a href="#toasts-loader">Toasts & Loader</a>
      <a href="#pdf">PDF Export — Internals</a>
      <a href="#security">Security Notes</a>
      <a href="#deploy">Deployment / Environment</a>
      <a href="#tasks">Common Tasks</a>
      <a href="#errors">Common Errors & Fixes</a>
      <a href="#testing">Testing Checklist</a>
      <a href="#roadmap">Roadmap</a>
      <a href="#maintenance">Maintenance</a>
      <a href="#appendix">Appendix A — Snippets</a>
    </nav>

    <div style="margin-top:14px">
      <span class="pill">Red Theme</span>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <section class="hero card">
      <h2 style="margin:6px 0 8px">Krasniqi Invoicing App — Project Docs</h2>
      <p class="lead">This page documents the whole app: structure, database, where to change things, PDF export, UI system, and common tasks. Save this file as <strong>project_docs.php</strong> in your project root.</p>
      <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px">
        <a class="btn primary" href="#quick-start">Jump to Cheat Sheet</a>
        <button class="btn" onclick="window.print()">Print / Save PDF</button>
      </div>
    </section>

    <!-- 1) Quick cheat -->
    <section id="quick-start">
      <h2>1) Quick “Where do I change X?” (Cheat Sheet)</h2>
      <div class="grid two">
        <div class="card">
          <ul>
            <li><strong>Company data (name, address, phone, email, VAT, IBAN, logo, signer)</strong><br>→ <code>company_profile</code> table via <code>company.php</code>. Auto-used by <code>invoice_form.php</code> and <code>landing.php</code>.</li>
            <li><strong>Default VAT for new invoices</strong><br>→ <code>company_profile.vat_rate</code> (overridable in invoice editor).</li>
            <li><strong>Buttons look (pill/gradient)</strong><br>→ CSS in pages; search for <code>.btn</code>, <code>.btn.primary</code>.</li>
            <li><strong>PDF filename format</strong><br>→ <code>invoice_form.php</code> → <code>downloadPDF()</code>.</li>
          </ul>
        </div>
        <div class="card">
          <ul>
            <li><strong>Toast & Loader</strong><br>→ Helpers <code>showToast()</code>, <code>showLoader()</code>, <code>hideLoader()</code>.</li>
            <li><strong>QR page text</strong><br>→ <code>invoice_form.php</code> → QR Page section + <code>setQR()</code>.</li>
            <li><strong>Autopdf link</strong><br>→ Append <code>?autopdf=1</code> to <code>invoice_form.php</code> URL.</li>
            <li><strong>Invoice columns/logic</strong><br>→ <code>invoice_form.php</code> (table + <code>recalc()</code>).</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- 2) Stack -->
    <section id="stack">
      <h2>2) Tech Stack</h2>
      <div class="card">
        <ul>
          <li><strong>Frontend:</strong> HTML/CSS/JS, CSS variables (red theme), pill buttons, toasts/loader.</li>
          <li><strong>Backend:</strong> PHP 8+ with <code>mysqli</code>.</li>
          <li><strong>Database:</strong> MySQL (<code>invoices_db</code>), UTF8MB4.</li>
          <li><strong>PDF:</strong> <code>html2canvas</code> + <code>jsPDF</code> (client-side, paginated A4).</li>
          <li><strong>Sessions:</strong> Native PHP sessions; private pages redirect to <code>login.php</code>.</li>
        </ul>
      </div>
    </section>

    <!-- 3) Structure -->
    <section id="layout">
      <h2>3) File / Folder Layout</h2>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code><?php echo htmlspecialchars(
'/ (webroot)
  config.php                # Session + DB connect (shared)
  landing.php               # Public landing (reads company_profile)
  index.php                 # Dashboard
  login.php                 # Auth (redirects back to next=?)
  users.php                 # Users (optional)
  company.php               # Company profile editor
  create.php                # Shortcut to invoice_form
  invoice_form.php          # Invoice editor + PDF
  /assets
    krasniqilogo.png        # Logo
    hero_mock.png           # Landing hero (optional)'
      ); ?></code></pre>
      <div class="callout">Tip: If you prefer, extract shared CSS/JS into <code>/assets/ui.css</code> and <code>/assets/ui.js</code> and include them in all pages.</div>
    </section>

    <!-- 4) DB schema -->
    <section id="db">
      <h2>4) Database Schema</h2>

      <h3>4.1 <code>company_profile</code></h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code><span class="c">-- Company profile (latest row is active)</span>
CREATE TABLE IF NOT EXISTS <span class="k">company_profile</span> (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  street VARCHAR(190) DEFAULT NULL,
  zip VARCHAR(20) DEFAULT NULL,
  city VARCHAR(120) DEFAULT NULL,
  phone VARCHAR(60) DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  iban VARCHAR(120) DEFAULT NULL,
  bank VARCHAR(120) DEFAULT NULL,
  company_id VARCHAR(120) DEFAULT NULL,
  vat_rate DECIMAL(5,2) DEFAULT 8.10,
  logo_path VARCHAR(255) DEFAULT NULL,
  signer VARCHAR(120) DEFAULT NULL,
  signer_title VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);</code></pre>

      <h4>Insert example</h4>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>INSERT INTO company_profile
(name, street, zip, city, phone, email, iban, bank, company_id, vat_rate, logo_path, signer, signer_title)
VALUES
('Krasniqi Plattenleger', 'Feldstrasse 110', '4123', 'Allschwil', '076 50 30 788',
 'info@krasniqi-plattenleger.ch', 'CH26 0023 3233 3300 2501 L', 'UBS Allschwil',
 'CHE-255-255-255', 8.10, 'krasniqilogo.png', 'SHKELQIM KRASNIQI', 'Geschäftsführer');</code></pre>

      <h3>4.2 <code>invoices</code></h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>CREATE TABLE IF NOT EXISTS <span class="k">invoices</span> (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(190) NOT NULL,
  bill_to TEXT NOT NULL,
  invoice_number VARCHAR(60) DEFAULT NULL,
  invoice_date VARCHAR(20) DEFAULT NULL,
  ref_text VARCHAR(190) DEFAULT NULL,
  vat_rate DECIMAL(5,2) DEFAULT 0.00,
  subtotal DECIMAL(12,2) DEFAULT 0.00,
  vat_amount DECIMAL(12,2) DEFAULT 0.00,
  total DECIMAL(12,2) DEFAULT 0.00,
  amount_due DECIMAL(12,2) DEFAULT 0.00,
  remark TEXT,
  items_json LONGTEXT,
  lump_sum TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);</code></pre>

      <h3>4.3 <code>users</code> (basic)</h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>CREATE TABLE IF NOT EXISTS <span class="k">users</span> (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);</code></pre>
    </section>

    <!-- 5) Config -->
    <section id="config">
      <h2>5) Configuration</h2>

      <h3>5.1 <code>config.php</code></h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code><?php echo htmlspecialchars(
'<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$mysqli = @new mysqli("localhost", "root", "", "invoices_db");
if ($mysqli->connect_error) {
  error_log("DB connection failed: ".$mysqli->connect_error);
} else {
  $mysqli->set_charset("utf8mb4");
}'
      ); ?></code></pre>

      <h3>5.2 Use in pages</h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code><?php echo htmlspecialchars(
'<?php require_once __DIR__."/config.php"; ?>'
      ); ?></code></pre>
    </section>

    <!-- 6) Pages -->
    <section id="pages">
      <h2>6) Page Responsibilities</h2>
      <table>
        <thead><tr><th>Page</th><th>Purpose</th><th>Notes</th></tr></thead>
        <tbody>
          <tr><td><code>landing.php</code></td><td>Public landing (reads company profile)</td><td>Gradient hero, alternating sections, shared buttons, toast/loader included.</td></tr>
          <tr><td><code>login.php</code></td><td>Auth form</td><td>Sets <code>$_SESSION['user']</code>, redirects to <code>next</code> or dashboard.</td></tr>
          <tr><td><code>index.php</code></td><td>Dashboard</td><td>List invoices, quick links.</td></tr>
          <tr><td><code>users.php</code></td><td>Users admin</td><td>Optional; restrict to admin.</td></tr>
          <tr><td><code>company.php</code></td><td>Edit company profile</td><td>New row per save; latest row is active.</td></tr>
          <tr><td><code>invoice_form.php</code></td><td>Invoice editor + PDF</td><td>Items, groups, lump-sum, Rabatt, VAT, deposit, remark, QR page.</td></tr>
        </tbody>
      </table>
      <div class="callout" style="margin-top:12px">
        <strong>Prepared statements tip:</strong> In <code>company.php</code> when calling <code>bind_param</code>, the type string must match variables count. Strings use <code>s</code>, decimals <code>d</code>, integers <code>i</code>.
      </div>
    </section>

    <!-- 7) Style -->
    <section id="style">
      <h2>7) Style System (Design Tokens)</h2>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>:root{
  --bg:#f5f7fb; --ink:#0f172a; --muted:#6b7280; --card:#ffffff; --line:#e5e7eb;
  --accent-50:#fef2f2; --accent-100:#fee2e2; --accent-200:#fecaca; --accent-300:#fca5a5;
  --accent-400:#f87171; --accent-500:#ef4444; --accent-600:#dc2626; --accent-700:#b91c1c;
  --accent-800:#991b1b; --accent-900:#7f1d1d; --surface:#fff7f7; --black:#000;
  --radius:14px;
}</code></pre>
      <p>Buttons:</p>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>.btn{border:1px solid var(--accent-300); background:#fff; color:var(--accent-800); padding:10px 14px; border-radius:999px; font-weight:600}
.btn.primary{background:linear-gradient(180deg,var(--accent-600),var(--accent-700)); color:#fff; border-color:var(--accent-700)}</code></pre>
    </section>

    <!-- 8) Toasts & Loader -->
    <section id="toasts-loader">
      <h2>8) Toasts & Loader (Global UX)</h2>
      <p>Add once per page:</p>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>&lt;div id="toastWrap"&gt;&lt;div id="toast"&gt;&lt;/div&gt;&lt;/div&gt;
&lt;div id="loader"&gt;&lt;div class="box"&gt;&lt;span class="spin"&gt;&lt;/span&gt;&lt;span id="loaderText"&gt;Bitte warten…&lt;/span&gt;&lt;/div&gt;&lt;/div&gt;</code></pre>

      <p>Helpers:</p>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>function showToast(message, type="success"){
  const el = document.getElementById("toast"); if (!el) return;
  el.textContent = message;
  el.style.background = (type==="error") ? "#991b1b" : "#065f46";
  el.style.opacity = "1"; el.style.transform = "translateY(0)";
  setTimeout(()=>{ el.style.opacity="0"; el.style.transform="translateY(-24px)"; }, 2200);
}
function showLoader(text="Bitte warten…"){
  const el = document.getElementById("loader"); if (!el) return;
  document.getElementById("loaderText").textContent = text; el.style.display = "flex";
}
function hideLoader(){ const el = document.getElementById("loader"); if (el) el.style.display = "none"; }</code></pre>
    </section>

    <!-- 9) PDF -->
    <section id="pdf">
      <h2>9) PDF Export — How It Works</h2>
      <ol>
        <li>Silent save via <code>saveInvoiceAjax()</code> to ensure an <code>id</code>.</li>
        <li>Add <code>.exporting</code> to <code>&lt;body&gt;</code> to hide UI.</li>
        <li>Clone document and paginate rows until footer area; totals on last page only.</li>
        <li>Append QR page.</li>
        <li>Each page → <code>html2canvas</code> → added to <code>jsPDF</code>.</li>
        <li>Filename pattern: <code>&lt;first-line of bill_to&gt; - Rechnung - &lt;date&gt;.pdf</code></li>
      </ol>
      <div class="callout">Tune <code>MAX_EXPORT_PAGES</code> and paddings if rows spill.</div>
    </section>

    <!-- 10) Security -->
    <section id="security">
      <h2>10) Security Notes</h2>
      <ul>
        <li>Protect private pages:
          <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code><?php echo htmlspecialchars(
'<?php
if (empty($_SESSION["user"])) {
  header("Location: login.php?next=".urlencode($_SERVER["REQUEST_URI"]));
  exit;
}'
          ); ?></code></pre>
        </li>
        <li>SQL: prefer prepared statements; types in <code>bind_param</code> must match.</li>
        <li>XSS: always <code>htmlspecialchars()</code> when echoing DB/user data.</li>
        <li>Uploads: validate MIME, store safely.</li>
      </ul>
    </section>

    <!-- 11) Deployment -->
    <section id="deploy">
      <h2>11) Deployment / Environment</h2>
      <ul>
        <li><strong>Local (XAMPP):</strong> project in <code>C:\xampp\htdocs\imv\</code> → <code>http://localhost/imv/landing.php</code></li>
        <li><strong>Prod:</strong> copy folder, set DB in <code>config.php</code>, ensure <code>/assets</code> readable, enable caching.</li>
      </ul>
    </section>

    <!-- 12) Tasks -->
    <section id="tasks">
      <h2>12) Common Tasks</h2>
      <h3>Change default VAT to 7.7%</h3>
      <ol class="check">
        <li>In <code>company.php</code>, set <strong>VAT rate</strong> to <code>7.7</code> and save; or:</li>
      </ol>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>INSERT INTO company_profile (name, vat_rate, ...) VALUES ("...", 7.70, ...);</code></pre>

      <h3>Change buttons globally</h3>
      <p>Edit <code>.btn</code> &amp; <code>.btn.primary</code> rules in pages (or extract to <code>/assets/ui.css</code>).</p>

      <h3>Add a new invoice field (e.g., Projekt-Nr.)</h3>
      <ol>
        <li><code>invoice_form.php</code> → add to <strong>Rechnungsdaten</strong> block</li>
        <li>Add hidden input for POST</li>
        <li>Set value in <code>fillHiddenFields()</code></li>
        <li>Extend DB + INSERT/UPDATE</li>
      </ol>

      <h3>Change PDF filename format</h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>// invoice_form.php → downloadPDF()
const filename = `${name} - Rechnung - ${dateStr}.pdf`;
// e.g., `${invNumber}_${name}_${dateStr}.pdf`</code></pre>

      <h3>Set “Alles inkl.” default ON</h3>
      <p>Set <code>lumpSumMode = true</code> at init and show <code>#lumpSumRow</code>.</p>

      <h3>Add a logo</h3>
      <ol>
        <li>Put file in <code>/assets</code></li>
        <li>In <code>company.php</code>, set <code>logo_path</code></li>
        <li><code>invoice_form.php</code> &amp; <code>landing.php</code> will render it.</li>
      </ol>
    </section>

    <!-- 13) Errors -->
    <section id="errors">
      <h2>13) Common Errors & Fixes</h2>
      <ul>
        <li><strong>bind_param mismatch</strong> → ensure types count equals variables count; use correct types (<code>s</code>/<code>d</code>/<code>i</code>).</li>
        <li><strong>Undefined array key</strong> → use <code>$_POST['field'] ?? ''</code>.</li>
        <li><strong>PDF overflow/blank</strong> → reduce per-page content or adjust paddings.</li>
        <li><strong>Logo blocked in html2canvas</strong> → use same-origin paths (local assets).</li>
      </ul>
    </section>

    <!-- 14) Testing -->
    <section id="testing">
      <h2>14) Testing Checklist</h2>
      <ol class="check">
        <li>Invoice: groups + 20+ items paginate correctly</li>
        <li>Lump-sum hides qty/price columns</li>
        <li>Rabatt % & CHF correct (before VAT)</li>
        <li>Deposit reduces Restbetrag & QR amount</li>
        <li>Remark empty → hidden in export</li>
        <li>Company profile reflected on invoice & landing</li>
        <li>Toasts/Loader centered</li>
        <li>Mobile view OK (sidebar toggle)</li>
      </ol>
    </section>

    <!-- 15) Roadmap -->
    <section id="roadmap">
      <h2>15) Roadmap (Nice-to-haves)</h2>
      <ul>
        <li>Autonumber invoices (<code>YYYY-####</code>)</li>
        <li>CSRF tokens</li>
        <li>Role-based access</li>
        <li>Server-side PDF (wkhtmltopdf)</li>
        <li>Swiss QR-Bill data payload</li>
        <li>Watermark option</li>
      </ul>
    </section>

    <!-- 16) Maintenance -->
    <section id="maintenance">
      <h2>16) Maintenance</h2>
      <ul>
        <li>Keep <code>company_profile</code> updated</li>
        <li>Compress images</li>
        <li>DB backups (<code>invoices</code> + <code>company_profile</code>)</li>
        <li>Track edits in <code>CHANGELOG.md</code></li>
      </ul>
    </section>

    <!-- Appendix -->
    <section id="appendix">
      <h2>Appendix A — Snippets</h2>
      <h3>Success toast</h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>showToast("Gespeichert");</code></pre>

      <h3>Error toast</h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>showToast("Fehler aufgetreten","error");</code></pre>

      <h3>Loader around async</h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code>showLoader("Bitte warten…");
try {
  await saveInvoiceAjax();
  showToast("Gespeichert");
} catch(e){
  showToast("Speichern fehlgeschlagen","error");
} finally {
  hideLoader();
}</code></pre>

      <h3>DB connect (shared)</h3>
      <pre><button class="btn copy" onclick="copyBlock(this)">Copy</button><code><?php echo htmlspecialchars(
'<?php
require_once __DIR__."/config.php";
if (!$mysqli || $mysqli->connect_error) { die("DB offline"); }'
      ); ?></code></pre>
    </section>

    <button id="topBtn" class="btn" onclick="window.scrollTo({top:0,behavior:\'smooth\'})">Top ↑</button>
    <div id="toastWrap"><div id="toast"></div></div>
    <div id="loader"><div class="box"><span class="spin"></span><span id="loaderText">Bitte warten…</span></div></div>
  </main>
</div>

<script>
/* Last updated date */
document.getElementById('updatedAt').textContent = new Date().toLocaleDateString();

/* Back to top visibility */
const topBtn = document.getElementById('topBtn');
window.addEventListener('scroll', () => {
  if (window.scrollY > 600) topBtn.classList.add('show'); else topBtn.classList.remove('show');
});

/* TOC active link highlight on scroll */
const sections = Array.from(document.querySelectorAll('main section[id]'));
const tocLinks = Array.from(document.querySelectorAll('#tocNav a'));
const byId = id => document.getElementById(id);

function onScrollSpy(){
  const y = window.scrollY + 120;
  let current = null;
  for (const sec of sections){
    const r = sec.getBoundingClientRect();
    const top = r.top + window.scrollY;
    if (top <= y) current = sec.id;
  }
  tocLinks.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#'+current));
}
document.addEventListener('scroll', onScrollSpy, {passive:true});
onScrollSpy();

/* TOC search filter */
function filterToc(q){
  q = (q||'').toLowerCase().trim();
  tocLinks.forEach(a=>{
    const txt = a.textContent.toLowerCase();
    a.style.display = txt.includes(q) ? '' : 'none';
  });
}
window.filterToc = filterToc;

/* Copy button for code blocks */
function copyBlock(btn){
  const pre = btn.closest('pre');
  const code = pre && pre.querySelector('code');
  if (!code) return;
  const text = code.innerText.replace(/\u00A0/g,' ');
  navigator.clipboard.writeText(text).then(()=>{
    btn.textContent = 'Copied!';
    setTimeout(()=>btn.textContent='Copy',1100);
  }).catch(()=>{
    btn.textContent = 'Failed';
    setTimeout(()=>btn.textContent='Copy',1100);
  });
}
window.copyBlock = copyBlock;

/* Toasts & Loader for this page (re-usable helpers) */
function showToast(message, type='success'){
  const el = document.getElementById('toast'); if (!el) return;
  el.textContent = message;
  el.style.background = (type==='error') ? '#991b1b' : '#065f46';
  el.style.opacity = '1';
  el.style.transform = 'translateY(0)';
  setTimeout(()=>{ el.style.opacity='0'; el.style.transform='translateY(-24px)'; }, 2200);
}
function showLoader(text='Bitte warten…'){
  const el = document.getElementById('loader'); if (!el) return;
  document.getElementById('loaderText').textContent = text; el.style.display = 'flex';
}
function hideLoader(){ const el = document.getElementById('loader'); if (el) el.style.display = 'none'; }

/* Demo toast on load (optional) */
// showToast('Docs ready');
</script>
</body>
</html>
