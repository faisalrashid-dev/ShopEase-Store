<?php
session_start();
include_once "connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$sampleProducts = include "catalog.php";
include_once "order_storage.php";

try {
    initializeOrderStorage($conn, $sampleProducts);
} catch (RuntimeException $error) {
    http_response_code(500);
    exit(htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$userId = (int) $_SESSION['user_id'];
$statement = $conn->prepare(
    "SELECT o.order_id, o.order_date, o.total_bill,
            od.product_id, od.price, od.quantity, p.name AS product_name
     FROM `order` o
     LEFT JOIN order_details od ON od.order_id = o.order_id
     LEFT JOIN products p ON p.product_id = od.product_id
     WHERE o.customer_id = ?
     ORDER BY o.order_date DESC, o.order_id DESC, od.order_detail_id ASC"
);
if (!$statement) {
    http_response_code(500);
    exit("Could not load your orders.");
}

$statement->bind_param("i", $userId);
if (!$statement->execute()) {
    $statement->close();
    http_response_code(500);
    exit("Could not load your orders.");
}

$result = $statement->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orderId = (int) $row['order_id'];
    if (!isset($orders[$orderId])) {
        $orders[$orderId] = [
            'date' => $row['order_date'],
            'total' => (float) $row['total_bill'],
            'items' => [],
        ];
    }

    if ($row['product_id'] !== null) {
        $orders[$orderId]['items'][] = [
            'name' => $row['product_name'] ?? $row['product_id'],
            'price' => (float) $row['price'],
            'quantity' => (int) $row['quantity'],
        ];
    }
}
$statement->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order List - ShopEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 mb-1">Order List</h1>
                <p class="text-muted mb-0">
                    Orders for <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
            <a href="product.php" class="btn btn-outline-primary">Continue Shopping</a>
        </div>

        <?php if (empty($orders)) { ?>
            <div class="alert alert-info">You have not placed any orders yet.</div>
        <?php } else { ?>
            <?php foreach ($orders as $orderId => $order) { ?>
                <section class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                        <strong>Order #<?php echo $orderId; ?></strong>
                        <span class="text-muted"><?php echo htmlspecialchars($order['date'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($order['items'])) { ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-3">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order['items'] as $item) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td>Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted">No item details are available for this order.</p>
                        <?php } ?>
                        <p class="text-end fw-bold mb-0">Total: Rs. <?php echo number_format($order['total'], 2); ?></p>
                    </div>
                </section>
            <?php } ?>
        <?php } ?>
    </main>
</body>
</html>
