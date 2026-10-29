<?php
// Окно «Запись к врачу». Врач подставляется скриптом (data-doctor-id / data-doctor-name на кнопке).
$bp = current_patient();
$bu = current_user();
$bname = $bp ? patient_name($bp) : '';
$bphone = $bp ? decrypt_field($bp['phone_enc']) : '';
?>
<div class="modal" id="bookModal" aria-hidden="true">
  <div class="modal-box">
    <button class="x" type="button" data-close>&times;</button>
    <h2>Запись к врачу</h2>
    <form method="post" action="book.php">
      <?= csrf_field() ?>
      <input type="hidden" name="doctor_id" id="bookDoctorId">
      <div class="fg"><label>Врач</label><input type="text" id="bookDoctorName" readonly style="background:var(--bg)"></div>
      <div class="fg"><label>Ваше ФИО</label><input type="text" name="full_name" placeholder="Иванов Иван Иванович" required value="<?= h($bname) ?>"></div>
      <div class="fg"><label>Телефон</label><input type="tel" name="phone" placeholder="+7 (___) ___-__-__" required value="<?= h($bphone) ?>" data-phone></div>
      <div class="fg"><label>Email</label><input type="email" name="email" placeholder="name@example.com" required value="<?= h($bu['email'] ?? '') ?>"></div>
      <div class="fg"><label>Дата приёма</label><input type="date" name="date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>"></div>
      <div class="fg"><label>Жалобы</label><textarea name="complaints" placeholder="Кратко опишите жалобы" maxlength="1000"></textarea></div>
      <button class="btn btn-primary btn-block" type="submit">Отправить заявку</button>
      <?php if (!$bu): ?><p class="demo" style="margin-top:14px">Для отправки заявки потребуется войти или зарегистрироваться — запись появится в личном кабинете.</p><?php endif; ?>
    </form>
  </div>
</div>
