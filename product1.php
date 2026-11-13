<?php
include "connection1.php";

if (isset($_POST['submit'])) {
    $name = $conn->real_escape_string($_POST['name']);

    $imageName = basename($_FILES['image']['name']);
    $imagePath = "uploads/" . time() . "_" . $imageName;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {

        $imagePath = $conn->real_escape_string($imagePath);

        $sql = "INSERT INTO slider (name, image)
                VALUES ('$name', '$imagePath')";

        if ($conn->query($sql)) {
            header("Location: product1.php");
            exit;
        } else {
            echo "Database error: " . $conn->error;
        }
    } else {
        echo "Image upload failed.";
    }
}

$result = $conn->query("SELECT * FROM slider ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Slider</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">

    <h2 class="mb-4">Slider Management</h2>

    <!-- Add Slider Button -->
    <button class="btn btn-primary mb-4"
            data-bs-toggle="collapse"
            data-bs-target="#sliderForm">
        Add Slider
    </button>

    <!-- Form -->
    <div class="collapse mb-4" id="sliderForm">
        <div class="card card-body">

            <form method="POST" enctype="multipart/form-data">

                <label class="form-label">Slider Name</label>
                <input type="text" name="name"
                       class="form-control mb-3" required>

                <label class="form-label">Slider Image</label>
                <input type="file" name="image"
                       class="form-control mb-3"
                       accept="image/jpeg,image/png,image/webp"
                       required>

                <button type="submit" name="submit"
                        class="btn btn-success">
                    Submit
                </button>

            </form>

        </div>
    </div>

    <!-- Display Sliders -->
    <div class="row">

        <?php while ($row = $result->fetch_assoc()) { ?>

            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">

                    <img src="<?php echo htmlspecialchars($row['image']); ?>"
                         class="card-img-top"
                         style="height:200px; object-fit:cover;"
                         alt="Slider image">

                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo htmlspecialchars($row['name']); ?>
                        </h5>

                        <p class="text-muted small">
                            <?php echo htmlspecialchars($row['image']); ?>
                        </p>
                    </div>

                </div>
            </div>

        <?php } ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>