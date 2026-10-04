<?php
declare(strict_types=1);
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* ============================================================
   HoodFi — Liquidity Protocol on Robinhood Chain (Mainnet)
   Single-file app. No SQL — settings live in settings.json,
   logo lives as logo.<ext> next to this file.
   ============================================================ */
const ADMIN_PASSWORD = 'Mithai@555';

$DEFAULTS = [
    'site_name'      => 'HoodFi',
    'tg_url'         => '',
    'x_url'          => '',
    'theme'          => 1,
    'logo'           => '',
    'contract_4663'  => '0x0E1d1dD4bE9e4335Bc8419D4874c27eac129DCc1',
    'contract_46630' => '',
];

/* settings file — web folder preferred, system temp fallback if read-only */
function hf_settings_path(): string {
    if (is_writable(__DIR__)) { return __DIR__ . '/settings.json'; }
    return sys_get_temp_dir() . '/hoodfi_settings_' . md5(__DIR__) . '.json';
}
function hf_load(array $d): array {
    $f = hf_settings_path();
    if (is_file($f)) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j)) { return array_merge($d, $j); }
    }
    return $d;
}
function hf_save(array $s): bool {
    return file_put_contents(hf_settings_path(), json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}
function hf_social(string $u, string $base): string {
    $u = trim($u);
    if ($u === '') { return ''; }
    if ($u[0] === '@') { $u = $base . substr($u, 1); }
    elseif (!preg_match('#^https?://#i', $u)) { $u = 'https://' . $u; }
    return filter_var($u, FILTER_VALIDATE_URL) ? substr($u, 0, 200) : '';
}

$action     = (string)($_GET['action'] ?? '');
$loginError = '';

if ($action === 'logout') {
    $_SESSION = [];
    if (session_id() !== '') { session_destroy(); }
    header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($action === 'admin' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pw = (string)($_POST['admin_password'] ?? '');
    if (hash_equals(ADMIN_PASSWORD, $pw)) {
        $_SESSION['hoodfi_admin'] = true;
        header('Location: ?action=admin');
        exit;
    }
    $loginError = 'Incorrect password. Please try again.';
}

$isAdmin = !empty($_SESSION['hoodfi_admin']);

/* ---- Admin API: save settings (name / socials / theme) ---- */
if ($action === 'save_settings') {
    header('Content-Type: application/json');
    if (!$isAdmin) { http_response_code(403); echo '{"ok":false,"error":"forbidden"}'; exit; }
    $b = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $S = hf_load($DEFAULTS);
    if (isset($b['site_name'])) {
        $n = trim(mb_substr((string)$b['site_name'], 0, 40));
        $S['site_name'] = $n !== '' ? $n : 'HoodFi';
    }
    if (isset($b['tg_url'])) { $S['tg_url'] = hf_social((string)$b['tg_url'], 'https://t.me/'); }
    if (isset($b['x_url']))  { $S['x_url']  = hf_social((string)$b['x_url'],  'https://x.com/'); }
    if (isset($b['theme']))  { $t = (int)$b['theme']; $S['theme'] = in_array($t, [1, 2, 3, 4, 5], true) ? $t : 1; }
    foreach (['contract_4663', 'contract_46630'] as $ck) {
        if (isset($b[$ck])) {
            $ca = trim((string)$b[$ck]);
            if ($ca === '' || preg_match('/^0x[a-fA-F0-9]{40}$/', $ca)) { $S[$ck] = $ca; }
        }
    }
    if (!hf_save($S)) {
        echo json_encode(['ok' => false, 'error' => 'Server cannot write the settings file — set folder permissions (CHMOD 777) in your hosting file manager, then try again.']);
        exit;
    }
    echo json_encode(['ok' => true, 'settings' => $S]);
    exit;
}

/* ---- Admin API: logo upload ---- */
if ($action === 'upload_logo') {
    header('Content-Type: application/json');
    if (!$isAdmin) { http_response_code(403); echo '{"ok":false,"error":"forbidden"}'; exit; }
    if (!is_writable(__DIR__)) {
        echo json_encode(['ok' => false, 'error' => 'Folder not writable — set CHMOD 777 in your hosting file manager, then retry']); exit;
    }
    if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'no file received']); exit;
    }
    if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'max 2 MB allowed']); exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['logo']['tmp_name']);
    $map  = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/gif' => 'gif'];
    if (!isset($map[$mime])) {
        echo json_encode(['ok' => false, 'error' => 'PNG / JPG / WebP / SVG / GIF only']); exit;
    }
    foreach (glob(__DIR__ . '/logo.*') ?: [] as $old) {
        if (preg_match('/\.(png|jpe?g|webp|svg|gif)$/', $old)) { @unlink($old); }
    }
    $fname = 'logo.' . $map[$mime];
    if (!move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/' . $fname)) {
        echo json_encode(['ok' => false, 'error' => 'upload failed — folder writable?']); exit;
    }
    $S = hf_load($DEFAULTS);
    $S['logo'] = $fname . '?v=' . time();
    hf_save($S);
    echo json_encode(['ok' => true, 'settings' => $S]);
    exit;
}

$S = hf_load($DEFAULTS);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoodFi — Liquidity Protocol on Robinhood Chain</title>
<meta name="description" content="Deposit ETH, receive LP shares, withdraw anytime. A transparent liquidity pool on Robinhood Chain.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=VT323&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.4/dist/ethers.umd.min.js"></script>
<style>
:root{
  --bg:#ffffff;
  --ink:#0b0b0c;
  --ink-2:#3f4247;
  --ink-3:#8b8c92;
  --line:#e6e7ec;
  --line-soft:#f0f1f5;
  --accent:#b3c0fe;            /* periwinkle */
  --accent-deep:#4c5ef2;
  --accent-ink:#2b3bd4;
  --mint:#daf8f1;
  --mint-deep:#0f9d7c;
  --red:#dc4446;
  --amber:#b97907;
  --card:#ffffff;
  --glass:rgba(255,255,255,.72);
  --radius:18px;
  --font-display:"Space Grotesk",Inter,system-ui,sans-serif;
  --font-body:Inter,system-ui,-apple-system,sans-serif;
  --font-mono:"JetBrains Mono",ui-monospace,SFMono-Regular,monospace;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  font-family:var(--font-body);
  background:var(--bg);
  color:var(--ink);
  -webkit-font-smoothing:antialiased;
  min-height:100vh;
}
::selection{background:var(--accent);color:var(--ink)}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer}
input,select{font-family:inherit}
.mono{font-family:var(--font-mono)}
.container{max-width:1180px;margin:0 auto;padding:0 28px}

