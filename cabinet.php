<?php
require_once __DIR__ . '/config/database.php';
require_login();
if (is_admin()) redirect('admin.php');
$page_title = 'Личный кабинет'; $active = '';
$u = current_user();
$p = current_patient();
if (!$p) { flash('Профиль пациента не найден', 'err'); redirect('index.php'); }
$name = patient_name($p);
$tab = $_GET['tab'] ?? 'appointments';
if (!in_array($tab, ['profile', 'appointments', 'hospitalizations', 'history'])) $tab = 'appointments';

// Отмена своей заявки (только пока она на рассмотрении)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_appt') {
    csrf_check();
    db_exec("DELETE FROM appointments WHERE id=? AND patient_id=? AND status='pending'", 'ii', [(int)$_POST['id'], $p['id']]);
    flash('Заявка отменена');
    redirect('cabinet.php?tab=appointments');
}
include 'includes/header.php';
?>
<main>
<section class="banner"><div class="container"><div class="who">
  <?= icon('user') ?>
  <div><h1>Личный кабинет</h1><p><?= h($name) ?></p></div>
</div></div></section>
<div class="container">
  <div class="tabs">
    <a class="tab <?= $tab=='profile'?'active':'' ?>" href="?tab=profile">Профиль</a>
    <a class="tab <?= $tab=='appointments'?'active':'' ?>" href="?tab=appointments">Мои записи</a>
    <a class="tab <?= $tab=='hospitalizations'?'active':'' ?>" href="?tab=hospitalizations">Госпитализации</a>
    <a class="tab <?= $tab=='history'?'active':'' ?>" href="?tab=history">История болезни</a>
  </div>

<?php if ($tab == 'profile'): ?>
  <div class="panel"><div class="panel-h"><h2>Профиль</h2></div><div class="panel-b">
    <dl class="profile">
      <dt>ФИО</dt><dd><?= h($name) ?></dd>
      <dt>Телефон</dt><dd><?= h(decrypt_field($p['phone_enc'])) ?></dd>
      <dt>Email</dt><dd><?= h($u['email']) ?></dd>
      <dt>Номер карты</dt><dd><?= h($p['card_number']) ?></dd>
      <dt>Дата регистрации</dt><dd><?= fdate($p['created_at']) ?></dd>
    </dl>
  </div></div>

<?php elseif ($tab == 'appointments'):
  $rows = db_all('SELECT a.*, d.name doctor, s.name specialty FROM appointments a
     JOIN doctors d ON d.id=a.doctor_id JOIN specialties s ON s.id=d.specialty_id
     WHERE a.patient_id=? ORDER BY a.appointment_date DESC, a.id DESC', 'i', [$p['id']]); ?>
  <div class="panel"><div class="panel-h"><h2>Записи к врачу</h2><a href="doctors.php" class="btn btn-primary btn-sm">+ Новая запись</a></div><div class="panel-b">
    <?php if (!$rows): ?><div class="empty">У вас пока нет записей. <a href="doctors.php" style="color:var(--blue)">Выбрать врача</a></div><?php endif; ?>
    <?php foreach ($rows as $a): ?>
      <div class="list-i">
        <div><b><?= h($a['doctor']) ?></b><small><?= h($a['specialty']) ?> · <?= fdate($a['appointment_date']) ?></small></div>
        <div style="display:flex;gap:10px;align-items:center">
          <?= badge(appt_status($a['status'])) ?>
          <?php if ($a['status'] == 'pending'): ?>
            <form method="post" onsubmit="return confirm('Отменить заявку?')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_appt"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-danger btn-sm">Отменить</button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div></div>

<?php elseif ($tab == 'hospitalizations'):
  $rows = db_all('SELECT h.*, r.number, c.name cat FROM hospitalizations h
     JOIN rooms r ON r.id=h.room_id JOIN room_categories c ON c.id=r.category_id
     WHERE h.patient_id=? ORDER BY h.admission_date DESC', 'i', [$p['id']]); ?>
  <div class="panel"><div class="panel-h"><h2>Госпитализации</h2></div>
    <?php if (!$rows): ?><div class="panel-b"><div class="empty">Госпитализаций нет. Госпитализацию оформляет администратор по рекомендации врача.</div></div>
    <?php else: ?><div class="table-wrap"><table>
      <tr><th>Палата</th><th>Период</th><th>Стоимость</th><th>Статус</th></tr>
      <?php foreach ($rows as $h): ?>
      <tr><td>№ <?= h($h['number']) ?> <?= h($h['cat']) ?></td><td><?= fdate($h['admission_date']) ?> — <?= fdate($h['discharge_date']) ?></td><td><?= money($h['total_cost']) ?></td><td><?= badge(hosp_status($h['status'])) ?></td></tr>
      <?php endforeach; ?></table></div><?php endif; ?>
  </div>

<?php else:
  $rows = db_all('SELECT a.appointment_date, d.name doctor_name, s.name specialty, m.icd_code, i.title icd_title, m.diagnosis_note_enc, m.treatment_enc
     FROM medical_records m
     JOIN appointments a ON a.id=m.appointment_id
     JOIN doctors d ON d.id=a.doctor_id JOIN specialties s ON s.id=d.specialty_id
     JOIN icd10 i ON i.code=m.icd_code
     WHERE a.patient_id=? ORDER BY a.appointment_date DESC', 'i', [$p['id']]); ?>
  <div class="panel"><div class="panel-h"><h2>История болезни</h2></div><div class="panel-b">
    <?php if (!$rows): ?><div class="empty">Записей в истории болезни пока нет. Они появляются после приёма у врача.</div><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <div class="list-i"><div>
        <b><?= h($r['icd_code']) ?> — <?= h($r['icd_title']) ?></b>
        <small><?= fdate($r['appointment_date']) ?> · <?= h($r['doctor_name']) ?>, <?= h(mb_strtolower($r['specialty'])) ?></small>
        <?php if ($n = decrypt_field($r['diagnosis_note_enc'])): ?><div class="hist"><b style="font-size:13.5px;display:inline">Заключение:</b> <?= h($n) ?></div><?php endif; ?>
        <?php if ($t = decrypt_field($r['treatment_enc'])): ?><div class="hist"><b style="font-size:13.5px;display:inline">Назначения:</b> <?= h($t) ?></div><?php endif; ?>
      </div></div>
    <?php endforeach; ?>
  </div></div>
<?php endif; ?>
</div>
</main>
<?php include 'includes/footer.php'; ?>
