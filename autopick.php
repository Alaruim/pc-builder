<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$budget = (int)($data['budget'] ?? 0);
$type = $data['type'] ?? '';

if ($budget <= 0 || !in_array($type, ['gaming', 'office', 'workstation'])) {
    echo json_encode(['success' => false, 'message' => 'Неверные данные.']);
    exit;
}

$percentages = [
    'gaming' => [
        'gpu' => 0.50,
        'cpu' => 0.22,
        'cooling' => 0.05,
        'ram' => 0.10,
        'storage' => 0.07,
        'motherboard' => 0.04,
        'power supply' => 0.015,
        'case' => 0.015
    ],
    'office' => [
        'cpu' => 0.45,
        'cooling' => 0.05,
        'motherboard' => 0.20,
        'ram' => 0.15,
        'storage' => 0.10,
        'power supply' => 0.03,
        'case' => 0.02,
        'gpu' => 0
    ],
    'workstation' => [
        'cpu' => 0.35,
        'cooling' => 0.05,
        'gpu' => 0.35,
        'ram' => 0.15,
        'storage' => 0.07,
        'motherboard' => 0.025,
        'power supply' => 0.015,
        'case' => 0.01
    ]
];

$type_ids = [
    'cpu' => 1,
    'motherboard' => 2,
    'gpu' => 3,
    'ram' => 4,
    'storage' => 5,
    'power supply' => 6,
    'case' => 7,
    'cooling' => 8,
];

$build = [];
$compatibility = [];

$integratedGpuSpecId = 72;

foreach ($percentages[$type] as $category => $ratio) {
    if ($ratio === 0) continue;
    $target = $budget * $ratio;

    $component_type_id = $type_ids[$category] ?? 0;
    if ($component_type_id === 0) {
        continue;
    }

    if ($type === 'office' && $category === 'cpu') {
        $sql = "
            SELECT c.*
            FROM components c
            JOIN component_specifications cs ON cs.component_id = c.id
            WHERE c.component_type_id = :type_id
              AND cs.specification_id = :spec_id
              AND cs.value = 'есть'
              AND c.price <= :max_price
            ORDER BY c.price DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':type_id' => $component_type_id,
            ':spec_id' => $integratedGpuSpecId,
            ':max_price' => $target,
        ]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM components WHERE component_type_id = ? AND price <= ? ORDER BY price DESC LIMIT 1");
        $stmt->execute([$component_type_id, $target]);
    }

    $component = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($component) {
        $build[$category] = [
            'id' => $component['id'],
            'name' => $component['name'],
            'price' => (int)$component['price'],
            'socket' => $component['socket'] ?? null,
            'chipset' => $component['chipset'] ?? null
        ];
        $compatibility[$category] = $component;
    }
}

$required = array_keys(array_filter($percentages[$type], fn($r) => $r > 0));
$missing = array_diff($required, array_keys($build));
if (!empty($missing)) {
    echo json_encode(['success' => false, 'message' => 'Не удалось найти компоненты: ' . implode(', ', $missing)]);
    exit;
}

if (!empty($compatibility['cpu']['socket']) && !empty($compatibility['motherboard']['socket'])) {
    if ($compatibility['cpu']['socket'] !== $compatibility['motherboard']['socket']) {
        echo json_encode(['success' => false, 'message' => 'Несовместимость: сокеты CPU и материнской платы не совпадают.']);
        exit;
    }
}

if (!empty($compatibility['motherboard']['ram_type']) && !empty($compatibility['ram']['type'])) {
    if ($compatibility['motherboard']['ram_type'] !== $compatibility['ram']['type']) {
        echo json_encode(['success' => false, 'message' => 'Несовместимость: тип оперативной памяти не поддерживается материнской платой.']);
        exit;
    }
}

if (!empty($compatibility['motherboard']['form_factor']) && !empty($compatibility['case']['form_factor'])) {
    if ($compatibility['case']['form_factor'] !== $compatibility['motherboard']['form_factor']) {
        echo json_encode(['success' => false, 'message' => 'Несовместимость: форм-фактор материнской платы не поддерживается корпусом.']);
        exit;
    }
}

if (!empty($compatibility['gpu']['length']) && !empty($compatibility['case']['gpu_max_length'])) {
    if ((int)$compatibility['gpu']['length'] > (int)$compatibility['case']['gpu_max_length']) {
        echo json_encode(['success' => false, 'message' => 'Несовместимость: видеокарта слишком длинная для корпуса.']);
        exit;
    }
}

if (!empty($compatibility['gpu']['recommended_wattage']) && !empty($compatibility['power_supply']['wattage'])) {
    if ((int)$compatibility['power_supply']['wattage'] < (int)$compatibility['gpu']['recommended_wattage']) {
        echo json_encode(['success' => false, 'message' => 'Несовместимость: блок питания не обеспечивает нужную мощность для видеокарты.']);
        exit;
    }
}

if (!empty($compatibility['cpu']['socket']) && !empty($compatibility['cooling']['supported_sockets'])) {
    if (!in_array($compatibility['cpu']['socket'], explode(',', $compatibility['cooling']['supported_sockets']))) {
        echo json_encode(['success' => false, 'message' => 'Несовместимость: кулер не поддерживает сокет выбранного процессора.']);
        exit;
    }
}

if (!empty($compatibility['cooling']['height']) && !empty($compatibility['case']['cooler_max_height'])) {
    if ((int)$compatibility['cooling']['height'] > (int)$compatibility['case']['cooler_max_height']) {
        echo json_encode(['success' => false, 'message' => 'Несовместимость: кулер не помещается в корпус.']);
        exit;
    }
}

foreach ($build as $k => &$v) {
    unset($v['socket'], $v['chipset']);
}

$_SESSION['selected'] = $build;

echo json_encode(['success' => true, 'message' => 'Сборка успешно подобрана!']);
exit;
