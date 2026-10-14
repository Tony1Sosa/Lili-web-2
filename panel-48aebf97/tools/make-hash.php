<?php
/**
 * ВРЕМЕНЕН инструмент за генериране на хеш на паролата.
 *
 * Употреба: отворете в браузър
 *     https://ВАШИЯТ-ДОМЕЙН/panel-48aebf97/tools/make-hash.php?p=вашата-парола
 * Копирайте изведения хеш в config.php (константата ADMIN_PASS_HASH),
 * след което ИЗТРИЙТЕ този файл от сървъра.
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$p = isset($_GET['p']) ? (string) $_GET['p'] : '';
if ($p === '') {
    echo "Добавете ?p=вашата-парола към адреса, за да получите хеш.\n";
    echo "Пример: make-hash.php?p=МоятаСилнаПарола123!\n";
    exit;
}
echo password_hash($p, PASSWORD_DEFAULT) . "\n";
echo "\nПоставете горния ред в config.php като ADMIN_PASS_HASH, после изтрийте този файл.\n";
