<?php
require_once __DIR__ . '/../db.php';
require_admin();

$error = '';
$success = '';$upload_dir = __DIR__ . '/../uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

/**
 * Helper to validate and upload a product image securely
 */
function handle_image_upload(array $file, string $upload_dir, ?string &$error): ?string {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error uploading image file.';
        return null;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        $error = 'Image size must be 2MB or less.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed_mimes[$mime_type])) {
        $error = 'Only JPG, PNG, and WEBP images are allowed.';
        return null;
    }

    $new_filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime_type];
    $destination = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $error = 'Failed to save uploaded image.';
        return null;
    }

    return $new_filename;
}

// Handle POST requests (Add, Update, or Delete Product)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. ADD NEW PRODUCT
    if ($action === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = $_POST['price'] ?? '';
        $stock = $_POST['stock'] ?? '';
        
        $variant_names = $_POST['variant_names'] ?? [];
        $variant_prices = $_POST['variant_prices'] ?? [];

        if ($name === '' || $description === '' || !is_numeric($price) || (float)$price < 0 || !is_numeric($stock) || (int)$stock < 0) {
            $error = 'Please fill in all product fields with valid values.';
        } else {
            $image_name = handle_image_upload($_FILES['image'] ?? [], $upload_dir, $error);

            if ($error === '') {
                $price_val = round((float)$price, 2);
                $stock_val = (int)$stock;

                $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdis", $name, $description, $price_val, $stock_val, $image_name);
                $stmt->execute();
                $new_product_id = $conn->insert_id;
                $stmt->close();

                // Save Variants if provided
                if (!empty($variant_names) && is_array($variant_names)) {
                    $v_stmt = $conn->prepare("INSERT INTO product_variants (product_id, variant_name, price, stock) VALUES (?, ?, ?, ?)");
                    foreach ($variant_names as $index => $v_name) {
                        $v_name = trim($v_name);
                        $v_price = isset($variant_prices[$index]) ? (float)$variant_prices[$index] : 0;
                        if ($v_name !== '' && $v_price >= 0) {
                            $v_stmt->bind_param("isdi", $new_product_id, $v_name, $v_price, $stock_val);
                            $v_stmt->execute();
                        }
                    }
                    $v_stmt->close();
                }

                $success = 'Product added successfully!';
            }
        }
    }

    // 2. UPDATE EXISTING PRODUCT
    elseif ($action === 'edit_product') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = $_POST['price'] ?? '';
        $stock = $_POST['stock'] ?? '';

        $variant_names = $_POST['variant_names'] ?? [];
        $variant_prices = $_POST['variant_prices'] ?? [];

        if ($product_id <= 0 || $name === '' || $description === '' || !is_numeric($price) || (float)$price < 0 || !is_numeric($stock) || (int)$stock < 0) {$error = 'Please provide valid product details.';
        } else {
            $stmt =$conn->prepare("SELECT image FROM products WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();$existing = $stmt->get_result()->fetch_assoc();$stmt->close();

            if (!$existing) {$error = 'Product not found.';
            } else {
                $image_name =$existing['image'];
                if (!empty($_FILES['image']['name'])) {
                    $uploaded = handle_image_upload($_FILES['image'], $upload_dir,$error);
                    if ($error === '' &&$uploaded !== null) {
                        if (!empty($image_name) && file_exists($upload_dir .$image_name)) {
                            @unlink($upload_dir .$image_name);
                        }
                        $image_name =$uploaded;
                    }
                }

                if ($error === '') {
                    $price_val = round((float)$price, 2);
                    $stock_val = (int)$stock;

                    $stmt =$conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, image = ? WHERE id = ?");
                    $stmt->bind_param("ssdisi", $name,$description, $price_val,$stock_val, $image_name,$product_id);
                    $stmt->execute();$stmt->close();

                    // Refresh Variants
                    $conn->query("DELETE FROM product_variants WHERE product_id = " . (int)$product_id);
                    if (!empty($variant_names) && is_array($variant_names)) {
                        $v_stmt =$conn->prepare("INSERT INTO product_variants (product_id, variant_name, price, stock) VALUES (?, ?, ?, ?)");
                        foreach ($variant_names as$index => $v_name) {$v_name = trim($v_name);$v_price = isset($variant_prices[$index]) ? (float)$variant_prices[$index] : 0;
                            if ($v_name !== '' &&$v_price >= 0) {
                                $v_stmt->bind_param("isdi", $product_id, $v_name,$v_price, $stock_val);$v_stmt->execute();
                            }
                        }
                        $v_stmt->close();
                    }

                    $success = 'Product updated successfully!';
                }
            }
        }
    }

    // 3. DELETE PRODUCT
    elseif ($action === 'delete_product') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            $stmt =$conn->prepare("SELECT image FROM products WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();$prod = $stmt->get_result()->fetch_assoc();$stmt->close();

            if ($prod) {
                // Delete variants first
                $conn->query("DELETE FROM product_variants WHERE product_id = " . (int)$product_id);

                $del =$conn->prepare("DELETE FROM products WHERE id = ?");
                $del->bind_param("i", $product_id);
                $del->execute();$del->close();

                if (!empty($prod['image']) && file_exists($upload_dir .$prod['image'])) {
                    @unlink($upload_dir .$prod['image']);
                }
                $success = 'Product deleted successfully.';
            }
        }
    }
}

