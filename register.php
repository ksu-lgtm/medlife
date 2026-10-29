<?php
require_once __DIR__ . '/config/database.php';
$page_title = 'Регистрация';
if (!empty($_SESSION['user_id'])) redirect('cabinet.php');
$errors = [];
$v = ['full_name' => '', 'email' => '', 'phone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['full_name'] = trim($_POST['full_name'] ?? '');
    $v['email'] = trim($_POST['email'] ?? '');
    $v['phone'] = trim($_POST['phone'] ?? '');
    $pass = $_POST['password'] ?? ''; $pass2 = $_POST['password2'] ?? '';
    if (mb_strlen($v['full_name']) < 5 || count(preg_split('/\s+/u', $v['full_name'])) < 2) $errors[] = 'Укажите ФИО полностью';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email';
    $phone = normalize_phone($v['phone']);
    if (!$phone) $errors[] = 'Телефон должен содержать 10 цифр после +7';
    if (strlen($pass) < 6) $errors[] = 'Пароль — не менее 6 символов';
    if ($pass !== $pass2) $errors[] = 'Пароли не совпадают';
    if (empty($_POST['agree'])) $errors[] = 'Необходимо согласие на обработку персональных данных';
    if (!$errors && db_one('SELECT id FROM users WHERE email=?', 's', [$v['email']])) $errors[] = 'Пользователь с таким email уже зарегистрирован';
    if (!$errors) {
        global $link;
        mysqli_begin_transaction($link);
        $ok = db_exec('INSERT INTO users (email,password,role) VALUES (?,?,\'user\')', 'ss', [$v['email'], password_hash($pass, PASSWORD_DEFAULT)]);
        $uid = db_insert_id();
        $card = 'ML-' . str_pad((string)((int)db_one('SELECT COALESCE(MAX(id),0)+1 n FROM patients')['n']), 6, '0', STR_PAD_LEFT);
        $ok = $ok && db_exec('INSERT INTO patients (card_number,user_id,full_name_enc,phone_enc) VALUES (?,?,?,?)', 'siss',
            [$card, $uid, encrypt_field($v['full_name']), encrypt_field($phone)]);
        if ($ok) {
            mysqli_commit($link);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid; $_SESSION['user_role'] = 'user';
            if (finish_pending_booking()) redirect('cabinet.php?tab=appointments');
            flash('Регистрация прошла успешно. Добро пожаловать!');
            redirect('cabinet.php');
        }
        mysqli_rollback($link);
        $errors[] = 'Не удалось создать аккаунт. Попробуйте позже.';
    }
}
include 'includes/header.php';
?>
<main><div class="authwrap"><div class="authcard">
  <div class="ico"><?= icon('user') ?></div>
  <h1>Регистрация пациента</h1>
  <p class="sub">Создайте личный кабинет медицинского центра «МедЛайф»</p>
  <?php foreach ($errors as $e): ?><div class="flash err" style="margin:0 0 10px"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="fg"><label>ФИО</label><input name="full_name" placeholder="Иванов Иван Иванович" required value="<?= h($v['full_name']) ?>"></div>
    <div class="fg"><label>Email</label><input type="email" name="email" placeholder="name@example.com" required value="<?= h($v['email']) ?>"></div>
    <div class="fg"><label>Телефон</label><input name="phone" placeholder="+7 (___) ___-__-__" required data-phone value="<?= h($v['phone']) ?>"></div>
    <div class="fg"><label>Пароль</label><input type="password" name="password" placeholder="Не менее 6 символов" required minlength="6"></div>
    <div class="fg"><label>Подтверждение пароля</label><input type="password" name="password2" placeholder="Повторите пароль" required></div>
    <label class="check"><input type="checkbox" name="agree" value="1" required> <span>Я даю согласие на обработку персональных данных (ФИО и телефон хранятся в зашифрованном виде)</span></label>
    <button class="btn btn-primary btn-block" type="submit">Зарегистрироваться</button>
  </form>
  <p class="alt">Уже есть аккаунт? <a href="login.php">Войти</a></p>
</div></div></main>
<?php include 'includes/footer.php'; ?>
