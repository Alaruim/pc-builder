<?php
session_start();

require_once 'db.php';

if (isset($_POST['export'])) {
    $filename = "pc_build_" . date("Ymd_His") . ".json";
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($_SESSION['selected'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['import']) && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file']['tmp_name'];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (is_array($data)) {
        $_SESSION['selected'] = $data;
    }
    header("Location: index.php");
    exit;
}

if (isset($_POST['clear'])) {
    unset($_SESSION['selected']);
    header("Location: index.php");
    exit;
}

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

if (isset($_GET['remove']) && array_key_exists($_GET['remove'], $types)) {
    unset($_SESSION['selected'][$_GET['remove']]);
    header("Location: index.php");
    exit;
}

$selected = $_SESSION['selected'] ?? [];

$total = 0;
foreach ($selected as $comp) {
    if (is_array($comp) && isset($comp['price'])) {
        $total += $comp['price'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Сборщик ПК</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f8;
        margin: 0;
        padding: 40px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    h1, h2 {
        color: #333;
        text-align: center;
    }

    table {
        border-collapse: collapse;
        width: 800px;
        background-color: #fff;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        overflow: hidden;
    }

    th, td {
        padding: 12px 15px;
        text-align: left;
    }

    th {
        background-color: #007acc;
        color: white;
    }

    tr:last-child th {
        background-color: #007acc;
        color: white;
    }

    tr:nth-child(even) td {
        background-color: #f9f9f9;
    }

    a {
        color: #0066cc;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }

    button {
        background-color: #003f6b;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    button:hover {
        background-color: #002f4d;
    }

    form {
        margin-top: 20px;
        text-align: center;
    }

    input[type="file"] {
        display: none;
    }

    .budget-block {
        margin: 20px auto 30px;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .budget-block input[type="number"] {
        padding: 8px;
        width: 160px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
    }

</style>

</head>
<body>

<?php session_start(); ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div style="color: red; font-weight: bold; margin: 10px 0;">
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['success'])): ?>
    <div style="color: green; font-weight: bold; margin: 10px 0;">
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<h1>Сборка ПК</h1>

<form method="post">
    <table border="1" cellpadding="10">
        <tr>
            <th>Компонент</th>
            <th>Ваш выбор</th>
            <th>Цена</th>
            <th></th>
        </tr>
        <?php foreach ($types as $key => $label): ?>
            <tr>
                <td><?= $label ?></td>
                <td>
                    <?php if (!empty($selected[$key])): ?>
                        <a href="component.php?id=<?= $selected[$key]['id'] ?>">
                            <?= htmlspecialchars($selected[$key]['name']) ?>
                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td class="price-cell"><?= $selected[$key]['price'] ?? '—' ?> ₽</td>
                <td>
                    <a href="choose.php?type=<?= $key ?>">Выбрать</a>
                    <?php if (!empty($selected[$key])): ?>
                        | <a href="index.php?remove=<?= $key ?>">Удалить</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <th colspan="2">Итого:</th>
            <th><span id="total-cost"><?= $total ?></span> ₽</th>
            <th><button type="submit" name="clear">Очистить</button></th>
        </tr>
    </table>
</form>

<div class="budget-block">
    <label for="budget"><strong>Введите ваш бюджет:</strong></label>
    <input type="number" id="budget" name="budget" placeholder="Напр. 70000">
    <button type="button">Автоподбор компонентов</button>
</div>

<h2>Экспорт / Импорт сборки</h2>
<form method="post" enctype="multipart/form-data" style="margin-bottom: 20px;">
    <button type="submit" name="export">Экспортировать сборку</button>
</form>

<form method="post" enctype="multipart/form-data" id="importForm" style="display: inline;">
    <input type="file" name="import_file" id="importFile" accept=".json" onchange="document.getElementById('importForm').submit();">
    <button type="button" onclick="document.getElementById('importFile').click();">Импортировать сборку</button>
</form>

<div id="modal" style="display:none; position:fixed; z-index:1000; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center;">
    <div style="background:white; padding:20px 30px; border-radius:8px; text-align:center; min-width:300px;">
        <h3>Выберите тип сборки</h3>
        <select id="pc-type" style="padding:8px; font-size:16px; margin: 10px 0;">
            <option value="gaming">Игровой ПК</option>
            <option value="office">Офисный ПК</option>
            <option value="workstation">Рабочая станция</option>
        </select><br>
        <button onclick="startAutoselect()">Подобрать</button>
        <button onclick="closeModal()" style="margin-left:10px; background:#ccc;">Отмена</button>
    </div>
</div>

<script>
    const modal = document.getElementById('modal');

    document.querySelector('.budget-block button').addEventListener('click', function () {
        const budget = document.getElementById('budget').value;
        if (!budget || budget <= 0) {
            alert("Пожалуйста, введите бюджет.");
            return;
        }
        modal.style.display = 'flex';
    });

    function closeModal() {
        modal.style.display = 'none';
    }

    function startAutoselect() {
        const budget = parseInt(document.getElementById('budget').value);
        const type = document.getElementById('pc-type').value;

        fetch('autopick.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ budget, type })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || "Не удалось подобрать сборку.");
            }
        });

        closeModal();
    }
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function recalculateTotal() {
        let total = 0;
        document.querySelectorAll('.price-cell').forEach(cell => {
            const text = cell.textContent.replace(/[^\d.]/g, '');
            const price = parseFloat(text);
            if (!isNaN(price)) total += price;
        });
        document.getElementById('total-cost').textContent = total;
    }

    recalculateTotal();
});

function startAutoselect() {
    const budget = parseInt(document.getElementById('budget').value);
    const type = document.getElementById('pc-type').value;

    fetch('autopick.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ budget, type })
    })
    .then(res => res.json())
    .then(data => {
        closeModal();
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || "Не удалось подобрать сборку.");
        }
    })
    .catch(() => {
        closeModal();
        alert("Ошибка сервера.");
    });
}
</script>

</body>
</html>