// Check if admin clicked "Edit" on a specific product
$edit_product = null;
$edit_variants = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt =$conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();$edit_product = $stmt->get_result()->fetch_assoc();$stmt->close();

    if ($edit_product) {
        $v_res =$conn->query("SELECT * FROM product_variants WHERE product_id = " . (int)$edit_id . " ORDER BY id ASC");
        while ($row =$v_res->fetch_assoc()) {
            $edit_variants[] =$row;
        }
    }
}

// Fetch Dashboard Summary Statistics
$total_products = (int)$conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'];
$total_orders   = (int)$conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
$pending_orders = (int)$conn->query("SELECT COUNT(*) AS c FROM orders WHERE status = 'Pending'")->fetch_assoc()['c'];
$total_revenue  = (float)$conn->query("SELECT COALESCE(SUM(total_amount), 0) AS r FROM orders")->fetch_assoc()['r'];

// Fetch All Products
$products_res =$conn->query("SELECT * FROM products ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — SKZ Store</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <header class="admin-header">
        <div class="container admin-nav">
            <a href="index.php" class="logo">SKZ <span>Admin</span></a>
            <div class="admin-nav-links">
                <a href="index.php">Products</a>
                <a href="orders.php">Orders (<?php echo $pending_orders; ?> Pending)</a>
                <a href="../index.php" target="_blank">View Store</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Logout (<?php echo e($_SESSION['admin_username']); ?>)</a>
            </div>
        </div>
    </header>

    <main class="main-content container">
        <div class="stats-grid">
            <div class="stat-card">
                <span>Total Products</span>
                <h3><?php echo $total_products; ?></h3>
            </div>
            <div class="stat-card">
                <span>Total Orders</span>
                <h3><?php echo $total_orders; ?></h3>
            </div>
            <div class="stat-card">
                <span>Pending Orders</span>
                <h3><?php echo $pending_orders; ?></h3>
            </div>
            <div class="stat-card">
                <span>Total Order Value</span>
                <h3>Rs. <?php echo number_format($total_revenue, 2); ?></h3>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>

        <!-- Add or Edit Product Form -->
        <div class="card">
            <div class="section-header">
                <h2><?php echo $edit_product ? 'Edit Product #' . (int)$edit_product['id'] : 'Add New Product'; ?></h2>
                <?php if ($edit_product): ?>
                    <a href="index.php" class="btn btn-outline btn-sm">Cancel Edit</a>
                <?php endif; ?>
            </div>

            <form method="post" action="index.php" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="<?php echo $edit_product ? 'edit_product' : 'add_product'; ?>">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="product_id" value="<?php echo (int)$edit_product['id']; ?>">
                <?php endif; ?>

                <div class="checkout-grid">
                    <div>
                        <div class="form-group">
                            <label for="name">Product Name</label>
                            <input type="text" id="name" name="name" class="form-control" required
                                   value="<?php echo e($edit_product['name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="description">Product Description</label>
                            <textarea id="description" name="description" rows="4" class="form-control" required><?php echo e($edit_product['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="image">Product Image (JPG, PNG, WEBP — Max 2MB)</label>
                            <input type="file" id="image" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (!empty($edit_product['image'])): ?>
                                <small style="color: var(--muted); display: block; margin-top: 4px;">
                                    Current image: <?php echo e($edit_product['image']); ?> (Leave blank to keep existing image)
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <div class="form-group">
                            <label for="price">Base / Starting Price (Rs.)</label>
                            <input type="number" step="0.01" min="0" id="price" name="price" class="form-control" required
                                   value="<?php echo e($edit_product['price'] ?? ''); ?>">
                            <small style="color: var(--muted);">Agar variants hon ge toh unki alag price use hogi.</small>
                        </div>

                        <div class="form-group">
                            <label for="stock">Stock Quantity</label>
                            <input type="number" min="0" id="stock" name="stock" class="form-control" required
                                   value="<?php echo e($edit_product['stock'] ?? '10'); ?>">
                        </div>

                        <!-- Product Variants (Types/Amperes) Section -->
                        <div class="form-group" style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;">
                            <label style="font-weight: bold; margin-bottom: 8px; display: block;">
                                Product Types / Ratings (Variants)
                            </label>
                            <small style="color: var(--muted); display: block; margin-bottom: 12px;">
                                Breaker ke liye alag alag rating (e.g. 2A, 4A, 6A) aur unki prices enter karein.
                            </small>

                            <div id="variants-container">
                                <?php if (!empty($edit_variants)): ?>
                                    <?php foreach ($edit_variants as$v): ?>
                                        <div class="variant-row" style="display: flex; gap: 8px; margin-bottom: 8px;">
                                            <input type="text" name="variant_names[]" class="form-control" placeholder="Rating (e.g. 2A)" value="<?php echo e($v['variant_name']); ?>" required>
                                            <input type="number" step="0.01" min="0" name="variant_prices[]" class="form-control" placeholder="Price (Rs.)" value="<?php echo e($v['price']); ?>" required>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="btn btn-outline btn-sm" onclick="addVariantRow()" style="margin-top: 6px;">
                                + Add Variant / Rating
                            </button>
                        </div>

                        <button type="submit" class="btn btn-block" style="margin-top: 20px;">
                            <?php echo $edit_product ? 'Update Product' : 'Add Product'; ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- All Products Table -->
        <div class="card">
            <div class="section-header">
                <h2>All Products (<?php echo $total_products; ?>)</h2>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Base Price</th>
                            <th>Variants</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products_res->num_rows === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--muted);">No products added yet. Use the form above to add your first product.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($prod =$products_res->fetch_assoc()): ?>
                                <?php 
                                    // Check variant count for badge
                                    $v_count = (int)$conn->query("SELECT COUNT(*) AS c FROM product_variants WHERE product_id = " . (int)$prod['id'])->fetch_assoc()['c'];
                                ?>
                                <tr>
                                    <td>#<?php echo (int)$prod['id']; ?></td>
                                    <td>
                                        <?php if (!empty($prod['image']) && file_exists($upload_dir .$prod['image'])): ?>
                                            <img src="../uploads/<?php echo e($prod['image']); ?>" alt="<?php echo e($prod['name']); ?>" style="width: 52px; height: 52px; object-fit: cover; border-radius: 6px;">
                                        <?php else: ?>
                                            <span class="no-image">No Img</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo e($prod['name']); ?></strong><br>
                                        <small style="color: var(--muted);"><?php echo e(mb_strimwidth($prod['description'], 0, 60, '...')); ?></small>
                                    </td>
                                    <td>Rs. <?php echo number_format((float)$prod['price'], 2); ?></td>
                                    <td>
                                        <?php if ($v_count > 0): ?>
                                            <span style="background: #eef2ff; color: #4338ca; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                                                <?php echo $v_count; ?> Types
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--muted); font-size: 12px;">Standard</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$prod['stock']; ?></td>
                                    <td>
                                        <div style="display: flex; gap: 6px;">
                                            <a href="index.php?edit=<?php echo (int)$prod['id']; ?>" class="btn btn-outline btn-sm">Edit</a>
                                            <form method="post" action="index.php" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="product_id" value="<?php echo (int)$prod['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    function addVariantRow(name = '', price = '') {
        const container = document.getElementById('variants-container');
        const row = document.createElement('div');
        row.className = 'variant-row';
        row.style.cssText = 'display: flex; gap: 8px; margin-bottom: 8px;';
        row.innerHTML = `
            <input type="text" name="variant_names[]" class="form-control" placeholder="Rating (e.g. 2A)" value="${name}" required>
            <input type="number" step="0.01" min="0" name="variant_prices[]" class="form-control" placeholder="Price (Rs.)" value="${price}" required>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    }
    </script>
</body>
</html>