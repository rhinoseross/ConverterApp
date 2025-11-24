<?php
// ----- CONFIG: simple relative exchange rates (base = 1 USD) -----
$rates = [
    'USD' => 1.0,      // US Dollar
    'EUR' => 0.92,     // Euro
    'GBP' => 0.79,     // British Pound
    'JPY' => 155.40,   // Japanese Yen
    'CAD' => 1.37,     // Canadian Dollar
    'AUD' => 1.52      // Australian Dollar
];

$amountFrom   = '';
$amountTo     = '0';
$fromCurrency = 'USD';
$toCurrency   = 'EUR';
$error        = '';

// ----------------- DB CONFIG (MySQL) -----------------
$host     = 'ca-db';       // e.g. '127.0.0.1'
$dbname   = 'converterApp';    // your MySQL database name
$username = 'root';            // your MySQL username
$password = '12345';                // your MySQL password
$table    = 'conversions';     // table to check
// -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amountFrom   = trim($_POST['amount_from'] ?? '');
    $fromCurrency = $_POST['from_currency'] ?? 'USD';
    $toCurrency   = $_POST['to_currency'] ?? 'EUR';

    // Validate amount: integer or up to 2 decimal places
    if ($amountFrom === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $amountFrom)) {
        $error = 'Please enter a valid amount (integer or up to 2 decimal places).';
    } elseif (!isset($rates[$fromCurrency]) || !isset($rates[$toCurrency])) {
        $error = 'Invalid currency selection.';
    } else {
        $numericAmount = (float) $amountFrom;

        // Convert: amount in USD, then to target currency
        $amountInUSD = $numericAmount / $rates[$fromCurrency];
        $converted   = $amountInUSD * $rates[$toCurrency];

        // Format to 2 decimal places
        $amountTo = number_format($converted, 2, '.', '');
    }
}

// ----- DATABASE CONNECTION CHECK (MySQL) -----
$dbMessage = "";
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Check if table "conversions" exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
    $stmt->execute(['table' => $table]);

    if ($stmt->rowCount() > 0) {
        $dbMessage = "<span style='color: green;'>Database connected successfully. Table '$table' found.</span>";
    } else {
        $dbMessage = "<span style='color: orange;'>Connected to database, but table '$table' does not exist.</span>";
    }

} catch (PDOException $e) {
    $dbMessage = "<span style='color: red;'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP Currency Converter</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 2rem;
            background: #f7f7f7;
        }
        .converter-container {
            max-width: 500px;
            margin: 0 auto;
            background: #ffffff;
            padding: 1.5rem 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .row {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        input[type="text"] {
            flex: 2;
            padding: 0.4rem;
            font-size: 1rem;
        }
        select {
            flex: 1;
            padding: 0.4rem;
            font-size: 0.95rem;
        }
        .error {
            color: #b00020;
            margin-bottom: 1rem;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 0.6rem;
            font-size: 1rem;
            border: none;
            border-radius: 4px;
            background: #007bff;
            color: white;
            cursor: pointer;
        }
        .btn:hover {
            background: #0056b3;
        }
        .note {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.75rem;
        }
        .db-status {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="converter-container">
    <h1>Currency Converter</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <!-- LEFT: user input -->
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

        <!-- RIGHT: converted amount (readonly) -->
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

        <div class="note">
            Note: Rates are hard-coded for demo purposes.  
            In a real app, fetch live rates from an exchange-rate API.
        </div>
    </form>
</div>

<div class="db-status">
    <?= $dbMessage ?>
</div>

</body>
</html>
