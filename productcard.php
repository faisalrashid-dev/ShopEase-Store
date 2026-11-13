
	<?php

         include_once "connection.php";
         $sampleProducts = include "catalog.php";

         $result = $conn->query("SELECT * FROM employees");

    ?>

    <?php foreach ($sampleProducts as $productId => $product) { ?>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>"
                     class="card-img-top"
                     alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                     style="height:250px; object-fit:cover;">
                <div class="card-body text-center">
                    <span class="badge bg-danger mb-2">
                        <?php echo htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <h5 class="fw-bold">
                        <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </h5>
                    <h5 style="color:#fca311;">
                        Rs. <?php echo number_format($product['price'], 2); ?>
                    </h5>
                    <form method="POST" action="product.php#trending">
                        <input type="hidden" name="product_id"
                               value="<?php echo htmlspecialchars($productId, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" name="add_to_cart" value="1"
                                class="btn w-100 fw-bold"
                                style="background-color:#14213d; color:white;">
                            <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php while ($row = $result->fetch_assoc()) { ?>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div style="position:relative;">
                    <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=600&q=80"
                         class="card-img-top" alt="Red sports shoes"
                         style="height:250px; object-fit:cover;">
                    <span class="badge bg-danger"
                          style="position:absolute; top:12px; left:12px;">Sale</span>
                </div>
                <div class="card-body text-center">
                    <h5 class="fw-bold"><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                    <p class="text-warning mb-2"><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <h5 style="color:#fca311;"><?php echo htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8'); ?></h5>
                    <p class="text-muted text-decoration-line-through">Rs. 4,500</p>
                    <form method="POST" action="product.php#trending">
                        <input type="hidden" name="product_id"
                               value="<?php echo (int) $row['id']; ?>">
                        <button type="submit" name="add_to_cart" value="1"
                                class="btn w-100 fw-bold"
                                style="background-color:#14213d; color:white;">
                            <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>
                    </form>
                </div>
            </div>
        </div>
        	<?php } ?>

    <?php $conn->close(); ?>