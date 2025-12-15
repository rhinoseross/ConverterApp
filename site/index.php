<?php
require_once __DIR__ . '/db.php';

/*// ----- CONFIG: simple relative exchange rates (base = 1 USD) -----
$rates = [
    'USD' => 1.0,
    'EUR' => 0.92,
    'GBP' => 0.79,
    'JPY' => 155.40,
    'CAD' => 1.37,
    'AUD' => 1.52
];*/

try {
    $rates = db_get_rates_cached(300); // or db_get_rates() if you don’t want caching yet
} catch (Throwable $e) {
    $rates = []; // so dropdowns don’t explode
    $error = "Could not load exchange rates from DB: " . $e->getMessage();
}

$amountFrom   = '';
$amountTo     = '0';
$fromCurrency = 'USD';
$toCurrency   = 'EUR';
$error        = '';

// Conversion logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amountFrom   = trim($_POST['amount_from'] ?? '');
    $fromCurrency = $_POST['from_currency'] ?? 'USD';
    $toCurrency   = $_POST['to_currency'] ?? 'EUR';

    if ($amountFrom === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $amountFrom)) {
        $error = 'Please enter a valid amount (integer or up to 2 decimal places).';
    } elseif (!isset($rates[$fromCurrency]) || !isset($rates[$toCurrency])) {
        $error = 'Invalid currency selection.';
    } else {
        $numericAmount = (float) $amountFrom;
        $amountInUSD = $numericAmount / $rates[$fromCurrency];
        $converted   = $amountInUSD * $rates[$toCurrency];
        $amountTo = number_format($converted, 2, '.', '');
    }
}

// DB status message (uses db.php helpers)
$dbMessage = db_status_message('conversions');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP Currency Converter</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="converter-container">
    <h1>Currency Converter</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="row">
            <input type="text"
                   name="amount_from"
                   value="<?= htmlspecialchars($amountFrom) ?>"
                   placeholder="Amount"
                   inputmode="decimal"
                   pattern="\d+(\.\d{1,2})?">
            <select name="from_currency">
                <?php foreach ($rates as $code => $rate): ?>
                    <option value="<?= $code ?>" <?= $code === $fromCurrency ? 'selected' : '' ?>>
                        <?= $code ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <input type="text"
                   name="amount_to"
                   value="<?= htmlspecialchars($amountTo) ?>"
                   placeholder="Converted amount"
                   readonly>
            <select name="to_currency">
                <?php foreach ($rates as $code => $rate): ?>
                    <option value="<?= $code ?>" <?= $code === $toCurrency ? 'selected' : '' ?>>
                        <?= $code ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn">Convert</button>
    </form>
</div>

<div class="db-status">
    <?= $dbMessage ?>
</div>
</body>
</html>
