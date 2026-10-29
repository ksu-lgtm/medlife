<?php
require_once __DIR__ . '/config/database.php';
$page_title = 'Контакты'; $active = 'contacts';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $n = trim($_POST['name'] ?? ''); $m = trim($_POST['message'] ?? '');
    if ($n === '' || $m === '') flash('Заполните имя и сообщение', 'err');
    else flash('Спасибо! Ваше сообщение отправлено, мы свяжемся с вами.');
    redirect('contacts.php');
}
include 'includes/header.php';
?>
<main>
<section class="banner"><div class="container"><h1>Контакты</h1><p>Свяжитесь с нами любым удобным способом. Мы всегда готовы ответить на ваши вопросы.</p></div></section>
<section class="section white" style="padding:64px 0 80px"><div class="container">
  <div class="cgrid">
    <div class="ccard"><div class="ico"><?= icon('pin') ?></div><h3>Адрес</h3><p>г. Москва, ул. Строителей, д. 15, корп. 2</p><p>м. Проспект Вернадского</p></div>
    <div class="ccard"><div class="ico"><?= icon('phone') ?></div><h3>Телефон</h3><p>+7 (495) 123-45-67</p><p>+7 (495) 123-45-68 (круглосуточно)</p></div>
    <div class="ccard"><div class="ico"><?= icon('mail') ?></div><h3>Email</h3><p>info@medlife.ru</p><p>admin@medlife.ru</p></div>
    <div class="ccard"><div class="ico"><?= icon('clock') ?></div><h3>Режим работы</h3><p>Круглосуточно</p><p>Без выходных</p></div>
  </div>
  <h2 class="h2" style="margin:64px 0 24px">Как нас найти</h2>
  <div class="find">
    <div class="map"><?= icon('pin') ?><span>Яндекс.Карты — г. Москва, ул. Строителей, д. 15, корп. 2</span></div>
    <div class="card">
      <h3>Обратная связь</h3>
      <form method="post"><?= csrf_field() ?>
        <div class="fg"><label>Ваше имя</label><input name="name" placeholder="Ваше имя" required></div>
        <div class="fg"><label>Email</label><input type="email" name="email" placeholder="Ваш Email"></div>
        <div class="fg"><label>Телефон</label><input name="phone" placeholder="Ваш телефон" data-phone></div>
        <div class="fg"><label>Сообщение</label><textarea name="message" placeholder="Ваше сообщение" required></textarea></div>
        <button class="btn btn-primary btn-block" type="submit">Отправить сообщение</button>
      </form>
    </div>
  </div>
</div></section>
</main>
<?php include 'includes/footer.php'; ?>
