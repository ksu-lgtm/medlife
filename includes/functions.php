<?php
// includes/functions.php — общие функции приложения

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect($url) { header('Location: ' . $url); exit; }

function flash($msg = null, $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'] = ['m' => $msg, 't' => $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- работа с БД (prepared statements) ----------
function db_exec($sql, $types = '', $params = []) {
    global $link;
    $st = mysqli_prepare($link, $sql);
    if (!$st) return false;
    if ($types !== '') mysqli_stmt_bind_param($st, $types, ...$params);
    $ok = mysqli_stmt_execute($st);
    return $ok ? $st : false;
}
function db_all($sql, $types = '', $params = []) {
    $st = db_exec($sql, $types, $params);
    if (!$st) return [];
    $res = mysqli_stmt_get_result($st);
    return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}
function db_one($sql, $types = '', $params = []) {
    $r = db_all($sql, $types, $params);
    return $r ? $r[0] : null;
}
function db_insert_id() { global $link; return mysqli_insert_id($link); }

// ---------- авторизация ----------
function current_user() {
    static $u = false;
    if ($u === false) {
        $u = isset($_SESSION['user_id']) ? db_one('SELECT id,email,role FROM users WHERE id=?', 'i', [$_SESSION['user_id']]) : null;
    }
    return $u;
}
function is_admin() { return ($_SESSION['user_role'] ?? '') === 'admin'; }
function require_login() {
    if (empty($_SESSION['user_id'])) { flash('Войдите в систему, чтобы продолжить', 'err'); redirect('login.php'); }
}
function require_admin() {
    if (!is_admin()) { redirect('login.php'); }
}
function current_patient() {
    if (empty($_SESSION['user_id'])) return null;
    return db_one('SELECT * FROM patients WHERE user_id=?', 'i', [$_SESSION['user_id']]);
}

// ---------- CSRF ----------
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Недействительный токен формы. Вернитесь назад и повторите.');
    }
}

// ---------- форматирование ----------
function money($v) { return number_format((float)$v, 0, ',', ' ') . ' ₽'; }
function fdate($d) { return $d ? date('d.m.Y', strtotime($d)) : ''; }
function years_word($n) {
    $n = (int)$n; $m10 = $n % 10; $m100 = $n % 100;
    if ($m10 == 1 && $m100 != 11) return $n . ' год';
    if ($m10 >= 2 && $m10 <= 4 && ($m100 < 10 || $m100 >= 20)) return $n . ' года';
    return $n . ' лет';
}
function initials($name) {
    $p = preg_split('/\s+/u', trim($name));
    // имя + фамилия (как на макете): «Иванова Елена» -> «ЕИ»
    $sur = mb_substr($p[0] ?? '', 0, 1);
    $first = mb_substr($p[1] ?? $p[0] ?? '', 0, 1);
    return mb_strtoupper($first . $sur);
}
function normalize_phone($p) {
    $d = preg_replace('/\D+/', '', $p);
    if (strlen($d) == 11 && ($d[0] == '8' || $d[0] == '7')) $d = substr($d, 1);
    if (strlen($d) != 10) return null;
    return sprintf('+7 (%s) %s-%s-%s', substr($d,0,3), substr($d,3,3), substr($d,6,2), substr($d,8,2));
}

// ---------- статусы ----------
function appt_status($s) {
    $m = [
        'pending'      => ['На рассмотрении', 'warn'],
        'confirmed'    => ['Подтверждена', 'ok'],
        'rejected'     => ['Отклонена', 'bad'],
        'completed'    => ['Приём состоялся', 'info'],
        'hospitalized' => ['Госпитализирован', 'accent'],
    ];
    return $m[$s] ?? [$s, 'info'];
}
function hosp_status($s) {
    $m = [
        'planned'   => ['Запланирована', 'warn'],
        'active'    => ['В стационаре', 'ok'],
        'completed' => ['Завершена', 'info'],
        'cancelled' => ['Отменена', 'bad'],
    ];
    return $m[$s] ?? [$s, 'info'];
}
function badge($pair) { return '<span class="badge badge-' . $pair[1] . '">' . h($pair[0]) . '</span>'; }

