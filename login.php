<?php
session_start();
include_once "connection.php";

if (isset($_SESSION['user_id'])) {
    header("Location: product.php");
    exit;
}

if (!isset($_SESSION['account_csrf_token'])) {
    $_SESSION['account_csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$email = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) && is_string($_POST['email'])
        ? trim($_POST['email'])
        : "";
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : "";
    $csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : "";
    $action = isset($_POST['action']) && is_string($_POST['action'])
        ? $_POST['action']
        : "";

    if (!hash_equals($_SESSION['account_csrf_token'], $csrfToken)) {
        $error = "Your session expired. Please refresh the page and try again.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif ($action === "login") {
        $statement = $conn->prepare(
            "SELECT id, name, email, password FROM users WHERE email = ?"
        );
        if (!$statement) {
            http_response_code(500);
            exit("Could not prepare account login.");
        }

        $statement->bind_param("s", $email);
        if (!$statement->execute()) {
            $statement->close();
            http_response_code(500);
            exit("Could not verify your account.");
        }

        $user = $statement->get_result()->fetch_assoc();
        $statement->close();

        $passwordMatches = false;
        if ($user) {
            $passwordMatches = password_verify($password, $user['password']);

            if (!$passwordMatches && hash_equals($user['password'], $password)) {
                $passwordMatches = true;
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStatement = $conn->prepare(
                    "UPDATE users SET password = ? WHERE id = ?"
                );
                if (!$updateStatement) {
                    http_response_code(500);
                    exit("Could not update your account password.");
                }
                $userId = (int) $user['id'];
                $updateStatement->bind_param("si", $passwordHash, $userId);
                if (!$updateStatement->execute()) {
                    $updateStatement->close();
                    http_response_code(500);
                    exit("Could not update your account password.");
                }
                $updateStatement->close();
            }
        }

        if ($user && $passwordMatches) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $user['name'];
            header("Location: product.php");
            exit;
        }

        $error = "Email or password is incorrect.";
    } else {
        $error = "Invalid request. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - ShopEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="text-center mb-4">
            <a class="text-decoration-none fw-bold fs-3" href="product.php" style="color:#14213d;">
                ShopEase
            </a>
            <h1 class="h3 mt-3">My Account</h1>
        </div>

        <?php if ($error !== "") { ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php } ?>

        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h4 mb-3">Login</h2>
                        <form method="POST">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo htmlspecialchars($_SESSION['account_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="login-email" class="form-label">Email</label>
                                <input id="login-email" type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                       autocomplete="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="login-password" class="form-label">Password</label>
                                <input id="login-password" type="password" name="password" class="form-control"
                                       autocomplete="current-password" required>
                            </div>
                            <p class="text-end mb-3">
                                <a href="forgot-password.php">Forgot password?</a>
                            </p>
                            <button type="submit" class="btn w-100 text-white"
                                    style="background-color:#14213d;">Login</button>
                        </form>
                        <p class="text-center mt-3 mb-0">
                            New here? <a href="register.php">Create an account</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
