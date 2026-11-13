<?php
session_start();
include_once "connection.php";
include_once "email_sender.php";

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

$email = "";
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) && is_string($_POST['email'])
        ? trim($_POST['email'])
        : "";
    $csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : "";

    if (!hash_equals($_SESSION['account_csrf_token'], $csrfToken)) {
        $error = "Your session expired. Please refresh the page and try again.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } else {
        $statement = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if (!$statement) {
            http_response_code(500);
            exit("Could not prepare the password reset request.");
        }

        $statement->bind_param("s", $email);
        if (!$statement->execute()) {
            $statement->close();
            http_response_code(500);
            exit("Could not process the password reset request.");
        }

        $user = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($user) {
            $userId = (int) $user['id'];
            $rateLimitStatement = $conn->prepare(
                "SELECT user_id FROM password_reset_tokens
                 WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
            );
            if (!$rateLimitStatement) {
                http_response_code(500);
                exit("Could not prepare the password reset request.");
            }

            $rateLimitStatement->bind_param("i", $userId);
            if (!$rateLimitStatement->execute()) {
                $rateLimitStatement->close();
                http_response_code(500);
                exit("Could not process the password reset request.");
            }

            $recentRequest = $rateLimitStatement->get_result()->fetch_assoc();
            $rateLimitStatement->close();

            if (!$recentRequest) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash("sha256", $token);
                $saveStatement = $conn->prepare(
                    "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())
                     ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash),
                         expires_at = VALUES(expires_at), created_at = VALUES(created_at)"
                );
                if (!$saveStatement) {
                    http_response_code(500);
                    exit("Could not prepare the password reset request.");
                }

                $saveStatement->bind_param("is", $userId, $tokenHash);
                if (!$saveStatement->execute()) {
                    $saveStatement->close();
                    http_response_code(500);
                    exit("Could not process the password reset request.");
                }
                $saveStatement->close();

                $baseUrl = getenv("PASSWORD_RESET_BASE_URL");
                if (!$baseUrl) {
                    $baseUrl = "http://localhost/project";
                }
                $resetUrl = rtrim($baseUrl, "/") . "/reset-password.php?token=" . rawurlencode($token);
                $subject = "Reset your ShopEase password";
                $body = "We received a request to reset your ShopEase password.\n\n"
                    . "Use this link within one hour to choose a new password:\n"
                    . $resetUrl
                    . "\n\nIf you did not request this, you can ignore this email.";
                if (!sendShopEmail($email, $subject, $body)) {
                    error_log("Password reset email could not be sent for user ID " . $userId);
                    $removeStatement = $conn->prepare(
                        "DELETE FROM password_reset_tokens WHERE user_id = ? AND token_hash = ?"
                    );
                    if ($removeStatement) {
                        $removeStatement->bind_param("is", $userId, $tokenHash);
                        if (!$removeStatement->execute()) {
                            error_log("Could not remove unsent password reset token for user ID " . $userId);
                        }
                        $removeStatement->close();
                    } else {
                        error_log("Could not prepare cleanup for unsent password reset token.");
                    }
                }
            }
        }

        $message = "If an account exists for that email address, password reset instructions will be sent.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ShopEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="text-center mb-4">
            <a class="text-decoration-none fw-bold fs-3" href="product.php" style="color:#14213d;">
                ShopEase
            </a>
            <h1 class="h3 mt-3">Forgot your password?</h1>
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
                        <?php if ($message !== "") { ?>
                            <div class="alert alert-info" role="status">
                                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php } ?>
                        <p>Enter your account email and we will send a password reset link if it matches an account.</p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo htmlspecialchars($_SESSION['account_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label for="reset-email" class="form-label">Email</label>
                                <input id="reset-email" type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                       autocomplete="email" required>
                            </div>
                            <button type="submit" class="btn w-100 text-white"
                                    style="background-color:#14213d;">Send reset link</button>
                        </form>
                        <p class="text-center mt-3 mb-0"><a href="login.php">Back to login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
