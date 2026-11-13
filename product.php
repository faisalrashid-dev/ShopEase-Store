<?php
session_start();
include_once "connection.php";

if (isset($_SESSION['user_id']) && !isset($_SESSION['user_name'])) {
    $userId = (int) $_SESSION['user_id'];
    $userStatement = $conn->prepare("SELECT name FROM users WHERE id = ?");
    if (!$userStatement) {
        http_response_code(500);
        exit("Could not load your account details.");
    }

    $userStatement->bind_param("i", $userId);
    if (!$userStatement->execute()) {
        $userStatement->close();
        http_response_code(500);
        exit("Could not load your account details.");
    }

    $userDetails = $userStatement->get_result()->fetch_assoc();
    $userStatement->close();
    if ($userDetails) {
        $_SESSION['user_name'] = $userDetails['name'];
    }
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $id = isset($_POST['product_id']) && is_string($_POST['product_id'])
        ? trim($_POST['product_id'])
        : '';

    if ($id === '') {
        http_response_code(400);
        exit("Invalid product.");
    }

    $sampleProducts = include "catalog.php";
    if (!isset($sampleProducts[$id])) {
        if (!ctype_digit($id) || (int) $id < 1) {
            http_response_code(404);
            exit("Product not found.");
        }

        $productId = (int) $id;
        $statement = $conn->prepare("SELECT id FROM employees WHERE id = ?");
        if (!$statement) {
            http_response_code(500);
            exit("Could not validate the selected product.");
        }

        $statement->bind_param("i", $productId);
        if (!$statement->execute()) {
            http_response_code(500);
            exit("Could not validate the selected product.");
        }

        $product = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!$product) {
            http_response_code(404);
            exit("Product not found.");
        }
    }

    $_SESSION['cart'][$id] = (int) ($_SESSION['cart'][$id] ?? 0) + 1;
    header("Location: product.php#trending");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopEase - Online Shopping</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body style="margin:0; padding:0; font-family:Arial,sans-serif; background-color:#f8f9fa;">


<nav class="navbar navbar-expand-lg navbar-dark sticky-top"
     style="background-color:#14213d; padding:15px 5%;">

    <a class="navbar-brand fw-bold fs-3" href="#home" style="color:#fca311;">
        <i class="bi bi-bag-heart-fill"></i> ShopEase
    </a>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#navbarMenu"
            aria-controls="navbarMenu" aria-expanded="false"
            aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMenu">
        <ul class="navbar-nav mx-auto text-center">
            <li class="nav-item">
                <a class="nav-link active" href="#home">Home</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#trending">Trending</a>
            </li>        
        </ul>

        <div class="d-flex justify-content-center gap-3 mt-3 mt-lg-0">
            <a href="#trending" class="btn btn-outline-light">
                <i class="bi bi-search"></i>
            </a>
            <a href="cart.php" class="btn btn-outline-light" aria-label="View cart" title="View cart">
                <i class="bi bi-cart3"></i>
                <?php $cartCount = array_sum($_SESSION['cart']); ?>
                <?php if ($cartCount > 0) { ?>
                    <span class="badge bg-warning text-dark"><?php echo $cartCount; ?></span>
                <?php } ?>
            </a>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i> My Account
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (isset($_SESSION['user_id'])) { ?>
                        <li>
                            <span class="dropdown-item-text">
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="orders.php">Order List</a></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    <?php } else { ?>
                        <li><a class="dropdown-item" href="login.php">Login</a></li>
                        <li><a class="dropdown-item" href="register.php">Register</a></li>
                    <?php } ?>
                </ul>
            </div>
            <a href="#footer" class="btn"
               style="background-color:#fca311; color:#14213d; font-weight:bold;">
                Shop Now
            </a>
        </div>
    </div>
</nav>

<section id="home">
    <div id="mainSlider" class="carousel slide" data-bs-ride="carousel">

        <div class="carousel-indicators">
            <button type="button" data-bs-target="#mainSlider"
                    data-bs-slide-to="0" class="active"
                    aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#mainSlider"
                    data-bs-slide-to="1" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#mainSlider"
                    data-bs-slide-to="2" aria-label="Slide 3"></button>
        </div>

        <div class="carousel-inner">

             <div class="carousel-item active">
                <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1600&q=85"
                     class="d-block w-100" alt="Fashion collection"
                     style="height:500px; object-fit:cover;">

                <div class="carousel-caption"
                     style="background-color:rgba(20,33,61,0.72); border-radius:12px; padding:25px;">
                    <h1 class="fw-bold">Discover Your Style</h1>
                    <p class="fs-5">Explore the latest fashion and new arrivals.</p>
                    <a href="#trending" class="btn btn-warning btn-lg fw-bold">
                        Shop Collection
                    </a>
                </div>
            </div>
                    

    </div>
</section>

<section class="container py-5" id="categories">
    <div class="row g-4 text-center">

        <div class="col-12 col-md-4">
            <div class="bg-white p-4 rounded shadow-sm h-100">
                <i class="bi bi-truck fs-1" style="color:#fca311;"></i>
                <h5 class="fw-bold mt-2">Free Delivery</h5>
                <p class="text-muted mb-0">On selected orders and products.</p>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="bg-white p-4 rounded shadow-sm h-100">
                <i class="bi bi-shield-check fs-1" style="color:#fca311;"></i>
                <h5 class="fw-bold mt-2">Secure Shopping</h5>
                <p class="text-muted mb-0">Shop with confidence and peace of mind.</p>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="bg-white p-4 rounded shadow-sm h-100">
                <i class="bi bi-arrow-repeat fs-1" style="color:#fca311;"></i>
                <h5 class="fw-bold mt-2">Easy Returns</h5>
                <p class="text-muted mb-0">Simple returns according to our policy.</p>
            </div>
        </div>

    </div>
</section>

<section class="container py-4" id="trending">

    <div class="text-center mb-5">
        <h2 class="fw-bold" style="color:#14213d;">Trending Products</h2>
        <p class="text-muted">Discover our most popular picks</p>
        <div style="width:80px; height:4px; background-color:#fca311; margin:auto;"></div>
    </div>

    <div class="row g-4">
	<?php include_once('productcard.php')?>
    </div>

</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
