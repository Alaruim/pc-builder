<?php
session_start();
$pdo = new PDO("mysql:host=localhost;dbname=pc_builder;charset=utf8", "root", "");

$type = $_GET['type'] ?? null;

$types = [
    'cpu' => 'Процессор',
    'motherboard' => 'Материнская плата',
    'gpu' => 'Видеокарта',
    'ram' => 'Оперативная память',
    'storage' => 'Накопитель',
    'power supply' => 'Блок питания',
    'case' => 'Корпус',
    'cooling' => 'Охлаждение'
];

$displayName = $types[$type] ?? ucfirst($type);
if (!$type) die("Тип компонента не указан!");

$typeStmt = $pdo->prepare("SELECT id FROM component_types WHERE name = ?");
$typeStmt->execute([$type]);
$typeId = $typeStmt->fetchColumn();
if (!$typeId) die("Неизвестный тип компонента!");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['component_id'])) {
    $id = (int)$_POST['component_id'];
    $stmt = $pdo->prepare("SELECT name, price FROM components WHERE id = ?");
    $stmt->execute([$id]);
    $comp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($comp) {
        $_SESSION['selected'][$type] = [
            'id' => $id,
            'name' => $comp['name'],
            'price' => $comp['price']
        ];
    }

    header("Location: index.php");
    exit;
}

$selected = $_SESSION['selected'] ?? [];
$specData = [];