/* ---------- Nav ---------- */
.nav{
  position:sticky;top:0;z-index:60;
  background:var(--glass);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border-bottom:1px solid var(--line);
}
.nav-inner{display:flex;align-items:center;gap:26px;height:68px}
.brand{display:flex;align-items:center;gap:11px;font-family:var(--font-display);font-weight:700;font-size:19px;letter-spacing:-.02em}
.brand svg{display:block}
.brand small{font-family:var(--font-mono);font-weight:500;font-size:9.5px;letter-spacing:.14em;color:var(--ink-3);border:1px solid var(--line);border-radius:99px;padding:3px 8px;margin-left:2px}
.tabs{display:flex;gap:4px;margin-left:8px}
.tab-btn{
  border:0;background:transparent;font-size:13.5px;font-weight:500;color:var(--ink-2);
  padding:8px 14px;border-radius:99px;transition:background .2s,color .2s;
}
.tab-btn:hover{background:var(--line-soft)}
.tab-btn.active{background:var(--ink);color:#fff}
.nav-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.net-pill{
  display:flex;align-items:center;gap:7px;font-family:var(--font-mono);font-size:11px;font-weight:500;
  border:1px solid var(--line);border-radius:99px;padding:7px 12px;color:var(--ink-2);background:#fff;
}
.net-dot{width:7px;height:7px;border-radius:50%;background:#c4c6cc}
.net-dot.on{background:var(--mint-deep);box-shadow:0 0 0 3px rgba(15,157,124,.15)}
.btn{
  border:0;border-radius:99px;font-weight:600;font-size:13.5px;
  padding:10px 20px;transition:transform .18s,box-shadow .25s,background .2s,opacity .2s;
}
.btn:active{transform:scale(.97)}
.btn:disabled{opacity:.45;cursor:not-allowed}
.btn-dark{background:var(--ink);color:#fff}
.btn-dark:hover:not(:disabled){box-shadow:0 6px 22px rgba(11,11,12,.22)}
.btn-accent{background:var(--accent-deep);color:#fff}
.btn-accent:hover:not(:disabled){box-shadow:0 6px 22px rgba(76,94,242,.32)}
.btn-ghost{background:#fff;border:1px solid var(--line);color:var(--ink)}
.btn-ghost:hover:not(:disabled){border-color:var(--ink)}
.btn-block{width:100%}

/* wallet dropdown menu */
#walletMenu{
  position:absolute;right:0;top:calc(100% + 8px);min-width:210px;padding:7px;display:none;z-index:90;
  background:var(--card);border:1px solid var(--line);border-radius:14px;
  box-shadow:0 18px 44px -18px rgba(11,11,12,.28);
}
#walletMenu.open{display:block;animation:panelIn .18s cubic-bezier(.22,1,.36,1)}
.wm-item{
  display:block;width:100%;background:none;border:0;padding:10px 13px;border-radius:9px;
  font-size:13.5px;font-weight:500;color:var(--ink-2);text-align:left;transition:background .15s;
}
.wm-item:hover{background:var(--line-soft);color:var(--ink)}
.wm-item.danger{color:var(--red)}

/* ---------- Hero ---------- */
.hero{position:relative;overflow:hidden;border-bottom:1px solid var(--line)}
#shader{position:absolute;inset:0;width:100%;height:100%;display:block;pointer-events:none}
.hero-inner{position:relative;display:grid;grid-template-columns:1.05fr .95fr;gap:56px;padding:88px 0 76px;align-items:center}
.eyebrow{
  font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.22em;color:var(--accent-ink);
  display:inline-flex;align-items:center;gap:8px;margin-bottom:22px;
}
.eyebrow::before{content:"";width:22px;height:1.5px;background:var(--accent-deep)}
h1{
  font-family:var(--font-display);font-weight:700;font-size:clamp(40px,4.6vw,62px);
  line-height:1.02;letter-spacing:-.035em;margin-bottom:20px;
}
h1 .hl{
  background:linear-gradient(100deg,var(--accent) 0%,#c9e9f5 55%,var(--mint) 100%);
  border-radius:.18em;padding:0 .14em;box-decoration-break:clone;-webkit-box-decoration-break:clone;
}
.lede{font-size:16.5px;line-height:1.65;color:var(--ink-2);max-width:46ch;margin-bottom:32px}
.hero-ctas{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.micro{font-family:var(--font-mono);font-size:11px;color:var(--ink-3);margin-top:18px;letter-spacing:.02em}
.micro b{color:var(--ink-2);font-weight:600}

/* ---------- Live dashboard card (hero right) ---------- */
.dash{
  background:var(--card);border:1px solid var(--line);border-radius:22px;
  box-shadow:0 24px 60px -30px rgba(43,59,212,.25),0 2px 6px rgba(11,11,12,.04);
  overflow:hidden;
}
.dash-head{
  display:flex;align-items:center;gap:10px;padding:16px 22px;border-bottom:1px solid var(--line-soft);
  font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.18em;color:var(--ink-3);
}
.live-dot{width:8px;height:8px;border-radius:50%;background:var(--mint-deep);animation:pulse 1.8s infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(15,157,124,.35)}55%{box-shadow:0 0 0 7px rgba(15,157,124,0)}}
.dash-head .spacer{margin-left:auto;color:var(--ink-3);letter-spacing:.05em;font-weight:500}
.dash-grid{display:grid;grid-template-columns:1fr 1fr}
.stat{padding:22px;border-bottom:1px solid var(--line-soft)}
.stat:nth-child(odd){border-right:1px solid var(--line-soft)}
.stat:nth-child(3),.stat:nth-child(4){border-bottom:0}
.stat-label{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.16em;color:var(--ink-3);margin-bottom:10px}
.stat-value{font-family:var(--font-display);font-weight:700;font-size:26px;letter-spacing:-.02em;display:flex;align-items:baseline;gap:6px}
.stat-unit{font-family:var(--font-mono);font-size:11px;font-weight:500;color:var(--ink-3)}
.slot{position:relative;display:inline-flex;flex-direction:column;height:1.08em;overflow:hidden}
.slot-line{display:block;height:1.08em;line-height:1.08em;transition:transform .45s cubic-bezier(.22,1,.36,1)}
.stat-sub{font-size:12px;color:var(--ink-3);margin-top:8px}
.dash-foot{
  border-top:1px solid var(--line-soft);padding:13px 22px;display:flex;align-items:center;gap:8px;
  font-family:var(--font-mono);font-size:10.5px;color:var(--ink-3);
}
.dash-foot a{color:var(--accent-ink);border-bottom:1px solid var(--accent)}

/* ---------- Sections / panels ---------- */
main{min-height:60vh}
.panel{display:none;padding:64px 0 90px}
.panel.active{display:block;animation:panelIn .4s cubic-bezier(.22,1,.36,1)}
@keyframes panelIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.section-head{margin-bottom:34px}
.section-kicker{font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.22em;color:var(--accent-ink);margin-bottom:12px}
.section-title{font-family:var(--font-display);font-weight:700;font-size:clamp(26px,3vw,36px);letter-spacing:-.03em}
.section-sub{color:var(--ink-2);margin-top:10px;max-width:60ch;line-height:1.6;font-size:15px}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:22px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:22px}
.mini-label{font-family:var(--font-mono);font-size:9.5px;font-weight:600;letter-spacing:.14em;color:var(--ink-3);margin-bottom:6px}
.mini-value{font-family:var(--font-display);font-weight:700;font-size:24px;letter-spacing:-.02em}
.card{
  background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:26px;
  transition:box-shadow .25s,border-color .25s;
}
.card:hover{border-color:#d8d9e2;box-shadow:0 14px 40px -24px rgba(11,11,12,.18)}
.card-label{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.16em;color:var(--ink-3);margin-bottom:14px}
.card-big{font-family:var(--font-display);font-weight:700;font-size:30px;letter-spacing:-.02em}
.card-sub{font-size:13px;color:var(--ink-3);margin-top:8px;line-height:1.5}

.field{margin-bottom:18px}
.field label{display:block;font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.14em;color:var(--ink-3);margin-bottom:8px}
.input-wrap{position:relative}
.input-wrap input{
  width:100%;border:1px solid var(--line);border-radius:14px;padding:15px 64px 15px 16px;
  font-family:var(--font-mono);font-size:16px;font-weight:500;color:var(--ink);background:#fff;
  transition:border-color .2s,box-shadow .2s;outline:none;
}
.input-wrap input:focus{border-color:var(--accent-deep);box-shadow:0 0 0 4px rgba(76,94,242,.12)}
.input-unit{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  font-family:var(--font-mono);font-size:11px;font-weight:600;color:var(--ink-3);
  background:var(--line-soft);border-radius:8px;padding:5px 9px;
}
.hint-row{display:flex;justify-content:space-between;font-size:12px;color:var(--ink-3);margin-top:8px}
.hint-row button{background:none;border:0;color:var(--accent-ink);font-weight:600;font-size:12px;text-decoration:underline;text-underline-offset:3px}
.quote-line{
  display:flex;justify-content:space-between;align-items:center;gap:12px;
  font-size:13px;color:var(--ink-2);padding:11px 0;border-top:1px dashed var(--line);
}
.quote-line .mono{font-size:12.5px;font-weight:600;color:var(--ink)}
.note{
  border:1px solid var(--line);border-left:3px solid var(--accent-deep);border-radius:12px;
  padding:14px 16px;font-size:13px;color:var(--ink-2);line-height:1.6;background:linear-gradient(180deg,#fbfcff,#fff);
}
.note.warn{border-left-color:var(--amber);background:linear-gradient(180deg,#fffdf7,#fff)}
.divider{height:1px;background:var(--line);margin:52px 0}

/* status chips */
.chip{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-mono);font-size:10.5px;font-weight:600;letter-spacing:.08em;border-radius:99px;padding:5px 11px}
.chip.ok{background:#e8f8f1;color:var(--mint-deep)}
.chip.off{background:#fdeeee;color:var(--red)}
.chip.idle{background:var(--line-soft);color:var(--ink-3)}

/* table */
.table{width:100%;border-collapse:collapse;font-size:13px}
.table th{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.14em;color:var(--ink-3);text-align:left;padding:10px 12px;border-bottom:1px solid var(--line)}
.table td{padding:12px;border-bottom:1px solid var(--line-soft);color:var(--ink-2)}
.table tr:last-child td{border-bottom:0}
.table .mono{font-size:12px}
.badge{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.06em;border-radius:6px;padding:3px 8px}
.badge.in{background:#e8f8f1;color:var(--mint-deep)}
.badge.out{background:#fdeeee;color:var(--red)}
.link{color:var(--accent-ink);border-bottom:1px solid var(--accent)}
.empty{color:var(--ink-3);font-size:13px;padding:26px 12px;text-align:center}

/* guide steps */
.steps{display:grid;gap:0}
.step{display:grid;grid-template-columns:44px 1fr;gap:18px;padding:22px 0;border-bottom:1px solid var(--line-soft)}
.step:last-child{border-bottom:0}
.step-num{
  width:44px;height:44px;border-radius:14px;background:var(--ink);color:#fff;
  display:flex;align-items:center;justify-content:center;font-family:var(--font-mono);font-weight:600;font-size:14px;
}
.step h3{font-family:var(--font-display);font-size:17px;font-weight:600;margin-bottom:7px;letter-spacing:-.01em}
.step p{font-size:14px;color:var(--ink-2);line-height:1.65}
.step code{font-family:var(--font-mono);font-size:12px;background:var(--line-soft);border-radius:6px;padding:2px 7px;color:var(--accent-ink)}

/* ---------- Admin ---------- */
.admin-hero{padding:64px 0 90px}
.login-card{max-width:420px;margin:8vh auto 0}
.login-card .card{padding:34px}
.admin-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px}
.admin-grid .card{padding:20px}
.admin-grid .card-big{font-size:22px}
.admin-cols{display:grid;grid-template-columns:1.4fr 1fr;gap:22px;align-items:start}
.kv{display:flex;justify-content:space-between;gap:14px;padding:10px 0;border-bottom:1px solid var(--line-soft);font-size:13px}
.kv:last-child{border-bottom:0}
.kv .k{color:var(--ink-3);font-family:var(--font-mono);font-size:11px;letter-spacing:.1em}
.kv .v{color:var(--ink);font-weight:500;text-align:right;word-break:break-all}

/* toast */
#toast{
  position:fixed;bottom:26px;left:50%;transform:translateX(-50%) translateY(20px);z-index:100;
  background:var(--ink);color:#fff;border-radius:14px;padding:14px 20px;font-size:13.5px;
  opacity:0;pointer-events:none;transition:opacity .25s,transform .25s;max-width:min(560px,90vw);
  box-shadow:0 18px 50px -12px rgba(11,11,12,.4);
}
#toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
#toast a{color:var(--accent);text-decoration:underline;text-underline-offset:3px}
#toast.err{background:#7c1d1d}

/* footer */
footer{border-top:1px solid var(--line);padding:34px 0 44px;margin-top:20px}
.foot-inner{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.foot-inner .mono{font-size:11px;color:var(--ink-3)}
.foot-links{margin-left:auto;display:flex;gap:20px;font-size:13px;color:var(--ink-2)}
.foot-links a{border-bottom:1px solid transparent;transition:border-color .2s}
.foot-links a:hover{border-color:var(--ink)}

/* reveal on scroll */
.reveal{opacity:0;transform:translateY(14px);transition:opacity .6s cubic-bezier(.22,1,.36,1),transform .6s cubic-bezier(.22,1,.36,1)}
.reveal.in{opacity:1;transform:none}

/* theme picker (admin) */
.theme-picker{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px}
.theme-opt{border:1px solid var(--line);border-radius:14px;padding:12px;cursor:pointer;display:block;transition:border-color .2s,box-shadow .2s}
.theme-opt input{display:none}
.theme-opt b{display:block;font-family:var(--font-display);font-size:14px;margin:9px 0 2px;letter-spacing:-.01em}
.theme-opt i{font-style:normal;font-size:11px;color:var(--ink-3)}
.theme-opt.sel{border-color:var(--accent-deep);box-shadow:0 0 0 3px rgba(76,94,242,.15)}
.theme-prev{display:block;height:56px;border-radius:9px;border:1px solid var(--line)}
.p1{background:linear-gradient(120deg,#ffffff 55%,#b3c0fe)}
.p2{background:linear-gradient(120deg,#0a0a0c 55%,#00d9a3)}
.p3{background:linear-gradient(120deg,#e5e7eb 55%,#fd630c)}

/* ============================================================
   THEME 2 — MIDNIGHT TERMINAL (dark, mint, centered hero)
   ============================================================ */
body.theme-2{
  --bg:#0a0a0c;--ink:#f4f4f5;--ink-2:#b6b7bd;--ink-3:#71717a;
  --line:#232328;--line-soft:#1a1a1f;--card:#121215;--glass:rgba(10,10,12,.8);
  --accent:#7cf5d0;--accent-deep:#00d9a3;--accent-ink:#34e3b0;
  --mint:#123c33;--mint-deep:#34e3b0;--red:#ff7a7a;--amber:#f5b942;--radius:12px;
}
body.theme-2 .brand svg rect{fill:#f4f4f5}
body.theme-2 .brand svg path{fill:#00d9a3}
body.theme-2 .brand svg circle{fill:#0a0a0c}
body.theme-2 .btn-dark{background:#f4f4f5;color:#0a0a0c}
body.theme-2 .btn-dark:hover:not(:disabled){box-shadow:0 6px 22px rgba(244,244,245,.18)}
body.theme-2 .btn-accent{color:#04120d}
body.theme-2 .btn-accent:hover:not(:disabled){box-shadow:0 6px 22px rgba(0,217,163,.35)}
body.theme-2 .btn-ghost{background:transparent;border-color:var(--line);color:var(--ink)}
body.theme-2 .btn-ghost:hover:not(:disabled){border-color:var(--accent-deep)}
body.theme-2 .tab-btn{color:var(--ink-2)}
body.theme-2 .tab-btn:hover{background:var(--line-soft)}
body.theme-2 .tab-btn.active{background:var(--accent-deep);color:#04120d}
body.theme-2 .net-pill{background:var(--card)}
body.theme-2 #shader{display:none}
body.theme-2 .hero{background:radial-gradient(60% 80% at 70% 0%,rgba(0,217,163,.13),transparent 60%),var(--bg)}
body.theme-2 .hero-inner{grid-template-columns:1fr;text-align:center;gap:46px;padding:80px 0 72px}
body.theme-2 .lede{margin:0 auto 32px}
body.theme-2 .hero-ctas{justify-content:center}
body.theme-2 .dash{max-width:700px;margin:0 auto;text-align:left;box-shadow:0 24px 60px -30px rgba(0,217,163,.28)}
body.theme-2 h1 .hl{background:linear-gradient(100deg,rgba(0,217,163,.32),rgba(124,245,208,.16));color:var(--accent)}
body.theme-2 .live-dot{background:var(--accent-deep)}
body.theme-2 .chip.idle{background:var(--line-soft);color:var(--ink-3)}
body.theme-2 .chip.ok{background:#0c2f27;color:var(--accent)}
body.theme-2 .chip.off{background:#3a1414;color:var(--red)}
body.theme-2 .input-wrap input{background:#0d0d10;color:var(--ink)}
body.theme-2 .input-unit{background:var(--line-soft);color:var(--ink-2)}
body.theme-2 .note{background:var(--card)}
body.theme-2 .note.warn{background:linear-gradient(180deg,#171307,var(--card))}
body.theme-2 .badge.in{background:#0c2f27;color:var(--accent)}
body.theme-2 .badge.out{background:#3a1414;color:var(--red)}
body.theme-2 .card:hover{box-shadow:0 14px 40px -24px rgba(0,0,0,.65)}
body.theme-2 .step-num{background:var(--accent-deep);color:#04120d}
body.theme-2 #toast{background:#f4f4f5;color:#0a0a0c}
body.theme-2 #toast.err{background:#5c1a1a;color:#fff}
body.theme-2 .theme-opt.sel{box-shadow:0 0 0 3px rgba(0,217,163,.2)}
body.theme-2 ::selection{background:#00d9a3;color:#04120d}

/* ============================================================
   THEME 3 — PRESS (brutalist light, black rules, orange)
   ============================================================ */
body.theme-3{
  --bg:#e5e7eb;--ink:#000000;--ink-2:#333335;--ink-3:#5f6063;
  --line:#000000;--line-soft:#c6c8cd;--card:#ffffff;--glass:rgba(229,231,235,.88);
  --accent:#fd630c;--accent-deep:#fd630c;--accent-ink:#d14e00;
  --mint:#c9f2e4;--mint-deep:#0a7a5c;--radius:0px;
}
body.theme-3 .nav{border-bottom:1.5px solid #000}
body.theme-3 .brand svg rect{rx:0}
body.theme-3 .tab-btn{border-radius:0}
body.theme-3 .tab-btn:hover{background:#d3d5da}
body.theme-3 .tab-btn.active{background:#000;color:#fff}
body.theme-3 .net-pill{border-radius:0;border:1.5px solid #000;background:#fff}
body.theme-3 .btn{border-radius:0}
body.theme-3 .btn-dark{background:#000;color:#fff}
body.theme-3 .btn-dark:hover:not(:disabled){box-shadow:4px 4px 0 #fd630c}
body.theme-3 .btn-accent{background:#fd630c;color:#000}
body.theme-3 .btn-accent:hover:not(:disabled){box-shadow:4px 4px 0 #000}
body.theme-3 .btn-ghost{background:#fff;border:1.5px solid #000;color:#000}
body.theme-3 .btn-ghost:hover:not(:disabled){box-shadow:4px 4px 0 #000}
body.theme-3 #shader{display:none}
body.theme-3 .hero{border-bottom:1.5px solid #000;background:var(--bg)}
body.theme-3 .hero-inner{grid-template-columns:1fr;gap:42px;padding:76px 0 64px}
body.theme-3 h1{text-transform:uppercase;font-size:clamp(42px,6.4vw,84px)}
body.theme-3 h1 .hl{background:#fd630c;color:#000;border-radius:0;padding:0 .12em}
body.theme-3 .lede{max-width:62ch}
body.theme-3 .eyebrow{color:#000}
body.theme-3 .eyebrow::before{background:#fd630c;height:3px;width:30px}
body.theme-3 .dash{border:1.5px solid #000;border-radius:0;box-shadow:none}
body.theme-3 .stat{border-bottom-color:#000}
body.theme-3 .stat:nth-child(odd){border-right-color:#000}
body.theme-3 .dash-head{border-bottom-color:#000}
body.theme-3 .dash-foot{border-top-color:#000}
body.theme-3 .card{border:1.5px solid #000;border-radius:0;box-shadow:none}
body.theme-3 .card:hover{border-color:#000;box-shadow:6px 6px 0 #000}
body.theme-3 .input-wrap input{border:1.5px solid #000;border-radius:0}
body.theme-3 .input-wrap input:focus{border-color:#000;box-shadow:4px 4px 0 #fd630c}
body.theme-3 .input-unit{border-radius:0;border:1px solid #000;background:#fff;color:#000}
body.theme-3 .note{border:1.5px solid #000;border-left:6px solid #fd630c;border-radius:0;background:#fff}
body.theme-3 .note.warn{border-left-color:#000}
body.theme-3 .step-num{border-radius:0;background:#000}
body.theme-3 .step{border-bottom-color:#000}
body.theme-3 .chip{border-radius:0}
body.theme-3 .badge{border-radius:0}
body.theme-3 .table th{border-bottom:1.5px solid #000}
body.theme-3 footer{border-top:1.5px solid #000}
body.theme-3 .divider{background:#000}
body.theme-3 .theme-opt{border-radius:0}
body.theme-3 .theme-prev{border-radius:0}
body.theme-3 .theme-opt.sel{border-color:#fd630c;box-shadow:3px 3px 0 #fd630c}
body.theme-3 ::selection{background:#fd630c;color:#000}

/* ---------- Stats strip ---------- */
.strip{display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid var(--line);border-bottom:1px solid var(--line);margin-bottom:64px}
.strip-cell{padding:26px 28px;border-right:1px solid var(--line)}
.strip-cell:last-child{border-right:0}
.strip-label{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.16em;color:var(--ink-3);margin-bottom:10px}
.strip-value{font-family:var(--font-display);font-weight:700;font-size:clamp(21px,2.3vw,29px);letter-spacing:-.02em;display:flex;align-items:baseline;gap:6px}
.strip-sub{font-size:12px;color:var(--ink-3);margin-top:6px}

/* ---------- Feature cards ---------- */
.feat{display:flex;flex-direction:column;gap:13px}
.icon-badge{width:44px;height:44px;border:1px solid var(--line);border-radius:13px;display:flex;align-items:center;justify-content:center;background:var(--card)}
.feat h3{font-family:var(--font-display);font-size:17px;font-weight:600;letter-spacing:-.01em}
.feat p{font-size:13.5px;color:var(--ink-2);line-height:1.65}

/* ---------- Activity feed ---------- */
.feed-row{display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--line-soft);font-size:13px}
.feed-row:last-child{border-bottom:0}
.feed-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.feed-dot.in{background:var(--mint-deep)}
.feed-dot.out{background:var(--red)}
.feed-main{flex:1;min-width:0;color:var(--ink-2)}
.feed-main b{color:var(--ink);font-weight:600}
.feed-main .mono{font-size:12px;color:var(--ink-3)}
.feed-amt{font-family:var(--font-mono);font-size:13px;font-weight:600;white-space:nowrap}
.feed-amt.in{color:var(--mint-deep)}
.feed-amt.out{color:var(--red)}

/* ---------- FAQ ---------- */
.faq{border-top:1px solid var(--line)}
.faq details{border-bottom:1px solid var(--line)}
.faq summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:14px;padding:20px 4px;font-family:var(--font-display);font-weight:600;font-size:16px;letter-spacing:-.01em;transition:color .2s}
.faq summary:hover{color:var(--accent-ink)}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"+";margin-left:auto;font-family:var(--font-mono);font-size:19px;font-weight:500;color:var(--ink-3);transition:transform .25s}
.faq details[open] summary::after{transform:rotate(45deg)}
.faq details p{padding:0 4px 22px;font-size:14px;line-height:1.7;color:var(--ink-2);max-width:72ch}

/* ---------- CTA band ---------- */
.cta-band{border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:72px 24px;text-align:center;margin-top:72px}
.cta-band h2{font-family:var(--font-display);font-weight:700;font-size:clamp(26px,3.4vw,44px);letter-spacing:-.03em;margin-bottom:14px}
.cta-band p{color:var(--ink-2);font-size:15px;margin-bottom:28px}
.cta-band .hero-ctas{justify-content:center}

/* formula box */
.formula{font-family:var(--font-mono);font-size:12.5px;line-height:2;background:var(--line-soft);border-radius:12px;padding:16px 18px;color:var(--ink-2);overflow-x:auto;white-space:nowrap}
.formula b{color:var(--accent-ink)}

/* theme 3 — square everything new */
body.theme-3 .strip{border-color:#000}
body.theme-3 .strip-cell{border-right-color:#000}
body.theme-3 .icon-badge{border-radius:0;border:1.5px solid #000}
body.theme-3 .faq,body.theme-3 .faq details{border-color:#000}
body.theme-3 .faq summary:hover{color:#fd630c}
body.theme-3 .cta-band{border-color:#000;border-width:1.5px}
body.theme-3 .formula{border-radius:0;border:1.5px solid #000;background:#fff}

/* ============================================================
   THEME 4 — PHOSPHOR (retro CRT terminal, scanlines, glow)
   ============================================================ */
body.theme-4{
  --bg:#020604;--ink:#c9ffd9;--ink-2:#7ec996;--ink-3:#3f7d58;
  --line:#123a24;--line-soft:#0a2317;--card:#051209;--glass:rgba(2,6,4,.86);
  --accent:#3dff7a;--accent-deep:#2be668;--accent-ink:#52ff8f;
  --mint:#0a2317;--mint-deep:#3dff7a;--red:#ff5d5d;--amber:#ffd23d;--radius:6px;
  --font-display:"VT323","JetBrains Mono",monospace;
  --font-body:"JetBrains Mono",ui-monospace,monospace;
}
/* scanlines + CRT vignette */
body.theme-4::before{content:"";position:fixed;inset:0;z-index:70;pointer-events:none;
  background:repeating-linear-gradient(0deg,rgba(0,0,0,.20) 0 1px,transparent 1px 3px)}
body.theme-4::after{content:"";position:fixed;inset:0;z-index:70;pointer-events:none;
  background:radial-gradient(120% 90% at 50% 40%,transparent 55%,rgba(0,0,0,.55) 100%)}
body.theme-4 ::selection{background:#3dff7a;color:#020604}
body.theme-4 .nav{border-bottom:1px solid var(--line);backdrop-filter:none;-webkit-backdrop-filter:none;background:var(--glass)}
body.theme-4 .brand svg rect{fill:#3dff7a}
body.theme-4 .brand svg path{fill:#020604}
body.theme-4 .brand svg circle{fill:#020604}
body.theme-4 .tab-btn{color:var(--ink-3);border-radius:4px}
body.theme-4 .tab-btn:hover{background:var(--line-soft);color:var(--ink)}
body.theme-4 .tab-btn.active{background:var(--accent);color:#020604}
body.theme-4 .net-pill{background:var(--card);border-color:var(--line);border-radius:4px}
body.theme-4 .net-dot{background:var(--accent);box-shadow:0 0 8px var(--accent)}
body.theme-4 .btn{border-radius:4px;font-family:var(--font-mono);letter-spacing:.06em}
body.theme-4 .btn-dark{background:var(--accent);color:#020604;box-shadow:0 0 18px rgba(61,255,122,.35)}
body.theme-4 .btn-dark:hover:not(:disabled){box-shadow:0 0 30px rgba(61,255,122,.6)}
body.theme-4 .btn-accent{background:transparent;border:1px solid var(--accent);color:var(--accent)}
body.theme-4 .btn-accent:hover:not(:disabled){box-shadow:0 0 22px rgba(61,255,122,.4)}
body.theme-4 .btn-ghost{background:transparent;border-color:var(--line);color:var(--ink-2)}
body.theme-4 .btn-ghost:hover:not(:disabled){border-color:var(--accent);color:var(--accent)}
body.theme-4 #shader{display:none}
body.theme-4 .hero{background:radial-gradient(70% 90% at 50% 0%,rgba(61,255,122,.08),transparent 65%),var(--bg);border-bottom:1px solid var(--line)}
body.theme-4 h1{font-weight:400;font-size:clamp(52px,6vw,84px);letter-spacing:0;text-shadow:0 0 26px rgba(61,255,122,.35)}
body.theme-4 h1::after{content:"▌";color:var(--accent);animation:blink 1.1s steps(1) infinite}
body.theme-4 h1 .hl{background:none;color:var(--accent);border-radius:0;padding:0;text-shadow:0 0 30px rgba(61,255,122,.6)}
@keyframes blink{50%{opacity:0}}
body.theme-4 .lede{color:var(--ink-2)}
body.theme-4 .eyebrow{color:var(--accent)}
body.theme-4 .eyebrow::before{background:var(--accent);box-shadow:0 0 8px var(--accent)}
body.theme-4 .section-kicker{color:var(--accent)}
body.theme-4 .section-kicker::before{content:"▸ "}
body.theme-4 .section-title{font-weight:400;text-shadow:0 0 18px rgba(61,255,122,.25)}
body.theme-4 .dash{border:1px solid var(--line);border-radius:6px;background:var(--card);box-shadow:0 0 40px rgba(61,255,122,.12)}
body.theme-4 .dash-head::after{content:"pool@robinhood:~$";font-family:var(--font-mono);font-size:10px;color:var(--ink-3);margin-left:auto}
body.theme-4 .stat-value{font-weight:400;text-shadow:0 0 14px rgba(61,255,122,.3)}
body.theme-4 .live-dot{background:var(--accent);box-shadow:0 0 8px var(--accent)}
body.theme-4 .chip{border-radius:4px}
body.theme-4 .chip.ok{background:#07240f;color:var(--accent)}
body.theme-4 .chip.off{background:#2a0b0b;color:var(--red)}
body.theme-4 .chip.idle{background:var(--line-soft);color:var(--ink-3)}
body.theme-4 .card{border:1px solid var(--line);border-radius:6px;background:var(--card)}
body.theme-4 .card:hover{border-color:var(--accent);box-shadow:0 0 30px rgba(61,255,122,.18)}
body.theme-4 .card-label,body.theme-4 .mini-label{color:var(--ink-3)}
body.theme-4 .strip{border-color:var(--line)}
body.theme-4 .strip-cell{border-right-color:var(--line)}
body.theme-4 .strip-value{font-weight:400;text-shadow:0 0 14px rgba(61,255,122,.3)}
body.theme-4 .icon-badge{border-radius:4px;border-color:var(--line);background:var(--card)}
body.theme-4 .icon-badge svg{stroke:var(--accent);filter:drop-shadow(0 0 5px rgba(61,255,122,.6))}
body.theme-4 .ticker{border-color:var(--line)}
body.theme-4 .flow-track{background:var(--line-soft);border-radius:4px}
body.theme-4 .flow-bar{border-radius:4px}
body.theme-4 .flow-bar.in{box-shadow:0 0 10px rgba(61,255,122,.5)}
body.theme-4 .input-wrap input{background:#030b06;color:var(--ink);border-color:var(--line);border-radius:4px;font-family:var(--font-mono)}
body.theme-4 .input-wrap input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(61,255,122,.15),0 0 18px rgba(61,255,122,.25)}
body.theme-4 .input-unit{background:var(--line-soft);color:var(--ink-2);border-radius:4px}
body.theme-4 .note{background:var(--card);border-left-color:var(--accent)}
body.theme-4 .note.warn{border-left-color:var(--amber);background:linear-gradient(180deg,#0f0d03,var(--card))}
body.theme-4 .faq,body.theme-4 .faq details{border-color:var(--line)}
body.theme-4 .faq summary{font-weight:400}
body.theme-4 .cta-band{border-color:var(--line);background:radial-gradient(60% 120% at 50% 50%,rgba(61,255,122,.07),transparent 70%)}
body.theme-4 .cta-band h2{font-weight:400;text-shadow:0 0 24px rgba(61,255,122,.35)}
body.theme-4 .formula{background:#030b06;border:1px solid var(--line);border-radius:4px}
body.theme-4 .badge{border-radius:4px}
body.theme-4 .badge.in{background:#07240f;color:var(--accent)}
body.theme-4 .badge.out{background:#2a0b0b;color:var(--red)}
body.theme-4 .table th{border-bottom:1px solid var(--line);color:var(--ink-3)}
body.theme-4 .step-num{background:var(--accent);color:#020604;border-radius:4px}
body.theme-4 .step{border-bottom-color:var(--line)}
body.theme-4 .divider{background:var(--line)}
body.theme-4 footer{border-top:1px solid var(--line)}
body.theme-4 #toast{background:var(--accent);color:#020604;font-family:var(--font-mono)}
body.theme-4 #toast.err{background:#3d0f0f;color:#fff}
body.theme-4 .theme-opt{border-radius:4px;background:var(--card)}
body.theme-4 .theme-opt.sel{border-color:var(--accent);box-shadow:0 0 16px rgba(61,255,122,.35)}

/* ============================================================
   THEME 5 — MAISON (luxury editorial, cream paper, serif)
   ============================================================ */
body.theme-5{
  --bg:#f6f1e7;--ink:#191410;--ink-2:#4d453c;--ink-3:#8a7f71;
  --line:#d8cfbe;--line-soft:#e9e2d3;--card:#fffdf8;--glass:rgba(246,241,231,.88);
  --accent:#7a2230;--accent-deep:#7a2230;--accent-ink:#6b1b28;
  --mint:#dce8df;--mint-deep:#3f6b54;--red:#a13a3a;--amber:#9a6a14;--radius:2px;
  --font-display:"Fraunces",Georgia,"Times New Roman",serif;
  --font-body:Georgia,"Times New Roman",serif;
}
body.theme-5 ::selection{background:#7a2230;color:#f6f1e7}
body.theme-5 .nav{border-bottom:1px solid var(--ink);background:var(--glass)}
body.theme-5 .brand{font-family:var(--font-display);font-weight:600}
body.theme-5 .brand svg rect{fill:#191410;rx:2}
body.theme-5 .brand svg path{fill:#f6f1e7}
body.theme-5 .brand svg circle{fill:#7a2230}
body.theme-5 .tab-btn{font-family:var(--font-mono);border-radius:2px}
body.theme-5 .tab-btn:hover{background:var(--line-soft)}
body.theme-5 .tab-btn.active{background:var(--ink);color:var(--bg)}
body.theme-5 .net-pill{background:var(--card);border-color:var(--ink);border-radius:2px}
body.theme-5 .net-dot{background:var(--mint-deep)}
body.theme-5 .btn{border-radius:2px;font-family:var(--font-mono);letter-spacing:.12em}
body.theme-5 .btn-dark{background:var(--ink);color:var(--bg)}
body.theme-5 .btn-dark:hover:not(:disabled){background:var(--accent);box-shadow:none}
body.theme-5 .btn-accent{background:var(--accent);color:#f6f1e7}
body.theme-5 .btn-accent:hover:not(:disabled){background:var(--ink);box-shadow:none}
body.theme-5 .btn-ghost{background:transparent;border-color:var(--ink);color:var(--ink)}
body.theme-5 .btn-ghost:hover:not(:disabled){border-color:var(--accent);color:var(--accent);box-shadow:none}
body.theme-5 #shader{display:none}
body.theme-5 .hero{border-bottom:3px double var(--ink);background:
  radial-gradient(80% 60% at 50% -10%,rgba(122,34,48,.05),transparent 60%),var(--bg)}
body.theme-5 .hero-inner{grid-template-columns:1fr;text-align:center;gap:44px;padding:84px 0 72px}
body.theme-5 h1{font-weight:600;font-size:clamp(44px,5.6vw,74px);letter-spacing:-.015em}
body.theme-5 h1 .hl{background:none;color:var(--accent);font-style:italic;font-weight:600;border-radius:0;padding:0}
body.theme-5 .lede{margin:0 auto 34px;font-size:17.5px;line-height:1.75;color:var(--ink-2)}
body.theme-5 .lede::first-letter{font-family:var(--font-display);font-size:3.1em;font-weight:700;float:left;line-height:.82;padding:4px 10px 0 0;color:var(--accent)}
body.theme-5 .hero-ctas{justify-content:center}
body.theme-5 .eyebrow{color:var(--accent)}
body.theme-5 .eyebrow::before{background:var(--accent);width:44px}
body.theme-5 .micro{text-align:center}
body.theme-5 .dash{max-width:680px;margin:0 auto;text-align:left;border:1px solid var(--ink);border-radius:2px;background:var(--card);box-shadow:8px 8px 0 rgba(25,20,16,.08)}
body.theme-5 .section-kicker{color:var(--accent)}
body.theme-5 .section-kicker::before{content:"◆ ";font-size:8px;vertical-align:2px}
body.theme-5 .section-title{font-weight:600;letter-spacing:-.015em}
body.theme-5 .card{border:1px solid var(--line);border-radius:2px;background:var(--card);box-shadow:none}
body.theme-5 .card:hover{border-color:var(--ink);box-shadow:6px 6px 0 rgba(25,20,16,.1)}
body.theme-5 .strip{border-top:3px double var(--ink);border-bottom:1px solid var(--ink)}
body.theme-5 .strip-cell{border-right:1px solid var(--line)}
body.theme-5 .strip-value{font-weight:600}
body.theme-5 .icon-badge{border-radius:50%;border:1px solid var(--ink);background:var(--card)}
body.theme-5 .icon-badge svg{stroke:var(--accent)}
body.theme-5 .feat h3{font-size:19px}
body.theme-5 .ticker{border-top:1px solid var(--ink);border-bottom:3px double var(--ink)}
body.theme-5 .tick-item{font-family:var(--font-display);font-style:italic;font-size:13px;letter-spacing:.06em}
body.theme-5 .tick-item b{font-style:normal}
body.theme-5 .flow-track{background:var(--line-soft);border-radius:2px}
body.theme-5 .flow-bar{border-radius:2px}
body.theme-5 .input-wrap input{background:var(--card);border-color:var(--ink);border-radius:2px;font-family:var(--font-mono)}
body.theme-5 .input-wrap input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(122,34,48,.12)}
body.theme-5 .input-unit{background:var(--line-soft);color:var(--ink);border-radius:2px;border:1px solid var(--line)}
body.theme-5 .note{background:var(--card);border:1px solid var(--line);border-left:3px solid var(--accent);border-radius:2px}
body.theme-5 .note.warn{border-left-color:var(--ink);background:linear-gradient(180deg,#faf5e9,var(--card))}
body.theme-5 .faq{border-top:1px solid var(--ink)}
body.theme-5 .faq details{border-bottom:1px solid var(--line)}
body.theme-5 .faq summary{font-size:18px}
body.theme-5 .faq summary:hover{color:var(--accent)}
body.theme-5 .cta-band{border-top:3px double var(--ink);border-bottom:3px double var(--ink)}
body.theme-5 .cta-band h2{font-weight:600;font-style:normal}
body.theme-5 .cta-band h2 em{font-style:italic;color:var(--accent)}
body.theme-5 .formula{background:#efe8d8;border:1px solid var(--line);border-radius:2px}
body.theme-5 .chip{border-radius:2px}
body.theme-5 .chip.ok{background:var(--mint);color:var(--mint-deep)}
body.theme-5 .chip.off{background:#f0dede;color:var(--red)}
body.theme-5 .chip.idle{background:var(--line-soft);color:var(--ink-3)}
body.theme-5 .badge{border-radius:2px}
body.theme-5 .badge.in{background:var(--mint);color:var(--mint-deep)}
body.theme-5 .badge.out{background:#f0dede;color:var(--red)}
body.theme-5 .table th{border-bottom:2px solid var(--ink)}
body.theme-5 .step-num{background:var(--ink);color:var(--bg);border-radius:2px;font-family:var(--font-display)}
body.theme-5 .step{border-bottom-color:var(--line)}
body.theme-5 .divider{background:none;border-top:1px solid var(--line);position:relative;height:1px}
body.theme-5 .divider::after{content:"❧";position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:var(--bg);padding:0 20px;color:var(--accent);font-size:18px}
body.theme-5 footer{border-top:3px double var(--ink)}
body.theme-5 #toast{background:var(--ink);color:var(--bg);font-family:var(--font-mono)}
body.theme-5 #toast.err{background:var(--accent);color:#fff}
body.theme-5 .theme-opt{border-radius:2px;background:var(--card)}
body.theme-5 .theme-opt.sel{border-color:var(--accent);box-shadow:0 0 0 3px rgba(122,34,48,.15)}
body.theme-5 .lb-rank{font-style:italic}
body.theme-5 .mini-value{font-weight:600}
.p4{background:linear-gradient(120deg,#020604 55%,#3dff7a)}
.p5{background:linear-gradient(120deg,#f6f1e7 55%,#7a2230)}

/* ---------- Live ticker ---------- */
.ticker{border-top:1px solid var(--line);border-bottom:1px solid var(--line);overflow:hidden;margin-bottom:64px;padding:13px 0}
.ticker-inner{display:inline-flex;white-space:nowrap;animation:tickmove 32s linear infinite;will-change:transform}
.ticker:hover .ticker-inner{animation-play-state:paused}
@keyframes tickmove{to{transform:translateX(-50%)}}
.tick-item{font-family:var(--font-mono);font-size:11px;font-weight:500;letter-spacing:.14em;color:var(--ink-2);padding:0 26px;display:inline-flex;align-items:center;gap:10px}
.tick-item i{font-style:normal;color:var(--accent-ink)}
.tick-item b{color:var(--ink);font-weight:600}

/* ---------- Capital flows ---------- */
.flow-row{display:flex;align-items:center;gap:12px;margin-top:16px}
.flow-tag{font-family:var(--font-mono);font-size:10px;font-weight:600;letter-spacing:.12em;width:32px;flex-shrink:0}
.flow-tag.in{color:var(--mint-deep)}
.flow-tag.out{color:var(--red)}
.flow-track{flex:1;height:10px;background:var(--line-soft);border-radius:99px;overflow:hidden}
.flow-bar{height:100%;border-radius:99px;width:0%;transition:width 1.1s cubic-bezier(.22,.61,.36,1)}
.flow-bar.in{background:var(--mint-deep)}
.flow-bar.out{background:var(--red)}
.flow-val{font-size:12px;color:var(--ink-2);min-width:78px;text-align:right;white-space:nowrap}

/* ---------- Leaderboard ---------- */
.lb-row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--line-soft)}
.lb-row:last-child{border-bottom:0}
.lb-rank{font-family:var(--font-display);font-weight:700;font-size:15px;color:var(--ink-3);width:18px;flex-shrink:0}
.lb-addr{flex:1;font-size:12.5px;color:var(--ink-2);overflow:hidden;text-overflow:ellipsis}
.lb-amt{font-size:12.5px;font-weight:600;white-space:nowrap}

@media(max-width:1100px){
  .grid-4{grid-template-columns:1fr 1fr}
}

@media(max-width:960px){
  .hero-inner{grid-template-columns:1fr;padding:60px 0}
  .grid-2,.grid-3,.grid-4,.admin-grid,.admin-cols{grid-template-columns:1fr}
  .strip{grid-template-columns:1fr 1fr}
  .strip-cell:nth-child(2){border-right:0}
  .strip-cell:nth-child(1),.strip-cell:nth-child(2){border-bottom:1px solid var(--line)}
  .tabs{display:none}
  .nav-inner{gap:14px}
}
</style>
</head>
<body>

<script>
//__APP_STATE_START__
window.APP = {
  action: <?= json_encode($action, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
  loginError: <?= json_encode($loginError, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
//__APP_STATE_END__
</script>

<script>
//__SITE_SETTINGS_START__
window.SITE_SETTINGS = <?= json_encode($S, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
window.SITE_INFO = <?= json_encode(['writable' => is_writable(__DIR__), 'storage' => basename(hf_settings_path())], JSON_HEX_TAG) ?>;
//__SITE_SETTINGS_END__
</script>

<nav class="nav">
  <div class="container nav-inner">
    <a class="brand" href="?">
      <span id="brandLogoWrap"><svg width="26" height="26" viewBox="0 0 26 26" fill="none" aria-hidden="true">
        <rect x="1" y="1" width="24" height="24" rx="7" fill="#0b0b0c"/>
        <path d="M13 6.5c3.2 3.8 5 6.7 5 9a5 5 0 1 1-10 0c0-2.3 1.8-5.2 5-9Z" fill="#b3c0fe"/>
        <circle cx="13" cy="15.5" r="2" fill="#daf8f1"/>
      </svg></span>
      <span class="js-sitename">HoodFi</span> <small>ROBINHOOD CHAIN</small>
    </a>
    <div class="tabs" id="tabs">
      <button class="tab-btn active" data-tab="overview">Overview</button>
      <button class="tab-btn" data-tab="pool">Pool</button>
      <button class="tab-btn" data-tab="wallet">Wallet</button>
      <button class="tab-btn" data-tab="guide">Guide</button>
    </div>
    <div class="nav-right">
      <div class="net-pill"><span class="net-dot" id="netDot"></span><span id="netLabel">Not connected</span></div>
      <div id="walletMenuWrap" style="position:relative">
        <button class="btn btn-dark" id="connectBtn">Connect Wallet</button>
        <div id="walletMenu">
          <button class="wm-item" id="wmCopy">Copy address</button>
          <button class="wm-item" id="wmExplorer">View on explorer</button>
          <button class="wm-item danger" id="wmDisconnect">Disconnect</button>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- ============================ PUBLIC SITE ============================ -->
<div id="publicView">

  <section class="hero">
    <canvas id="shader"></canvas>
    <div class="container hero-inner">
      <div>
        <div class="eyebrow">LIQUIDITY PROTOCOL · CHAIN ID 4663 · MAINNET</div>
        <h1>Liquidity on the chain <span class="hl">built for markets.</span></h1>
        <p class="lede">
          Deposit ETH into a transparent, non-custodial pool on Robinhood Chain.
          You receive LP shares that track your exact share of the pool — withdraw
          your ETH back at any time. No database, no middleman: every number on
          this page is read directly from the blockchain.
        </p>
        <div class="hero-ctas">
          <button class="btn btn-accent" id="heroConnect">Connect Wallet</button>
          <button class="btn btn-ghost" data-goto="pool">Add Liquidity</button>
          <button class="btn btn-ghost" data-goto="guide">Deploy Guide</button>
        </div>
        <div class="micro">NETWORK <b>Robinhood Chain (Mainnet)</b> &nbsp;·&nbsp; GAS TOKEN <b>ETH</b> &nbsp;·&nbsp; CUSTODY <b>none — contract only</b></div>
      </div>

      <div class="dash reveal in">
        <div class="dash-head"><span class="live-dot"></span> LIVE POOL OBSERVATION <span class="spacer" id="dashBlock">block —</span></div>
        <div class="dash-grid">
          <div class="stat">
            <div class="stat-label">TOTAL VALUE LOCKED</div>
            <div class="stat-value"><span class="slot" id="statTvl">—</span><span class="stat-unit">ETH</span></div>
            <div class="stat-sub">ETH held by the pool contract</div>
          </div>
          <div class="stat">
            <div class="stat-label">LP SHARES ISSUED</div>
            <div class="stat-value"><span class="slot" id="statShares">—</span><span class="stat-unit">HF-LP</span></div>
            <div class="stat-sub">total claims on the pool</div>
          </div>
          <div class="stat">
            <div class="stat-label">YOUR POSITION</div>
            <div class="stat-value"><span class="slot" id="statMine">—</span><span class="stat-unit">ETH</span></div>
            <div class="stat-sub" id="statMinePct">connect wallet to view</div>
          </div>
          <div class="stat">
            <div class="stat-label">POOL STATUS</div>
            <div class="stat-value" style="font-size:16px;align-items:center"><span class="chip idle" id="statStatus">READING…</span></div>
            <div class="stat-sub" id="statContract">contract not configured</div>
          </div>
        </div>
        <div class="dash-foot">
          <span id="footChain">Robinhood Chain (Mainnet)</span> ·
          <a href="#" id="footExplorer" target="_blank" rel="noopener">view contract on explorer</a>
        </div>
      </div>
    </div>
  </section>

  <main class="container">

    <!-- ============ OVERVIEW ============ -->
    <section class="panel active" id="panel-overview">
      <!-- stats strip -->
      <div class="strip">
        <div class="strip-cell">
          <div class="strip-label">TOTAL VALUE LOCKED</div>
          <div class="strip-value"><span id="stripTvl">0</span> <span class="unit" style="font-size:13px">ETH</span></div>
          <div class="strip-sub">Robinhood Chain mainnet</div>
        </div>
        <div class="strip-cell">
          <div class="strip-label">LP SHARES ISSUED</div>
          <div class="strip-value" id="stripShares">0</div>
          <div class="strip-sub">1:1 backed by pool ETH</div>
        </div>
        <div class="strip-cell">
          <div class="strip-label">LIQUIDITY PROVIDERS</div>
          <div class="strip-value" id="stripUsers">—</div>
          <div class="strip-sub">unique depositors on-chain</div>
        </div>
        <div class="strip-cell">
          <div class="strip-label">LATEST BLOCK</div>
          <div class="strip-value" id="stripBlock">—</div>
          <div class="strip-sub">chain heartbeat · live</div>
        </div>
      </div>

      <!-- live ticker -->
      <div class="ticker"><div class="ticker-inner" id="tickerInner"></div></div>

      <!-- network pulse widgets -->
      <div class="section-head">
        <div class="section-kicker">NETWORK PULSE</div>
        <div class="section-title">The chain, breathing in real time.</div>
      </div>
      <div class="grid-3" style="margin-bottom:64px">
        <div class="card reveal">
          <div class="card-label">AVG BLOCK TIME</div>
          <div class="mini-value" style="font-size:36px"><span id="pulseBlockTime">—</span><span style="font-size:14px;font-weight:400;color:var(--ink-3)"> sec</span></div>
          <canvas id="pulseSpark" height="46" style="width:100%;height:46px;margin-top:14px;display:block"></canvas>
          <div class="strip-sub" style="margin-top:10px">last 20 block intervals · live</div>
        </div>
        <div class="card reveal">
          <div class="card-label">CAPITAL FLOWS — ALL TIME</div>
          <div class="flow-row"><span class="flow-tag in">IN</span><div class="flow-track"><div class="flow-bar in" id="flowInBar"></div></div><span class="flow-val mono" id="flowInVal">0 ETH</span></div>
          <div class="flow-row"><span class="flow-tag out">OUT</span><div class="flow-track"><div class="flow-bar out" id="flowOutBar"></div></div><span class="flow-val mono" id="flowOutVal">0 ETH</span></div>
          <div class="strip-sub" id="flowNet" style="margin-top:16px">reading flows…</div>
        </div>
        <div class="card reveal">
          <div class="card-label">TOP PROVIDERS</div>
          <div id="leaderboard" style="margin-top:4px">
            <div class="lb-row" style="color:var(--ink-3)">reading chain…</div>
          </div>
        </div>
      </div>

      <div class="section-head">
        <div class="section-kicker">HOW IT WORKS</div>
        <div class="section-title">One pool. Three moves. Zero trust required.</div>
      </div>
      <div class="grid-3">
        <div class="card reveal">
          <div class="card-label">01 — DEPOSIT</div>
          <h3 style="font-family:var(--font-display);font-size:19px;margin-bottom:10px">Send ETH, get shares</h3>
          <p class="card-sub">Call <span class="mono" style="font-size:12px;color:var(--accent-ink)">deposit()</span> with any amount of ETH. The contract mints LP shares proportional to your stake — the first deposit is 1:1.</p>
        </div>
        <div class="card reveal">
          <div class="card-label">02 — EARN / HOLD</div>
          <h3 style="font-family:var(--font-display);font-size:19px;margin-bottom:10px">Shares track the pool</h3>
          <p class="card-sub">Your shares always represent the same percentage of total liquidity. The pool's balance and your position are readable on-chain by anyone, any time.</p>
        </div>
        <div class="card reveal">
          <div class="card-label">03 — WITHDRAW</div>
          <h3 style="font-family:var(--font-display);font-size:19px;margin-bottom:10px">Burn shares, ETH back</h3>
          <p class="card-sub">Call <span class="mono" style="font-size:12px;color:var(--accent-ink)">withdraw(shares)</span> or <span class="mono" style="font-size:12px;color:var(--accent-ink)">withdrawAll()</span>. The contract burns your shares and returns your ETH in the same transaction.</p>
        </div>
      </div>
      <div class="divider"></div>
      <div class="grid-2">
        <div class="note">
          <b>Why no SQL?</b> Balances, shares and history all live inside the smart contract on Robinhood Chain.
          This site is a window into that contract — even the admin panel reads everything from the chain itself.
          If this website disappeared tomorrow, your funds would still be in the contract, withdrawable from any other interface.
        </div>
        <div class="note warn">
          <b>Honest by design.</b> The contract owner can only pause new deposits or hand over ownership —
          the owner <u>cannot</u> move or freeze user funds. Verify the contract source on the explorer before
          depositing anything, and start with a small amount first.
        </div>
      </div>

      <div class="divider"></div>

      <!-- Pool calculator -->
      <div class="section-head">
        <div class="section-kicker">POOL CALCULATOR</div>
        <div class="section-title">Know your shares before you send.</div>
      </div>
      <div class="grid-2">
        <div class="card reveal">
          <div class="card-label">SIMULATE A DEPOSIT</div>
          <div class="input-wrap">
            <input id="calcAmount" type="number" min="0" step="any" placeholder="0.00" autocomplete="off">
            <span class="input-unit">ETH</span>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px">
            <div>
              <div class="mini-label">YOU WOULD RECEIVE</div>
              <div class="mini-value" id="calcShares">0</div>
              <div style="font-size:11px;color:var(--ink-3)">LP shares</div>
            </div>
            <div>
              <div class="mini-label">POOL OWNERSHIP</div>
              <div class="mini-value" id="calcPct">0%</div>
              <div style="font-size:11px;color:var(--ink-3)">of total liquidity</div>
            </div>
          </div>
        </div>
        <div class="card reveal">
          <div class="card-label">THE MATH — PUBLIC BY DESIGN</div>
          <div class="formula">shares  = deposit × <b>totalShares</b> ÷ <b>poolBalance</b><br>payout  = shares × <b>poolBalance</b> ÷ <b>totalShares</b><br><span style="color:var(--ink-3)">// first deposit is 1:1 · no fees · no slippage</span></div>
          <p class="card-sub" style="margin-top:14px">The contract does no hidden arithmetic. Every number the calculator shows comes from the same formulas, executed on-chain — verify them against the contract source on the explorer.</p>
        </div>
      </div>

      <div class="divider"></div>

      <!-- Features -->
      <div class="section-head">
        <div class="section-kicker">BUILT DIFFERENT</div>
        <div class="section-title">Why people trust this pool.</div>
      </div>
      <div class="grid-4" id="featGrid">
        <div class="card feat reveal">
          <div class="icon-badge">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-ink)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
          </div>
          <h3>Non-custodial</h3>
          <p>Your ETH lives in the contract, not in anyone's pocket. The owner can pause new deposits — nothing more. Moving user funds is not a power the contract gives anyone.</p>
        </div>
        <div class="card feat reveal">
          <div class="icon-badge">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-ink)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14a5 5 0 007.07 0l2.12-2.12a5 5 0 00-7.07-7.07L11 5.93"/><path d="M14 10a5 5 0 00-7.07 0L4.81 12.12a5 5 0 007.07 7.07L13 18.07"/></svg>
          </div>
          <h3>Fully on-chain</h3>
          <p>No database, no off-chain ledger, no SQL. Balances, shares and history are state inside the contract — this site only reads and relays what the chain already knows.</p>
        </div>
        <div class="card feat reveal">
          <div class="icon-badge">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-ink)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4.5 13.5H11L10 22l8.5-11.5H13L13 2z"/></svg>
          </div>
          <h3>Withdraw anytime</h3>
          <p>No lock-ups, no cooldowns, no tickets. Burn any amount of your shares and the same transaction returns your ETH — straight back to your wallet.</p>
        </div>
        <div class="card feat reveal">
          <div class="icon-badge">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-ink)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/></svg>
          </div>
          <h3>Verifiable live</h3>
          <p>Every number on this page is queried from Robinhood Chain in your browser. Check the pool yourself on the block explorer — the contract speaks for itself.</p>
        </div>
      </div>

      <div class="divider"></div>

      <!-- Live activity -->
      <div class="section-head" style="display:flex;align-items:flex-end;gap:18px;flex-wrap:wrap">
        <div>
          <div class="section-kicker">RECENT POOL ACTIVITY</div>
          <div class="section-title">What the chain saw, live.</div>
        </div>
        <a class="btn btn-ghost" style="margin-left:auto" id="feedExplorerLink" target="_blank" rel="noopener">VIEW ALL ON EXPLORER ↗</a>
      </div>
      <div class="card reveal" style="padding:10px 28px 16px">
        <div id="activityFeed">
          <div class="feed-row" style="color:var(--ink-3)">Reading on-chain events…</div>
        </div>
      </div>

      <div class="divider"></div>

      <!-- FAQ -->
      <div class="section-head">
        <div class="section-kicker">QUESTIONS</div>
        <div class="section-title">Before you deposit.</div>
      </div>
      <div class="faq reveal">
        <details open>
          <summary>What exactly is this pool?</summary>
          <p>A community liquidity pool on Robinhood Chain mainnet. You deposit ETH into the smart contract and receive LP shares representing your percentage of the pool. The pooled liquidity stays on-chain and is withdrawable by share holders at any time.</p>
        </details>
        <details>
          <summary>How are my shares calculated?</summary>
          <p>Shares are minted proportionally: <span class="mono" style="font-size:12.5px">deposit × totalShares ÷ poolBalance</span>. The very first deposit gets shares 1:1 with ETH. There are no deposit fees and no withdrawal fees — gas only.</p>
        </details>
        <details>
          <summary>Can the contract owner take my ETH?</summary>
          <p>No. The owner's only powers are pausing new deposits and transferring ownership. There is no function that lets anyone — owner included — move or freeze user funds. Your shares are always redeemable for ETH by you, and only by you.</p>
        </details>
        <details>
          <summary>How do I get my ETH back?</summary>
          <p>Go to the Pool tab, enter the amount of shares to burn, and confirm the transaction. The contract burns your shares and returns the corresponding ETH in the same transaction. You can also use "Withdraw All" to exit completely.</p>
        </details>
        <details>
          <summary>Which wallets are supported?</summary>
          <p>MetaMask, Robinhood Wallet, Rabby, and any EVM-compatible browser wallet. On mobile, open this site inside your wallet app's built-in browser. The site will offer to add Robinhood Chain to your wallet automatically if it isn't configured yet.</p>
        </details>
      </div>

      <!-- CTA band -->
      <div class="cta-band reveal">
        <div class="section-kicker" style="margin-bottom:14px">READY WHEN YOU ARE</div>
        <h2>Put your ETH<br>to work on Robinhood Chain.</h2>
        <p>Connect a wallet, deposit in one transaction, withdraw whenever you like.</p>
        <div class="hero-ctas">
          <button class="btn btn-dark btn-lg" onclick="connectWallet()">CONNECT WALLET</button>
          <button class="btn btn-ghost btn-lg" data-goto="pool">OPEN THE POOL</button>
        </div>
      </div>
    </section>

    <!-- ============ POOL ============ -->
    <section class="panel" id="panel-pool">
      <div class="section-head">
        <div class="section-kicker">POOL</div>
        <div class="section-title">Add or remove liquidity</div>
        <p class="section-sub">Transactions are signed in your wallet and executed by the smart contract on Robinhood Chain. This site never sees your keys.</p>
      </div>
      <div id="noContractNote" class="note warn" style="display:none;margin-bottom:22px">
        The pool contract address is not configured for this network yet. Deploy
        <span class="mono" style="font-size:12px">LiquidityPool.sol</span> (see the <b>Guide</b> tab), then paste the
        address into <span class="mono" style="font-size:12px">CONFIG.CONTRACTS</span> at the top of this file's script.
        Balance and network features still work.
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-label">ADD LIQUIDITY</div>
          <div class="field">
            <label>AMOUNT (ETH)</label>
            <div class="input-wrap">
              <input id="depAmount" type="text" inputmode="decimal" placeholder="0.0" autocomplete="off">
              <span class="input-unit">ETH</span>
            </div>
            <div class="hint-row">
              <span>Wallet balance: <span class="mono" id="depBalance">—</span></span>
              <button id="depMax" type="button">MAX</button>
            </div>
          </div>
          <div class="quote-line"><span>You will receive</span><span class="mono" id="depQuote">—</span></div>
          <div class="quote-line"><span>Share of pool after deposit</span><span class="mono" id="depSharePct">—</span></div>
          <div style="height:18px"></div>
          <button class="btn btn-accent btn-block" id="depBtn">Deposit ETH</button>
        </div>
        <div class="card">
          <div class="card-label">REMOVE LIQUIDITY</div>
          <div class="field">
            <label>LP SHARES TO BURN</label>
            <div class="input-wrap">
              <input id="wdShares" type="text" inputmode="decimal" placeholder="0.0" autocomplete="off">
              <span class="input-unit">HF-LP</span>
            </div>
            <div class="hint-row">
              <span>Your shares: <span class="mono" id="wdBalance">—</span></span>
              <button id="wdMax" type="button">MAX</button>
            </div>
          </div>
          <div class="quote-line"><span>You will receive</span><span class="mono" id="wdQuote">—</span></div>
          <div class="quote-line"><span>Remaining position</span><span class="mono" id="wdRemain">—</span></div>
          <div style="height:18px"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <button class="btn btn-dark" id="wdBtn">Withdraw</button>
            <button class="btn btn-ghost" id="wdAllBtn">Withdraw All</button>
          </div>
        </div>
      </div>
      <div style="height:22px"></div>
      <div class="card">
        <div class="card-label">YOUR RECENT POOL ACTIVITY</div>
        <table class="table">
          <thead><tr><th>TYPE</th><th>ETH</th><th>SHARES</th><th>TX</th></tr></thead>
          <tbody id="myEvents"><tr><td colspan="4" class="empty">Connect your wallet to load your on-chain activity.</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ============ WALLET ============ -->
    <section class="panel" id="panel-wallet">
      <div class="section-head">
        <div class="section-kicker">WALLET</div>
        <div class="section-title">Connection &amp; balances</div>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-label">CONNECTED ACCOUNT</div>
          <div id="walletBox">
            <div style="padding:26px 0;text-align:center;color:var(--ink-3);font-size:14px">
              No wallet connected.<br><br>
              <button class="btn btn-dark" id="walletConnect">Connect Wallet</button>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-label">NETWORK</div>
          <div class="kv"><span class="k">CHAIN</span><span class="v" id="nwName">—</span></div>
          <div class="kv"><span class="k">CHAIN ID</span><span class="v mono" id="nwId">—</span></div>
          <div class="kv"><span class="k">RPC</span><span class="v mono" id="nwRpc" style="font-size:11px">—</span></div>
          <div class="kv"><span class="k">EXPLORER</span><span class="v"><a class="link" id="nwExplorer" target="_blank" rel="noopener" href="#">open</a></span></div>
          <div style="height:14px"></div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn btn-ghost" id="switchMainnet">Switch to Mainnet</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ GUIDE ============ -->
    <section class="panel" id="panel-guide">
      <div class="section-head">
        <div class="section-kicker">DEPLOY GUIDE</div>
        <div class="section-title">Live in five steps</div>
        <p class="section-sub">Everything runs on Robinhood Chain. The contract is a single file with zero imports — deploy it from Remix in minutes.</p>
      </div>
      <div class="card">
        <div class="steps">
          <div class="step">
            <div class="step-num">1</div>
            <div><h3>Add Robinhood Chain to your wallet</h3>
            <p>This site runs on <b>mainnet only</b> — Chain ID <code>4663</code>, RPC <code>https://rpc.mainnet.chain.robinhood.com</code>.
            Your wallet switches to this network automatically when you connect.</p></div>
          </div>
          <div class="step">
            <div class="step-num">2</div>
            <div><h3>Get ETH for gas</h3>
            <p>You need mainnet ETH for deployment and deposits —
            bridge it using the <a class="link" target="_blank" rel="noopener" href="https://docs.robinhood.com/chain">Robinhood Chain docs</a>.</p></div>
          </div>
          <div class="step">
            <div class="step-num">3</div>
            <div><h3>Deploy the contract</h3>
            <p>Open <a class="link" target="_blank" rel="noopener" href="https://remix.ethereum.org">remix.ethereum.org</a>, create
            <code>LiquidityPool.sol</code>, paste the contract file shipped with this site, compile with Solidity
            <code>0.8.20+</code>, then in the Deploy tab choose <b>Injected Provider — MetaMask</b> and press <b>Deploy</b>.</p></div>
          </div>
          <div class="step">
            <div class="step-num">4</div>
            <div><h3>Set the contract address</h3>
            <p>Paste the deployed address in <b>Admin Panel → Site Settings</b> — the whole site goes live on the new
            contract the moment you save, no file editing needed. You can also hardcode it in
            <code>CONFIG.CONTRACTS</code> — the admin panel value always overrides it.</p></div>
          </div>
          <div class="step">
            <div class="step-num">5</div>
            <div><h3>Admin panel &amp; branding</h3>
            <p>Visit <code>yoursite.com/?action=admin</code> and log in. From there you can change the <b>site name</b>,
            <b>upload a logo</b>, set <b>Telegram / X handles</b> and pick any of the <b>5 themes</b> — everything saves
            to settings.json, no database. The panel reads TVL, shares, depositors and the latest events straight from the chain.</p></div>
          </div>
        </div>
      </div>
    </section>

  </main>
</div>

<!-- ============================ ADMIN ============================ -->
<div id="adminView" style="display:none">
  <main class="container admin-hero">

    <div id="adminLogin" class="login-card" style="display:none">
      <div class="card">
        <div class="card-label">ADMIN ACCESS</div>
        <h2 style="font-family:var(--font-display);font-size:24px;letter-spacing:-.02em;margin-bottom:8px">Sign in</h2>
        <p class="card-sub" style="margin-bottom:22px">Restricted area — pool administration.</p>
        <form method="post" action="?action=admin" id="adminLoginForm">
          <div class="field">
            <label>PASSWORD</label>
            <div class="input-wrap" style="display:block">
              <input type="password" name="admin_password" id="adminPass" placeholder="••••••••" style="padding-right:16px" required autofocus>
            </div>
          </div>
          <div class="note warn" id="loginErr" style="display:none;margin-bottom:16px"></div>
          <button class="btn btn-dark btn-block" type="submit">Unlock Panel</button>
        </form>
        <p style="margin-top:16px;text-align:center"><a class="link" href="?" style="font-size:13px">← back to site</a></p>
      </div>
    </div>

    <div id="adminPanel" style="display:none">
      <div class="section-head" style="display:flex;align-items:flex-end;gap:18px;flex-wrap:wrap">
        <div>
          <div class="section-kicker">ADMIN PANEL</div>
          <div class="section-title">Pool control room</div>
          <p class="section-sub">All figures below are read live from the smart contract on Robinhood Chain. No server database involved.</p>
        </div>
        <div style="margin-left:auto;display:flex;gap:10px">
          <button class="btn btn-ghost" id="adminRefresh">Refresh</button>
          <a class="btn btn-dark" href="?action=logout" id="adminLogout">Log out</a>
        </div>
      </div>

      <div class="card" style="margin-bottom:22px">
        <div class="card-label">SITE SETTINGS — name, logo, socials, theme</div>
        <div class="mono" id="storageStatus" style="font-size:11px;margin:-6px 0 16px"></div>
        <div class="grid-2" style="gap:18px">
          <div class="field">
            <label>SITE NAME</label>
            <div class="input-wrap"><input id="setName" placeholder="HoodFi" style="padding-right:16px"></div>
          </div>
          <div class="field">
            <label>LOGO (PNG/JPG/SVG/WebP · max 2 MB)</label>
            <div style="display:flex;gap:12px;align-items:center">
              <input type="file" id="setLogo" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" style="font-size:12px;color:var(--ink-2)">
              <img id="logoPreview" alt="" style="width:36px;height:36px;border-radius:8px;object-fit:contain;border:1px solid var(--line);display:none">
            </div>
          </div>
          <div class="field">
            <label>CONTRACT ADDRESS — MAINNET (4663)</label>
            <div class="input-wrap"><input id="setCaMain" placeholder="0x…" style="padding-right:16px;font-size:13px"></div>
          </div>
          <div class="field">
            <label>TELEGRAM (handle or URL)</label>
            <div class="input-wrap"><input id="setTg" placeholder="@yourchannel" style="padding-right:16px"></div>
          </div>
          <div class="field">
            <label>X / TWITTER (handle or URL)</label>
            <div class="input-wrap"><input id="setX" placeholder="@yourhandle" style="padding-right:16px"></div>
          </div>
        </div>
        <div class="field">
          <label>SITE THEME — click to preview instantly</label>
          <div class="theme-picker">
            <label class="theme-opt" data-theme="1"><input type="radio" name="themePick" value="1"><span class="theme-prev p1"></span><b>Aurora</b><i>Light · periwinkle · soft glass</i></label>
            <label class="theme-opt" data-theme="2"><input type="radio" name="themePick" value="2"><span class="theme-prev p2"></span><b>Midnight</b><i>Dark terminal · mint accent</i></label>
            <label class="theme-opt" data-theme="3"><input type="radio" name="themePick" value="3"><span class="theme-prev p3"></span><b>Press</b><i>Brutalist light · black &amp; orange</i></label>
            <label class="theme-opt" data-theme="4"><input type="radio" name="themePick" value="4"><span class="theme-prev p4"></span><b>Phosphor</b><i>CRT terminal · scanlines &amp; glow</i></label>
            <label class="theme-opt" data-theme="5"><input type="radio" name="themePick" value="5"><span class="theme-prev p5"></span><b>Maison</b><i>Editorial serif · cream &amp; oxblood</i></label>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:14px">
          <button class="btn btn-accent" id="saveSettings">Save Settings</button>
          <span class="mono" id="settingsMsg" style="font-size:11px;color:var(--mint-deep)"></span>
        </div>
      </div>

      <div class="admin-grid">
        <div class="card"><div class="card-label">TOTAL VALUE LOCKED</div><div class="card-big"><span class="slot" id="admTvl">—</span> <span class="stat-unit">ETH</span></div></div>
        <div class="card"><div class="card-label">LP SHARES</div><div class="card-big"><span class="slot" id="admShares">—</span></div></div>
        <div class="card"><div class="card-label">UNIQUE DEPOSITORS</div><div class="card-big"><span class="slot" id="admUsers">—</span></div></div>
        <div class="card"><div class="card-label">DEPOSITS / WITHDRAWS</div><div class="card-big"><span class="slot" id="admOps">—</span></div></div>
      </div>

      <div class="admin-cols">
        <div class="card">
          <div class="card-label">LATEST POOL EVENTS (ON-CHAIN)</div>
          <table class="table">
            <thead><tr><th>EVENT</th><th>USER</th><th>ETH</th><th>TX</th></tr></thead>
            <tbody id="admEvents"><tr><td colspan="4" class="empty">Loading…</td></tr></tbody>
          </table>
        </div>
        <div class="card">
          <div class="card-label">CONTRACT &amp; CHAIN</div>
          <div class="kv"><span class="k">NETWORK</span><span class="v" id="admNet">—</span></div>
          <div class="kv"><span class="k">CONTRACT</span><span class="v mono" id="admContract" style="font-size:11px">—</span></div>
          <div class="kv"><span class="k">OWNER</span><span class="v mono" id="admOwner" style="font-size:11px">—</span></div>
          <div class="kv"><span class="k">STATUS</span><span class="v" id="admPaused">—</span></div>
          <div class="kv"><span class="k">LATEST BLOCK</span><span class="v mono" id="admBlock">—</span></div>
          <div class="kv"><span class="k">RPC LATENCY</span><span class="v mono" id="admLatency">—</span></div>
          <div style="height:14px"></div>
          <div class="note" style="font-size:12.5px">
            Owner powers are limited to pause/unpause and ownership transfer — user ETH can never be moved by the owner.
            To pause the pool, connect the owner wallet in Remix and call <span class="mono" style="font-size:11.5px">pause()</span>.
          </div>
        </div>
      </div>
    </div>

  </main>
</div>

<footer>
  <div class="container foot-inner">
    <span class="mono"><span class="js-sitename" data-upper>HOODFI</span> — LIQUIDITY PROTOCOL</span>
    <span class="mono" id="footStatus">reading chain…</span>
    <div class="foot-links">
      <a href="#" id="tgLink" target="_blank" rel="noopener" style="display:none">Telegram</a>
      <a href="#" id="xLink" target="_blank" rel="noopener" style="display:none">X</a>
      <a href="https://docs.robinhood.com/chain" target="_blank" rel="noopener">Chain docs</a>
      <a href="?action=admin">Admin</a>
    </div>
  </div>
</footer>

<div id="toast"></div>

<script>
/* ============================================================
   CONFIG — after deploying LiquidityPool.sol, paste addresses here
   ============================================================ */
const CONFIG = {
  DEFAULT_CHAIN: 4663,
  CHAINS: {
    4663:  { name:"Robinhood Chain", short:"Mainnet", hex:"0x1237",
             rpc:"https://rpc.mainnet.chain.robinhood.com",
             explorer:"https://robinhoodchain.blockscout.com" }
  },
  CONTRACTS: {
    4663:  "0x0E1d1dD4bE9e4335Bc8419D4874c27eac129DCc1"   // mainnet contract (also changeable from admin panel)
  },
  REFRESH_MS: 15000
};

const POOL_ABI = [
  "function deposit() payable",
  "function withdraw(uint256 shares)",
  "function withdrawAll()",
  "function totalLiquidity() view returns (uint256)",
  "function totalShares() view returns (uint256)",
  "function sharesOf(address) view returns (uint256)",
  "function valueOf(address) view returns (uint256)",
  "function previewDeposit(uint256 amount) view returns (uint256)",
  "function getPoolInfo(address user) view returns (uint256 liquidity, uint256 shares, uint256 myShares, uint256 myValue, bool isPaused, address contractOwner)",
  "function owner() view returns (address)",
  "function paused() view returns (bool)",
  "event Deposited(address indexed user, uint256 amount, uint256 sharesMinted)",
  "event Withdrawn(address indexed user, uint256 amount, uint256 sharesBurned)"
];

const ZERO = "0x0000000000000000000000000000000000000000";
const $ = (id) => document.getElementById(id);

/* ---------------- state ---------------- */
let browserProvider = null;   // wallet provider (ethers.BrowserProvider)
let signer = null;
let account = null;
let viewChain = CONFIG.DEFAULT_CHAIN;   // chain the UI is reading
let refreshTimer = null;

const iface = () => new ethers.Interface(POOL_ABI);
const chainCfg = (id) => CONFIG.CHAINS[id];
/* admin-panel address (settings.json) overrides the hardcoded default */
const contractAddr = (id) => {
  const s = window.SITE_SETTINGS || {};
  const v = s.contract_4663 || "";
  return (v && v.toLowerCase() !== ZERO) ? v : (CONFIG.CONTRACTS[id] || ZERO);
};
const hasContract = (id) => contractAddr(id) !== ZERO;
const readProvider = (id) => new ethers.JsonRpcProvider(chainCfg(id).rpc, id, { staticNetwork: true });

/* ---------------- helpers ---------------- */
function toast(msg, isErr = false, ms = 4200) {
  const t = $("toast");
  t.innerHTML = msg;
  t.className = "show" + (isErr ? " err" : "");
  clearTimeout(t._h);
  t._h = setTimeout(() => t.className = "", ms);
}
function fmtEth(wei, dp = 5) {
  try {
    const n = parseFloat(ethers.formatEther(wei));
    if (n === 0) return "0";
    if (n < 0.00001) return "<0.00001";
    return n.toLocaleString("en-US", { maximumFractionDigits: dp });
  } catch { return "0"; }
}
const short = (a) => a ? a.slice(0, 6) + "…" + a.slice(-4) : "—";
const txLink = (hash, chainId) => `<a class="link" target="_blank" rel="noopener" href="${chainCfg(chainId).explorer}/tx/${hash}">${short(hash)}</a>`;
const addrLink = (a, chainId) => `<a class="link" target="_blank" rel="noopener" href="${chainCfg(chainId).explorer}/address/${a}">${short(a)}</a>`;

/* slot-machine number animation */
function slotSet(el, val) {
  if (!el || el.dataset.v === val) return;
  el.dataset.v = val;
  const old = [...el.children];
  const line = document.createElement("span");
  line.className = "slot-line";
  line.textContent = val;
  el.appendChild(line);
  requestAnimationFrame(() => [...el.children].forEach(c => c.style.transform = "translateY(-100%)"));
  setTimeout(() => old.forEach(l => l.remove()), 500);
}

/* ---------------- WebGL pastel shader (hero) ---------------- */
(function shaderBg() {
  const cv = $("shader");
  const gl = cv.getContext("webgl", { alpha: true, antialias: false });
  if (!gl) { cv.style.background = "linear-gradient(120deg,#eef0ff, #f7fbff 55%, #eafaf6)"; return; }
  const vs = "attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}";
  const fs = `precision highp float;uniform float t;uniform vec2 r;
    vec2 pcg2d(vec2 p){p=vec2(dot(p,vec2(127.1,311.7)),dot(p,vec2(269.5,183.3)));return fract(sin(p)*43758.5453);}
    float noise(vec2 p){vec2 i=floor(p),f=fract(p);f=f*f*(3.-2.*f);
      float a=pcg2d(i).x,b=pcg2d(i+vec2(1,0)).x,c=pcg2d(i+vec2(0,1)).x,d=pcg2d(i+vec2(1,1)).x;
      return mix(mix(a,b,f.x),mix(c,d,f.x),f.y);}
    float fbm(vec2 p){float v=0.,a=.5;for(int i=0;i<4;i++){v+=a*noise(p);p*=2.03;a*=.5;}return v;}
    void main(){
      vec2 uv=gl_FragCoord.xy/r; uv.x*=r.x/r.y;
      float n=fbm(uv*1.6+vec2(t*.05,t*.03));
      float m=fbm(uv*2.2-vec2(t*.04,t*.02)+n);
      vec3 c0=vec3(.702,.753,.996); vec3 c1=vec3(.929,.941,1.); vec3 c2=vec3(.855,.969,1.);
      vec3 col=mix(c1,c0,smoothstep(.25,.75,n));
      col=mix(col,c2,smoothstep(.45,.85,m)*.7);
      float fade=smoothstep(1.05,.25,gl_FragCoord.y/r.y*.9);
      gl_FragColor=vec4(col,fade*.85);
    }`;
  function sh(type, src) { const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
  const pr = gl.createProgram();
  gl.attachShader(pr, sh(gl.VERTEX_SHADER, vs));
  gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, fs));
  gl.linkProgram(pr); gl.useProgram(pr);
  const buf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, buf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 3,-1, -1,3]), gl.STATIC_DRAW);
  const loc = gl.getAttribLocation(pr, "p");
  gl.enableVertexAttribArray(loc); gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
  const uT = gl.getUniformLocation(pr, "t"), uR = gl.getUniformLocation(pr, "r");
  function frame(ms) {
    const w = cv.clientWidth, h = cv.clientHeight;
    if (cv.width !== w || cv.height !== h) { cv.width = w; cv.height = h; gl.viewport(0, 0, w, h); }
    gl.uniform1f(uT, ms / 1000); gl.uniform2f(uR, w, h);
    gl.drawArrays(gl.TRIANGLES, 0, 3);
    requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
})();

/* ---------------- tabs ---------------- */
document.querySelectorAll(".tab-btn").forEach(b => b.addEventListener("click", () => showTab(b.dataset.tab)));
document.querySelectorAll("[data-goto]").forEach(b => b.addEventListener("click", () => { showTab(b.dataset.goto); window.scrollTo({ top: 0, behavior: "smooth" }); }));
function showTab(name) {
  document.querySelectorAll(".tab-btn").forEach(b => b.classList.toggle("active", b.dataset.tab === name));
  document.querySelectorAll(".panel").forEach(p => p.classList.toggle("active", p.id === "panel-" + name));
}

/* ---------------- view router (public vs admin) ---------------- */
(function route() {
  if (APP.action === "admin") {
    $("publicView").style.display = "none";
    document.querySelector(".nav .tabs").style.display = "none";
    $("adminView").style.display = "block";
    if (APP.isAdmin) { $("adminPanel").style.display = "block"; initAdmin(); }
    else {
      $("adminLogin").style.display = "block";
      if (APP.loginError) { const e = $("loginErr"); e.textContent = APP.loginError; e.style.display = "block"; }
    }
  }
})();

/* static preview: client-side admin gate (PHP host par server-side auth chalti hai) */
if (window.STATIC_DEMO) {
  $("adminLoginForm").addEventListener("submit", (ev) => {
    ev.preventDefault();
    if ($("adminPass").value === window.STATIC_ADMIN_PASSWORD) {
      sessionStorage.setItem("hoodfi_admin", "1");
      location.href = "?action=admin";
    } else {
      const e = $("loginErr"); e.textContent = "Incorrect password. Please try again."; e.style.display = "block";
    }
  });
}

/* ---------------- wallet ---------------- */
async function connectWallet() {
  if (!window.ethereum) {
    toast("No wallet found. On mobile: open this site inside the <b>MetaMask app's built-in browser</b>. On desktop: install <a href='https://metamask.io' target='_blank' rel='noopener'>MetaMask</a> or Robinhood Wallet.", true, 8000);
    return;
  }
  try {
    browserProvider = new ethers.BrowserProvider(window.ethereum);
    const accounts = await browserProvider.send("eth_requestAccounts", []);
    account = accounts[0];
    const net = await browserProvider.getNetwork();
    let cid = Number(net.chainId);
    if (!chainCfg(cid)) {
      await switchNetwork(CONFIG.DEFAULT_CHAIN);
      cid = CONFIG.DEFAULT_CHAIN;
    }
    viewChain = cid;
    onConnected();
  } catch (e) {
    toast("Connection was cancelled — click <b>Connect Wallet</b> again and press <b>Approve</b> in your wallet.", true);
  }
}

async function switchNetwork(chainId) {
  const c = chainCfg(chainId);
  if (!window.ethereum) { toast("Wallet not found.", true); return; }
  try {
    await window.ethereum.request({ method: "wallet_switchEthereumChain", params: [{ chainId: c.hex }] });
  } catch (e) {
    if (e.code === 4902) {
      await window.ethereum.request({
        method: "wallet_addEthereumChain",
        params: [{
          chainId: c.hex, chainName: c.name,
          rpcUrls: [c.rpc],
          nativeCurrency: { name: "Ether", symbol: "ETH", decimals: 18 },
          blockExplorerUrls: [c.explorer]
        }]
      });
    } else { toast("Network switch failed: " + (e.shortMessage || e.message), true); return; }
  }
  viewChain = chainId;
  if (account) onConnected(); else refreshPublic();
}

function onConnected() {
  $("connectBtn").textContent = short(account);
  $("heroConnect").textContent = short(account) + " connected";
  $("netDot").classList.add("on");
  $("netLabel").textContent = chainCfg(viewChain).name;
  renderWalletBox();
  refreshPublic();
  if (APP.isAdmin && APP.action === "admin") refreshAdmin();
}

if (window.ethereum) {
  window.ethereum.on("accountsChanged", (accs) => {
    account = accs[0] || null;
    if (account) onConnected(); else location.reload();
  });
  window.ethereum.on("chainChanged", (hexId) => {
    const cid = parseInt(hexId, 16);
    if (chainCfg(cid)) { viewChain = cid; onConnected(); } else location.reload();
  });
}

/* ---------------- public reads ---------------- */
async function refreshPublic() {
  const c = chainCfg(viewChain);
  $("footChain").textContent = c.name;
  $("footExplorer").href = c.explorer + (hasContract(viewChain) ? "/address/" + contractAddr(viewChain) : "");
  $("nwName").textContent = c.name;
  $("nwId").textContent = viewChain;
  $("nwRpc").textContent = c.rpc.replace("https://", "");
  $("nwExplorer").href = c.explorer;
  $("noContractNote").style.display = hasContract(viewChain) ? "none" : "block";

  const rp = readProvider(viewChain);
  rp.getBlockNumber().then(n => { $("dashBlock").textContent = "block " + n.toLocaleString(); $("footStatus").textContent = c.short + " · block " + n.toLocaleString(); const sb = $("stripBlock"); if (sb) sb.textContent = n.toLocaleString(); lastBlock = n; renderTicker(); }).catch(() => {});

  if (account) {
    try { $("depBalance").textContent = fmtEth(await rp.getBalance(account)) + " ETH"; } catch { }
  }

  if (!hasContract(viewChain)) {
    slotSet($("statTvl"), "0"); slotSet($("statShares"), "0"); slotSet($("statMine"), "0");
    $("stripTvl").textContent = "0"; $("stripShares").textContent = "0";
    poolStatusTxt = "NOT DEPLOYED";
    const st = $("statStatus"); st.textContent = "NOT DEPLOYED"; st.className = "chip idle";
    $("statContract").textContent = "paste address in CONFIG to go live";
    return;
  }

  try {
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, rp);
    const info = await pool.getPoolInfo(account || ZERO);
    slotSet($("statTvl"), fmtEth(info.liquidity));
    slotSet($("statShares"), fmtEth(info.shares, 2));
    $("stripTvl").textContent = fmtEth(info.liquidity);
    $("stripShares").textContent = fmtEth(info.shares, 2);
    lastTvl = fmtEth(info.liquidity);
    poolStatusTxt = info.isPaused ? "PAUSED" : "ACTIVE";
    renderTicker();
    const st = $("statStatus");
    st.textContent = info.isPaused ? "PAUSED" : "ACTIVE";
    st.className = "chip " + (info.isPaused ? "off" : "ok");
    $("statContract").textContent = short(contractAddr(viewChain));

    if (account) {
      slotSet($("statMine"), fmtEth(info.myValue));
      const pct = info.shares > 0n ? (Number(info.myShares * 10000n / info.shares) / 100).toFixed(2) : "0";
      $("statMinePct").textContent = fmtEth(info.myShares, 2) + " HF-LP · " + pct + "% of pool";
      $("wdBalance").textContent = fmtEth(info.myShares, 4) + " HF-LP";
    } else {
      $("statMinePct").textContent = "connect wallet to view";
    }
  } catch (e) {
    console.warn(e);
    const st = $("statStatus"); st.textContent = "READ ERROR"; st.className = "chip off";
  }
}

/* quotes */
$("depAmount").addEventListener("input", async () => {
  const v = $("depAmount").value.trim();
  if (!v || isNaN(v) || Number(v) <= 0 || !hasContract(viewChain)) { $("depQuote").textContent = "—"; $("depSharePct").textContent = "—"; return; }
  try {
    const rp = readProvider(viewChain);
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, rp);
    const wei = ethers.parseEther(v);
    const shares = await pool.previewDeposit(wei);
    const total = await pool.totalShares();
    $("depQuote").textContent = fmtEth(shares, 4) + " HF-LP";
    const after = total + shares;
    $("depSharePct").textContent = after > 0n ? (Number(shares * 10000n / after) / 100).toFixed(2) + "%" : "—";
  } catch { $("depQuote").textContent = "—"; }
});

$("wdShares").addEventListener("input", async () => {
  const v = $("wdShares").value.trim();
  if (!v || isNaN(v) || Number(v) <= 0 || !hasContract(viewChain)) { $("wdQuote").textContent = "—"; $("wdRemain").textContent = "—"; return; }
  try {
    const rp = readProvider(viewChain);
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, rp);
    const shares = ethers.parseEther(v);
    const total = await pool.totalShares();
    const liq = await pool.totalLiquidity();
    const out = total > 0n ? shares * liq / total : 0n;
    $("wdQuote").textContent = fmtEth(out) + " ETH";
    const mine = account ? await pool.sharesOf(account) : 0n;
    const remShares = mine > shares ? mine - shares : 0n;
    const remVal = total > 0n ? remShares * liq / total : 0n;
    $("wdRemain").textContent = fmtEth(remVal) + " ETH";
  } catch { $("wdQuote").textContent = "—"; }
});

/* calculator widget (overview) */
$("calcAmount").addEventListener("input", async () => {
  const v = $("calcAmount").value.trim();
  if (!v || isNaN(v) || Number(v) <= 0 || !hasContract(viewChain)) { $("calcShares").textContent = "0"; $("calcPct").textContent = "0%"; return; }
  try {
    const rp = readProvider(viewChain);
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, rp);
    const wei = ethers.parseEther(v);
    const shares = await pool.previewDeposit(wei);
    const total = await pool.totalShares();
    $("calcShares").textContent = fmtEth(shares, 4);
    const after = total + shares;
    $("calcPct").textContent = after > 0n ? (Number(shares * 10000n / after) / 100).toFixed(2) + "%" : "0%";
  } catch { $("calcShares").textContent = "0"; $("calcPct").textContent = "0%"; }
});

/* overview: depositors count + live activity feed */
let extrasTick = 0;

/* ticker + pulse state */
let lastBlock = 0, lastTvl = "0", lastUsers = "—", poolStatusTxt = "—", avgBlockTime = 0, lastIv = [];
function renderTicker() {
  const el = $("tickerInner"); if (!el) return;
  const items = [
    ["NETWORK", "ROBINHOOD CHAIN · 4663"],
    ["BLOCK", lastBlock ? lastBlock.toLocaleString() : "—"],
    ["BLOCK TIME", avgBlockTime ? avgBlockTime.toFixed(2) + "s" : "—"],
    ["POOL", poolStatusTxt],
    ["TVL", lastTvl + " ETH"],
    ["PROVIDERS", String(lastUsers)],
    ["CONTRACT", hasContract(viewChain) ? short(contractAddr(viewChain)) : "—"],
    ["CUSTODY", "NONE — CONTRACT ONLY"],
  ];
  const html = items.map(([k, v]) => `<span class="tick-item"><i>◆</i>${k}&nbsp;<b>${v}</b></span>`).join("");
  el.innerHTML = html + html;
}

/* block-time pulse + sparkline */
async function computePulse() {
  try {
    const rp = readProvider(viewChain);
    const latest = await rp.getBlockNumber();
    const nums = [];
    for (let n = latest; n > latest - 20 && n > 0; n--) nums.push(n);
    const blocks = await Promise.all(nums.map(n => rp.getBlock(n)));
    const ts = blocks.map(b => Number(b.timestamp));
    const iv = [];
    for (let i = 0; i < ts.length - 1; i++) iv.push(ts[i] - ts[i + 1]);
    if (!iv.length) return;
    avgBlockTime = iv.reduce((a, b) => a + b, 0) / iv.length;
    $("pulseBlockTime").textContent = avgBlockTime.toFixed(2);
    lastIv = iv.reverse();
    drawSpark(lastIv);
    renderTicker();
  } catch { }
}
function drawSpark(iv) {
  const c = $("pulseSpark"); if (!c || iv.length < 2) return;
  const dpr = window.devicePixelRatio || 1;
  const w = c.clientWidth || 260, h = 46;
  c.width = w * dpr; c.height = h * dpr;
  const x = c.getContext("2d"); x.scale(dpr, dpr);
  const min = Math.min(...iv), max = Math.max(...iv), span = (max - min) || 1;
  const col = (getComputedStyle(document.body).getPropertyValue("--accent-deep") || "#4c5ef2").trim();
  x.beginPath();
  iv.forEach((v, i) => {
    const px = (i / (iv.length - 1)) * w, py = h - 7 - ((v - min) / span) * (h - 14);
    i ? x.lineTo(px, py) : x.moveTo(px, py);
  });
  x.strokeStyle = col; x.lineWidth = 2; x.lineJoin = "round"; x.stroke();
  x.lineTo(w, h); x.lineTo(0, h); x.closePath();
  x.globalAlpha = .13; x.fillStyle = col; x.fill();
}

/* flows + leaderboard (from pool events) */
function renderFlows(evs) {
  let inSum = 0n, outSum = 0n, inN = 0, outN = 0;
  for (const e of evs) {
    if (e.type === "DEPOSIT") { inSum += e.amount; inN++; } else { outSum += e.amount; outN++; }
  }
  const max = inSum > outSum ? inSum : outSum;
  $("flowInBar").style.width = max > 0n ? Number(inSum * 100n / max) + "%" : "0%";
  $("flowOutBar").style.width = max > 0n ? Number(outSum * 100n / max) + "%" : "0%";
  $("flowInVal").textContent = fmtEth(inSum) + " ETH";
  $("flowOutVal").textContent = fmtEth(outSum) + " ETH";
  const net = inSum - outSum;
  $("flowNet").textContent = inN + " deposits · " + outN + " withdrawals · net " + (net >= 0n ? "+" : "−") + fmtEth(net < 0n ? -net : net) + " ETH";
}
function renderLeaders(evs) {
  const agg = new Map();
  for (const e of evs) {
    if (e.type !== "DEPOSIT") continue;
    const a = e.user.toLowerCase();
    agg.set(a, (agg.get(a) || 0n) + e.amount);
  }
  const top = [...agg.entries()].sort((a, b) => (b[1] > a[1] ? 1 : -1)).slice(0, 5);
  $("leaderboard").innerHTML = top.length
    ? top.map(([a, v], i) => `<div class="lb-row"><span class="lb-rank">${i + 1}</span><span class="mono lb-addr">${short(a)}</span><span class="lb-amt mono">${fmtEth(v)} ETH</span></div>`).join("")
    : '<div class="lb-row" style="color:var(--ink-3)">No providers yet — be the first.</div>';
}

async function loadOverviewExtras() {
  const feed = $("activityFeed");
  if (!hasContract(viewChain)) {
    if (feed) feed.innerHTML = '<div class="feed-row" style="color:var(--ink-3)">No pool contract configured — set the address in the admin panel.</div>';
    return;
  }
  $("feedExplorerLink").href = chainCfg(viewChain).explorer + "/address/" + contractAddr(viewChain);
  try {
    const evs = await fetchPoolEvents(viewChain);
    const users = new Set(evs.filter(e => e.type === "DEPOSIT").map(e => e.user.toLowerCase()));
    $("stripUsers").textContent = String(users.size);
    lastUsers = String(users.size);
    renderFlows(evs); renderLeaders(evs); renderTicker();
    if (!evs.length) {
      feed.innerHTML = '<div class="feed-row" style="color:var(--ink-3)">No pool activity yet — the first deposit could be yours.</div>';
      return;
    }
    feed.innerHTML = evs.slice(0, 6).map(e => {
      const isIn = e.type === "DEPOSIT";
      return `<div class="feed-row">
        <span class="feed-dot ${isIn ? "in" : "out"}"></span>
        <div class="feed-main"><b>${isIn ? "Deposit" : "Withdrawal"}</b> · <span class="mono">${short(e.user)}</span></div>
        <span class="feed-amt ${isIn ? "in" : "out"}">${isIn ? "+" : "−"}${fmtEth(e.amount)} ETH</span>
      </div>`;
    }).join("");
  } catch {
    feed.innerHTML = '<div class="feed-row" style="color:var(--ink-3)">Could not read events right now — check the explorer link.</div>';
  }
}

$("depMax").addEventListener("click", async () => {
  if (!account) return;
  try {
    const bal = await readProvider(viewChain).getBalance(account);
    const gas = ethers.parseEther("0.0002");
    const usable = bal > gas ? bal - gas : 0n;
    $("depAmount").value = ethers.formatEther(usable);
    $("depAmount").dispatchEvent(new Event("input"));
  } catch { }
});
$("wdMax").addEventListener("click", async () => {
  if (!account || !hasContract(viewChain)) return;
  try {
    const s = await new ethers.Contract(contractAddr(viewChain), POOL_ABI, readProvider(viewChain)).sharesOf(account);
    $("wdShares").value = ethers.formatEther(s);
    $("wdShares").dispatchEvent(new Event("input"));
  } catch { }
});

/* ---------------- transactions ---------------- */
async function withSigner() {
  if (!account) { await connectWallet(); if (!account) return null; }
  const net = await browserProvider.getNetwork();
  if (Number(net.chainId) !== viewChain) { await switchNetwork(viewChain); }
  signer = await browserProvider.getSigner();
  return signer;
}

$("depBtn").addEventListener("click", async () => {
  if (!account) { toast("Please <b>Connect Wallet</b> first — then deposit will work.", true); return; }
  if (!hasContract(viewChain)) { toast("No pool contract on this network. Switch to <b>Robinhood Chain Mainnet (4663)</b>.", true); return; }
  const v = $("depAmount").value.trim();
  if (!v || isNaN(v) || Number(v) <= 0) { toast("Enter a valid ETH amount.", true); return; }
  const s = await withSigner(); if (!s) return;
  try {
    $("depBtn").disabled = true; $("depBtn").textContent = "Confirm in wallet…";
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, s);
    const tx = await pool.deposit({ value: ethers.parseEther(v) });
    $("depBtn").textContent = "Depositing…";
    toast("Transaction sent — " + txLink(tx.hash, viewChain));
    await tx.wait();
    toast("Deposit confirmed. " + txLink(tx.hash, viewChain));
    $("depAmount").value = "";
    refreshPublic(); loadMyEvents();
  } catch (e) {
    toast("Deposit failed: " + (e.shortMessage || e.message), true);
  } finally {
    $("depBtn").disabled = false; $("depBtn").textContent = "Deposit ETH";
  }
});

async function doWithdraw(sharesWei) {
  if (!account) { toast("Please <b>Connect Wallet</b> first.", true); return; }
  if (!hasContract(viewChain)) { toast("No pool contract on this network. Switch to Mainnet (4663).", true); return; }
  const s = await withSigner(); if (!s) return;
  try {
    $("wdBtn").disabled = true; $("wdAllBtn").disabled = true;
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, s);
    const tx = sharesWei === null ? await pool.withdrawAll() : await pool.withdraw(sharesWei);
    toast("Transaction sent — " + txLink(tx.hash, viewChain));
    await tx.wait();
    toast("Withdrawal confirmed. " + txLink(tx.hash, viewChain));
    $("wdShares").value = "";
    refreshPublic(); loadMyEvents();
  } catch (e) {
    toast("Withdraw failed: " + (e.shortMessage || e.message), true);
  } finally {
    $("wdBtn").disabled = false; $("wdAllBtn").disabled = false;
  }
}
$("wdBtn").addEventListener("click", () => {
  const v = $("wdShares").value.trim();
  if (!v || isNaN(v) || Number(v) <= 0) { toast("Enter shares to burn.", true); return; }
  doWithdraw(ethers.parseEther(v));
});
$("wdAllBtn").addEventListener("click", () => doWithdraw(null));

/* ---------------- user activity (own events) ---------------- */
async function loadMyEvents() {
  const tb = $("myEvents");
  if (!account || !hasContract(viewChain)) { tb.innerHTML = '<tr><td colspan="4" class="empty">Connect your wallet to load your on-chain activity.</td></tr>'; return; }
  tb.innerHTML = '<tr><td colspan="4" class="empty">Reading chain…</td></tr>';
  const evs = await fetchPoolEvents(viewChain, account);
  if (!evs.length) { tb.innerHTML = '<tr><td colspan="4" class="empty">No deposits or withdrawals yet for this wallet.</td></tr>'; return; }
  tb.innerHTML = evs.map(e => `<tr>
    <td><span class="badge ${e.type === "DEPOSIT" ? "in" : "out"}">${e.type}</span></td>
    <td class="mono">${fmtEth(e.amount)}</td>
    <td class="mono">${fmtEth(e.shares, 3)}</td>
    <td>${txLink(e.tx, viewChain)}</td></tr>`).join("");
}

/* event fetching: Blockscout API first (8s timeout), bounded RPC fallback */
async function fetchPoolEvents(chainId, onlyUser = null) {
  const c = chainCfg(chainId);
  const addr = contractAddr(chainId);
  const t0 = ethers.id("Deposited(address,uint256,uint256)");
  const t1 = ethers.id("Withdrawn(address,uint256,uint256)");
  let logs = [];
  try {
    const url = `${c.explorer}/api?module=logs&action=getLogs&fromBlock=0&toBlock=latest&address=${addr}&topic0_1_opr=or&topic0=${t0}&topic1=${t1}`;
    const r = await fetch(url, { signal: AbortSignal.timeout(8000) });
    const j = await r.json();
    if (j.status === "1" && Array.isArray(j.result)) logs = j.result;
    else throw new Error("api");
  } catch {
    try {
      const rp = readProvider(chainId);
      const latest = await rp.getBlockNumber();
      const step = 90000;
      const wins = [];
      for (let to = latest; to > 0 && wins.length < 40; to -= step) wins.push([Math.max(0, to - step), to]);
      const batch = 8;
      for (let i = 0; i < wins.length && logs.length < 60; i += batch) {
        const res = await Promise.allSettled(wins.slice(i, i + batch).map(([f, t]) =>
          rp.getLogs({ address: addr, topics: [[t0, t1]], fromBlock: f, toBlock: t })));
        for (const r of res) if (r.status === "fulfilled") logs.push(...r.value);
      }
    } catch { }
  }
  const itf = iface();
  const out = [];
  for (const l of logs) {
    try {
      const p = itf.parseLog({ topics: l.topics, data: l.data });
      const user = p.args.user || p.args[0];
      if (onlyUser && user.toLowerCase() !== onlyUser.toLowerCase()) continue;
      out.push({
        type: p.name === "Deposited" ? "DEPOSIT" : "WITHDRAW",
        user,
        amount: p.args.amount ?? p.args[1],
        shares: p.name === "Deposited" ? (p.args.sharesMinted ?? p.args[2]) : (p.args.sharesBurned ?? p.args[2]),
        tx: l.transactionHash,
        block: Number(l.blockNumber)
      });
    } catch { }
  }
  return out.sort((a, b) => b.block - a.block).slice(0, 25);
}

/* ---------------- wallet tab render ---------------- */
function renderWalletBox() {
  const box = $("walletBox");
  if (!account) return;
  box.innerHTML = `
    <div class="kv"><span class="k">ADDRESS</span><span class="v mono" style="font-size:12px">${addrLink(account, viewChain)}</span></div>
    <div class="kv"><span class="k">ETH BALANCE</span><span class="v mono" id="wbBal">reading…</span></div>
    <div class="kv"><span class="k">POOL SHARES</span><span class="v mono" id="wbShares">—</span></div>
    <div class="kv"><span class="k">POOL VALUE</span><span class="v mono" id="wbValue">—</span></div>
    <div style="height:16px"></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-ghost" onclick="navigator.clipboard.writeText('${account}');toast('Address copied.')">Copy address</button>
      <a class="btn btn-ghost" target="_blank" rel="noopener" href="${chainCfg(viewChain).explorer}/address/${account}">View on explorer</a>
      <button class="btn btn-ghost" onclick="location.reload()">Disconnect</button>
    </div>`;
  readProvider(viewChain).getBalance(account).then(b => { const el = $("wbBal"); if (el) el.textContent = fmtEth(b) + " ETH"; }).catch(() => {});
  if (hasContract(viewChain)) {
    const pool = new ethers.Contract(contractAddr(viewChain), POOL_ABI, readProvider(viewChain));
    pool.sharesOf(account).then(s => { const el = $("wbShares"); if (el) el.textContent = fmtEth(s, 4) + " HF-LP"; }).catch(() => {});
    pool.valueOf(account).then(v => { const el = $("wbValue"); if (el) el.textContent = fmtEth(v) + " ETH"; }).catch(() => {});
  }
}

/* ---------------- site settings (name / logo / socials / theme) ---------------- */
let DEFAULT_BRAND_LOGO = null;
function applySiteSettings(s) {
  if (!s) return;
  const name = (s.site_name || "HoodFi").trim() || "HoodFi";
  document.title = name + " — Liquidity Protocol on Robinhood Chain";
  document.querySelectorAll(".js-sitename").forEach(el => {
    el.textContent = el.hasAttribute("data-upper") ? name.toUpperCase() : name;
  });
  const wrap = $("brandLogoWrap");
  if (wrap) {
    if (DEFAULT_BRAND_LOGO === null) DEFAULT_BRAND_LOGO = wrap.innerHTML;
    wrap.innerHTML = s.logo
      ? `<img src="${s.logo}" alt="${name}" style="width:26px;height:26px;object-fit:contain;border-radius:7px;display:block">`
      : DEFAULT_BRAND_LOGO;
  }
  const tg = $("tgLink"), x = $("xLink");
  if (tg) { if (s.tg_url) { tg.href = s.tg_url; tg.style.display = ""; } else tg.style.display = "none"; }
  if (x)  { if (s.x_url)  { x.href  = s.x_url;  x.style.display  = ""; } else x.style.display  = "none"; }
  document.body.classList.remove("theme-1", "theme-2", "theme-3", "theme-4", "theme-5");
  document.body.classList.add("theme-" + (s.theme || 1));
  if (lastIv.length) drawSpark(lastIv);
}

function markTheme(t) {
  document.querySelectorAll(".theme-opt").forEach(o => o.classList.toggle("sel", +o.dataset.theme === t));
  const r = document.querySelector(`input[name="themePick"][value="${t}"]`);
  if (r) r.checked = true;
}
function currentSettings() {
  return {
    site_name: ($("setName").value.trim() || "HoodFi"),
    tg_url: $("setTg").value.trim(),
    x_url: $("setX").value.trim(),
    contract_4663: $("setCaMain").value.trim(),
    theme: +(document.querySelector('input[name="themePick"]:checked')?.value || 1),
    logo: (SITE_SETTINGS && SITE_SETTINGS.logo) || ""
  };
}
async function saveSettings() {
  const s = currentSettings();
  $("settingsMsg").style.color = "var(--mint-deep)";
  if (window.STATIC_DEMO) {
    localStorage.setItem("hoodfi_settings", JSON.stringify(s));
    window.SITE_SETTINGS = s; applySiteSettings(s);
    $("settingsMsg").textContent = "Saved ✓ (demo preview: browser storage)";
    return;
  }
  try {
    const r = await fetch("?action=save_settings", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(s) });
    const j = await r.json();
    if (j.ok) {
      window.SITE_SETTINGS = j.settings; applySiteSettings(j.settings);
      $("settingsMsg").textContent = "Saved ✓ — live for all visitors";
      refreshPublic(); if (APP.action === "admin") refreshAdmin();
    }
    else { $("settingsMsg").style.color = "var(--red)"; $("settingsMsg").textContent = "Save failed: " + (j.error || ""); }
  } catch { $("settingsMsg").style.color = "var(--red)"; $("settingsMsg").textContent = "Save failed."; }
}
async function uploadLogo() {
  const f = $("setLogo").files[0];
  if (!f) return;
  if (window.STATIC_DEMO) {
    const rd = new FileReader();
    rd.onload = () => {
      const s = { ...currentSettings(), logo: rd.result };
      localStorage.setItem("hoodfi_settings", JSON.stringify(s));
      window.SITE_SETTINGS = s; applySiteSettings(s);
      const p = $("logoPreview"); p.src = rd.result; p.style.display = "block";
      toast("Logo updated (demo preview).");
    };
    rd.readAsDataURL(f);
    return;
  }
  const fd = new FormData();
  fd.append("logo", f);
  try {
    const r = await fetch("?action=upload_logo", { method: "POST", body: fd });
    const j = await r.json();
    if (j.ok) {
      window.SITE_SETTINGS = j.settings; applySiteSettings(j.settings);
      const p = $("logoPreview"); p.src = j.settings.logo; p.style.display = "block";
      toast("Logo updated — live for all visitors.");
    } else toast("Logo upload failed: " + (j.error || ""), true);
  } catch { toast("Logo upload failed.", true); }
}
function initSettingsForm() {
  const s = SITE_SETTINGS || {};
  const ss = $("storageStatus");
  if (ss && window.SITE_INFO) {
    ss.innerHTML = SITE_INFO.writable
      ? 'Storage: <b style="color:var(--mint-deep)">OK</b> — settings save on this server (' + SITE_INFO.storage + ')'
      : 'Storage: <b style="color:var(--red)">READ-ONLY</b> — folder not writable! In your hosting file manager set this folder to <b>CHMOD 777</b>, otherwise name/logo/socials cannot be saved.';
  }
  $("setName").value = s.site_name || "";
  $("setTg").value = s.tg_url || "";
  $("setX").value = s.x_url || "";
  $("setCaMain").value = s.contract_4663 || "";
  markTheme(+(s.theme || 1));
  if (s.logo) { const p = $("logoPreview"); p.src = s.logo; p.style.display = "block"; }
  document.querySelectorAll(".theme-opt").forEach(o => o.addEventListener("click", () => {
    markTheme(+o.dataset.theme);
    applySiteSettings({ ...currentSettings(), theme: +o.dataset.theme });  // instant preview
  }));
  $("setLogo").addEventListener("change", uploadLogo);
  $("saveSettings").addEventListener("click", saveSettings);
}

/* ---------------- admin ---------------- */
async function initAdmin() { initSettingsForm(); await refreshAdmin(); }

async function refreshAdmin() {
  const c = chainCfg(viewChain);
  $("admNet").textContent = c.name + " (chain " + viewChain + ")";
  const addr = contractAddr(viewChain);
  $("admContract").innerHTML = hasContract(viewChain)
    ? `<a class="link" target="_blank" rel="noopener" href="${c.explorer}/address/${addr}">${addr}</a>`
    : "not configured";

  const t0 = performance.now();
  try {
    const rp = readProvider(viewChain);
    $("admBlock").textContent = (await rp.getBlockNumber()).toLocaleString();
    $("admLatency").textContent = Math.round(performance.now() - t0) + " ms";
  } catch { $("admLatency").textContent = "RPC error"; }

  if (!hasContract(viewChain)) {
    slotSet($("admTvl"), "0"); slotSet($("admShares"), "0"); slotSet($("admUsers"), "0"); slotSet($("admOps"), "0");
    $("admOwner").textContent = "—";
    $("admPaused").innerHTML = '<span class="chip idle">NOT DEPLOYED</span>';
    $("admEvents").innerHTML = '<tr><td colspan="4" class="empty">Deploy LiquidityPool.sol and paste its address in CONFIG to see live data.</td></tr>';
    return;
  }
  try {
    const rp = readProvider(viewChain);
    const pool = new ethers.Contract(addr, POOL_ABI, rp);
    const info = await pool.getPoolInfo(ZERO);
    slotSet($("admTvl"), fmtEth(info.liquidity));
    slotSet($("admShares"), fmtEth(info.shares, 2));
    $("admOwner").innerHTML = `<a class="link" target="_blank" rel="noopener" href="${c.explorer}/address/${info.contractOwner}">${short(info.contractOwner)}</a>`;
    $("admPaused").innerHTML = info.isPaused ? '<span class="chip off">PAUSED</span>' : '<span class="chip ok">ACTIVE</span>';

    const evs = await fetchPoolEvents(viewChain);
    const users = new Set(evs.map(e => e.user.toLowerCase()));
    slotSet($("admUsers"), String(users.size));
    slotSet($("admOps"), evs.filter(e => e.type === "DEPOSIT").length + " / " + evs.filter(e => e.type === "WITHDRAW").length);
    $("admEvents").innerHTML = evs.length ? evs.slice(0, 12).map(e => `<tr>
      <td><span class="badge ${e.type === "DEPOSIT" ? "in" : "out"}">${e.type}</span></td>
      <td class="mono">${addrLink(e.user, viewChain)}</td>
      <td class="mono">${fmtEth(e.amount)}</td>
      <td>${txLink(e.tx, viewChain)}</td></tr>`).join("")
      : '<tr><td colspan="4" class="empty">No events yet — the pool is waiting for its first deposit.</td></tr>';
  } catch (e) {
    console.warn(e);
    toast("Admin read failed: " + (e.shortMessage || e.message), true);
  }
}
$("adminRefresh") && $("adminRefresh").addEventListener("click", refreshAdmin);

/* ---------------- wire up ---------------- */
$("connectBtn").addEventListener("click", () => {
  if (!account) { connectWallet(); return; }
  $("walletMenu").classList.toggle("open");
});
$("heroConnect").addEventListener("click", connectWallet);
$("walletConnect") && $("walletConnect").addEventListener("click", connectWallet);
$("switchMainnet").addEventListener("click", () => switchNetwork(4663));
$("wmCopy").addEventListener("click", () => { navigator.clipboard.writeText(account); toast("Address copied."); $("walletMenu").classList.remove("open"); });
$("wmExplorer").addEventListener("click", () => { window.open(chainCfg(viewChain).explorer + "/address/" + account, "_blank"); $("walletMenu").classList.remove("open"); });
$("wmDisconnect").addEventListener("click", disconnectWallet);
document.addEventListener("click", (e) => {
  if (!e.target.closest("#walletMenuWrap")) $("walletMenu").classList.remove("open");
});

function disconnectWallet() {
  account = null; signer = null;
  $("walletMenu").classList.remove("open");
  $("connectBtn").textContent = "Connect Wallet";
  $("heroConnect").textContent = "Connect Wallet";
  $("netDot").classList.remove("on");
  $("netLabel").textContent = "Not connected";
  $("walletBox").innerHTML = '<div style="padding:26px 0;text-align:center;color:var(--ink-3);font-size:14px">No wallet connected.<br><br><button class="btn btn-dark" id="walletConnect">Connect Wallet</button></div>';
  $("walletConnect").addEventListener("click", connectWallet);
  $("depBalance").textContent = "—";
  $("wdBalance").textContent = "—";
  slotSet($("statMine"), "—");
  $("statMinePct").textContent = "connect wallet to view";
  loadMyEvents();
  toast("Wallet disconnected.");
}

/* silently restore wallet after page reload — no popup */
(async function silentReconnect() {
  if (!window.ethereum) return;
  try {
    browserProvider = new ethers.BrowserProvider(window.ethereum);
    const accs = await browserProvider.send("eth_accounts", []);
    if (accs && accs[0]) {
      account = accs[0];
      const net = await browserProvider.getNetwork();
      if (chainCfg(Number(net.chainId))) viewChain = Number(net.chainId);
      onConnected();
    }
  } catch (e) { }
})();

/* reveal on scroll */
const io = new IntersectionObserver(es => es.forEach(e => e.isIntersecting && e.target.classList.add("in")), { threshold: .12 });
document.querySelectorAll(".reveal").forEach(el => io.observe(el));

/* boot */
applySiteSettings(window.SITE_SETTINGS);
refreshPublic();
loadOverviewExtras();
computePulse();
renderTicker();
refreshTimer = setInterval(() => {
  refreshPublic();
  if (++extrasTick % 4 === 0) { loadOverviewExtras(); computePulse(); }
  if (APP.isAdmin && APP.action === "admin") refreshAdmin();
}, CONFIG.REFRESH_MS);
</script>
</body>
</html>
