<?php
// includes/header.php — шапка публичной части. Перед подключением задайте $page_title и $active.
$active = $active ?? '';
$page_title = $page_title ?? 'МедЛайф';
$u = current_user();
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?> — МедЛайф</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap&subset=cyrillic" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="header">
  <div class="container">
    <a href="index.php" class="logo"><?= icon('heart') ?><span>Мед<b>Лайф</b></span></a>
    <nav class="nav" id="nav">
      <a href="index.php" class="<?= $active=='home'?'active':'' ?>">Главная</a>
      <a href="doctors.php" class="<?= $active=='doctors'?'active':'' ?>">Врачи</a>
      <a href="stationary.php" class="<?= $active=='stationary'?'active':'' ?>">Стационар</a>
      <a href="contacts.php" class="<?= $active=='contacts'?'active':'' ?>">Контакты</a>
    </nav>
    <div class="hright">
      <?php if ($u): ?>
        <a href="<?= $u['role']=='admin' ? 'admin.php' : 'cabinet.php' ?>" class="btn btn-green"><?= $u['role']=='admin' ? 'Админ-панель' : 'Личный кабинет' ?></a>
        <a href="logout.php" class="btn btn-ghost">Выйти</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-ghost">Войти</a>
        <a href="register.php" class="btn btn-green">Регистрация</a>
      <?php endif; ?>
      <button class="burger" id="burger" aria-label="Меню">☰</button>
    </div>
  </div>
</header>
<?php if ($f = flash()): ?>
<div class="container"><div class="flash <?= $f['t'] ?>"><?= h($f['m']) ?></div></div>
<?php endif; ?>
