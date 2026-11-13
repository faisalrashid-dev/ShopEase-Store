<?php

function initializeOrderStorage(mysqli $conn, array $sampleProducts): void
{
    $tableStatements = [
        "CREATE TABLE IF NOT EXISTS products (
            product_id VARCHAR(64) NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            category VARCHAR(100) NOT NULL,
            image TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS `order` (
            order_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_date DATETIME NOT NULL,
            customer_id INT UNSIGNED NOT NULL,
            total_bill DECIMAL(12,2) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS order_details (
            order_detail_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            product_id VARCHAR(64) NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            CONSTRAINT fk_order_details_order
                FOREIGN KEY (order_id) REFERENCES `order` (order_id),
            CONSTRAINT fk_order_details_product
                FOREIGN KEY (product_id) REFERENCES products (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($tableStatements as $sql) {
        if (!$conn->query($sql)) {
            throw new RuntimeException("Could not initialize order tables: " . $conn->error);
        }
    }

    $sampleStatement = $conn->prepare(
        "INSERT INTO products (product_id, name, price, category, image)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price),
                                 category = VALUES(category), image = VALUES(image)"
    );
    if (!$sampleStatement) {
        throw new RuntimeException("Could not prepare sample product records: " . $conn->error);
    }

    foreach ($sampleProducts as $productId => $product) {
        $price = (float) $product['price'];
        $name = $product['name'];
        $category = $product['category'];
        $image = $product['image'];
        $sampleStatement->bind_param(
            "ssdss",
            $productId,
            $name,
            $price,
            $category,
            $image
        );
        if (!$sampleStatement->execute()) {
            throw new RuntimeException("Could not save sample product: " . $sampleStatement->error);
        }
    }
    $sampleStatement->close();

    $employeeResult = $conn->query("SELECT id, name, phone FROM employees");
    if (!$employeeResult) {
        throw new RuntimeException("Could not load database products: " . $conn->error);
    }

    $employeeStatement = $conn->prepare(
        "INSERT INTO products (product_id, name, price, category, image)
         VALUES (?, ?, ?, 'Product', '')
         ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price)"
    );
    if (!$employeeStatement) {
        throw new RuntimeException("Could not prepare database product records: " . $conn->error);
    }

    while ($employee = $employeeResult->fetch_assoc()) {
        $productId = 'employee-' . (int) $employee['id'];
        $name = $employee['name'];
        $price = (float) $employee['phone'];
        $employeeStatement->bind_param("ssd", $productId, $name, $price);
        if (!$employeeStatement->execute()) {
            throw new RuntimeException("Could not save database product: " . $employeeStatement->error);
        }
    }

    $employeeStatement->close();
    $employeeResult->free();
}
