<?php
declare(strict_types=1);
/**
 * Строителна Компания БГ — админ панел за "Директни продажби".
 * Единствената динамична част на сайта. Управлява data/listings.json.
 * Защита: PHP сесия + bcrypt парола (хешът е в config.php, извън Git).
 */

// ---- Secure session ----
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$CONFIG = __DIR__ . '/config.php';
$DATA   = __DIR__ . '/../data/listings.json';

$TYPES    = ['jil' => 'Жилищен', 'biznes' => 'Бизнес'];
$STATUSES = ['available' => 'Свободен', 'in_progress' => 'В строеж', 'sold' => 'Продаден'];

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function load_listings(string $path): array {
    if (!is_file($path)) return [];
    $raw = file_get_contents($path);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return (is_array($data) && isset($data['listings']) && is_array($data['listings'])) ? $data['listings'] : [];
}

function save_listings(string $path, array $listings): bool {
    $json = json_encode(['listings' => array_values($listings)],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    return file_put_contents($path, $json . "\n", LOCK_EX) !== false;
}

// ---- Config present? ----
$configOk = is_file($CONFIG);
if ($configOk) { require $CONFIG; }
$configOk = $configOk && defined('ADMIN_USER') && defined('ADMIN_PASS_HASH');

// ---- CSRF token ----
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

$flash = '';
$flashOk = true;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$authed = !empty($_SESSION['auth']);

// ---- Handle POST actions ----
if ($method === 'POST' && $configOk) {
    if (!csrf_ok()) {
        $flash = 'Невалиден токен (CSRF). Опитайте отново.'; $flashOk = false;
    } elseif ($action === 'login') {
        $u = (string)($_POST['username'] ?? '');
        $p = (string)($_POST['password'] ?? '');
        $hash = ADMIN_PASS_HASH;
        if (hash_equals(ADMIN_USER, $u) && is_string($hash) && password_verify($p, $hash)) {
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        usleep(700000);
        $flash = 'Грешно потребителско име или парола.'; $flashOk = false;
    } elseif ($authed && $action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($authed && $action === 'save') {
        $id     = trim((string)($_POST['id'] ?? ''));
        $title  = trim((string)($_POST['title'] ?? ''));
        $type   = (string)($_POST['type'] ?? 'jil');
        $status = (string)($_POST['status'] ?? 'available');
        if ($title === '') {
            $flash = 'Заглавието е задължително.'; $flashOk = false;
        } elseif (!isset($TYPES[$type]) || !isset($STATUSES[$status])) {
            $flash = 'Невалиден тип или статус.'; $flashOk = false;
        } else {
            $item = [
                'id'          => $id !== '' ? $id : ('obj-' . bin2hex(random_bytes(4))),
                'title'       => mb_substr($title, 0, 160),
                'type'        => $type,
                'status'      => $status,
                'location'    => mb_substr(trim((string)($_POST['location'] ?? '')), 0, 160),
                'area'        => mb_substr(trim((string)($_POST['area'] ?? '')), 0, 160),
                'price'       => mb_substr(trim((string)($_POST['price'] ?? '')), 0, 120),
                'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 1200),
                'image'       => mb_substr(trim((string)($_POST['image'] ?? '')), 0, 300),
            ];
            $listings = load_listings($DATA);
            $found = false;
            foreach ($listings as $k => $l) {
                if (($l['id'] ?? '') === $item['id']) { $listings[$k] = $item; $found = true; break; }
            }
            if (!$found) { $listings[] = $item; }
            if (save_listings($DATA, $listings)) {
                $flash = $found ? 'Обявата е обновена.' : 'Обявата е добавена.';
            } else {
                $flash = 'Неуспешен запис. Проверете правата за писане на data/listings.json.'; $flashOk = false;
            }
        }
    } elseif ($authed && $action === 'delete') {
        $id = (string)($_POST['id'] ?? '');
        $listings = array_values(array_filter(load_listings($DATA), fn($l) => ($l['id'] ?? '') !== $id));
        $flash = save_listings($DATA, $listings) ? 'Обявата е изтрита.' : 'Неуспешно изтриване.';
        $flashOk = strpos($flash, 'Неуспешно') === false;
    }
    $authed = !empty($_SESSION['auth']);
}

$listings = $authed ? load_listings($DATA) : [];
$edit = null;
if ($authed && ($_GET['edit'] ?? '') !== '') {
    foreach ($listings as $l) { if (($l['id'] ?? '') === $_GET['edit']) { $edit = $l; break; } }
}
$csrf = $_SESSION['csrf'];
?><!doctype html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Админ панел — Директни продажби</title>
<style>
  :root{ --bg:#F7F4EE; --surface:#FBF9F5; --text:#26231E; --muted:#6A6056; --accent:#BB5428; --accent-h:#A3461F; --border:#E0D9CC; --panel:#2E2A24; }
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;line-height:1.5}
  .bar{background:var(--panel);color:#F3EEE4;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;gap:12px}
  .bar h1{font-size:17px;margin:0} .bar form{margin:0}
  .wrap{max-width:960px;margin:0 auto;padding:24px 18px 60px}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:22px;margin-bottom:20px}
  h2{font-size:20px;margin:0 0 14px} label{display:block;font-weight:600;font-size:13.5px;margin:0 0 5px}
  input,select,textarea{width:100%;font:inherit;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;background:#fff;color:var(--text)}
  textarea{min-height:90px;resize:vertical}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:14px} @media(max-width:620px){.row{grid-template-columns:1fr}}
  .field{margin-bottom:14px}
  .btn{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:8px;padding:10px 16px;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none}
  .btn-primary{background:var(--accent);color:#fff} .btn-primary:hover{background:var(--accent-h)}
  .btn-ghost{background:transparent;border:1.5px solid var(--border);color:var(--text)}
  .btn-danger{background:transparent;border:1.5px solid #c0553a;color:#9a3a1a}
  .flash{padding:11px 15px;border-radius:8px;margin-bottom:18px;font-weight:600;font-size:14px}
  .flash.ok{background:#e4efe6;color:#2f6b4d} .flash.err{background:#f6e0d6;color:#9a3a1a}
  table{width:100%;border-collapse:collapse;font-size:14px} th,td{text-align:left;padding:10px;border-bottom:1px solid var(--border);vertical-align:top}
  th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
  .tagx{display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:#eee3d6;color:var(--accent-h)}
  .muted{color:var(--muted)} .login{max-width:380px;margin:60px auto}
  code{background:#efe9df;padding:2px 6px;border-radius:5px;font-size:13px}
  .actions{display:flex;gap:8px;flex-wrap:wrap}
</style>
</head>
<body>

<?php if (!$configOk): ?>
  <div class="wrap">
    <div class="card">
      <h2>Настройка на панела</h2>
      <p>Липсва <code>config.php</code>. За да активирате панела на сървъра:</p>
      <ol>
        <li>Копирайте <code>config.sample.php</code> като <code>config.php</code>.</li>
        <li>Задайте потребителско име в <code>ADMIN_USER</code>.</li>
        <li>Отворете <code>tools/make-hash.php?p=вашата-парола</code>, копирайте хеша в <code>ADMIN_PASS_HASH</code>.</li>
        <li>Изтрийте <code>tools/make-hash.php</code>.</li>
      </ol>
      <p class="muted">Вижте DEPLOYMENT.md за пълните стъпки.</p>
    </div>
  </div>

<?php elseif (!$authed): ?>
  <div class="wrap">
    <div class="card login">
      <h2>Вход</h2>
      <?php if ($flash): ?><div class="flash <?= $flashOk ? 'ok' : 'err' ?>"><?= e($flash) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="field"><label for="u">Потребител</label><input id="u" name="username" required autofocus></div>
        <div class="field"><label for="p">Парола</label><input id="p" type="password" name="password" required></div>
        <button class="btn btn-primary" type="submit">Вход</button>
      </form>
    </div>
  </div>

<?php else: ?>
  <div class="bar">
    <h1>Директни продажби — управление</h1>
    <form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="btn btn-ghost" style="color:#F3EEE4;border-color:rgba(243,238,228,.4)">Изход</button></form>
  </div>
  <div class="wrap">
    <?php if ($flash): ?><div class="flash <?= $flashOk ? 'ok' : 'err' ?>"><?= e($flash) ?></div><?php endif; ?>

    <div class="card">
      <h2><?= $edit ? 'Редакция на обява' : 'Нова обява' ?></h2>
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
        <div class="field"><label>Заглавие *</label><input name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
        <div class="row">
          <div class="field"><label>Тип</label><select name="type">
            <?php foreach ($TYPES as $k => $v): ?><option value="<?= e($k) ?>" <?= (($edit['type'] ?? '') === $k) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select></div>
          <div class="field"><label>Статус</label><select name="status">
            <?php foreach ($STATUSES as $k => $v): ?><option value="<?= e($k) ?>" <?= (($edit['status'] ?? '') === $k) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select></div>
        </div>
        <div class="row">
          <div class="field"><label>Локация</label><input name="location" value="<?= e($edit['location'] ?? '') ?>"></div>
          <div class="field"><label>Площ / детайл</label><input name="area" value="<?= e($edit['area'] ?? '') ?>"></div>
        </div>
        <div class="row">
          <div class="field"><label>Цена</label><input name="price" placeholder="при запитване" value="<?= e($edit['price'] ?? '') ?>"></div>
          <div class="field"><label>Снимка (път, по избор)</label><input name="image" placeholder="assets/img/listings/..." value="<?= e($edit['image'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>Описание</label><textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
        <div class="actions">
          <button class="btn btn-primary" type="submit"><?= $edit ? 'Запази промените' : 'Добави обява' ?></button>
          <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e($_SERVER['PHP_SELF']) ?>">Отказ</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card">
      <h2>Обяви (<?= count($listings) ?>)</h2>
      <?php if (!$listings): ?>
        <p class="muted">Все още няма обяви.</p>
      <?php else: ?>
        <table>
          <thead><tr><th>Заглавие</th><th>Тип</th><th>Статус</th><th>Локация</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($listings as $l): ?>
            <tr>
              <td><strong><?= e($l['title'] ?? '') ?></strong><?php if (!empty($l['price'])): ?><br><span class="muted"><?= e($l['price']) ?></span><?php endif; ?></td>
              <td><span class="tagx"><?= e($TYPES[$l['type'] ?? ''] ?? ($l['type'] ?? '')) ?></span></td>
              <td><?= e($STATUSES[$l['status'] ?? ''] ?? ($l['status'] ?? '')) ?></td>
              <td class="muted"><?= e($l['location'] ?? '') ?></td>
              <td class="actions">
                <a class="btn btn-ghost" href="?edit=<?= e(urlencode($l['id'] ?? '')) ?>">Редакция</a>
                <form method="post" onsubmit="return confirm('Да изтрия ли тази обява?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= e($l['id'] ?? '') ?>">
                  <button class="btn btn-danger" type="submit">Изтрий</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <p class="muted" style="margin-top:14px;font-size:13px;">Промените се записват в <code>data/listings.json</code> и се показват на страница „Директни продажби“.</p>
    </div>
  </div>
<?php endif; ?>

</body>
</html>
