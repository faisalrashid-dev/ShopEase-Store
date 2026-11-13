
<?php
session_start();
include 'connection.php';
include_once "email_sender.php";
$sampleProducts = include "catalog.php";
include_once "order_storage.php";

try {
    initializeOrderStorage($conn, $sampleProducts);
} catch (RuntimeException $error) {
    http_response_code(500);
    exit(htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8'));
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    if (empty($_SESSION['cart'])) {
        http_response_code(400);
        exit("Your cart is empty.");
    }

    $conn->begin_transaction();
    try {
        $cart = $_SESSION['cart'];
        $items = [];
        $employeeIds = [];
        foreach (array_keys($cart) as $cartId) {
            if (ctype_digit((string) $cartId) && (int) $cartId > 0) {
                $employeeIds[] = (int) $cartId;
            } elseif (isset($sampleProducts[$cartId])) {
                $items[] = [
                    'id' => $cartId,
                    'name' => $sampleProducts[$cartId]['name'],
                    'price' => (float) $sampleProducts[$cartId]['price'],
                    'quantity' => (int) $cart[$cartId],
                    'image' => $sampleProducts[$cartId]['image'],
                ];
            } else {
                throw new RuntimeException("The cart contains an invalid product.");
            }
        }

        if (!empty($employeeIds)) {
            $idList = implode(',', $employeeIds);
            $employeeResult = $conn->query(
                "SELECT id, name, phone FROM employees WHERE id IN ($idList)"
            );
            if (!$employeeResult) {
                throw new RuntimeException("Could not load cart products: " . $conn->error);
            }

            while ($employee = $employeeResult->fetch_assoc()) {
                $employeeId = (int) $employee['id'];
                $items[] = [
                    'id' => 'employee-' . $employeeId,
                    'name' => $employee['name'],
                    'price' => (float) $employee['phone'],
                    'quantity' => (int) ($cart[$employeeId] ?? 0),
                    'image' => '',
                ];
            }
            $employeeResult->free();
        }

        if (count($items) !== count($cart)) {
            throw new RuntimeException("One or more cart products are no longer available.");
        }

        $totalBill = 0;
        foreach ($items as $item) {
            if ($item['quantity'] < 1) {
                throw new RuntimeException("The cart contains an invalid quantity.");
            }
            $totalBill += $item['price'] * $item['quantity'];
        }

        $orderStatement = $conn->prepare(
            "INSERT INTO `order` (order_date, customer_id, total_bill)
             VALUES (NOW(), ?, ?)"
        );
        if (!$orderStatement) {
            throw new RuntimeException("Could not prepare the order: " . $conn->error);
        }
        $customerId = (int) $_SESSION['user_id'];
        $orderStatement->bind_param("id", $customerId, $totalBill);
        if (!$orderStatement->execute()) {
            throw new RuntimeException("Could not save the order: " . $orderStatement->error);
        }
        $orderId = $conn->insert_id;
        $orderStatement->close();

        $detailStatement = $conn->prepare(
            "INSERT INTO order_details (order_id, product_id, price, quantity)
             VALUES (?, ?, ?, ?)"
        );
        if (!$detailStatement) {
            throw new RuntimeException("Could not prepare order details: " . $conn->error);
        }

        foreach ($items as $item) {
            $price = $item['price'];
            $quantity = $item['quantity'];
            $productId = $item['id'];
            $detailStatement->bind_param(
                "isdi",
                $orderId,
                $productId,
                $price,
                $quantity
            );
            if (!$detailStatement->execute()) {
                throw new RuntimeException("Could not save order details: " . $detailStatement->error);
            }
        }
        $detailStatement->close();

        $customerStatement = $conn->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
        if (!$customerStatement) {
            throw new RuntimeException("Could not prepare customer email lookup: " . $conn->error);
        }
        $customerStatement->bind_param("i", $customerId);
        if (!$customerStatement->execute()) {
            $customerError = $customerStatement->error;
            $customerStatement->close();
            throw new RuntimeException("Could not load customer email: " . $customerError);
        }
        $customer = $customerStatement->get_result()->fetch_assoc();
        $customerStatement->close();
        if (!$customer || !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Could not find a valid email address for your account.");
        }

        if (!$conn->commit()) {
            throw new RuntimeException("Could not confirm your order: " . $conn->error);
        }

        $itemLines = [];
        foreach ($items as $item) {
            $itemLines[] = sprintf(
                "- %s x %d: Rs. %s",
                $item['name'],
                $item['quantity'],
                number_format($item['price'] * $item['quantity'], 2)
            );
        }
        $subject = "ShopEase order confirmation #" . $orderId;
        $body = "Hello " . $customer['name'] . ",\n\n"
            . "Your order #" . $orderId . " has been confirmed.\n\n"
            . "Order items:\n" . implode("\n", $itemLines) . "\n\n"
            . "Total: Rs. " . number_format($totalBill, 2) . "\n\n"
            . "Thank you for shopping with ShopEase.";
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'email_templates.php';
        $htmlBody = buildOrderConfirmationEmail(
            (int) $orderId,
            $customer['name'],
            $customer['email'],
            $items,
            (float) $totalBill
        );
        $emailSent = sendShopEmail($customer['email'], $subject, $body, $htmlBody);
        if (!$emailSent) {
            error_log(
                "Order confirmation email could not be sent for order ID "
                . $orderId . " to user ID " . $customerId
            );
        }

        $_SESSION['order_email_status'] = $emailSent
            ? 'sent'
            : 'failed';
        $_SESSION['cart'] = [];
        header("Location: cart.php?order_success=" . $orderId);
        exit;
    } catch (Throwable $error) {
        $conn->rollback();
        http_response_code(500);
        exit(htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $id = isset($_POST['product_id']) && is_string($_POST['product_id'])
        ? trim($_POST['product_id'])
        : '';
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($id === '' ||
        $quantity === false || $quantity === null || $quantity < 1 ||
        !isset($_SESSION['cart'][$id])) {
        http_response_code(400);
        exit("Invalid product or quantity.");
    }

    $_SESSION['cart'][$id] = $quantity;
    header("Location: cart.php");
    exit;
}

if (isset($_GET['remove'])) {
    $id = is_string($_GET['remove']) ? $_GET['remove'] : '';
    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
    }

    header("Location: cart.php");
    exit;
}

$cart = $_SESSION['cart'];
$items = [];
$total = 0;
$employeeIds = [];

foreach (array_keys($cart) as $cartId) {
    if (ctype_digit((string) $cartId) && (int) $cartId > 0) {
        $employeeIds[] = (int) $cartId;
    } elseif (isset($sampleProducts[$cartId])) {
        $items[] = [
            'id' => $cartId,
            'name' => $sampleProducts[$cartId]['name'],
            'price' => (float) $sampleProducts[$cartId]['price'],
        ];
    }
}

if (!empty($employeeIds)) {
    $idList = implode(',', $employeeIds);
    $result = mysqli_query(
        $conn,
        "SELECT * FROM employees WHERE id IN ($idList)"
    );

    while ($row = mysqli_fetch_assoc($result)) {
        $row['price'] = (float) $row['phone'];
        $items[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Cart - ShopEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">

    <h2 class="fw-bold mb-4">My Shopping Cart</h2>

    <?php if (isset($_GET['order_success']) && ctype_digit((string) $_GET['order_success'])) { ?>
        <?php $emailStatus = $_SESSION['order_email_status'] ?? ''; ?>
        <?php unset($_SESSION['order_email_status']); ?>
        <?php if ($emailStatus === 'sent') { ?>
            <div class="alert alert-success">
                Order #<?php echo (int) $_GET['order_success']; ?> confirmed successfully. A confirmation email was sent to your account email.
            </div>
        <?php } elseif ($emailStatus === 'failed') { ?>
            <div class="alert alert-warning">
                Order #<?php echo (int) $_GET['order_success']; ?> was confirmed, but the confirmation email could not be sent. Please contact the shop if you need help.
            </div>
        <?php } else { ?>
            <div class="alert alert-success">
                Order #<?php echo (int) $_GET['order_success']; ?> confirmed successfully.
            </div>
        <?php } ?>
    <?php } ?>

    <div class="table-responsive bg-white p-3 rounded shadow-sm">

        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($items as $item) {
                $id = (string) $item['id'];
                $qty = (int) ($cart[$id] ?? 0);
                $price = (float) $item['price'];
                $subtotal = $price * $qty;
                $total += $subtotal;
            ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($item['name']); ?>
                    </td>

                    <td>
                        Rs. <?php echo number_format($price, 2); ?>
                    </td>

                    <td>
                        <form method="POST" action="cart.php" class="d-flex gap-2">
                            <input type="hidden" name="product_id"
                                   value="<?php echo $id; ?>">
                            <input type="number" name="quantity"
                                   class="form-control form-control-sm"
                                   value="<?php echo $qty; ?>" min="1" required
                                   aria-label="Quantity for <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" name="update_quantity" value="1"
                                    class="btn btn-primary btn-sm">
                                Update
                            </button>
                        </form>
                    </td>

                    <td>Rs. <?php echo number_format($subtotal, 2); ?></td>

                    <td>
                        <a href="cart.php?remove=<?php echo rawurlencode($id); ?>"
                           class="btn btn-danger btn-sm">
                            Remove
                        </a>
                    </td>
                </tr>
            <?php } ?>

            <?php if (empty($items)) { ?>
                <tr>
                    <td colspan="5" class="text-center">
                        Your cart is empty.
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <h4 class="text-end fw-bold">
            Total: Rs. <?php echo number_format($total, 2); ?>
        </h4>

        <a href="product.php" class="btn btn-primary">
            Continue Shopping
        </a>

        <?php if (!empty($items) && isset($_SESSION['user_id'])) { ?>
            <form method="POST" action="cart.php" class="mt-4 ms-auto" style="max-width:360px;">
                <button type="submit" name="confirm_order" value="1"
                        class="btn btn-success w-100">
                    Confirm Order
                </button>
            </form>
        <?php } elseif (!empty($items)) { ?>
            <div class="alert alert-info mt-4 ms-auto" style="max-width:360px;">
                Please <a href="login.php">log in</a> to place your order and save it to your account.
            </div>
        <?php } ?>

    </div>
</div>

</body>
</html>
