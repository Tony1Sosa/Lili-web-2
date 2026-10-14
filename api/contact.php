<?php
declare(strict_types=1);
/**
 * Contact / offer form handler for Строителна Компания БГ.
 * Static site + this single PHP endpoint. Works on PHP hosts (SuperHosting.bg).
 * NOTE: uses PHP mail(). For reliable delivery, configure SMTP on the host
 * (see DEPLOYMENT.md) — mail() can land in spam depending on server setup.
 */

$RECIPIENT = 'stroitelna.compania.bg@gmail.com';
$SITE_NAME = 'Строителна Компания БГ';
// Fixed sender domain — do NOT derive from the client Host header (spoofable).
// Update to the final production domain so it matches the host's SPF/DKIM records.
$FROM_ADDR = 'no-reply@stroitelna-compania-bg.com';

function is_ajax(): bool {
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $acc = $_SERVER['HTTP_ACCEPT'] ?? '';
    return (strtolower($xrw) === 'fetch') || (strpos($acc, 'application/json') !== false);
}

function respond(bool $ok, string $msg, int $code = 200): void {
    http_response_code($code);
    if (is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'error' => $ok ? null : $msg], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $t = $ok ? 'Благодарим!' : 'Възникна грешка';
        echo '<!doctype html><html lang="bg"><meta charset="utf-8"><title>' . $t . '</title>'
           . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#26231E;">'
           . '<h1>' . $t . '</h1><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
           . '<p><a href="../kontakti.html">← Назад към контакти</a></p></body></html>';
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(false, 'Невалиден метод на заявката.', 405);
}

// Honeypot — pretend success so bots don't retry.
if (!empty($_POST['website'] ?? '')) {
    respond(true, 'OK');
}

$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$phone   = trim((string)($_POST['phone'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? 'Запитване'));
$message = trim((string)($_POST['message'] ?? ''));
$consent = isset($_POST['consent']);

if ($name === '' || $email === '' || $message === '') {
    respond(false, 'Моля, попълнете име, имейл и съобщение.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Моля, въведете валиден имейл адрес.', 422);
}
if (!$consent) {
    respond(false, 'Необходимо е съгласие за обработка на данните.', 422);
}
// Guard against header injection via submitted values.
if (preg_match('/[\r\n]/', $name . $email . $subject . $phone)) {
    respond(false, 'Невалидни данни във формата.', 422);
}
if (mb_strlen($message) > 5000) {
    respond(false, 'Съобщението е твърде дълго.', 422);
}

$mailSubject = '[Запитване] ' . mb_substr($subject, 0, 120);
$body  = "Ново запитване от сайта на {$SITE_NAME}\n\n";
$body .= "Име: {$name}\n";
$body .= "Имейл: {$email}\n";
$body .= 'Телефон: ' . ($phone !== '' ? $phone : '—') . "\n";
$body .= "Тип обект: {$subject}\n\n";
$body .= "Съобщение:\n{$message}\n";

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= 'From: ' . $SITE_NAME . ' <' . $FROM_ADDR . ">\r\n";
$headers .= 'Reply-To: ' . $email . "\r\n";

$encodedSubject = '=?UTF-8?B?' . base64_encode($mailSubject) . '?=';
$sent = @mail($RECIPIENT, $encodedSubject, $body, $headers);

if ($sent) {
    respond(true, 'Съобщението е изпратено успешно.');
}
respond(false, 'Съобщението не можа да бъде изпратено. Моля, пишете ни на ' . $RECIPIENT . '.', 500);
