<?php
require_once __DIR__ . '/../db.php';
require_admin();

$error = '';
$success = '';
$allowed_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered'];

// Handle Order Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    verify_csrf();
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';

    if ($order_id > 0 && in_array($new_status, $allowed_statuses, true)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Order #' . $order_id . ' status updated to ' . $new_status . '.';
    } else {
        $error = 'Invalid order ID or status selected.';
    }
}

// Check if viewing a single order's full details
$selected_order = null;
$order_items = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_id = (int)$_GET['view'];

    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $selected_order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selected_order) {
        $item_stmt = $conn->prepare("
            SELECT oi.*, p.name AS product_name, p.image AS product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $item_stmt->bind_param("i", $view_id);
        $item_stmt->execute();
        $res = $item_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $order_items[] = $row;
        }
        $item_stmt->close();
    }
}

// Fetch all customer orders (latest first)
$orders_res = $conn->query("SELECT * FROM orders ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders — SKZ Store Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <header class="admin-header">
        <div class="container admin-nav">
            <a href="index.php" class="logo">SKZ <span>Admin</span></a>
            <div class="admin-nav-links">
                <a href="index.php">Products</a>
                <a href="orders.php">Orders</a>
                <a href="../index.php" target="_blank">View Store</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Logout (<?php echo e($_SESSION['admin_username']); ?>)</a>
            </div>
        </div>
    </header>

    <main class="main-content container">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>

        <?php if ($selected_order): ?>
            <!-- Individual Order Details Card -->
            <div class="card">
                <div class="section-header">
                    <h2>Order #<?php echo (int)$selected_order['id']; ?> Details</h2>
                    <a href="orders.php" class="btn btn-outline btn-sm">&larr; Back to All Orders</a>
                </div>

                <div class="checkout-grid" style="margin-bottom: 24px;">
                    <div>
                        <h4 style="margin-bottom: 8px; color: var(--dark);">Customer & Delivery Info</h4>
                        <p><strong>Name:</strong> <?php echo e($selected_order['customer_name']); ?></p>
                        <p><strong>Phone:</strong> <?php echo e($selected_order['customer_phone']); ?></p>
                        <p><strong>Email:</strong> <?php echo e($selected_order['customer_email']); ?></p>
                        <p><strong>Address:</strong> <?php echo nl2br(e($selected_order['customer_address'])); ?></p>
                    </div>

                    <div>
                        <h4 style="margin-bottom: 8px; color: var(--dark);">Order Summary</h4>
                        <p><strong>Order Date:</strong> <?php echo e($selected_order['order_date']); ?></p>
                        <p><strong>Payment Method:</strong> <?php echo e($selected_order['payment_method']); ?></p>
                        <p><strong>Current Status:</strong>
                            <span class="badge badge-<?php echo e($selected_order['status']); ?>">
                                <?php echo e($selected_order['status']); ?>
                            </span>
                        </p>
                        <p style="font-size: 1.15rem; margin-top: 8px;">
                            <strong>Total Amount:</strong> Rs. <?php echo number_format((float)$selected_order['total_amount'], 2); ?>
                        </p>

                        <!-- Update Order Status Form -->
                        <form method="post" action="orders.php?view=<?php echo (int)$selected_order['id']; ?>" style="margin-top: 14px; display: flex; gap: 8px; align-items: center;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo (int)$selected_order['id']; ?>">
                            <select name="status" class="form-control" style="max-width: 180px;">
                                <?php foreach ($allowed_statuses as $st): ?>
                                    <option value="<?php echo e($st); ?>" <?php echo ($selected_order['status'] === $st) ? 'selected' : ''; ?>>
                                        <?php echo e($st); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm">Update Status</button>
                        </form>
                    </div>
                </div>

                <h4 style="margin-bottom: 12px; color: var(--dark);">Ordered Items</h4>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($item['product_name'] ?? 'Deleted Product (ID #' . (int)$item['product_id'] . ')'); ?></strong>
                                        <?php if (!empty($item['variant_name'])): ?>
                                            <div style="margin-top: 4px;">
                                                <span style="display: inline-block; background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                                    Type: <?php echo e($item['variant_name']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>Rs. <?php echo number_format((float)$item['price'], 2); ?></td>
                                    <td><?php echo (int)$item['quantity']; ?></td>
                                    <td>Rs. <?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- All Customer Orders Table -->
        <div class="card">
            <div class="section-header">
                <h2>All Customer Orders</h2>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_res->num_rows === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--muted);">No customer orders have been placed yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($ord = $orders_res->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$ord['id']; ?></strong></td>
                                    <td><?php echo e($ord['customer_name']); ?></td>
                                    <td><?php echo e($ord['customer_phone']); ?></td>
                                    <td>Rs. <?php echo number_format((float)$ord['total_amount'], 2); ?></td>
                                    <td><?php echo e($ord['order_date']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo e($ord['status']); ?>">
                                            <?php echo e($ord['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="orders.php?view=<?php echo (int)$ord['id']; ?>" class="btn btn-outline btn-sm">View Details</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>