<?php
session_start();
include_once "connection.php";
header("Cache-Control: no-store");
header("Referrer-Policy: no-referrer");

if (isset($_SESSION['user_id'])) {
    header("Location: product.php");
    exit;
}

if (!isset($_SESSION['account_csrf_token'])) {
    $_SESSION['account_csrf_token'] = bin2hex(random_bytes(32));
}

$tableSql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
    user_id INT NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX (expires_at),
    CONSTRAINT fk_password_reset_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB";
if (!$conn->query($tableSql)) {
    error_log("Could not create password reset token table: " . $conn->error);
    http_response_code(500);
    exit("Password reset is temporarily unavailable.");
}

$token = isset($_GET['token']) && is_string($_GET['token'])
    ? $_GET['token']
    : (isset($_POST['token']) && is_string($_POST['token']) ? $_POST['token'] : "");
$error = "";
$success = false;

if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    http_response_code(400);
    $error = "This password reset link is invalid or has expired.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : "";
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : "";
    $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
        ? $_POST['confirm_password']
        : "";

    if (!hash_equals($_SESSION['account_csrf_token'], $csrfToken)) {
        $error = "Your session expired. Please refresh the page and try again.";
    } elseif (strlen($password) < 8) {
        $error = "Your password must be at least 8 characters.";
    } elseif (!hash_equals($password, $confirmPassword)) {
        $error = "The passwords do not match.";
    } else {
        $tokenHash = hash("sha256", $token);
        if (!$conn->begin_transaction()) {
            http_response_code(500);
            exit("Could not start the password reset.");
        }

        $statement = $conn->prepare(
            "SELECT user_id FROM password_reset_tokens
             WHERE token_hash = ? AND expires_at > NOW() LIMIT 1 FOR UPDATE"
        );
        if (!$statement) {
            $conn->rollback();
            http_response_code(500);
            exit("Could not prepare the password reset.");
        }

        $statement->bind_param("s", $tokenHash);
        if (!$statement->execute()) {
            $statement->close();
            $conn->rollback();
            http_response_code(500);
            exit("Could not verify the password reset link.");
        }

        $reset = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!$reset) {
            $conn->rollback();
            http_response_code(400);
            $error = "This password reset link is invalid or has expired.";
        } else {
            $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);
            $userId = (int) $reset['user_id'];

            $updateStatement = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if (!$updateStatement) {
                $conn->rollback();
                http_response_code(500);
                exit("Could not prepare the password update.");
            }
            $updateStatement->bind_param("si", $newPasswordHash, $userId);
            if (!$updateStatement->execute()) {
                $updateStatement->close();
                $conn->rollback();
                http_response_code(500);
                exit("Could not update your password.");
            }
            $updateStatement->close();

            $deleteStatement = $conn->prepare(
                "DELETE FROM password_reset_tokens WHERE user_id = ? AND token_hash = ?"
            );
            if (!$deleteStatement) {
                $conn->rollback();
                http_response_code(500);
                exit("Could not prepare the password reset cleanup.");
            }
            $deleteStatement->bind_param("is", $userId, $tokenHash);
            if (!$deleteStatement->execute()) {
                $deleteStatement->close();
                $conn->rollback();
                http_response_code(500);
                exit("Could not complete the password reset.");
            }
            $deleteStatement->close();

            if (!$conn->commit()) {
                $conn->rollback();
                http_response_code(500);
                exit("Could not complete the password reset.");
            }

            $success = true;
        }
    }
} elseif (preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    $tokenHash = hash("sha256", $token);
    $statement = $conn->prepare(
        "SELECT user_id FROM password_reset_tokens
         WHERE token_hash = ? AND expires_at > NOW() LIMIT 1"
    );
    if (!$statement) {
        http_response_code(500);
        exit("Could not prepare the password reset link check.");
    }

    $statement->bind_param("s", $tokenHash);
    if (!$statement->execute()) {
        $statement->close();
        http_response_code(500);
        exit("Could not verify the password reset link.");
    }

    $reset = $statement->get_result()->fetch_assoc();
    $statement->close();
    if (!$reset) {
        http_response_code(400);
        $error = "This password reset link is invalid or has expired.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ShopEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="text-center mb-4">
            <a class="text-decoration-none fw-bold fs-3" href="product.php" style="color:#14213d;">
                ShopEase
            </a>
            <h1 class="h3 mt-3">Reset your password</h1>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <?php if ($error !== "") { ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php } ?>
                        <?php if ($success) { ?>
                            <div class="alert alert-success" role="status">
                                Your password has been updated. You can now log in with your new password.
                            </div>
                            <a class="btn w-100 text-white" href="login.php" style="background-color:#14213d;">
                                Go to login
                            </a>
                        <?php } elseif ($error === "" && preg_match('/\A[a-f0-9]{64}\z/', $token)) { ?>
                            <form method="POST">
                                <input type="hidden" name="token"
                                       value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="csrf_token"
                                       value="<?php echo htmlspecialchars($_SESSION['account_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="mb-3">
                                    <label for="new-password" class="form-label">New password</label>
                                    <input id="new-password" type="password" name="password" class="form-control"
                                           minlength="8" autocomplete="new-password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm-password" class="form-label">Confirm new password</label>
                                    <input id="confirm-password" type="password" name="confirm_password"
                                           class="form-control" minlength="8" autocomplete="new-password" required>
                                </div>
                                <button type="submit" class="btn w-100 text-white"
                                        style="background-color:#14213d;">Save new password</button>
                            </form>
                        <?php } ?>
                        <p class="text-center mt-3 mb-0"><a href="login.php">Back to login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
