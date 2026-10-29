<div class="dcard">
  <div class="avatar"><?= h(initials($d['name'])) ?></div>
  <h3><?= h($d['name']) ?></h3>
  <div class="spec"><?= h($d['specialty']) ?></div>
  <div class="exp"><?= icon('briefcase') ?> Стаж <?= h(years_word($d['experience'])) ?></div>
  <div class="rate"><?= stars($d['rating']) ?></div>
  <a href="#" class="btn btn-primary btn-block" data-open="bookModal" data-doctor-id="<?= (int)$d['id'] ?>" data-doctor-name="<?= h($d['name']) ?>">Записаться</a>
</div>
