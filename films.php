<?php
session_start();

// ==== CONFIG ====
const ACCESS_CODE   = 'Ваш код доступа';
const ALLOHA_TOKEN  = 'Ваш токен;
const BHCESH_TOKEN  = 'Ваш токен';

// ==== HELPERS ====
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function fetch_json(string $url, array &$errors, string $label) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'User-Agent: Mozilla/5.0 (compatible; ExampleFetcher/1.0)'
    ],
  ]);
  $body = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($err || $code >= 400 || $body === false || $body === '') {
    $errors[] = "Ошибка запроса ({$label}): HTTP $code" . ($err ? " — $err" : "");
    return null;
  }
  $json = json_decode($body, true);
  if ($json === null) {
    $errors[] = "Невалидный JSON ({$label})";
    return null;
  }
  return $json;
}

/** Извлекает Kinopoisk ID из:
 * - ?kp=..., где значение может быть числом ИЛИ полной ссылкой
 * - ?url=... (полная ссылка)
 * - пути вида /film/5501397/ или /что-угодно/7524629/
 * Возвращает последний числовой блок длиной >=3.
 */
function extract_kp_from_request(): string {
  $candidates = [];
  if (isset($_GET['kp']))  $candidates[] = (string)$_GET['kp'];
  if (isset($_GET['url'])) $candidates[] = (string)$_GET['url'];
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  if ($uri) $candidates[] = $uri;

  foreach ($candidates as $cand) {
    if (preg_match_all('/(\d{3,})/', $cand, $m) && !empty($m[1])) {
      return (string)end($m[1]);
    }
  }
  return '';
}

// ==== AUTH GATE ====
if (isset($_GET['logout'])) {
  $_SESSION = [];
  session_destroy();
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
  exit;
}

if (isset($_POST['access_code'])) {
  if (trim($_POST['access_code']) === ACCESS_CODE) {
    $_SESSION['authed'] = true;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
  } else {
    $gate_error = 'Неверный код доступа';
  }
}

