<?php
require_once __DIR__ . '/config/database.php';
$page_title = 'Вход';
if (!empty($_SESSION['user_id'])) redirect(is_admin() ? 'admin.php' : 'cabinet.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $u = db_one('SELECT * FROM users WHERE email=?', 's', [$email]);
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['user_role'] = $u['role'];
        if ($u['role'] === 'admin') redirect('admin.php');
        if (finish_pending_booking()) redirect('cabinet.php?tab=appointments');
        redirect('cabinet.php');
    }
    $error = 'Неверный email или пароль';
}
include 'includes/header.php';
?>
<main><div class="authwrap"><div class="authcard">
  <div class="ico"><?= icon('user') ?></div>
  <h1>Вход в личный кабинет</h1>
  <p class="sub">Войдите, чтобы записаться к врачу и следить за статусом заявок</p>
  <?php if ($error): ?><div class="flash err" style="margin:0 0 16px"><?= h($error) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="fg"><label>Email</label><input type="email" name="email" placeholder="name@example.com" required value="<?= h($_POST['email'] ?? '') ?>"></div>
    <div class="fg"><label>Пароль</label><input type="password" name="password" placeholder="Введите пароль" required></div>
    <button class="btn btn-primary btn-block" type="submit">Войти</button>
  </form>
  <p class="alt">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
  <div class="demo"><b>Демо-доступ.</b> Пациент: ivanov@example.com / user123. Администратор: admin@medlife.ru / admin123.</div>
</div></div></main>
<?php include 'includes/footer.php'; ?>
