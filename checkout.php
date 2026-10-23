<?php
require_once __DIR__ . '/db.php';

$error = '';
$confirmed_order = null;
$confirmed_items = [];

// 1. Check if displaying Order Confirmation Screen after successful checkout
if (isset($_GET['success']) && !empty($_SESSION['last_order_id'])) {
    $last_order_id = (int)$_SESSION['last_order_id'];

    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $last_order_id);
    $stmt->execute();
    $confirmed_order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($confirmed_order) {
        $item_stmt = $conn->prepare("
            SELECT oi.*, p.name AS product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $item_stmt->bind_param("i", $last_order_id);
        $item_stmt->execute();
        $res = $item_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $confirmed_items[] = $row;
        }
        $item_stmt->close();
    }
} else {
    // 2. Standard Checkout Mode: Redirect to cart if cart is empty
    if (empty($_SESSION['cart'])) {
        header('Location: cart.php');
        exit;
    }

    // Handle Checkout Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();

        $customer_name    = trim($_POST['customer_name'] ?? '');
        $customer_phone   = trim($_POST['customer_phone'] ?? '');
        $customer_email   = trim($_POST['customer_email'] ?? '');
        $customer_address = trim($_POST['customer_address'] ?? '');

        if ($customer_name === '' || strlen($customer_name) < 2) {
            $error = 'Please enter your full name.';
        } elseif ($customer_phone === '' || strlen($customer_phone) < 7) {
            $error = 'Please enter a valid phone number.';
        } elseif (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($customer_address === '' || strlen($customer_address) < 5) {
            $error = 'Please enter your complete delivery address.';
        } else {
            // Start Database Transaction for Order + Order Items + Stock Deduction
            $conn->begin_transaction();

            try {
                $total_amount = 0.0;
                $verified_items = [];

                // Lock product rows and verify stock & variant price
                $lock_stmt = $conn->prepare("SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE");
                $v_stmt    = $conn->prepare("SELECT id, variant_name, price FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1");

                foreach ($_SESSION['cart'] as $cart_key => $qty) {
                    $parts = explode('_', (string)$cart_key);
                    $pid = (int)$parts[0];
                    $vid = isset($parts[1]) ? (int)$parts[1] : 0;
                    $qty = (int)$qty;

                    if ($pid <= 0 || $qty <= 0) {
                        continue;
                    }

                    $lock_stmt->bind_param("i", $pid);
                    $lock_stmt->execute();
                    $prod = $lock_stmt->get_result()->fetch_assoc();

                    if (!$prod) {
                        throw new Exception('One of the products in your cart is no longer available.');
                    }
                    if ((int)$prod['stock'] < $qty) {
                        throw new Exception('Insufficient stock for "' . $prod['name'] . '" (Available: ' . (int)$prod['stock'] . ').');
                    }

                    $unit_price = (float)$prod['price'];
                    $variant_name = null;

                    if ($vid > 0) {
                        $v_stmt->bind_param("ii", $vid, $pid);
                        $v_stmt->execute();
                        $v_data = $v_stmt->get_result()->fetch_assoc();
                        if ($v_data) {
                            $unit_price = (float)$v_data['price'];
                            $variant_name = $v_data['variant_name'];
                        }
                    }

                    $total_amount += ($unit_price * $qty);

                    $verified_items[] = [
                        'product_id'   => $pid,
                        'variant_id'   => $vid > 0 ? $vid : null,
                        'variant_name' => $variant_name,
                        'quantity'     => $qty,
                        'price'        => $unit_price
                    ];
                }
                $lock_stmt->close();
                $v_stmt->close();

                if (empty($verified_items)) {
                    throw new Exception('Your cart is empty.');
                }

                // Insert into orders table
                $payment_method = 'COD';
                $status = 'Pending';
                $order_stmt = $conn->prepare("
                    INSERT INTO orders (customer_name, customer_phone, customer_email, customer_address, total_amount, payment_method, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $order_stmt->bind_param(
                    "ssssdss",
                    $customer_name,
                    $customer_phone,
                    $customer_email,
                    $customer_address,
                    $total_amount,
                    $payment_method,
                    $status
                );
                $order_stmt->execute();
                $new_order_id = (int)$conn->insert_id;
                $order_stmt->close();

                // Insert into order_items with variant details and deduct product stock
                $item_stmt  = $conn->prepare("INSERT INTO order_items (order_id, product_id, variant_id, variant_name, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
                $stock_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

                foreach ($verified_items as $vi) {
                    $item_stmt->bind_param("iiisid", $new_order_id, $vi['product_id'], $vi['variant_id'], $vi['variant_name'], $vi['quantity'], $vi['price']);
                    $item_stmt->execute();

                    $stock_stmt->bind_param("ii", $vi['quantity'], $vi['product_id']);
                    $stock_stmt->execute();
                }

                $item_stmt->close();
                $stock_stmt->close();

                // Commit Transaction
                $conn->commit();

                // Clear cart and redirect to confirmation view
                $_SESSION['cart'] = [];
                $_SESSION['last_order_id'] = $new_order_id;
                header('Location: checkout.php?success=1');
                exit;
            } catch (Exception $ex) {
                $conn->rollback();
                $error = $ex->getMessage();
            }
        }
    }

    // Load current cart summary for display on the checkout page
    $checkout_items = [];
    $checkout_total = 0.0;
    $stmt   = $conn->prepare("SELECT id, name, price, stock FROM products WHERE id = ? LIMIT 1");
    $v_stmt = $conn->prepare("SELECT variant_name, price FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1");

    foreach ($_SESSION['cart'] as $cart_key => $qty) {
        $parts = explode('_', (string)$cart_key);
        $pid = (int)$parts[0];
        $vid = isset($parts[1]) ? (int)$parts[1] : 0;

        if ($pid <= 0) {
            continue;
        }

        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();

        if ($prod) {
            $unit_price = (float)$prod['price'];
            $variant_name = '';

            if ($vid > 0) {
                $v_stmt->bind_param("ii", $vid, $pid);
                $v_stmt->execute();
                $v_res = $v_stmt->get_result()->fetch_assoc();
                if ($v_res) {
                    $unit_price = (float)$v_res['price'];
                    $variant_name = $v_res['variant_name'];
                }
            }

            $sub = $unit_price * (int)$qty;
            $checkout_total += $sub;

            $prod['variant_name'] = $variant_name;
            $prod['effective_price'] = $unit_price;
            $prod['cart_qty'] = (int)$qty;
            $prod['subtotal'] = $sub;
            $checkout_items[] = $prod;
        }
    }
    $stmt->close();
    $v_stmt->close();
}

$page_title = $confirmed_order
    ? 'Order Confirmed | SKZ Store'
    : 'Secure Checkout | SKZ Store';

$meta_description = $confirmed_order
    ? 'Your SKZ Store order confirmation and delivery details.'
    : 'Complete your SKZ Store order using Cash on Delivery.';

$meta_robots = 'noindex, follow';
$canonical_url = '';
require_once __DIR__ . '/header.php';
?>

<?php if ($confirmed_order): ?>
    <!-- Order Confirmation Screen -->
    <div class="card" style="max-width: 720px; margin: 0 auto;">
        <div class="alert alert-success" style="text-align: center; font-size: 1.05rem;">
            Thank you! Your order has been placed successfully.
        </div>

        <h2 style="color: var(--dark); margin-bottom: 6px;">Order Confirmation #<?php echo (int)$confirmed_order['id']; ?></h2>
        <p style="color: var(--muted); margin-bottom: 20px;">
            We have received your order and will contact you shortly to confirm delivery.
        </p>

        <div class="checkout-grid" style="margin-bottom: 22px;">
            <div>
                <h4 style="color: var(--dark); margin-bottom: 6px;">Delivery Details</h4>
                <p><strong>Name:</strong> <?php echo e($confirmed_order['customer_name']); ?></p>
                <p><strong>Phone:</strong> <?php echo e($confirmed_order['customer_phone']); ?></p>
                <p><strong>Email:</strong> <?php echo e($confirmed_order['customer_email']); ?></p>
                <p><strong>Address:</strong> <?php echo nl2br(e($confirmed_order['customer_address'])); ?></p>
            </div>
            <div>
                <h4 style="color: var(--dark); margin-bottom: 6px;">Order Info</h4>
                <p><strong>Order ID:</strong> #<?php echo (int)$confirmed_order['id']; ?></p>
                <p><strong>Date:</strong> <?php echo e($confirmed_order['order_date']); ?></p>
                <p><strong>Payment Method:</strong> Cash on Delivery (<?php echo e($confirmed_order['payment_method']); ?>)</p>
                <p><strong>Status:</strong> <span class="badge badge-<?php echo e($confirmed_order['status']); ?>"><?php echo e($confirmed_order['status']); ?></span></p>
            </div>
        </div>

        <div class="table-responsive" style="margin-bottom: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($confirmed_items as $ci): ?>
                        <tr>
                            <td>
                                <strong><?php echo e($ci['product_name'] ?? 'Product #' . (int)$ci['product_id']); ?></strong>
                                <?php if (!empty($ci['variant_name'])): ?>
                                    <div style="font-size: 12px; color: var(--primary, #2563eb); font-weight: 600;">
                                        Type: <?php echo e($ci['variant_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>Rs. <?php echo number_format((float)$ci['price'], 2); ?></td>
                            <td><?php echo (int)$ci['quantity']; ?></td>
                            <td>Rs. <?php echo number_format((float)$ci['price'] * (int)$ci['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="font-size: 1.25rem; color: var(--dark);">
                Total Payable on Delivery: <strong>Rs. <?php echo number_format((float)$confirmed_order['total_amount'], 2); ?></strong>
            </div>
            <a href="index.php" class="btn">Continue Shopping</a>
        </div>
    </div>

<?php else: ?>
    <!-- Checkout Form & Order Summary -->
    <div class="section-header">
        <h2>Checkout</h2>
        <a href="cart.php" class="btn btn-outline btn-sm">&larr; Back to Cart</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="checkout-grid">
        <div class="card">
            <h3 style="color: var(--dark); margin-bottom: 16px;">Shipping & Contact Information</h3>

            <form method="post" action="checkout.php">
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label for="customer_name">Full Name *</label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" required
                           value="<?php echo e($_POST['customer_name'] ?? ''); ?>" placeholder="e.g., Ali Khan">
                </div>

                <div class="form-group">
                    <label for="customer_phone">Phone Number *</label>
                    <input type="text" id="customer_phone" name="customer_phone" class="form-control" required
                           value="<?php echo e($_POST['customer_phone'] ?? ''); ?>" placeholder="e.g., 03001234567">
                </div>

                <div class="form-group">
                    <label for="customer_email">Email Address *</label>
                    <input type="email" id="customer_email" name="customer_email" class="form-control" required
                           value="<?php echo e($_POST['customer_email'] ?? ''); ?>" placeholder="e.g., ali@example.com">
                </div>

                <div class="form-group">
                    <label for="customer_address">Complete Delivery Address *</label>
                    <textarea id="customer_address" name="customer_address" rows="3" class="form-control" required
                              placeholder="House #, Street, Area, City"><?php echo e($_POST['customer_address'] ?? ''); ?></textarea>
                </div>

                <div class="form-group" style="background: #f1f5f9; padding: 12px 14px; border-radius: 8px; margin-bottom: 20px;">
                    <label style="margin-bottom: 2px;">Payment Method</label>
                    <div style="font-size: 0.92rem; color: var(--text);">
                        <strong>Cash on Delivery (COD)</strong> — Pay in cash when your order is delivered to your doorstep.
                    </div>
                </div>

                <button type="submit" class="btn btn-block" style="padding: 12px;">
                    Place Order (Rs. <?php echo number_format($checkout_total, 2); ?>)
                </button>
            </form>
        </div>

        <div class="card">
            <h3 style="color: var(--dark); margin-bottom: 16px;">Order Summary</h3>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checkout_items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($item['name']); ?></strong>
                                    <?php if (!empty($item['variant_name'])): ?>
                                        <div style="font-size: 12px; color: var(--primary, #2563eb); font-weight: 600;">
                                            Type: <?php echo e($item['variant_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>x<?php echo (int)$item['cart_qty']; ?></td>
                                <td>Rs. <?php echo number_format((float)$item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; font-size: 1.2rem; color: var(--dark);">
                <span>Total (COD):</span>
                <strong>Rs. <?php echo number_format($checkout_total, 2); ?></strong>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>