foreach ($selected as $selectedType => $compData) {
    if (!is_array($compData) || empty($compData['id'])) continue;

    $stmt = $pdo->prepare("
        SELECT s.name, cs.value
        FROM component_specifications cs
        JOIN specifications s ON cs.specification_id = s.id
        WHERE cs.component_id = ?
    ");
    $stmt->execute([$compData['id']]);
    $specs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $specData[$selectedType] = $specs;
}

$sql = "
    SELECT DISTINCT c.id, c.name, c.price
    FROM components c
    JOIN component_specifications cs ON c.id = cs.component_id
    JOIN specifications s ON cs.specification_id = s.id
    WHERE c.component_type_id = ?
";
$params = [$typeId];
$filters = [];

if ($type === 'cpu' && isset($specData['motherboard']['Socket'])) {
    $filters[] = "(s.name = 'Socket' AND cs.value = ?)";
    $params[] = $specData['motherboard']['Socket'];
}
if ($type === 'motherboard' && isset($specData['cpu']['Socket'])) {
    $filters[] = "(s.name = 'Socket' AND cs.value = ?)";
    $params[] = $specData['cpu']['Socket'];
}

if ($type === 'ram' && isset($specData['motherboard']['RAM Type'])) {
    $filters[] = "(s.name = 'Type' AND cs.value = ?)";
    $params[] = $specData['motherboard']['RAM Type'];
}
if ($type === 'motherboard' && isset($specData['ram']['Type'])) {
    $filters[] = "(s.name = 'RAM Type' AND cs.value = ?)";
    $params[] = $specData['ram']['Type'];
}

if ($type === 'motherboard' && isset($specData['case']['Form Factor'])) {
    $filters[] = "(s.name = 'Form Factor' AND cs.value = ?)";
    $params[] = $specData['case']['Form Factor'];
}
if ($type === 'case' && isset($specData['motherboard']['Form Factor'])) {
    $filters[] = "(s.name = 'Form Factor' AND cs.value = ?)";
    $params[] = $specData['motherboard']['Form Factor'];
}

if ($type === 'gpu' && isset($specData['case']['GPU Max Length'])) {
    $filters[] = "(s.name = 'Length' AND cs.value <= ?)";
    $params[] = $specData['case']['GPU Max Length'];
}
if ($type === 'case' && isset($specData['gpu']['Length'])) {
    $filters[] = "(s.name = 'GPU Max Length' AND cs.value >= ?)";
    $params[] = $specData['gpu']['Length'];
}

if ($type === 'gpu' && isset($specData['power supply']['Wattage'])) {
    $filters[] = "(s.name = 'Recommended Wattage' AND cs.value <= ?)";
    $params[] = $specData['power supply']['Wattage'];
}
if ($type === 'power supply' && isset($specData['gpu']['Recommended Wattage'])) {
    $filters[] = "(s.name = 'Wattage' AND cs.value >= ?)";
    $params[] = $specData['gpu']['Recommended Wattage'];
}

if ($type === 'cooling' && isset($specData['cpu']['Socket'])) {
    $filters[] = "(s.name = 'Socket Compatibility' AND cs.value = ?)";
    $params[] = $specData['cpu']['Socket'];
}
if ($type === 'cpu' && isset($specData['cooling']['Socket Compatibility'])) {
    $filters[] = "(s.name = 'Socket' AND cs.value = ?)";
    $params[] = $specData['cooling']['Socket Compatibility'];
}

if ($type === 'cooling' && isset($specData['case']['Cooler Max Height'])) {
    $filters[] = "(s.name = 'Height' AND cs.value <= ?)";
    $params[] = $specData['case']['Cooler Max Height'];
}
if ($type === 'case' && isset($specData['cooling']['Height'])) {
    $filters[] = "(s.name = 'Cooler Max Height' AND cs.value >= ?)";
    $params[] = $specData['cooling']['Height'];
}

$minPrice = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 100000;

$filters[] = "c.price BETWEEN ? AND ?";
$params[] = $minPrice;
$params[] = $maxPrice;

if (!empty($_GET['spec']) && is_array($_GET['spec'])) {
    foreach ($_GET['spec'] as $specName => $values) {
        if (empty($values)) continue;

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $filters[] = "
            EXISTS (
                SELECT 1 FROM component_specifications cs2
                JOIN specifications s2 ON cs2.specification_id = s2.id
                WHERE cs2.component_id = c.id
                  AND s2.name = ?
                  AND cs2.value IN ($placeholders)
            )
        ";

        $params[] = $specName;
        foreach ($values as $val) {
            $params[] = $val;
        }
    }
}

if (!empty($filters)) {
    $sql .= " AND " . implode(" AND ", $filters);
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$components = $stmt->fetchAll(PDO::FETCH_ASSOC);

$specsStmt = $pdo->prepare("
    SELECT s.name, cs.value
    FROM components c
    JOIN component_specifications cs ON c.id = cs.component_id
    JOIN specifications s ON cs.specification_id = s.id
    WHERE c.component_type_id = ?
");
$specsStmt->execute([$typeId]);
$rawSpecs = $specsStmt->fetchAll(PDO::FETCH_ASSOC);

$specOptions = [];
foreach ($rawSpecs as $row) {
    $specOptions[$row['name']][] = $row['value'];
}
foreach ($specOptions as &$values) {
    $values = array_unique($values);
}
unset($values);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Выбор компонента</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f5f5f5;
        margin: 0;
        padding: 20px;
    }

    h1 {
        text-align: center;
        color: #333;
    }

    .container {
        display: flex;
        gap: 20px;
        max-width: 1200px;
        margin: 0 auto;
        align-items: flex-start;
    }

    .filters {
        width: 280px;
        background-color: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    .filters fieldset {
        margin-bottom: 20px;
        border: 1px solid #ccc;
        border-radius: 8px;
        padding: 10px;
    }

    .filters legend {
        font-weight: bold;
        padding: 0 5px;
    }

    .components {
        flex: 1;
        background-color: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        background-color: #007acc;
        color: white;
        padding: 12px;
    }

    td {
        border: 1px solid #ccc;
        padding: 10px;
    }

    td a {
        text-decoration: none;
        color: #007acc;
    }

    button {
        padding: 6px 12px;
        background-color: #007acc;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    button:hover {
        background-color: #005f99;
    }

    a.back-link {
        display: block;
        text-align: center;
        margin-top: 30px;
        color: #007acc;
        text-decoration: none;
    }

    a.back-link:hover {
        text-decoration: underline;
    }
</style>

</head>
<body>
    <h1>Выберите компонент: <?= htmlspecialchars($displayName) ?></h1>

    <div class="container">
        <form method="get" class="filters">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

            <fieldset>
                <legend>Цена</legend>
                От <input type="number" name="min_price" value="<?= $minPrice ?>"> — 
                До <input type="number" name="max_price" value="<?= $maxPrice ?>"> ₽
            </fieldset>

            <?php foreach ($specOptions as $specName => $values): ?>
                <fieldset>
                    <legend><?= htmlspecialchars($specName) ?></legend>
                    <?php foreach ($values as $val): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="spec[<?= htmlspecialchars($specName) ?>][]"
                                value="<?= htmlspecialchars($val) ?>"
                                <?= in_array($val, $_GET['spec'][$specName] ?? []) ? 'checked' : '' ?>
                            >
                            <?= htmlspecialchars($val) ?>
                        </label><br>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        </form>

        <div class="components">
            <form method="post">
                <table>
                    <tr>
                        <th>Название</th>
                        <th>Цена</th>
                        <th>Выбор</th>
                    </tr>
                    <?php foreach ($components as $comp): ?>
                        <tr>
                            <td><a href="component.php?id=<?= $comp['id'] ?>&type=<?= urlencode($type) ?>">
                                <?= htmlspecialchars($comp['name']) ?>
                            </a></td>
                            <td><?= $comp['price'] ?> ₽</td>
                            <td>
                                <button type="submit" name="component_id" value="<?= $comp['id'] ?>">Выбрать</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($components)): ?>
                        <tr><td colspan="3">Нет подходящих компонентов</td></tr>
                    <?php endif; ?>
                </table>
            </form>
        </div>
    </div>

    <a href="index.php" class="back-link">← Назад к сборке</a>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterForm = document.querySelector('form[method="get"]');
            const filterInputs = filterForm.querySelectorAll('input');

            filterInputs.forEach(input => {
                input.addEventListener('change', () => {
                    filterForm.submit();
                });
            });

            const postButtons = document.querySelectorAll('form[method="post"] button');
            postButtons.forEach(button => {
                button.addEventListener('click', () => {
                    button.textContent = 'Выбирается...';
                });
            });
        });
    </script>

</body>
</html>
