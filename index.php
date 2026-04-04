<?php

$items_path = 'content/items.json';
$data_dir = 'data';
$log_path = $data_dir . '/inventory.log';

if (!is_dir($data_dir)) {
    mkdir($data_dir, 0775, true);
}

function read_items(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    $items = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
    $result = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            continue;
        }

        $result[$id] = [
            'id' => $id,
            'name' => (string) ($item['name'] ?? $id),
            'ef' => (float) ($item['ef'] ?? 0),
            'unit_weight_kg' => (float) ($item['unit_weight_kg'] ?? 0),
            'default_mode' => ($item['default_mode'] ?? 'count') === 'weight' ? 'weight' : 'count',
        ];
    }

    return $result;
}

function append_log(string $path, array $entry): bool
{
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return false;
    }

    return file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
}

function read_log(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $rows = [];
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return [];
    }

    while (($line = fgets($handle, 1048576)) !== false) {
        $decoded = json_decode(trim($line), true);
        if (!is_array($decoded)) {
            continue;
        }
        $rows[] = $decoded;
    }

    fclose($handle);
    return $rows;
}

function summarize(array $items, array $entries): array
{
    $per_item = [];

    foreach ($items as $id => $item) {
        $per_item[$id] = [
            'id' => $id,
            'name' => $item['name'],
            'ef' => $item['ef'],
            'unit_weight_kg' => $item['unit_weight_kg'],
            'total_count' => 0.0,
            'total_weight_kg' => 0.0,
            'co2e_kg' => 0.0,
        ];
    }

    foreach ($entries as $entry) {
        $id = (string) ($entry['item_id'] ?? '');
        if (!isset($per_item[$id])) {
            continue;
        }

        $mode = (($entry['mode'] ?? 'count') === 'weight') ? 'weight' : 'count';
        $value = (float) ($entry['value'] ?? 0);
        if ($value <= 0) {
            continue;
        }

        if ($mode === 'count') {
            $per_item[$id]['total_count'] += $value;
            $per_item[$id]['total_weight_kg'] += $value * $per_item[$id]['unit_weight_kg'];
        } else {
            $per_item[$id]['total_weight_kg'] += $value;
        }
    }

    $total_co2e = 0.0;
    foreach ($per_item as $id => $row) {
        $co2e = $row['total_weight_kg'] * $row['ef'];
        $per_item[$id]['co2e_kg'] = $co2e;
        $total_co2e += $co2e;
    }

    return [
        'per_item' => array_values($per_item),
        'total_co2e_kg' => $total_co2e,
    ];
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$items = read_items($items_path);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (string) ($_POST['item_id'] ?? '');
    $mode = (string) ($_POST['mode'] ?? 'count');
    $mode = $mode === 'weight' ? 'weight' : 'count';
    $value = (float) ($_POST['value'] ?? 0);

    if (!isset($items[$item_id])) {
        $error = 'Unknown item selected.';
    } elseif ($value <= 0) {
        $error = 'Value must be greater than zero.';
    } else {
        $ok = append_log($log_path, [
            'ts' => time(),
            'item_id' => $item_id,
            'mode' => $mode,
            'value' => $value,
        ]);

        if ($ok) {
            header('Location: ' . strtok((string) $_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        $error = 'Could not append inventory entry.';
    }
}

$entries = read_log($log_path);
$summary = summarize($items, $entries);
$recent_entries = array_slice(array_reverse($entries), 0, 15);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Carbon Calculator</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
  <style>
    .grid-two {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
    .number {
      text-align: right;
      white-space: nowrap;
    }
  </style>
</head>
<body>
<main class="container">
  <h1>Carbon Calculator</h1>
  <p>Add inventory as count or direct weight. The page computes weight and CO2e immediately from the log.</p>

  <?php if ($error !== ''): ?>
    <article>
      <strong><?= esc($error) ?></strong>
    </article>
  <?php endif; ?>

  <section class="grid-two">
    <article>
      <h2>Add Inventory Entry</h2>
      <form method="post">
        <label for="item_id">Item</label>
        <select id="item_id" name="item_id" required>
          <?php foreach ($items as $item): ?>
            <option value="<?= esc($item['id']) ?>"><?= esc($item['name']) ?> (EF <?= esc((string) $item['ef']) ?>, unit <?= esc((string) $item['unit_weight_kg']) ?> kg)</option>
          <?php endforeach; ?>
        </select>

        <fieldset>
          <legend>Entry Type</legend>
          <label>
            <input type="radio" name="mode" value="count" checked>
            Count x unit weight
          </label>
          <label>
            <input type="radio" name="mode" value="weight">
            Direct weight (kg)
          </label>
        </fieldset>

        <label for="value">Value</label>
        <input id="value" name="value" type="number" step="0.001" min="0.001" placeholder="e.g. 4 or 2.5" required>

        <button type="submit">Append Count Log</button>
      </form>
      <small>Log file: data/inventory.log (append-only JSON lines)</small>
    </article>

    <article>
      <h2>Recent Log Entries</h2>
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Item</th>
            <th>Mode</th>
            <th class="number">Value</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_entries as $entry): ?>
            <tr>
              <td><?= esc(date('Y-m-d H:i:s', (int) ($entry['ts'] ?? 0))) ?></td>
              <td><?= esc((string) ($entry['item_id'] ?? '')) ?></td>
              <td><?= esc((string) ($entry['mode'] ?? '')) ?></td>
              <td class="number"><?= number_format((float) ($entry['value'] ?? 0), 3) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </article>
  </section>

  <section>
    <h2>Intermediate Calculations</h2>
    <table>
      <thead>
        <tr>
          <th>Item</th>
          <th class="number">Count</th>
          <th class="number">Unit weight (kg)</th>
          <th class="number">Total weight (kg)</th>
          <th class="number">EF</th>
          <th class="number">CO2e (kg)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary['per_item'] as $row): ?>
          <tr>
            <td><?= esc((string) $row['name']) ?></td>
            <td class="number"><?= number_format((float) $row['total_count'], 3) ?></td>
            <td class="number"><?= number_format((float) $row['unit_weight_kg'], 3) ?></td>
            <td class="number"><?= number_format((float) $row['total_weight_kg'], 3) ?></td>
            <td class="number"><?= number_format((float) $row['ef'], 3) ?></td>
            <td class="number"><?= number_format((float) $row['co2e_kg'], 3) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="5" class="number">Total CO2e (kg)</th>
          <th class="number"><?= number_format((float) $summary['total_co2e_kg'], 3) ?></th>
        </tr>
      </tfoot>
    </table>
  </section>
</main>
<footer class="container">
  <small><?= date('Y-m-d') ?></small>
</footer>
</body>
</html>
