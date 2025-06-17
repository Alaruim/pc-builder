<?php
require_once 'db.php';
session_start();

$type = $_GET['type'] ?? null;
$id = $_GET['id'] ?? null;

if (!$id) {
    echo "ID компонента не указан.";
    exit;
}

$stmt = $pdo->prepare("SELECT c.name, c.price, ct.name AS type
                       FROM components c
                       JOIN component_types ct ON c.component_type_id = ct.id
                       WHERE c.id = ?");
$stmt->execute([$id]);
$component = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$component) {
    echo "Компонент не найден.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typeKey = strtolower($component['type']);
    $_SESSION['selected'][$typeKey] = [
        'id' => $id,
        'name' => $component['name'],
        'price' => $component['price']
    ];

    header("Location: index.php");
    exit;
}

$specsStmt = $pdo->prepare("SELECT s.name, cs.value
                            FROM component_specifications cs
                            JOIN specifications s ON cs.specification_id = s.id
                            WHERE cs.component_id = ?");
$specsStmt->execute([$id]);
$specs = $specsStmt->fetchAll(PDO::FETCH_ASSOC);

$importantSpecsMap = [
    'cpu' => [
        'Socket',
        'Total Cores',
        'Base Clock',
        'Cache L2',
        'Cache L3',
        'Memory Channels',
        'Memory Frequency',
        'GPU Model',
        'TDP'
    ],
    'motherboard' => [
        'Socket',
        'Chipset',
        'CPU Support',
        'Memory Slots'
    ],
    'gpu' => [
        'PCI Express Support',
        'Memory Size',
        'Memory Type',
        'Memory Bus Width',
        'Video Outputs',
        'GPU Frequency'
    ],
    'ram' => [
        'Type',
        'Capacity',
        'Module Count',
        'Frequency',
        'Timings'
    ],
    'power supply' => [
        'Power',
        'Main Connector',
        'CPU Connector',
        'SATA Connectors'
    ],
    'case' => [
        'Size',
        'Supported Motherboards',
        'USB Ports'
    ],
    'storage' => [
        'Interface',
        'Read Speed',
        'Write Speed',
        'Memory Type',
        'TBW'
    ],
    'cooling' => [
        'Type',
        'Socket Support'
    ]
];

$specMap = [];
foreach ($specs as $spec) {
    $specMap[$spec['name']] = $spec['value'];
}

$shortSpecs = [];
$typeKey = strtolower($component['type']);
if (isset($importantSpecsMap[$typeKey])) {
    foreach ($importantSpecsMap[$typeKey] as $importantName) {
        if (isset($specMap[$importantName])) {
            $shortSpecs[$importantName] = $specMap[$importantName];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($component['name']) ?></title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f3f3f3;
        margin: 0;
        padding: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .component-card {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        max-width: 900px;
        width: 100%;
        box-shadow: 0 0 12px rgba(0, 0, 0, 0.1);
    }

    .component-title {
        font-size: 28px;
        margin-bottom: 20px;
        text-align: center;
        color: #333;
    }

    .component-top {
        display: flex;
        gap: 30px;
        align-items: flex-start;
        margin-bottom: 30px;
    }

    .component-image {
        width: 300px;
        height: auto;
        object-fit: contain;
        border-radius: 10px;
        background: #eee;
    }

    .short-specs {
        flex: 1;
    }

    .short-specs h3 {
        margin-bottom: 10px;
        color: #007acc;
    }

    .short-specs ul {
        list-style: none;
        padding: 0;
    }

    .short-specs li {
        margin-bottom: 8px;
        font-size: 16px;
    }

    .full-specs h2 {
        color: #007acc;
        margin-bottom: 10px;
    }

    .full-specs ul {
        list-style: none;
        padding: 0;
    }

    .full-specs li {
        margin-bottom: 6px;
    }

    .choose-button {
        margin-top: 30px;
        display: flex;
        justify-content: center;
    }

    button {
        background-color: #007acc;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 16px;
        cursor: pointer;
    }

    button:hover {
        background-color: #005f99;
    }

    .back-link {
        text-align: center;
        margin-top: 20px;
        color: #007acc;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }
</style>

</head>
<body>
<div class="component-card">
    <div class="component-title"><?= htmlspecialchars($component['name']) ?></div>

    <div class="component-top">
        <img class="component-image" src="<?= htmlspecialchars($component['image_url'] ?? 'placeholder.jpg') ?>" alt="Фото компонента">

        <div class="short-specs">
            <h3>Краткие характеристики</h3>
            <ul>
                <li><strong>Тип:</strong> <?= htmlspecialchars($component['type']) ?></li>
                <li><strong>Цена:</strong> <?= $component['price'] ?> ₽</li>
                <?php foreach ($shortSpecs as $name => $value): ?>
                    <li><strong><?= htmlspecialchars($name) ?>:</strong> <?= htmlspecialchars($value) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="full-specs">
        <h2>Подробные характеристики</h2>
        <ul>
            <?php foreach ($specs as $spec): ?>
                <li><strong><?= htmlspecialchars($spec['name']) ?>:</strong> <?= htmlspecialchars($spec['value']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <form method="post" class="choose-button">
        <button type="submit">Выбрать этот компонент</button>
    </form>

    <p class="back-link">
        <?php if ($type): ?>
            <a href="choose.php?type=<?= urlencode($type) ?>">← Вернуться к выбору <?= htmlspecialchars($component['type']) ?></a>
        <?php else: ?>
            <a href="index.php">← Вернуться на главную</a>
        <?php endif; ?>
    </p>
</div>

</body>
</html>
