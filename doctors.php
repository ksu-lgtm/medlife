<?php
require_once __DIR__ . '/config/database.php';
$page_title = 'Врачи'; $active = 'doctors';
$q = trim($_GET['q'] ?? '');
$spec = (int)($_GET['spec'] ?? 0);
$exp = $_GET['exp'] ?? '';
$where = []; $types = ''; $params = [];
if ($q !== '') { $where[] = 'name LIKE ?'; $types .= 's'; $params[] = '%' . $q . '%'; }
if ($spec) { $where[] = 'specialty = (SELECT name FROM specialties WHERE id=?)'; $types .= 'i'; $params[] = $spec; }
$ranges = ['0-5' => [0,4], '5-10' => [5,9], '10-20' => [10,19], '20+' => [20,99]];
if (isset($ranges[$exp])) { $where[] = 'experience BETWEEN ? AND ?'; $types .= 'ii'; array_push($params, $ranges[$exp][0], $ranges[$exp][1]); }
$doctors = get_doctors($where ? 'WHERE ' . implode(' AND ', $where) : '', $types, $params);
$specs = db_all('SELECT * FROM specialties ORDER BY name');
include 'includes/header.php';
?>
<main>
<section class="banner"><div class="container"><h1>Наши врачи</h1><p>Опытные специалисты, готовые помочь вам 24/7. Выберите врача и запишитесь на приём.</p></div></section>
<section class="filters"><div class="container">
  <form method="get">
    <div class="field-search"><?= icon('search') ?><input type="text" name="q" placeholder="Поиск по имени..." value="<?= h($q) ?>"></div>
    <select name="spec"><option value="0">Все специализации</option>
      <?php foreach ($specs as $s): ?><option value="<?= $s['id'] ?>" <?= $spec==$s['id']?'selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="exp">
      <option value="">Любой стаж</option>
      <option value="0-5" <?= $exp=='0-5'?'selected':'' ?>>До 5 лет</option>
      <option value="5-10" <?= $exp=='5-10'?'selected':'' ?>>5–10 лет</option>
      <option value="10-20" <?= $exp=='10-20'?'selected':'' ?>>10–20 лет</option>
      <option value="20+" <?= $exp=='20+'?'selected':'' ?>>Более 20 лет</option>
    </select>
    <button class="btn btn-primary" type="submit">Применить</button>
  </form>
</div></section>
<section class="section" style="padding:56px 0 72px"><div class="container">
  <?php if (!$doctors): ?><div class="empty">По вашему запросу врачи не найдены. <a href="doctors.php" style="color:var(--blue)">Сбросить фильтры</a></div>
  <?php else: ?><div class="grid3"><?php foreach ($doctors as $d): include 'includes/doctor_card.php'; endforeach; ?></div><?php endif; ?>
</div></section>
</main>
<?php include 'includes/footer.php'; ?>
