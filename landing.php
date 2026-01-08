<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Krasniqi – Rechnungen, aber hübsch ✨</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<meta name="description" content="Schöne Rechnungen, stabile A4-PDFs, MWST 8.1%, Dashboard & Benutzerverwaltung.">
<style>
/* ===== Theme ===== */
:root{
  /* Base */
  --bg:#0b1020;           /* deep slate */
  --card:#0f1629;         /* darker card */
  --ink:#e5e7eb;          /* light text */
  --muted:#94a3b8;        /* slate */
  --line:#1f2a44;         /* divider */

  /* Brand spectrum */
  --r1:#ef4444; --r2:#dc2626; --r3:#b91c1c; /* reds */
  --o1:#f59e0b; /* amber */
  --p1:#db2777; /* fuchsia */
  --v1:#7c3aed; /* violet */
  --c1:#06b6d4; --c2:#0891b2; /* cyan */

  --radius:16px; --container:1140px;
}

/* ===== Reset / Base ===== */
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--ink);
  font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}

/* ===== Aurora Background ===== */
body::before,
body::after{
  content:"";
  position:fixed; inset:-20vmax;
  background:
    radial-gradient(35vmax 35vmax at 20% 20%, color-mix(in oklab, var(--r1), #fff 10%) 0%, transparent 60%),
    radial-gradient(40vmax 40vmax at 80% 10%, color-mix(in oklab, var(--p1), #fff 5%) 0%, transparent 60%),
    radial-gradient(45vmax 45vmax at 50% 90%, color-mix(in oklab, var(--c1), #fff 8%) 0%, transparent 60%);
  filter: blur(40px) saturate(120%);
  z-index:-2; opacity:.26;
  animation: floaty 22s ease-in-out infinite alternate;
}
body::after{
  opacity:.22; filter: blur(60px) saturate(140%);
  animation-duration: 28s;
}
@keyframes floaty{
  0% { transform: translate3d(0,0,0) scale(1); }
  100%{ transform: translate3d(2vw,-1vh,0) scale(1.06); }
}

/* ===== Header / Nav ===== */
.header{position:sticky;top:0;z-index:30;
  backdrop-filter:saturate(160%) blur(8px);
  background:linear-gradient(180deg, rgba(15,22,41,.75), rgba(15,22,41,.55));
  border-bottom:1px solid var(--line);
}
.container{max-width:var(--container);margin:0 auto;padding:0 20px}
.nav{display:flex;align-items:center;justify-content:space-between;height:68px}
.brand{display:flex;align-items:center;gap:12px}
.logo{width:36px;height:36px;border-radius:10px;
  background: conic-gradient(from 180deg, var(--r1), var(--o1), var(--p1), var(--v1), var(--r1));
  box-shadow:0 0 0 2px #ffffff22 inset, 0 8px 24px #00000040;
}
.brand span{font-weight:800;letter-spacing:.2px}
.nav-links{display:flex;gap:10px;align-items:center}
.nav a{color:var(--ink);text-decoration:none;padding:8px 12px;border-radius:12px}
.nav a:hover{background:#ffffff12}
.burger{display:none;border:1px solid var(--line);background:#101a30;border-radius:10px;padding:8px;color:var(--ink)}

/* ===== Buttons ===== */
.btn{
  --grad: linear-gradient(90deg, var(--r2), var(--o1));
  display:inline-block; padding:12px 16px; border-radius:14px;
  background: var(--grad); color:white; font-weight:800; letter-spacing:.2px;
  box-shadow: 0 8px 24px #0008, 0 0 0 1px #ffffff12 inset;
  text-decoration:none; transition: transform .12s ease, box-shadow .12s ease;
}
.btn:hover{ transform: translateY(-1px); box-shadow: 0 12px 28px #000a, 0 0 0 1px #ffffff22 inset; }
.btn-ghost{
  display:inline-block; padding:12px 16px; border-radius:14px;
  background:#0f1629; color:var(--ink); border:1px solid #ffffff1c; text-decoration:none; font-weight:700;
}
.btn-ghost:hover{ background:#111b33; }

/* ===== Hero ===== */
.hero{padding:72px 0 36px}
.hero-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:32px;align-items:center}
.kicker{
  display:inline-flex;gap:10px;align-items:center;
  padding:6px 12px; border-radius:999px; font-weight:700; letter-spacing:.2px;
  background: linear-gradient(90deg, #ffffff0c, #ffffff05);
  border:1px solid #ffffff24;
}
.badge{display:inline-flex;align-items:center;gap:6px;
  padding:2px 8px; border-radius:999px; font-size:12px; font-weight:800;
  color:white; background:linear-gradient(90deg, var(--p1), var(--v1));
  box-shadow:0 0 0 1px #ffffff38 inset;
}
.hero h1{
  font-size:42px; line-height:1.06; margin:16px 0 10px;
  background: linear-gradient(90deg, #fff, #fff, #ffffffcc);
  -webkit-background-clip: text; background-clip: text; color: transparent;
  text-shadow: 0 1px 0 #0003;
}
.hero p.lead{font-size:18px;color:#cbd5e1}
.cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:14px}

/* Mock window */
.shot{
  border-radius:18px; overflow:hidden; background:#0c1324; border:1px solid #ffffff1a;
  box-shadow: 0 30px 70px #000c, 0 0 0 1px #ffffff10 inset;
}
.shot .bar{height:46px; display:flex; align-items:center; gap:8px; padding:0 10px;
  background: linear-gradient(180deg, #111a31, #0f172a);
  border-bottom:1px solid #ffffff12;
}
.dot{width:10px;height:10px;border-radius:999px;background:#64748b}
.shot .body{
  height:380px;
  background:
    linear-gradient(90deg, #ffffff08 1px, transparent 1px) left/44px 100%,
    linear-gradient(#ffffff08 1px, transparent 1px) top/44px 44px;
  position:relative;
}
.shot .body::after{
  content:"A4 • PDF Vorschau"; position:absolute; right:12px; bottom:12px;
  font-size:12px; color:#9aa7bf; background:#0b1223; padding:6px 10px; border-radius:10px; border:1px solid #ffffff16;
}

/* ===== Section wrapper ===== */
.section{padding:48px 0}

/* ===== Rainbow Divider ===== */
.divider{
  height:2px; margin:24px 0; border-radius:999px;
  background: linear-gradient(90deg, var(--r1), var(--o1), var(--p1), var(--v1), var(--c1));
  filter: saturate(150%);
}

/* ===== Default tiles (dark) ===== */
.features{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.tile{
  background: linear-gradient(180deg, #0f172a, #0c1324);
  border:1px solid #ffffff14; border-radius:16px; padding:18px;
  box-shadow: 0 12px 32px #000a;
  position:relative; overflow:hidden;
}
.tile h3{margin:0 0 6px; font-size:16px}
.tile .chip{
  display:inline-flex; align-items:center; gap:6px; font-size:11px; font-weight:800;
  padding:2px 8px; border-radius:999px; color:white;
  background: linear-gradient(90deg, var(--r2), var(--o1));
  border:1px solid #ffffff28;
}

/* ===== Steps (dark) ===== */
.steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.step{
  background: linear-gradient(180deg, #0e1628, #0b1324);
  border:1px solid #ffffff12; border-radius:16px; padding:18px;
}
.step .n{
  width:28px;height:28px;border-radius:999px; display:inline-grid; place-items:center;
  background:linear-gradient(90deg, var(--r1), var(--p1));
  font-weight:900; border:1px solid #ffffff2c;
  margin-bottom:8px;
}

/* ===== Pricing (dark default) ===== */
.pricing{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.plan{
  background: linear-gradient(180deg, #0f172a, #0b1223);
  border:1px solid #ffffff18; border-radius:18px; padding:20px;
  box-shadow: 0 12px 32px #000a;
}
.plan.pro{ background: radial-gradient(120% 120% at 100% 0%, #ffffff08, #0000 40%), linear-gradient(180deg, #111a31, #0e1629); border-color:#ffffff28; }
.price{font-size:32px;font-weight:900}
ul.clean{list-style:none;margin:10px 0 0;padding:0}
ul.clean li{margin:8px 0;padding-left:22px;position:relative;color:#d1d5db}
ul.clean li::before{content:"✓";position:absolute;left:0;top:0;color:#22c55e}

/* ===== FAQ ===== */
.faq{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.q{background:linear-gradient(180deg, #0f172a, #0c1426);border:1px solid #ffffff16;border-radius:16px;padding:16px}
.q h4{margin:0 0 6px}

/* ===== Footer ===== */
.footer{background:linear-gradient(180deg, #0f172a, #0a0f1f);border-top:1px solid #ffffff10;padding:28px 0;margin-top:36px}
.foot{display:flex;gap:16px;justify-content:space-between;align-items:center;flex-wrap:wrap}
.links{display:flex;gap:12px;flex-wrap:wrap}
.small{font-size:12px;color:#94a3b8}

/* ===== Themed Sections ===== */
/* 1) Warm RED section variant */
.alt-red{
  --sec-top: #1a0d12;
  --sec-bot: #140a11;
  background: radial-gradient(120% 140% at 10% 0%, #3a1217 0%, #210c12 40%, #12070b 100%),
              linear-gradient(180deg, var(--sec-top), var(--sec-bot));
  border-top: 1px solid #ffffff14;
  border-bottom: 1px solid #ffffff10;
}
.alt-red .tile{
  background: linear-gradient(180deg, #1b0e14, #12080d);
  border-color:#ffffff22;
  box-shadow: 0 12px 40px rgba(185,28,28,.25);
}
.alt-red .tile .chip{
  background: linear-gradient(90deg, var(--r1), var(--o1));
  border-color:#ffffff38;
}
.alt-red h2, .alt-red p{ color:#f0f2f8 }

/* 2) Cool CYAN section variant */
.alt-cyan{
  --sec-top:#0a1620; --sec-bot:#08131c;
  background: radial-gradient(120% 140% at 100% 0%, #0a2a37 0%, #081a24 40%, #051018 100%),
              linear-gradient(180deg, var(--sec-top), var(--sec-bot));
  border-top: 1px solid #ffffff14;
  border-bottom: 1px solid #ffffff10;
}
.alt-cyan .plan{
  background: linear-gradient(180deg, #0b1a26, #0a141e);
  border-color:#0ea5b81f;
  box-shadow: 0 12px 40px rgba(8,145,178,.25);
}
.alt-cyan .plan.pro{
  background: radial-gradient(120% 120% at 100% 0%, #0b2a34 0%, #0b1622 40%), linear-gradient(180deg, #0e1d2a, #0b141e);
  border-color:#0ea5b833;
}
.alt-cyan h2, .alt-cyan p{ color:#e9fbff }

/* ===== Responsive ===== */
@media (max-width:980px){
  .hero-grid{grid-template-columns:1fr}
  .features{grid-template-columns:1fr 1fr}
  .pricing{grid-template-columns:1fr}
}
@media (max-width:640px){
  .features{grid-template-columns:1fr}
  .faq{grid-template-columns:1fr}
  .nav-links{display:none}
  .burger{display:block}
}
</style>
</head>
<body>

<!-- Header -->
<header class="header">
  <div class="container nav">
    <div class="brand">
      <div class="logo"></div>
      <span>Krasniqi – Admin</span>
    </div>
    <nav class="nav-links">
      <a href="#features">Funktionen</a>
      <a href="#screens">Screens</a>
      <a href="#pricing">Preise</a>
      <a href="#faq">FAQ</a>
      <a class="btn-ghost" href="login.php">Login</a>
      <a class="btn" href="create.php">Jetzt starten</a>
    </nav>
    <button class="burger" onclick="toggleMenu()">☰</button>
  </div>
</header>

<!-- Hero -->
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="kicker">🇨🇭 MWST-ready <span class="badge">8.1%</span></span>
      <h1>Rechnungen in Minuten. <br/>PDFs in Perfektion.</h1>
      <p class="lead">Items oder „Alles inkl.“, Gruppen/Räume, Rabatte & Anzahlungen. Export als A4-PDF mit QR-Seite. Dashboard, Filter, CSV & Benutzerverwaltung – alles in Rot, aber schön.</p>
      <div class="cta">
        <a class="btn" href="login.php">Anmelden</a>
        <a class="btn-ghost" href="#screens">Demo ansehen</a>
      </div>
      <div class="divider"></div>
      <p class="lead" style="font-size:14px;color:#a5b4fc">Sicher: gehashte Passwörter • CSRF • eigene MySQL-Daten.</p>
    </div>

    <div id="screens" class="shot">
      <div class="bar"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>
      <div class="body"></div>
    </div>
  </div>
</section>

<!-- Features (ALT RED) -->
<section id="features" class="section alt-red">
  <div class="container">
    <h2>Farbig, aber praktisch</h2>
    <p class="lead" style="font-size:15px;opacity:.95">Power-Features ohne Overkill. Fein abgestimmt auf deinen Workflow.</p>
    <div class="features" style="margin-top:16px">
      <div class="tile">
        <span class="chip">PDF</span>
        <h3>Stabile A4-Seiten</h3>
        <p>Saubere Seitenumbrüche, QR-Seite getrennt, schöner Dateiname (<code>Name – Rechnung – Datum.pdf</code>).</p>
      </div>
      <div class="tile">
        <span class="chip">Gruppen</span>
        <h3>Räume & Abschnitte</h3>
        <p>Positionen nach Zimmern sortieren, Headings zählen nicht in die Summe.</p>
      </div>
      <div class="tile">
        <span class="chip">Rabatt</span>
        <h3>% oder CHF</h3>
        <p>Vor MWST, zusammen mit Anzahlung → Restbetrag automatisch.</p>
      </div>
      <div class="tile">
        <span class="chip">MWST</span>
        <h3>8.1% korrekt</h3>
        <p>Netto → MWST → Total; Dashboard zeigt Umsatz, Netto, Steuern & Status.</p>
      </div>
      <div class="tile">
        <span class="chip">CSV</span>
        <h3>Export & Suche</h3>
        <p>Filter (Monat/Quartal/Jahr/Custom), Sortierung, Paging, CSV-Export.</p>
      </div>
      <div class="tile">
        <span class="chip">Users</span>
        <h3>Admin & Staff</h3>
        <p>Geordnete Rechte, Passwort-Reset, sichere Sessions.</p>
      </div>
    </div>
  </div>
</section>

<!-- Steps (default dark) -->
<section class="section" style="padding-top:36px">
  <div class="container">
    <h2>So einfach ist es</h2>
    <div class="steps" style="margin-top:16px">
      <div class="step"><div class="n">1</div><h3>Erstellen</h3><p>Kunde, Positionen oder Pauschale. Rabatt/Anzahlung einstellen.</p></div>
      <div class="step"><div class="n">2</div><h3>Exportieren</h3><p>Ein Klick → A4-PDF + QR-Seite, schöner Dateiname automatisch.</p></div>
      <div class="step"><div class="n">3</div><h3>Überblicken</h3><p>Umsatz, Netto, MWST, bezahlt/offen – plus CSV & Top-Kunden.</p></div>
    </div>
  </div>
</section>

<!-- Pricing (ALT CYAN) -->
<section id="pricing" class="section alt-cyan">
  <div class="container">
    <h2>Preise</h2>
    <p class="lead" style="font-size:15px;opacity:.95">Self-hosted heute. Pro-Addons morgen.</p>
    <div class="pricing" style="margin-top:16px">
      <div class="plan">
        <h3>Self-Hosted</h3>
        <div class="price">0 CHF</div>
        <ul class="clean">
          <li>Unbegrenzte Rechnungen</li>
          <li>A4-PDF + QR-Seite</li>
          <li>Dashboard, Suche, CSV</li>
          <li>Benutzer & Rollen</li>
        </ul>
        <div class="cta" style="margin-top:12px">
          <a class="btn" href="login.php" style="--grad:linear-gradient(90deg,var(--r2),var(--o1))">Los geht’s</a>
          <a class="btn-ghost" href="create.php">Neue Rechnung</a>
        </div>
      </div>
      <div class="plan pro">
        <h3>Pro (Bald)</h3>
        <div class="price">XX CHF/Monat</div>
        <ul class="clean">
          <li>Wiederkehrende Rechnungen</li>
          <li>Online-Zahlungen</li>
          <li>Mehrwährungen & Rundungen</li>
          <li>Kundenportal</li>
        </ul>
        <div class="cta" style="margin-top:12px">
          <a class="btn" href="#faq" style="--grad:linear-gradient(90deg,var(--c1),var(--v1))">Informiert bleiben</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ (default dark) -->
<section id="faq" class="section" style="padding-top:0">
  <div class="container">
    <h2>FAQ</h2>
    <div class="faq" style="margin-top:16px">
      <div class="q"><h4>Wie genau ist der PDF-Export?</h4><p>Jede Seite wird als fixiertes A4 gerendert, Totals & QR sauber getrennt.</p></div>
      <div class="q"><h4>Kann ich Räume/Abschnitte nutzen?</h4><p>Ja, Gruppen/Rooms sind als Überschriften ohne Berechnung.</p></div>
      <div class="q"><h4>Wem gehören die Daten?</h4><p>Dir. Alles in deiner MySQL-DB – plus CSV-Export.</p></div>
      <div class="q"><h4>Ist das sicher?</h4><p>Passwörter gehasht, CSRF-geschützt, Session-Hardening aktiv.</p></div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer">
  <div class="container foot">
    <div class="brand" style="gap:8px">
      <div class="logo"></div><span>Krasniqi</span>
    </div>
    <div class="links">
      <a href="#features">Funktionen</a>
      <a href="#pricing">Preise</a>
      <a href="#faq">FAQ</a>
      <a href="login.php">Login</a>
      <a href="create.php">Neue Rechnung</a>
    </div>
    <div class="small">© <script>document.write(new Date().getFullYear())</script> Krasniqi. Alle Rechte vorbehalten.</div>
  </div>
</footer>

<script>
function toggleMenu(){
  const nav = document.querySelector('.nav-links');
  if (!nav) return;
  nav.style.display = (nav.style.display === 'flex' ? 'none' : 'flex');
}
</script>
</body>
</html>
