<?php
require_once __DIR__ . '/config/database.php';
$page_title = 'Главная'; $active = 'home';
$top = get_doctors('', '', [], 3);
include 'includes/header.php';
?>
<main>
<section class="hero">
  <div class="container">
    <div>
      <span class="pill"><?= icon('clock') ?> Приёмное отделение работает 24/7</span>
      <h1>Медицинский центр, где о вас заботятся круглосуточно</h1>
      <p>Записывайтесь к врачам, узнавайте о палатах стационара и отслеживайте статус заявок в личном кабинете — без звонков и очередей.</p>
      <div class="acts">
        <a href="doctors.php" class="btn btn-primary">Записаться к врачу</a>
        <a href="stationary.php" class="btn btn-outline">Стационар</a>
      </div>
    </div>
    <img src="assets/images/hero.jpg" alt="Холл медицинского центра МедЛайф">
  </div>
</section>

<section class="section white">
  <div class="container">
    <h2 class="sec-title">Всё для вашего здоровья в одном месте</h2>
    <p class="sec-sub">Удобные онлайн-сервисы медицинского центра</p>
    <div class="grid4">
      <div class="feat"><div class="ico"><?= icon('calendar') ?></div><h3>Онлайн-запись к врачу</h3><p>Выберите специалиста по стажу и специализации и отправьте заявку за минуту.</p></div>
      <div class="feat"><div class="ico"><?= icon('bed') ?></div><h3>Стационар</h3><p>Категории палат, их стоимость и количество свободных мест — всегда актуально.</p></div>
      <div class="feat"><div class="ico"><?= icon('user') ?></div><h3>Личный кабинет</h3><p>История записей и госпитализаций со статусами рассмотрения в одном месте.</p></div>
      <div class="feat"><div class="ico"><?= icon('clock') ?></div><h3>Работаем 24/7</h3><p>Приёмное отделение принимает пациентов круглосуточно и без выходных.</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2 class="sec-title">Наши врачи</h2>
    <p class="sec-sub">Опытные специалисты, готовые помочь вам</p>
    <div class="grid3">
      <?php foreach ($top as $d): include 'includes/doctor_card.php'; endforeach; ?>
    </div>
    <div class="center mt"><a href="doctors.php" class="btn btn-outline">Все врачи</a></div>
  </div>
</section>

<section class="cta">
  <div class="container">
    <div><h2>Нужна консультация?</h2><p>Позвоните нам круглосуточно: +7 (495) 123-45-68</p></div>
    <a href="doctors.php" class="btn btn-white">Записаться онлайн</a>
  </div>
</section>
</main>
<?php include 'includes/footer.php'; ?>
