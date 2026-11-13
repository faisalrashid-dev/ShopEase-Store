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
$name = "";
$email = "";
$username = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) && is_string($_POST['name'])
        ? trim($_POST['name'])
        : "";
    $email = isset($_POST['email']) && is_string($_POST['email'])
        ? trim($_POST['email'])
        : "";
    $username = isset($_POST['username']) && is_string($_POST['username'])
        ? trim($_POST['username'])
        : "";
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : "";
    $csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : "";

    if (!hash_equals($_SESSION['account_csrf_token'], $csrfToken)) {
        $error = "Your session expired. Please refresh the page and try again.";
    } elseif ($name === "" || strlen($name) > 100) {
        $error = "Enter your name (up to 100 characters).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $error = "Enter a valid email address.";
    } elseif ($username === "" || strlen($username) > 100) {
        $error = "Enter a username (up to 100 characters).";
    } elseif (strlen($password) < 8) {
        $error = "Your password must be at least 8 characters.";
    } else {
        $checkStatement = $conn->prepare(
            "SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1"
        );
        if (!$checkStatement) {
            http_response_code(500);
            exit("Could not check existing accounts.");
        }

        $checkStatement->bind_param("ss", $email, $username);
        if (!$checkStatement->execute()) {
            $checkStatement->close();
            http_response_code(500);
            exit("Could not check existing accounts.");
        }

        $accountExists = $checkStatement->get_result()->fetch_assoc();
        $checkStatement->close();

        if ($accountExists) {
            $error = "That email or username is already registered.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $statement = $conn->prepare(
                "INSERT INTO users (name, email, username, password) VALUES (?, ?, ?, ?)"
            );
            if (!$statement) {
                http_response_code(500);
                exit("Could not prepare account registration.");
            }

            $statement->bind_param("ssss", $name, $email, $username, $passwordHash);
            if ($statement->execute()) {
                $_SESSION['user_id'] = $statement->insert_id;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                session_regenerate_id(true);
                $statement->close();
                header("Location: product.php");
                exit;
            }

            if ($statement->errno === 1062) {
                $error = "An account with that email already exists. Please log in.";
            } else {
                $statement->close();
                http_response_code(500);
                exit("Could not create your account.");
            }
            $statement->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ShopEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="text-center mb-4">
            <a class="text-decoration-none fw-bold fs-3" href="product.php" style="color:#14213d;">
                ShopEase
            </a>
            <h1 class="h3 mt-3">Create an account</h1>
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

                        <form method="POST">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo htmlspecialchars($_SESSION['account_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label for="register-name" class="form-label">Name</label>
                                <input id="register-name" type="text" name="name" class="form-control"
                                       maxlength="100" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                                       autocomplete="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="register-email" class="form-label">Email</label>
                                <input id="register-email" type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                       autocomplete="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="register-username" class="form-label">Username</label>
                                <input id="register-username" type="text" name="username" class="form-control"
                                       maxlength="100" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                                       autocomplete="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="register-password" class="form-label">Password</label>
                                <input id="register-password" type="password" name="password" class="form-control"
                                       minlength="8" autocomplete="new-password" required>
                                <div class="form-text">Use at least 8 characters.</div>
                            </div>
                            <button type="submit" class="btn btn-warning w-100 fw-bold">Register</button>
                        </form>
                        <p class="text-center mt-3 mb-0">
                            Already have an account? <a href="login.php">Login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
