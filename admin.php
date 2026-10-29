<?php
require_once __DIR__ . '/config/database.php';
require_admin();
$page_title = 'Админ-панель';
$tab = $_GET['tab'] ?? 'hospitalizations';
if (!in_array($tab, ['hospitalizations', 'appointments', 'doctors', 'rooms', 'reports'])) $tab = 'hospitalizations';
$f = flash();
function tabs($tab) {
    $t = ['hospitalizations' => 'Госпитализации', 'appointments' => 'Записи к врачам', 'doctors' => 'Врачи', 'rooms' => 'Палаты', 'reports' => 'Отчёты'];
    echo '<div class="tabs">';
    foreach ($t as $k => $v) echo '<a class="tab ' . ($tab == $k ? 'active' : '') . '" href="?tab=' . $k . '">' . $v . '</a>';
    echo '</div>';
}
function act_form($action, $id, $label, $cls, $tab, $confirm = '') {
    return '<form method="post" action="admin_actions.php"' . ($confirm ? ' onsubmit="return confirm(\'' . $confirm . '\')"' : '') . '>'
        . csrf_field() . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="tab" value="' . $tab . '"><button class="btn btn-sm ' . $cls . '">' . $label . '</button></form>';
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Админ-панель — МедЛайф</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="header"><div class="container">
  <div style="display:flex;align-items:center"><a href="admin.php" class="logo"><?= icon('heart') ?><span>Мед<b>Лайф</b></span></a><span class="tag-admin">Админ-панель</span></div>
  <div class="hright"><a href="index.php" class="btn btn-ghost">На сайт</a><a href="logout.php" class="btn btn-ghost">Выйти</a></div>
</div></header>
<main><div class="container">
<?php if ($f): ?><div class="flash <?= $f['t'] ?>"><?= h($f['m']) ?></div><?php endif; ?>
<?php tabs($tab); ?>

<?php if ($tab == 'hospitalizations'):
  $cnt = db_one("SELECT (SELECT COUNT(*) FROM hospitalizations) h, (SELECT COUNT(*) FROM appointments) a, (SELECT COUNT(*) FROM doctors WHERE is_active=1) d, (SELECT COUNT(*) FROM rooms WHERE status='free') r");
  $rows = db_all('SELECT h.*, p.full_name_enc, r.number, c.name cat FROM hospitalizations h
     JOIN patients p ON p.id=h.patient_id JOIN rooms r ON r.id=h.room_id JOIN room_categories c ON c.id=r.category_id
     ORDER BY FIELD(h.status,\'active\',\'planned\',\'completed\',\'cancelled\'), h.admission_date DESC');
  // данные для окна оформления
  $pats = [];
  foreach (db_all('SELECT id, full_name_enc FROM patients') as $p) $pats[$p['id']] = ['id' => (int)$p['id'], 'name' => decrypt_field($p['full_name_enc']), 'appts' => []];
  foreach (db_all("SELECT a.id, a.patient_id, a.appointment_date, s.name spec FROM appointments a
      JOIN doctors d ON d.id=a.doctor_id JOIN specialties s ON s.id=d.specialty_id
      WHERE a.status='completed' ORDER BY a.appointment_date DESC") as $a)
    if (isset($pats[$a['patient_id']])) $pats[$a['patient_id']]['appts'][] = ['id' => (int)$a['id'], 'label' => $a['spec'] . ' · ' . fdate($a['appointment_date'])];
  usort($pats, fn($x, $y) => strcmp($x['name'], $y['name']));
  $cats = db_all('SELECT * FROM room_categories ORDER BY price');
  $roomsJs = [];
  foreach (db_all('SELECT r.id, r.number, r.status, r.category_id FROM rooms r ORDER BY r.number') as $r) $roomsJs[$r['category_id']][] = ['id' => (int)$r['id'], 'number' => $r['number'], 'free' => $r['status'] == 'free'];
  $catsJs = []; foreach ($cats as $c) $catsJs[$c['id']] = (float)$c['price'];
?>
  <div class="stats">
    <div class="stat"><div class="ico"><?= icon('bed') ?></div><div><b><?= (int)$cnt['h'] ?></b><small>Госпитализации</small></div></div>
    <div class="stat"><div class="ico"><?= icon('calendar') ?></div><div><b><?= (int)$cnt['a'] ?></b><small>Записи к врачам</small></div></div>
    <div class="stat"><div class="ico"><?= icon('users') ?></div><div><b><?= (int)$cnt['d'] ?></b><small>Врачи</small></div></div>
    <div class="stat"><div class="ico"><?= icon('door') ?></div><div><b><?= (int)$cnt['r'] ?></b><small>Свободные палаты</small></div></div>
  </div>
  <div class="panel">
    <div class="panel-h"><h2>Госпитализации</h2><button class="btn btn-primary btn-sm" data-open="hospModal">+ Оформить госпитализацию</button></div>
    <div class="table-wrap"><table>
      <tr><th>Пациент</th><th>Палата</th><th>Период</th><th>Стоимость</th><th>Основание</th><th>Статус</th><th>Действия</th></tr>
      <?php foreach ($rows as $h): ?>
      <tr>
        <td><?= h(decrypt_field($h['full_name_enc'])) ?></td>
        <td>№ <?= h($h['number']) ?> <?= h($h['cat']) ?></td>
        <td><?= fdate($h['admission_date']) ?> — <?= fdate($h['discharge_date']) ?></td>
        <td><?= money($h['total_cost']) ?></td>
        <td><?= $h['appointment_id'] ? 'После приёма' : 'Прямая' ?></td>
        <td><?= badge(hosp_status($h['status'])) ?></td>
        <td><?php
          if ($h['status'] == 'planned') echo act_form('hosp_cancel', $h['id'], 'Отменить', 'btn-danger', $tab, 'Отменить госпитализацию?') . act_form('hosp_start', $h['id'], 'В стационар', 'btn-green', $tab);
          elseif ($h['status'] == 'active') echo act_form('hosp_finish', $h['id'], 'Завершить', 'btn-green', $tab, 'Завершить госпитализацию и освободить палату?');
        ?></td>
      </tr>
      <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">Госпитализаций пока нет</td></tr><?php endif; ?>
    </table></div>
  </div>

  <div class="modal" id="hospModal"><div class="modal-box wide">
    <form method="post" action="admin_actions.php" id="hospForm">
      <?= csrf_field() ?><input type="hidden" name="action" value="hosp_create"><input type="hidden" name="tab" value="hospitalizations">
      <div class="modal-h"><h2>Оформление госпитализации</h2></div>
      <div class="modal-b">
        <div class="fg"><label>Пациент</label>
          <select name="patient_id" id="hPatient" required><option value="">Выберите пациента</option>
            <?php foreach ($pats as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option><?php endforeach; ?></select></div>
        <div class="fg"><label>Основание</label>
          <div class="seg"><label><input type="radio" name="basis" value="after" checked><span>После приёма</span></label><label><input type="radio" name="basis" value="direct"><span>Прямая</span></label></div></div>
        <div class="fg" id="hApptWrap"><label>Приём</label><select name="appointment_id" id="hAppt"></select><small id="hApptHint"></small></div>
        <div class="fg"><label>Категория</label>
          <select id="hCat" required><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?> — <?= money($c['price']) ?>/сут</option><?php endforeach; ?></select></div>
        <div class="fg"><label>Палата</label><select name="room_id" id="hRoom" required></select></div>
        <div class="fg"><label>Дата поступления</label><input type="date" name="admission_date" id="hD1" required value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></div>
        <div class="fg"><label>Дата выписки</label><input type="date" name="discharge_date" id="hD2" required value="<?= date('Y-m-d', strtotime('+5 day')) ?>"></div>
      </div>
      <div class="modal-f"><div class="total" id="hTotal">Итого: —</div>
        <div style="display:flex;gap:12px"><button type="button" class="btn btn-sm" style="border-color:var(--line);background:#fff;padding:11px 22px;font-size:15px" data-close>Отмена</button><button class="btn btn-primary" style="padding:11px 24px">Оформить</button></div></div>
    </form>
  </div></div>
  <script>
  (function(){
    var P=<?= json_encode(array_values($pats), JSON_UNESCAPED_UNICODE) ?>, R=<?= json_encode($roomsJs) ?>, PR=<?= json_encode($catsJs) ?>;
    var $=function(i){return document.getElementById(i)};
    var pat=$('hPatient'),appt=$('hAppt'),wrap=$('hApptWrap'),cat=$('hCat'),room=$('hRoom'),d1=$('hD1'),d2=$('hD2'),tot=$('hTotal'),hint=$('hApptHint');
    function basis(){return document.querySelector('input[name=basis]:checked').value}
    function fillAppt(){
      var p=P.filter(function(x){return x.id==pat.value})[0];
      appt.innerHTML='';
      if(basis()=='direct'){wrap.style.display='none';appt.disabled=true;return}
      wrap.style.display='';appt.disabled=false;
      var list=p?p.appts:[];
      list.forEach(function(a){var o=document.createElement('option');o.value=a.id;o.textContent=a.label;appt.appendChild(o)});
      if(!list.length){var o=document.createElement('option');o.value='';o.textContent=p?'Нет состоявшихся приёмов':'Сначала выберите пациента';appt.appendChild(o)}
      hint.textContent=list.length?(list.length+' '+(list.length==1?'запись':'записи')):'';
    }
    function fillRooms(){
      room.innerHTML='';
      (R[cat.value]||[]).forEach(function(r){var o=document.createElement('option');o.value=r.id;o.textContent='Палата № '+r.number+(r.free?'':' (занята сейчас)');room.appendChild(o)});
      var f=Array.prototype.filter.call(room.options,function(o){return o.textContent.indexOf('занята')<0})[0]; if(f)room.value=f.value;
    }
    function calc(){
      var n=Math.round((new Date(d2.value)-new Date(d1.value))/86400000), pr=PR[cat.value]||0;
      tot.textContent=(n>0)?('Итого: '+(n*pr+500).toLocaleString('ru-RU')+' ₽ ('+n+' суток × '+pr.toLocaleString('ru-RU')+' ₽ + 500 ₽ за оформление)'):'Итого: проверьте даты';
    }
    pat.addEventListener('change',fillAppt);
    document.querySelectorAll('input[name=basis]').forEach(function(r){r.addEventListener('change',fillAppt)});
    cat.addEventListener('change',function(){fillRooms();calc()});
    [d1,d2].forEach(function(x){x.addEventListener('input',calc)});
    fillAppt();fillRooms();calc();
    // открытие из вкладки «Записи» с предзаполнением
    window.openHosp=function(pid,aid){pat.value=pid;document.querySelector('input[name=basis][value=after]').checked=true;fillAppt();appt.value=aid;$('hospModal').classList.add('open')};
    if(location.hash.indexOf('#hosp=')==0){var q=location.hash.slice(6).split('-');window.openHosp(q[0],q[1])}
  })();
  </script>

<?php elseif ($tab == 'appointments'):
  $rows = db_all('SELECT a.*, p.full_name_enc, p.id pid, d.name doctor, s.name spec FROM appointments a
     JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id JOIN specialties s ON s.id=d.specialty_id
     ORDER BY FIELD(a.status,\'pending\',\'confirmed\',\'completed\',\'hospitalized\',\'rejected\'), a.appointment_date DESC');
  $icd = db_all('SELECT * FROM icd10 ORDER BY code');
  function short_doc($n) { $p = preg_split('/\s+/u', $n); return $p[0] . ' ' . mb_substr($p[1] ?? '', 0, 1) . '. ' . mb_substr($p[2] ?? '', 0, 1) . '.'; }
?>
  <div class="panel">
    <div class="panel-h"><h2>Записи к врачам</h2></div>
    <div class="table-wrap"><table>
      <tr><th>Пациент</th><th>Врач</th><th>Дата</th><th>Жалобы</th><th>Статус</th><th>Действия</th></tr>
      <?php foreach ($rows as $a): $nm = decrypt_field($a['full_name_enc']); ?>
      <tr>
        <td><?= h($nm) ?></td>
        <td><?= h(short_doc($a['doctor'])) ?> — <?= h(mb_strtolower($a['spec'])) ?></td>
        <td><?= fdate($a['appointment_date']) ?></td>
        <td style="max-width:240px;color:var(--muted);font-size:14px"><?= h(decrypt_field($a['complaints_enc'])) ?></td>
        <td><?= badge(appt_status($a['status'])) ?></td>
        <td><?php
          if ($a['status'] == 'pending') echo act_form('appt_confirm', $a['id'], 'Подтвердить', 'btn-green', $tab) . act_form('appt_reject', $a['id'], 'Отклонить', 'btn-danger', $tab, 'Отклонить запись?');
          elseif ($a['status'] == 'confirmed') echo '<button class="btn btn-sm btn-primary" data-open="resModal" data-appt="' . $a['id'] . '" data-who="' . h($nm) . '">Результат приёма</button> ' . act_form('appt_reject', $a['id'], 'Отклонить', 'btn-danger', $tab, 'Отклонить запись?');
          elseif ($a['status'] == 'completed') echo '<a class="btn btn-sm btn-green" href="?tab=hospitalizations#hosp=' . $a['pid'] . '-' . $a['id'] . '">Госпитализировать</a>';
        ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
  </div>
  <div class="modal" id="resModal"><div class="modal-box">
    <button class="x" type="button" data-close>&times;</button>
    <h2>Результат приёма</h2>
    <form method="post" action="admin_actions.php"><?= csrf_field() ?>
      <input type="hidden" name="action" value="appt_result"><input type="hidden" name="tab" value="appointments"><input type="hidden" name="id" id="resId">
      <div class="fg"><label>Пациент</label><input id="resWho" readonly style="background:var(--bg)"></div>
      <div class="fg"><label>Диагноз (МКБ-10)</label><select name="icd_code" required><option value="">Выберите диагноз</option>
        <?php foreach ($icd as $i): ?><option value="<?= h($i['code']) ?>"><?= h($i['code']) ?> — <?= h($i['title']) ?></option><?php endforeach; ?></select></div>
      <div class="fg"><label>Заключение врача</label><textarea name="note" placeholder="Краткое заключение по результатам приёма"></textarea></div>
      <div class="fg"><label>Назначения</label><textarea name="treatment" placeholder="Лечение, рекомендации"></textarea></div>
      <button class="btn btn-primary btn-block">Сохранить результат</button>
      <p class="demo" style="margin-top:14px">Данные вносятся со слов врача или по бумажному заключению. Диагноз кодируется по МКБ-10, пояснения хранятся в зашифрованном виде. Пациент увидит запись в разделе «История болезни» личного кабинета.</p>
    </form>
  </div></div>
  <script>document.querySelectorAll('[data-appt]').forEach(function(b){b.addEventListener('click',function(){document.getElementById('resId').value=b.dataset.appt;document.getElementById('resWho').value=b.dataset.who})})</script>

<?php elseif ($tab == 'doctors'):
  $rows = db_all('SELECT d.*, s.name spec FROM doctors d JOIN specialties s ON s.id=d.specialty_id WHERE d.is_active=1 ORDER BY d.id');
  $specs = db_all('SELECT * FROM specialties ORDER BY name'); ?>
  <div class="panel">
    <div class="panel-h"><h2>Врачи</h2><button class="btn btn-primary btn-sm" data-open="docModal" data-new="1">+ Добавить врача</button></div>
    <div class="table-wrap"><table>
      <tr><th>ФИО</th><th>Специализация</th><th>Стаж</th><th>Рейтинг</th><th>Действия</th></tr>
      <?php foreach ($rows as $d): ?>
      <tr><td><?= h($d['name']) ?></td><td><?= h($d['spec']) ?></td><td><?= h(years_word($d['experience'])) ?></td><td><?= number_format($d['rating'], 1) ?></td>
        <td><button class="btn btn-sm btn-outline" data-open="docModal" data-doc='<?= h(json_encode(['id' => $d['id'], 'name' => $d['name'], 'sid' => $d['specialty_id'], 'exp' => $d['experience'], 'rating' => $d['rating']], JSON_UNESCAPED_UNICODE)) ?>'>Изменить</button>
        <?= act_form('doctor_delete', $d['id'], 'Удалить', 'btn-danger', $tab, 'Удалить врача с сайта?') ?></td></tr>
      <?php endforeach; ?>
    </table></div>
  </div>
  <div class="modal" id="docModal"><div class="modal-box">
    <button class="x" type="button" data-close>&times;</button>
    <h2 id="docTitle">Добавить врача</h2>
    <form method="post" action="admin_actions.php"><?= csrf_field() ?>
      <input type="hidden" name="action" value="doctor_save"><input type="hidden" name="tab" value="doctors"><input type="hidden" name="id" id="docId" value="0">
      <div class="fg"><label>ФИО</label><input name="name" id="docName" required placeholder="Иванова Елена Сергеевна"></div>
      <div class="fg"><label>Специализация</label><select name="specialty_id" id="docSpec" required><?php foreach ($specs as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="fg"><label>Стаж, лет</label><input type="number" name="experience" id="docExp" min="0" max="70" value="1" required></div>
      <div class="fg"><label>Рейтинг (0–5)</label><input type="number" name="rating" id="docRate" min="0" max="5" step="0.1" value="4.5" required></div>
      <button class="btn btn-primary btn-block">Сохранить</button>
    </form>
  </div></div>
  <script>document.querySelectorAll('[data-open=docModal]').forEach(function(b){b.addEventListener('click',function(){
    var d=b.dataset.doc?JSON.parse(b.dataset.doc):{id:0,name:'',sid:document.getElementById('docSpec').options[0].value,exp:1,rating:4.5};
    document.getElementById('docTitle').textContent=d.id?'Изменить врача':'Добавить врача';
    document.getElementById('docId').value=d.id;document.getElementById('docName').value=d.name;document.getElementById('docSpec').value=d.sid;
    document.getElementById('docExp').value=d.exp;document.getElementById('docRate').value=d.rating})})</script>

<?php elseif ($tab == 'rooms'):
  $st = room_stats();
  $rows = db_all('SELECT r.*, c.name cat, c.price FROM rooms r JOIN room_categories c ON c.id=r.category_id ORDER BY r.number'); ?>
  <div class="stat3">
    <div class="stat-card"><small>Всего палат</small><b><?= (int)$st['total'] ?></b></div>
    <div class="stat-card ok"><small>Свободно</small><b><?= (int)$st['free'] ?></b></div>
    <div class="stat-card bad"><small>Занято</small><b><?= (int)$st['occupied'] ?></b></div>
  </div>
  <div class="panel">
    <div class="panel-h"><h2>Палаты стационара</h2></div>
    <div class="table-wrap"><table>
      <tr><th>Номер</th><th>Категория</th><th>Цена за сутки</th><th>Статус</th><th>Действие</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr><td><?= h($r['number']) ?></td><td><?= h($r['cat']) ?></td><td><?= money($r['price']) ?></td>
        <td><?= $r['status'] == 'free' ? '<span class="badge badge-ok">Свободна</span>' : '<span class="badge badge-bad">Занята</span>' ?></td>
        <td><?= act_form('room_toggle', $r['id'], 'Сменить статус', 'btn-outline', $tab) ?></td></tr>
      <?php endforeach; ?>
    </table></div>
  </div>

<?php else:
  // «Прошли приём» = статусы completed и hospitalized
  $sum = db_one("SELECT
      (SELECT COUNT(*) FROM patients) AS patients_total,
      COUNT(DISTINCT CASE WHEN status IN ('completed','hospitalized') THEN patient_id END) AS patients_passed,
      COALESCE(SUM(status IN ('completed','hospitalized')), 0) AS visits_done,
      COALESCE(SUM(status = 'hospitalized'), 0) AS hospitalized_after_visit,
      ROUND(100 * COALESCE(SUM(status = 'hospitalized'), 0) / NULLIF(SUM(status IN ('completed','hospitalized')), 0), 1) AS hospitalized_percent
    FROM appointments");
  $bySpec = db_all("SELECT s.name AS specialty,
      COALESCE(SUM(a.status IN ('completed','hospitalized')), 0) AS visits_done,
      COUNT(DISTINCT CASE WHEN a.status IN ('completed','hospitalized') THEN a.patient_id END) AS patients_passed,
      COALESCE(SUM(a.status = 'hospitalized'), 0) AS hospitalized_after_visit
    FROM specialties s
    LEFT JOIN doctors d ON d.specialty_id = s.id
    LEFT JOIN appointments a ON a.doctor_id = d.id
    GROUP BY s.id, s.name ORDER BY s.name");
  $byDoc = db_all("SELECT d.name AS doctor_name, s.name AS specialty,
      COALESCE(SUM(a.status IN ('completed','hospitalized')), 0) AS visits_done,
      COUNT(DISTINCT CASE WHEN a.status IN ('completed','hospitalized') THEN a.patient_id END) AS patients_passed,
      COALESCE(SUM(a.status = 'hospitalized'), 0) AS hospitalized_after_visit
    FROM doctors d
    JOIN specialties s ON s.id = d.specialty_id
    LEFT JOIN appointments a ON a.doctor_id = d.id
    WHERE d.is_active = 1
    GROUP BY d.id, d.name, s.name ORDER BY visits_done DESC, d.name");
  $byMonth = db_all("SELECT DATE_FORMAT(appointment_date, '%Y-%m') AS period,
      SUM(status IN ('completed','hospitalized')) AS visits_done,
      SUM(status = 'hospitalized') AS hospitalized_after_visit
    FROM appointments WHERE status IN ('completed','hospitalized')
    GROUP BY period ORDER BY period");
?>
  <div class="stats">
    <div class="stat"><div class="ico"><?= icon('users') ?></div><div><b><?= (int)$sum['patients_total'] ?></b><small>Пациентов в базе</small></div></div>
    <div class="stat"><div class="ico"><?= icon('user') ?></div><div><b><?= (int)$sum['patients_passed'] ?></b><small>Прошли приём</small></div></div>
    <div class="stat"><div class="ico"><?= icon('calendar') ?></div><div><b><?= (int)$sum['visits_done'] ?></b><small>Состоявшихся приёмов</small></div></div>
    <div class="stat"><div class="ico"><?= icon('bed') ?></div><div><b><?= (int)$sum['hospitalized_after_visit'] ?></b><small>Госпитализированы после приёма<?= $sum['hospitalized_percent'] !== null ? ' (' . rtrim(rtrim($sum['hospitalized_percent'], '0'), '.') . '%)' : '' ?></small></div></div>
  </div>
  <div class="rgrid">
    <div class="chart-card"><h3>Приёмы и госпитализации по специализациям</h3><canvas id="chSpec"></canvas></div>
    <div class="chart-card"><h3>Доля госпитализированных после приёма</h3><canvas id="chShare"></canvas></div>
  </div>
  <div class="chart-card" style="margin-bottom:24px"><h3>Динамика по месяцам</h3><canvas id="chMonth"></canvas></div>
  <div class="panel">
    <div class="panel-h"><h2>Отчёт по врачам</h2></div>
    <div class="table-wrap"><table>
      <tr><th>Врач</th><th>Специализация</th><th>Приёмов состоялось</th><th>Пациентов</th><th>Госпитализировано</th></tr>
      <?php foreach ($byDoc as $r): ?>
      <tr><td><?= h($r['doctor_name']) ?></td><td><?= h($r['specialty']) ?></td><td><?= (int)$r['visits_done'] ?></td><td><?= (int)$r['patients_passed'] ?></td><td><?= (int)$r['hospitalized_after_visit'] ?></td></tr>
      <?php endforeach; ?>
    </table></div>
  </div>
  <script src="assets/js/chart.umd.js"></script>
  <script>
  var S=<?= json_encode($bySpec, JSON_UNESCAPED_UNICODE) ?>, M=<?= json_encode($byMonth, JSON_UNESCAPED_UNICODE) ?>;
  var mon=['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'];
  Chart.defaults.font.family="Inter,sans-serif";
  new Chart(document.getElementById('chSpec'),{type:'bar',data:{labels:S.map(function(x){return x.specialty}),datasets:[
    {label:'Приёмов состоялось',data:S.map(function(x){return +x.visits_done}),backgroundColor:'#0d6efd',borderRadius:6},
    {label:'Госпитализировано',data:S.map(function(x){return +x.hospitalized_after_visit}),backgroundColor:'#20c997',borderRadius:6}]},
    options:{scales:{y:{beginAtZero:true,ticks:{precision:0}}},plugins:{legend:{position:'bottom'}}}});
  var hs=<?= (int)$sum['hospitalized_after_visit'] ?>, vs=<?= (int)$sum['visits_done'] ?>;
  new Chart(document.getElementById('chShare'),{type:'doughnut',data:{labels:['Госпитализированы','Без госпитализации'],datasets:[{data:[hs,vs-hs],backgroundColor:['#20c997','#0d6efd'],borderWidth:0}]},options:{plugins:{legend:{position:'bottom'}},cutout:'62%'}});
  new Chart(document.getElementById('chMonth'),{type:'line',data:{labels:M.map(function(x){var p=x.period.split('-');return mon[+p[1]-1]+' '+p[0]}),datasets:[
    {label:'Приёмов',data:M.map(function(x){return +x.visits_done}),borderColor:'#0d6efd',backgroundColor:'rgba(13,110,253,.1)',fill:true,tension:.3},
    {label:'Госпитализировано',data:M.map(function(x){return +x.hospitalized_after_visit}),borderColor:'#20c997',tension:.3}]},
    options:{scales:{y:{beginAtZero:true,ticks:{precision:0}}},plugins:{legend:{position:'bottom'}}}});
  </script>
<?php endif; ?>
</div></main>
<script src="assets/js/app.js"></script>
</body>
</html>
