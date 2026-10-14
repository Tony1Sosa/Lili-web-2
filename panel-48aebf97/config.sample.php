<?php
/**
 * ПРИМЕРЕН конфигурационен файл за админ панела.
 *
 * НА СЪРВЪРА:
 *   1. Копирайте този файл като  config.php  (в същата папка).
 *   2. Задайте потребителско име в ADMIN_USER.
 *   3. Генерирайте хеш на паролата с tools/make-hash.php и го поставете в ADMIN_PASS_HASH.
 *   4. Изтрийте tools/make-hash.php след употреба.
 *
 * ВАЖНО: config.php НЕ се качва в Git (вижте .gitignore) — той съдържа хеша на паролата.
 * Никога не записвайте паролата в чист вид тук.
 */

define('ADMIN_USER', 'AdmiN');

// Хеш на паролата (bcrypt/argon). Заменете със стойността от tools/make-hash.php.
define('ADMIN_PASS_HASH', '$2y$10$ЗАМЕНЕТЕ.С.РЕАЛЕН.BCRYPT.ХЕШ.ОТ.MAKE-HASH.PHP.XXXXXXXXXXXXXXX');
