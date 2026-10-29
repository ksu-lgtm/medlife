<?php
require_once __DIR__ . '/config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('doctors.php');
csrf_check();
$data = [
    'doctor_id' => (int)($_POST['doctor_id'] ?? 0),
    'full_name' => $_POST['full_name'] ?? '',
    'phone' => $_POST['phone'] ?? '',
    'date' => $_POST['date'] ?? '',
    'complaints' => $_POST['complaints'] ?? '',
];
if (empty($_SESSION['user_id'])) {
    $_SESSION['pending_booking'] = $data;
    flash('Войдите или зарегистрируйтесь — после этого заявка будет отправлена автоматически', 'ok');
    redirect('login.php');
}
if (is_admin()) { flash('Администратор не может записываться на приём', 'err'); redirect('doctors.php'); }
$err = create_appointment($data);
if ($err) { flash($err, 'err'); redirect('doctors.php'); }
flash('Заявка отправлена. Статус можно отслеживать в личном кабинете.');
redirect('cabinet.php?tab=appointments');