if (empty($_SESSION['authed'])): ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Вход — 390room</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root { --bg:#0e1117; --panel:#171a21; --border:#2a2f3a; --text:#e9edf4; --muted:#aab1c0; --accent:#67b2ff; --danger:#ff6b6b; }
    * { box-sizing: border-box; }
    html, body { height:100%; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; color:var(--text);
      display:grid; place-items:center; background: radial-gradient(900px 400px at 10% -10%, #0d1220 10%, transparent 60%) , var(--bg); }
    .card { width:min(420px, 92vw); background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:22px 18px 18px; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
    h1 { margin:0 0 12px; font-size:18px; }
    label { display:block; font-size:13px; color:var(--muted); margin-bottom:6px; }
    .stack { display:grid; gap:10px; }
    .input, .btn { width:100%; height:46px; padding:10px 12px; border-radius:10px; border:1px solid var(--border);
      background:#0d1117; color:var(--text); font-size:15px; line-height:24px; appearance:none; }
    .input::placeholder { color:#8a93a6; }
    .input:focus { outline:none; border-color:#344458; box-shadow:0 0 0 2px rgba(103,178,255,.18); }
    .btn { cursor:pointer; background:linear-gradient(180deg,#0f141c,#0c1118); display:inline-flex; align-items:center; justify-content:center; }
    .btn:hover { border-color:#3a4252; transform: translateY(-1px); transition:.15s ease; }
    .err { color:var(--danger); font-size:14px; margin-top:8px; }
  </style>
</head>
<body>
  <form class="card" method="post" autocomplete="off">
    <h1>Доступ</h1>
    <div class="stack">
      <div>
        <label for="access_code">Код</label>
        <input id="access_code" name="access_code" type="password" class="input" placeholder="введите код" autofocus>
      </div>
      <button type="submit" class="btn">Войти</button>
      <?php if (!empty($gate_error)): ?><div class="err"><?=h($gate_error)?></div><?php endif; ?>
    </div>
  </form>
</body>
</html>
<?php exit; endif;

// ==== MAIN PAGE ====
// поддерживаем ?kp=, ?url= и «красивые» пути
$kp = extract_kp_from_request();

$errors = [];
$bh_data = null;
$al_data = null;
$bh_url  = null;
$al_url  = null;

if ($kp !== '') {
  $bh_url = "https://api.bhcesh.me/franchise/details?token=" . urlencode(BHCESH_TOKEN) . "&kinopoisk_id=" . urlencode($kp);
  $al_url = "https://api.alloha.tv/?token=" . urlencode(ALLOHA_TOKEN) . "&kp=" . urlencode($kp);

  $bh_json = fetch_json($bh_url, $errors, 'bhcesh');
  if ($bh_json) $bh_data = $bh_json;

  $al_json = fetch_json($al_url, $errors, 'alloha');
  if ($al_json) $al_data = $al_json;
}

// Вспомогалки
function first_value($arr, $keys, $default = null) {
  foreach ($keys as $k) {
    if (isset($arr[$k]) && $arr[$k] !== '' && $arr[$k] !== null) return $arr[$k];
  }
  return $default;
}

// «расплющенные» данные
$bh_core = $bh_data ?: [];
$al_core = ($al_data['data'] ?? null) ?: [];

$is_series_alloha = isset($al_core['seasons']) && is_array($al_core['seasons']) && count($al_core['seasons']) > 0;
$bh_is_series     = isset($bh_core['seasons']) && is_array($bh_core['seasons']) && count($bh_core['seasons']) > 0;

$card_title  = first_value($bh_core, ['name','name_eng','title'], $al_core['name'] ?? '');
$card_year   = first_value($bh_core, ['year'], $al_core['year'] ?? '');
$card_poster = first_value($bh_core, ['poster','posterUrl','cover'], $al_core['poster'] ?? '');
$bh_iframe   = first_value($bh_core, ['iframe_url','iframe'], null);

$BH_JSON = json_encode($bh_core, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$AL_JSON = json_encode($al_core, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Смотри & Кайфуй</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root {
      --bg:#0f1115; --panel:#171a21; --panel-2:#131720; --muted:#aab1c0; --text:#e9edf4;
      --accent:#67b2ff; --accent-2:#7bda9c; --border:#2a2f3a; --danger:#ff6b6b; --radius:12px;
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
      color:var(--text);
      background:radial-gradient(1200px 600px at 10% -10%, #0d1220 8%, transparent 60%) , linear-gradient(180deg,#0e1117,#11151d 40%, #0d1117);
    }
    a { color: var(--accent); text-decoration: none; }

    /* ======= HEADER ======= */
    .app-header {
      position: sticky; top:0; z-index:15; background: rgba(19, 23, 32, .65);
      backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); box-shadow: 0 6px 30px rgba(0,0,0,.25);
    }
    .app-header::before{ content:""; position:absolute; left:0; right:0; top:0; height:2px; background: linear-gradient(90deg, var(--accent), var(--accent-2), var(--accent)); opacity:.7; }
    .header-wrap{ max-width: 1400px; margin:0 auto; padding: 12px 18px 14px; display:flex; flex-direction:column; gap:10px; }
    .top-links{ margin-left:auto; display:flex; gap:8px; }
    .link{ display:inline-flex; align-items:center; gap:8px; color:var(--muted); padding:8px 10px; border:1px solid transparent; border-radius:10px; transition:.15s ease; }
    .link:hover{ border-color:var(--border); color:var(--text); transform: translateY(-1px); }
    .link svg{ width:14px; height:14px; opacity:.9; }

    .search{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .search__field-wrap{ position:relative; flex:1 1 420px; min-width:220px; }
    .search__field{
      width:100%; height:46px; border-radius:12px; border:1px solid var(--border);
      background:#0b0f14; color:var(--text); padding:10px 56px 10px 12px; font-size:14px; appearance:none;
    }
    .search__field:focus{ outline:none; border-color:#344458; box-shadow:0 0 0 2px rgba(103,178,255,.18); }
    .search__btn{
      height:46px; border-radius:12px; border:1px solid var(--border);
      background:linear-gradient(180deg,#0f141c,#0c1118); color:var(--text);
      padding:0 14px; display:flex; align-items:center; gap:8px; cursor:pointer; transition:.15s ease;
    }
    .search__btn:hover{ border-color:#3a4252; transform: translateY(-1px); }
    .search__btn svg{ width:16px; height:16px; }
    .hint{ position:absolute; right:8px; top:50%; transform:translateY(-50%); background:#0f141c; border:1px solid var(--border); color:var(--muted); padding:2px 6px; border-radius:6px; font-size:11px; }

    .err { color:var(--danger); font-size:14px; margin-top:6px; }

    /* ======= LAYOUT ======= */
    main { padding: 18px; max-width: 1400px; margin: 0 auto 36px; }
    .grid { display:grid; grid-template-columns: 340px 1fr; gap:18px; align-items:start; }
    .card { background:var(--panel); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .card h2 { margin:0; padding:12px 14px; border-bottom:1px solid var(--border); background:var(--panel-2); font-size:16px; }
    .poster { padding:12px; }
    .poster img { width:100%; border-radius:10px; border:1px solid var(--border); display:block; }
    .meta { padding:10px 14px 16px; }
    .meta table { width:100%; border-collapse:collapse; font-size:14px; }
    .meta th, .meta td { text-align:left; padding:7px 0; vertical-align:top; }
    .meta th { color:var(--muted); width:40%; font-weight:500; }

    .tabs { display:flex; gap:8px; padding:10px; border-bottom:1px solid var(--border); background:var(--panel-2); }
    .tab-btn { appearance:none; border:1px solid var(--border); background:#0d1117; color:var(--text); padding:8px 12px; border-radius:10px; font-size:14px; cursor:pointer; }
    .tab-btn[aria-selected="true"] { border-color:var(--accent); box-shadow: 0 0 0 2px rgba(103,178,255,.12) inset; }
    .tab-btn[disabled] { opacity:.5; cursor:not-allowed; }

    .panel { padding:12px; display:block; }
    .panel[hidden] { display:none; }

    iframe { width:100%; height: clamp(300px, 65vh, 680px); border:none; background:#0b0f14; border-radius:10px; outline:1px solid var(--border); }

    /* SUPPORT BADGE — как лого сайта слева от поиска */
    .support{ display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:var(--muted); padding-right:4px; white-space:nowrap; }
    .support:hover{ color:var(--text); }
    .support__label{ font-size:16px; line-height:1; opacity:.9; }
    .support__logo{ display:block; height:32px; width:auto; border-radius:6px; box-shadow: 0 2px 10px rgba(0,0,0,.25); }

    /* Контейнер плеера с фоновой подсказкой */
    .player-shell{ position: relative; border-radius:10px; outline:1px solid var(--border); background:#0b0f14; overflow:hidden; }
    .player-shell::before{ content: attr(data-note); position:absolute; inset:0; display:grid; place-items:center; padding:18px; text-align:center;
      font-size:14px; line-height:1.45; color:var(--muted); opacity:.9; pointer-events:none; user-select:none; z-index:0; white-space:pre-line; }
    .player-shell > iframe, .player-shell iframe{ background:transparent !important; display:block; position:relative; z-index:1; }

    /* ====== Адаптив ====== */
    @media (max-width: 900px){
      .support{ order:0; width:100%; justify-content:center; }
      .support__logo{ height:20px; }
    }
    @media (max-width: 700px){
      .header-wrap{ gap:12px; }
      .top-links{ width:100%; justify-content:flex-end; }
      .grid { grid-template-columns: 1fr; }
      .search{ align-items:stretch; }
      .search__field-wrap{ order:1; flex:1 1 100%; }
      .search__btn{ order:2; width:100%; }
      iframe { height: 250px; }
    }
    @media (max-width: 420px){
      .hint{ display:none; }
      .support__logo{ height:18px; }
    }
  </style>
</head>
<body>
<header class="app-header">
  <div class="header-wrap">

    <form class="search" method="get" action="">
      <!-- SUPPORT BADGE -->
      <a class="support" href="https://animeflow.su" target="_blank" rel="noopener" aria-label="Animeflow.su">
        <span class="support__label">При поддержке</span>
        <img class="support__logo" src="/logo.png" alt="Animeflow.su">
      </a>

      <div class="search__field-wrap">
        <input type="text" name="kp" class="search__field" placeholder="Вставь KP ID или ссылку Кинопоиска и нажми Enter" value="<?=h($kp)?>" autocomplete="off">
        <kbd class="hint">Enter</kbd>
      </div>

      <button type="submit" class="search__btn" title="Загрузить">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79L20 21.5 21.5 20 15.5 14zM9.5 14C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
        <span>Загрузить</span>
      </button>

      <nav class="top-links">
        <a class="link" href="/" title="Сбросить ввод">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 6v3l4-4-4-4v3C6.48 4 2 8.48 2 14a8 8 0 0 0 13.66 5.66l-1.42-1.42A6 6 0 1 1 12 6z"/></svg>
          Сброс
        </a>
        <a class="link" href="?logout=1" title="Завершить сессию">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 17l1.41-1.41L8.83 13H21v-2H8.83l2.58-2.59L10 7l-5 5 5 5zM4 19h8v2H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h8v2H4v14z"/></svg>
          Выйти
        </a>
      </nav>
    </form>

    <?php if ($errors): ?>
      <div class="err"><?=h(implode(' · ', $errors))?></div>
    <?php endif; ?>
  </div>
</header>

<main>
  <div class="grid">
    <div class="card">
      <h2>Инфо</h2>
      <div class="poster">
        <?php if ($card_poster): ?>
          <img src="<?=h($card_poster)?>" alt="Постер">
        <?php else: ?>
          <div class="muted" style="padding:10px 12px">Постер не найден</div>
        <?php endif; ?>
      </div>
      <div class="meta">
        <table>
          <tr><th>Название</th><td><?=h($card_title ?: '—')?><?= $card_year ? ' ('.h($card_year).')':'' ?></td></tr>
          <tr><th>Тип</th><td><?= ($is_series_alloha || $bh_is_series) ? 'Сериал' : 'Фильм' ?></td></tr>
          <tr><th>KP ID</th><td><?=h($kp ?: '—')?></td></tr>
          <tr><th>Источник bhcesh</th><td><?= $bh_iframe ? 'iframe найден' : 'iframe не найден' ?></td></tr>
          <tr><th>Источник alloha</th><td><?= $is_series_alloha ? 'сезоны/эпизоды обнаружены' : 'без сезонов (возможно фильм)' ?></td></tr>
        </table>
      </div>
    </div>

    <div class="card">
      <h2>Плееры</h2>
      <div class="tabs" role="tablist" aria-label="Выбор плеера">
        <button class="tab-btn" role="tab" id="tab-btn-namy"   aria-selected="true"  aria-controls="panel-namy">Collaps</button>
        <button class="tab-btn" role="tab" id="tab-btn-kodik"  aria-selected="false" aria-controls="panel-kodik" <?= $kp ? '' : 'disabled title="Введите KP ID"' ?>>Kodik</button>
        <button class="tab-btn" role="tab" id="tab-btn-miyagi" aria-selected="false" aria-controls="panel-miyagi" <?= empty($al_core) ? 'disabled title="Нет данных Alloha"' : '' ?>>Alloha</button>
      </div>

      <!-- Collaps -->
      <section class="panel" id="panel-namy" role="tabpanel" aria-labelledby="tab-btn-namy">
        <div class="player-shell" data-note="При включенном VPN плееры могут не отображаться">
          <iframe id="namyFrame" allowfullscreen referrerpolicy="no-referrer"></iframe>
        </div>
      </section>

      <!-- Kodik -->
      <section class="panel" id="panel-kodik" role="tabpanel" aria-labelledby="tab-btn-kodik" hidden>
        <div class="player-shell" data-note="Kodik: если не загрузился — проверь VPN/блокировщики">
          <div id="kodik-player"></div>
        </div>
      </section>

      <!-- Alloha -->
      <section class="panel" id="panel-miyagi" role="tabpanel" aria-labelledby="tab-btn-miyagi" hidden>
        <div class="player-shell" data-note="Если плеер пуст — у Alloha нет iframe для этого тайтла">
          <iframe id="miyFrame" allowfullscreen referrerpolicy="no-referrer"></iframe>
        </div>
      </section>
    </div>
  </div>
</main>

<script>
  'use strict';
  // Данные с сервера
  const BH    = <?= $BH_JSON ?: 'null' ?>;
  const AL    = <?= $AL_JSON ?: 'null' ?>;
  const KP    = <?= json_encode($kp) ?>;
  const TITLE = <?= json_encode($card_title ?: '') ?>;

  // ===== утилиты =====
  function setBtnEnabled(btnId, on, tip){
    const btn = document.getElementById(btnId);
    if (!btn) return;
    if (on){
      btn.removeAttribute('disabled');
      if (tip) btn.title = tip; else btn.removeAttribute('title');
    } else {
      btn.setAttribute('disabled','');
      btn.title = tip || 'Недоступно';
    }
  }
  function waitFrameLoaded(ifr, deadlineMs = 6500){
    return new Promise(resolve => {
      if (!ifr || !ifr.src) return resolve(false);
      let done = false;
      const finish = ok => { if (!done){ done = true; clearTimeout(t); resolve(ok); } };
      const t = setTimeout(()=> finish(false), deadlineMs);
      ifr.addEventListener('load', ()=> finish(true), { once:true });
    });
  }
  function isEmptyObj(o){ return !o || typeof o !== 'object' || (Array.isArray(o) ? o.length === 0 : Object.keys(o).length === 0); }
  function waitChildIframe(container, deadlineMs = 6000){
    if (!container) return Promise.resolve(false);
    return new Promise(resolve => {
      const start = Date.now();
      const mo = new MutationObserver(() => {
        if (container.querySelector('iframe')) { mo.disconnect(); resolve(true); }
      });
      mo.observe(container, {childList:true, subtree:true});
      (function tick(){
        if (container.querySelector('iframe')) { mo.disconnect(); return resolve(true); }
        if (Date.now() - start >= deadlineMs) { mo.disconnect(); return resolve(false); }
        setTimeout(tick, 250);
      })();
    });
  }

  // Скрыть из шапки «Ошибка запроса (bhcesh): HTTP 404»
  (function cleanBh404(){
    const errBox = document.querySelector('.header-wrap .err');
    if (!errBox) return;
    const parts = errBox.textContent.split('·').map(s=>s.trim());
    const filtered = parts.filter(s => !/Ошибка запроса\s*\(bhcesh\).*HTTP\s*404/i.test(s));
    if (filtered.length === 0) errBox.remove();
    else errBox.textContent = filtered.join(' · ');
  })();

  // Показать уведомление «нет доступных плееров» под поиском
  function showNoPlayersNotice(){
    const form = document.querySelector('.header-wrap form.search');
    if (!form) return;
    if (document.getElementById('no-players-note')) return;
    const div = document.createElement('div');
    div.className = 'err';
    div.id = 'no-players-note';
    div.textContent = 'Для этого тайтла нет доступных плееров.';
    form.insertAdjacentElement('afterend', div);
  }

  // ---- Tabs ----
  const tabBtns = Array.from(document.querySelectorAll('.tab-btn[role="tab"]'));
  const panels  = {
    namy:   document.getElementById('panel-namy'),
    kodik:  document.getElementById('panel-kodik'),
    miyagi: document.getElementById('panel-miyagi'),
  };
  function selectTabByButtonId(btnId){
    const btn = document.getElementById(btnId);
    if (!btn || btn.disabled) return false;
    tabBtns.forEach(b => b.setAttribute('aria-selected', String(b.id === btnId)));
    Object.values(panels).forEach(p => { if (p) p.hidden = true; });
    const panel = document.getElementById(btn.getAttribute('aria-controls'));
    if (panel) panel.hidden = false;
    return true;
  }
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled) return;
      tabBtns.forEach(b => b.setAttribute('aria-selected', String(b === btn)));
      Object.values(panels).forEach(p => p.hidden = true);
      const panel = document.getElementById(btn.getAttribute('aria-controls'));
      if (panel) panel.hidden = false;
    });
  });

  // ===== Collaps (bhcesh) =====
  const namyFrame = document.getElementById('namyFrame');
  function setupNamy(){
    const BTN_ID = 'tab-btn-namy';
    try{
      if (isEmptyObj(BH)){
        namyFrame.src = '';
        setBtnEnabled(BTN_ID, false, 'Нет данных Collaps');
        return Promise.resolve(false);
      }
      const src = BH.iframe_url || BH.iframe || '';
      if (!src){
        namyFrame.src = '';
        setBtnEnabled(BTN_ID, false, 'Collaps: iframe не найден');
        return Promise.resolve(false);
      }
      setBtnEnabled(BTN_ID, true, '');
      namyFrame.src = src;
      return waitFrameLoaded(namyFrame, 6500).then(ok => {
        if (!ok) setBtnEnabled(BTN_ID, false, 'Collaps: не загрузился (VPN/блокировщик?)');
        return ok;
      });
    } catch(e){
      console.warn('Collaps init failed:', e);
      setBtnEnabled(BTN_ID, false, 'Collaps: ошибка инициализации');
      return Promise.resolve(false);
    }
  }

  // ===== Alloha =====
  const miyFrame = document.getElementById('miyFrame');
  function findFirstSeriesIframe(ALobj){
    const seasons = (ALobj && ALobj.seasons) || {};
    const sKeys = Object.keys(seasons).sort((a,b)=>parseInt(a)-parseInt(b));
    for (const s of sKeys){
      const eps = seasons[s]?.episodes || {};
      const eKeys = Object.keys(eps).sort((a,b)=>parseInt(a)-parseInt(b));
      for (const e of eKeys){
        const tr = eps[e]?.translation || {};
        for (const [, obj] of Object.entries(tr)){
          if (obj && obj.iframe) return obj.iframe;
        }
      }
    }
    return '';
  }
  function setupMiyagi(){
    const BTN_ID = 'tab-btn-miyagi';
    try{
      if (isEmptyObj(AL)){
        miyFrame.src = '';
        setBtnEnabled(BTN_ID, false, 'Нет данных Alloha');
        return Promise.resolve(false);
      }
      let src = '';
      const hasSeasons = AL.seasons && typeof AL.seasons === 'object';
      if (!hasSeasons){
        const firstTr = Object.values(AL.translation_iframe || {})[0];
        src = (firstTr && firstTr.iframe) || AL.iframe || '';
      } else {
        src = findFirstSeriesIframe(AL) || '';
      }
      if (!src){
        miyFrame.src = '';
        setBtnEnabled(BTN_ID, false, 'Alloha: iframe не найден');
        return Promise.resolve(false);
      }
      setBtnEnabled(BTN_ID, true, '');
      miyFrame.src = src;
      return waitFrameLoaded(miyFrame, 6500).then(ok => {
        if (!ok) setBtnEnabled(BTN_ID, false, 'Alloha: не загрузился (VPN/блокировщик?)');
        return ok;
      });
    } catch(e){
      console.warn('Alloha init failed:', e);
      setBtnEnabled(BTN_ID, false, 'Alloha: ошибка инициализации');
      return Promise.resolve(false);
    }
  }

  // ===== Kodik =====
  function setupKodik(){
    const BTN_ID = 'tab-btn-kodik';
    try{
      const container = document.getElementById('kodik-player');
      if (!KP){
        setBtnEnabled(BTN_ID, false, 'Введите KP ID');
        return Promise.resolve(false);
      }
      setBtnEnabled(BTN_ID, false, 'Идёт инициализация Kodik…');

      // Конфиг ДО загрузки скрипта
      window.kodikAddPlayers = {
        onDomReady: true,
        title: TITLE || 'Видео',
        kinopoiskID: String(KP)
      };

      const afterLoad = () =>
        waitChildIframe(container, 6000).then(ok => {
          setBtnEnabled(BTN_ID, ok, ok ? '' : 'Нет плеера Kodik для этого тайтла');
          return ok;
        });

      const existing = document.querySelector('script[src*="kodik-add.com/add-players.min.js"]');
      if (!existing){
        if (container) container.innerHTML = '';
        const s = document.createElement('script');
        s.async = true;
        s.src = 'https://kodik-add.com/add-players.min.js';
        s.onload  = afterLoad;
        s.onerror = () => { setBtnEnabled(BTN_ID, false, 'Ошибка загрузки скрипта Kodik'); };
        document.head.appendChild(s);
        // Вернём промис, который зарезолвится после загрузки скрипта
        return new Promise(res => { s.addEventListener('load', async ()=> res(await afterLoad())); });
      } else {
        if (typeof window.kodikAddPlayersInit === 'function') {
          if (container) container.innerHTML = '';
          window.kodikAddPlayersInit();
        }
        return afterLoad();
      }
    } catch(e){
      console.warn('Kodik init failed:', e);
      setBtnEnabled(BTN_ID, false, 'Kodik: ошибка инициализации');
      return Promise.resolve(false);
    }
  }

  // ===== Запуск и авто-переключение =====
  Promise.all([ setupNamy(), setupKodik(), setupMiyagi() ]).then(([hasNamy, hasKodik, hasMiyagi])=>{
    // если активный Collaps недоступен — переключаемся по приоритету
    const namyBtn = document.getElementById('tab-btn-namy');
    const isNamyActive = namyBtn && namyBtn.getAttribute('aria-selected') === 'true';

    if (!hasNamy && isNamyActive){
      if (hasKodik && selectTabByButtonId('tab-btn-kodik')) {
        // ок
      } else if (hasMiyagi && selectTabByButtonId('tab-btn-miyagi')) {
        // ок
      } else {
        // ничего нет
        setBtnEnabled('tab-btn-namy',   false);
        setBtnEnabled('tab-btn-kodik',  false);
        setBtnEnabled('tab-btn-miyagi', false);
        showNoPlayersNotice();
      }
    } else if (!hasNamy && !hasKodik && !hasMiyagi){
      // изначально тоже ничего нет
      setBtnEnabled('tab-btn-namy',   false);
      setBtnEnabled('tab-btn-kodik',  false);
      setBtnEnabled('tab-btn-miyagi', false);
      showNoPlayersNotice();
    }
  });
</script>

</body>
</html>
