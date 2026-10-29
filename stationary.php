<?php
require_once __DIR__ . '/config/database.php';
$page_title = 'Стационар'; $active = 'stationary';
$cats = room_stats_by_category();
include 'includes/header.php';
?>
<main>
<section class="banner"><div class="container"><h1>Стационар</h1><p>Категории палат и их стоимость. Госпитализацию оформляет администратор — после приёма врача или по прямому обращению.</p></div></section>
<section class="section" style="padding:56px 0 72px"><div class="container">
  <h2 class="h2">Категории палат</h2>
  <div class="grid3">
    <?php foreach ($cats as $c): ?>
    <div class="room">
      <img src="<?= h($c['photo']) ?>" alt="Палата «<?= h($c['name']) ?>»">
      <h3><?= h($c['name']) ?></h3>
      <p class="desc"><?= h($c['description']) ?></p>
      <div class="price"><?= money($c['price']) ?></div>
      <div class="per">в сутки · свободно <?= (int)$c['free'] ?> из <?= (int)$c['total'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <h2 class="h2" style="margin-top:56px">Как попасть в стационар</h2>
  <div class="how">
    <div class="step"><div class="n">1</div><h4>Запишитесь на приём</h4><p>Выберите врача и отправьте заявку — статус будет виден в личном кабинете.</p></div>
    <div class="step"><div class="n">2</div><h4>Пройдите осмотр</h4><p>Если врач рекомендует госпитализацию, администратор свяжется с вами по телефону.</p></div>
    <div class="step"><div class="n">3</div><h4>Администратор оформит госпитализацию</h4><p>Подберёт палату, укажет даты и рассчитает стоимость. Информация появится в кабинете.</p></div>
  </div>
</div></section>
</main>
<?php include 'includes/footer.php'; ?>