function stars($r) { return '<span class="stars">★★★★★</span> <b>' . number_format((float)$r, 1) . '</b>'; }

// ---------- доменная логика ----------
function patient_name($p) { return decrypt_field($p['full_name_enc'] ?? ''); }

function get_doctors($where = '', $types = '', $params = [], $limit = null) {
    $sql = 'SELECT * FROM (SELECT d.id, d.name, s.name AS specialty, d.experience, d.rating, d.photo FROM doctors d JOIN specialties s ON s.id = d.specialty_id WHERE d.is_active = 1) t ' . $where . ' ORDER BY id' . ($limit ? ' LIMIT ' . (int)$limit : '');
    return db_all($sql, $types, $params);
}

function room_stats() {
    return db_one("SELECT COUNT(*) total, COALESCE(SUM(status='free'),0) free, COALESCE(SUM(status='occupied'),0) occupied FROM rooms");
}
function room_stats_by_category() {
    return db_all("SELECT c.*, COUNT(r.id) total, COALESCE(SUM(r.status='free'),0) free
        FROM room_categories c LEFT JOIN rooms r ON r.category_id=c.id GROUP BY c.id ORDER BY c.price");
}
function rooms_of_category($cid) {
    return db_all('SELECT number,status FROM rooms WHERE category_id=? ORDER BY number', 'i', [$cid]);
}

// ---------- иконки (inline SVG) ----------
function icon($n) {
    $p = [
     'heart'=>'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/><path d="M3 12h4l2-3 3 6 2-3h7" stroke-width="1.6"/>',
     'calendar'=>'<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
     'bed'=>'<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>',
     'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
     'users'=>'<circle cx="9" cy="8" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1M17 4a4 4 0 0 1 0 8M22 21v-1a6 6 0 0 0-4-5.6"/>',
     'clock'=>'<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
     'pin'=>'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
     'phone'=>'<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
     'mail'=>'<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
     'search'=>'<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
     'door'=>'<path d="M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17M3 21h18M14 12h.01"/>',
     'briefcase'=>'<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24">' . ($p[$n] ?? '') . '</svg>';
}

// ---------- запись на приём ----------
// Создаёт запись для пациента текущего пользователя. Возвращает текст ошибки или null.
function create_appointment($data) {
    $p = current_patient();
    if (!$p) return 'Запись доступна только пациентам';
    $doc = db_one('SELECT id FROM doctors WHERE id=? AND is_active=1', 'i', [(int)$data['doctor_id']]);
    if (!$doc) return 'Врач не найден';
    $date = $data['date'] ?? '';
    $ts = strtotime($date);
    if (!$ts || $ts <= strtotime('today')) return 'Выберите дату приёма не раньше завтрашнего дня';
    $name = trim($data['full_name'] ?? '');
    $phone = normalize_phone($data['phone'] ?? '');
    if ($name === '' || !$phone) return 'Проверьте ФИО и телефон';
    $enc_n = encrypt_field($name); $enc_p = encrypt_field($phone);
    db_exec('UPDATE patients SET full_name_enc=?, phone_enc=? WHERE id=?', 'ssi', [$enc_n, $enc_p, $p['id']]);
    $c = trim($data['complaints'] ?? '');
    $enc_c = $c === '' ? null : encrypt_field($c);
    $ok = db_exec('INSERT INTO appointments (patient_id,doctor_id,appointment_date,complaints_enc,status) VALUES (?,?,?,?,\'pending\')',
        'iiss', [$p['id'], $doc['id'], date('Y-m-d', $ts), $enc_c]);
    return $ok ? null : 'Не удалось создать запись';
}
function finish_pending_booking() {
    if (empty($_SESSION['pending_booking'])) return false;
    $d = $_SESSION['pending_booking']; unset($_SESSION['pending_booking']);
    $err = create_appointment($d);
    flash($err ?: 'Заявка отправлена. Статус можно отслеживать в личном кабинете.', $err ? 'err' : 'ok');
    return !$err;
}
