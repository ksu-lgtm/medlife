# МедЛайф — веб-приложение медицинского центра (PHP + MySQL)

## Запуск в Open Server
1. Скопируйте папку `medlife` в `OpenServer/domains/` (получится `domains/medlife`). Запустите Open Server.
2. Откройте phpMyAdmin → вкладка «Импорт» → выберите `database/medlife_db.sql` → «Вперёд». База `medlife` создастся сама.
3. Проверьте `config/database.php`: по умолчанию `localhost`, пользователь `root`, пароль пустой.
4. Откройте в браузере `http://medlife/`. В настройках модуля PHP должны быть включены `mysqli` и `openssl`.

## Тестовые учётные записи
| Роль | Email | Пароль |
|---|---|---|
| Администратор | admin@medlife.ru | admin123 |
| Пациент | ivanov@example.com | user123 |
| Пациент | petrova@example.com | user123 |

## Структура
- `index.php, doctors.php, stationary.php, contacts.php` — публичные страницы
- `login.php, register.php, logout.php, book.php` — вход, регистрация, запись к врачу
- `cabinet.php` — личный кабинет (профиль, записи, госпитализации, история болезни)
- `admin.php, admin_actions.php` — админ-панель (госпитализации, записи, врачи, палаты, отчёты)
- `config/database.php` — подключение к БД; `includes/` — общие функции, шапка/подвал, шифрование (`crypto.php`)
- `assets/` — стили, скрипты (Chart.js лежит локально), изображения; `database/medlife_db.sql` — БД

## Безопасность
Пароли — `password_hash`; ФИО, телефон, жалобы и медицинские записи хранятся зашифрованными (AES-256-CBC); все запросы к БД — подготовленные (`mysqli_prepare`); вывод через `htmlspecialchars`; формы защищены CSRF-токеном; доступ к админ-панели проверяется по роли в сессии.
