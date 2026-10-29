<?php
// admin_actions.php — обработчик действий администратора (только POST)
require_once __DIR__ . '/config/database.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin.php');
csrf_check();
global $link;

$action = $_POST['action'] ?? '';
$back = 'admin.php?tab=' . urlencode($_POST['tab'] ?? 'hospitalizations');
$id = (int)($_POST['id'] ?? 0);

function done($msg, $type = 'ok') { global $back; flash($msg, $type); redirect($back); }

switch ($action) {

// ---------- записи к врачам ----------
case 'appt_confirm':
    db_exec("UPDATE appointments SET status='confirmed' WHERE id=? AND status='pending'", 'i', [$id]);
    done('Запись подтверждена');
case 'appt_reject':
    db_exec("UPDATE appointments SET status='rejected' WHERE id=? AND status IN ('pending','confirmed')", 'i', [$id]);
    done('Запись отклонена');
case 'appt_result': // результат приёма: диагноз по МКБ-10 + статус «Приём состоялся»
    $a = db_one("SELECT id FROM appointments WHERE id=? AND status='confirmed'", 'i', [$id]);
    if (!$a) done('Результат можно внести только для подтверждённой записи', 'err');
    $code = $_POST['icd_code'] ?? '';
    if (!db_one('SELECT code FROM icd10 WHERE code=?', 's', [$code])) done('Выберите диагноз из справочника МКБ-10', 'err');
    mysqli_begin_transaction($link);
    $ok = db_exec('INSERT INTO medical_records (appointment_id,icd_code,diagnosis_note_enc,treatment_enc) VALUES (?,?,?,?)', 'isss',
        [$id, $code, encrypt_field(trim($_POST['note'] ?? '')), encrypt_field(trim($_POST['treatment'] ?? ''))]);
    $ok = $ok && db_exec("UPDATE appointments SET status='completed' WHERE id=?", 'i', [$id]);
    if ($ok) { mysqli_commit($link); done('Результат приёма сохранён. Запись в истории болезни пациента создана.'); }
    mysqli_rollback($link); done('Не удалось сохранить результат', 'err');

// ---------- госпитализации ----------
case 'hosp_create':
    $pid = (int)($_POST['patient_id'] ?? 0);
    $basis = ($_POST['basis'] ?? 'after') === 'direct' ? 'direct' : 'after';
    $rid = (int)($_POST['room_id'] ?? 0);
    $d1 = $_POST['admission_date'] ?? ''; $d2 = $_POST['discharge_date'] ?? '';
    $t1 = strtotime($d1); $t2 = strtotime($d2);
    if (!db_one('SELECT id FROM patients WHERE id=?', 'i', [$pid])) done('Выберите пациента', 'err');
    if (!$t1 || !$t2 || $t2 <= $t1) done('Дата выписки должна быть позже даты поступления', 'err');
    $room = db_one('SELECT r.id, c.price FROM rooms r JOIN room_categories c ON c.id=r.category_id WHERE r.id=?', 'i', [$rid]);
    if (!$room) done('Выберите палату', 'err');
    $busy = db_one("SELECT id FROM hospitalizations WHERE room_id=? AND status IN ('planned','active') AND admission_date < ? AND discharge_date > ?",
        'iss', [$rid, date('Y-m-d', $t2), date('Y-m-d', $t1)]);
    if ($busy) done('Палата занята на выбранные даты — выберите другую', 'err');
    $aid = null;
    if ($basis === 'after') {
        $aid = (int)($_POST['appointment_id'] ?? 0);
        $ap = db_one("SELECT id FROM appointments WHERE id=? AND patient_id=? AND status='completed'", 'ii', [$aid, $pid]);
        if (!$ap) done('Выберите состоявшийся приём этого пациента', 'err');
    }
    mysqli_begin_transaction($link);
    $ok = db_exec('INSERT INTO hospitalizations (patient_id,appointment_id,room_id,admission_date,discharge_date,daily_price,service_fee,status,created_by)
        VALUES (?,?,?,?,?,?,500,\'planned\',?)', 'iiissdi',
        [$pid, $aid, $rid, date('Y-m-d', $t1), date('Y-m-d', $t2), $room['price'], $_SESSION['user_id']]);
    if ($ok && $aid) $ok = db_exec("UPDATE appointments SET status='hospitalized' WHERE id=?", 'i', [$aid]);
    if ($ok) { mysqli_commit($link); done('Госпитализация оформлена'); }
    mysqli_rollback($link); done('Не удалось оформить госпитализацию', 'err');

case 'hosp_start': // запланирована -> в стационаре
    $h = db_one("SELECT h.*, r.status rstatus FROM hospitalizations h JOIN rooms r ON r.id=h.room_id WHERE h.id=? AND h.status='planned'", 'i', [$id]);
    if (!$h) done('Госпитализация не найдена', 'err');
    if ($h['rstatus'] !== 'free') done('Палата сейчас занята — дождитесь освобождения', 'err');
    mysqli_begin_transaction($link);
    $ok = db_exec("UPDATE hospitalizations SET status='active' WHERE id=?", 'i', [$id])
       && db_exec("UPDATE rooms SET status='occupied' WHERE id=?", 'i', [$h['room_id']]);
    if ($ok) { mysqli_commit($link); done('Пациент помещён в стационар, палата отмечена как занятая'); }
    mysqli_rollback($link); done('Ошибка', 'err');

case 'hosp_finish': // в стационаре -> завершена
    $h = db_one("SELECT * FROM hospitalizations WHERE id=? AND status='active'", 'i', [$id]);
    if (!$h) done('Госпитализация не найдена', 'err');
    mysqli_begin_transaction($link);
    $ok = db_exec("UPDATE hospitalizations SET status='completed' WHERE id=?", 'i', [$id])
       && db_exec("UPDATE rooms SET status='free' WHERE id=?", 'i', [$h['room_id']]);
    if ($ok) { mysqli_commit($link); done('Госпитализация завершена, палата освобождена'); }
    mysqli_rollback($link); done('Ошибка', 'err');

case 'hosp_cancel': // запланирована -> отменена
    $h = db_one("SELECT * FROM hospitalizations WHERE id=? AND status='planned'", 'i', [$id]);
    if (!$h) done('Отменить можно только запланированную госпитализацию', 'err');
    mysqli_begin_transaction($link);
    $ok = db_exec("UPDATE hospitalizations SET status='cancelled' WHERE id=?", 'i', [$id]);
    if ($ok && $h['appointment_id']) $ok = db_exec("UPDATE appointments SET status='completed' WHERE id=? AND status='hospitalized'", 'i', [$h['appointment_id']]);
    if ($ok) { mysqli_commit($link); done('Госпитализация отменена'); }
    mysqli_rollback($link); done('Ошибка', 'err');

// ---------- врачи ----------
case 'doctor_save':
    $name = trim($_POST['name'] ?? ''); $sid = (int)($_POST['specialty_id'] ?? 0);
    $exp = max(0, min(70, (int)($_POST['experience'] ?? 0)));
    $rate = max(0, min(5, round((float)str_replace(',', '.', $_POST['rating'] ?? '4.5'), 1)));
    if (mb_strlen($name) < 5) done('Укажите ФИО врача', 'err');
    if (!db_one('SELECT id FROM specialties WHERE id=?', 'i', [$sid])) done('Выберите специализацию', 'err');
    if ($id) { db_exec('UPDATE doctors SET name=?, specialty_id=?, experience=?, rating=? WHERE id=?', 'siidi', [$name, $sid, $exp, $rate, $id]); done('Данные врача обновлены'); }
    db_exec('INSERT INTO doctors (name,specialty_id,experience,rating) VALUES (?,?,?,?)', 'siid', [$name, $sid, $exp, $rate]);
    done('Врач добавлен');
case 'doctor_delete':
    $open = db_one("SELECT COUNT(*) n FROM appointments WHERE doctor_id=? AND status IN ('pending','confirmed')", 'i', [$id]);
    if ($open && $open['n'] > 0) done('У врача есть незавершённые записи — сначала обработайте их', 'err');
    db_exec('UPDATE doctors SET is_active=0 WHERE id=?', 'i', [$id]);
    done('Врач скрыт с сайта (история приёмов сохранена)');

// ---------- палаты ----------
case 'room_toggle':
    $r = db_one('SELECT * FROM rooms WHERE id=?', 'i', [$id]);
    if (!$r) done('Палата не найдена', 'err');
    if (db_one("SELECT id FROM hospitalizations WHERE room_id=? AND status='active'", 'i', [$id]))
        done('В палате находится пациент — статус изменится при завершении госпитализации', 'err');
    db_exec('UPDATE rooms SET status=? WHERE id=?', 'si', [$r['status'] == 'free' ? 'occupied' : 'free', $id]);
    done('Статус палаты изменён');
}
redirect($back);
