<?php
require_once __DIR__ . '/db.php';

/*
|--------------------------------------------------------------------------
| Initialize Cart
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/*
|--------------------------------------------------------------------------
| Handle Cart Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    // ADD ITEM TO CART
    if ($action === 'add') {
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $variant_id = isset($_POST['variant_id']) && is_numeric($_POST['variant_id']) ? (int) $_POST['variant_id'] : 0;
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        if ($product_id > 0) {
            $stmt = $conn->prepare(
                "SELECT id, name, price, stock FROM products WHERE id = ? LIMIT 1"
            );

            if ($stmt) {
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $prod = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($prod && (int) $prod['stock'] > 0) {
                    $item_name = $prod['name'];
                    $variant_name = '';

                    // Check if variant is selected and valid
                    if ($variant_id > 0) {
                        $v_stmt = $conn->prepare("SELECT id, variant_name, price FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1");
                        if ($v_stmt) {
                            $v_stmt->bind_param("ii", $variant_id, $product_id);
                            $v_stmt->execute();
                            $variant_data = $v_stmt->get_result()->fetch_assoc();
                            $v_stmt->close();

                            if ($variant_data) {
                                $variant_name = $variant_data['variant_name'];
                                $item_name .= ' (' . $variant_name . ')';
                            } else {
                                $variant_id = 0; // Fallback if invalid
                            }
                        }
                    }

                    // Composite Cart Key
                    $cart_key = $variant_id > 0 ? ($product_id . '_' . $variant_id) : (string)$product_id;

                    $current_qty = (int) ($_SESSION['cart'][$cart_key] ?? 0);
                    $new_qty = min(
                        (int) $prod['stock'],
                        $current_qty + $quantity
                    );

                    $_SESSION['cart'][$cart_key] = $new_qty;

                    $_SESSION['cart_notice'] = '"' . $item_name . '" added to your cart.';
                    unset($_SESSION['cart_error']);
                } else {
                    $_SESSION['cart_error'] = 'Sorry, this product is out of stock.';
                }
            } else {
                $_SESSION['cart_error'] = 'Unable to process this product right now.';
            }
        }

        header('Location: cart.php');
        exit;
    }

    // UPDATE QUANTITIES
    elseif ($action === 'update') {
        $quantities = $_POST['quantities'] ?? [];

        if (is_array($quantities)) {
            foreach ($quantities as $cart_key => $qty) {
                $cart_key = trim((string)$cart_key);
                $qty = filter_var($qty, FILTER_VALIDATE_INT);

                if ($cart_key === '') {
                    continue;
                }

                if ($qty === false || $qty <= 0) {
                    unset($_SESSION['cart'][$cart_key]);
                    continue;
                }

                // Extract product_id from key
                $parts = explode('_', $cart_key);
                $pid = (int)$parts[0];

                if ($pid <= 0) {
                    unset($_SESSION['cart'][$cart_key]);
                    continue;
                }

                $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ? LIMIT 1");
                if (!$stmt) {
                    continue;
                }

                $stmt->bind_param("i", $pid);
                $stmt->execute();
                $prod = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($prod && (int) $prod['stock'] > 0) {
                    $_SESSION['cart'][$cart_key] = min(
                        (int) $prod['stock'],
                        $qty
                    );
                } else {
                    unset($_SESSION['cart'][$cart_key]);
                }
            }

            $_SESSION['cart_notice'] = 'Shopping cart updated.';
            unset($_SESSION['cart_error']);
        }

        header('Location: cart.php');
        exit;
    }

    // REMOVE SINGLE ITEM
    elseif ($action === 'remove') {
        $cart_key = trim((string)($_POST['cart_key'] ?? ''));

        if ($cart_key !== '' && isset($_SESSION['cart'][$cart_key])) {
            unset($_SESSION['cart'][$cart_key]);
            $_SESSION['cart_notice'] = 'Item removed from cart.';
            unset($_SESSION['cart_error']);
        }

        header('Location: cart.php');
        exit;
    }

    // CLEAR CART
    elseif ($action === 'clear') {
        $_SESSION['cart'] = [];

        $_SESSION['cart_notice'] = 'Shopping cart cleared.';
        unset($_SESSION['cart_error']);

        header('Location: cart.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$cart_notice = $_SESSION['cart_notice'] ?? '';
$cart_error = $_SESSION['cart_error'] ?? '';

unset(
    $_SESSION['cart_notice'],
    $_SESSION['cart_error']
);

/*
|--------------------------------------------------------------------------
| Load Cart Products
|--------------------------------------------------------------------------
*/

$cart_items = [];
$grand_total = 0.0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cart_key => $qty) {
        $parts = explode('_', (string)$cart_key);
        $pid = (int)$parts[0];
        $vid = isset($parts[1]) ? (int)$parts[1] : 0;

        if ($pid <= 0) {
            unset($_SESSION['cart'][$cart_key]);
            continue;
        }

        $stmt = $conn->prepare("SELECT id, name, price, stock, image FROM products WHERE id = ? LIMIT 1");
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($prod && (int)$prod['stock'] > 0) {
            $unit_price = (float)$prod['price'];
            $variant_name = '';

            // Check variant details
            if ($vid > 0) {
                $v_stmt = $conn->prepare("SELECT variant_name, price FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1");
                if ($v_stmt) {
                    $v_stmt->bind_param("ii", $vid, $pid);
                    $v_stmt->execute();
                    $v_res = $v_stmt->get_result()->fetch_assoc();
                    $v_stmt->close();

                    if ($v_res) {
                        $variant_name = $v_res['variant_name'];
                        $unit_price = (float)$v_res['price'];
                    }
                }
            }

            $valid_qty = min((int)$prod['stock'], max(1, (int)$qty));
            $_SESSION['cart'][$cart_key] = $valid_qty;

            $subtotal = $unit_price * $valid_qty;
            $grand_total += $subtotal;

            $prod['cart_key'] = (string)$cart_key;
            $prod['variant_id'] = $vid;
            $prod['variant_name'] = $variant_name;
            $prod['effective_price'] = $unit_price;
            $prod['cart_qty'] = $valid_qty;
            $prod['subtotal'] = $subtotal;

            $cart_items[] = $prod;
        } else {
            unset($_SESSION['cart'][$cart_key]);
        }
    }
}

$total_quantity = 0;
foreach ($cart_items as $item) {
    $total_quantity += (int) $item['cart_qty'];
}

/*
|--------------------------------------------------------------------------
| Page Metadata
|--------------------------------------------------------------------------
*/

$page_title = 'Shopping Cart | SKZ Store';
$meta_description =
    'Review your SKZ Store shopping cart, update product quantities, and continue to Cash on Delivery checkout.';
$meta_robots = 'noindex, follow';

require_once __DIR__ . '/header.php';
?>

<div id="cart-content" class="cart-page">

    <!-- Page Heading -->
    <header class="cart-page-header">
        <div class="cart-heading-content">
            <p class="cart-eyebrow">YOUR SHOPPING BAG</p>
            <h1>Your Shopping Cart</h1>
            <p class="cart-subtitle">
                Review your items before proceeding to checkout.
            </p>
        </div>

        <a href="index.php" class="btn btn-outline cart-continue">
            <span aria-hidden="true">&larr;</span>
            Continue Shopping
        </a>
    </header>

    <!-- Notifications -->
    <?php if ($cart_notice !== ''): ?>
        <div class="alert alert-success cart-message" role="status">
            <?php echo e($cart_notice); ?>
        </div>
    <?php endif; ?>

    <?php if ($cart_error !== ''): ?>
        <div class="alert alert-danger cart-message" role="alert">
            <?php echo e($cart_error); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>

        <!-- Empty Cart -->
        <section class="cart-empty-state">
            <div class="cart-empty-icon" aria-hidden="true">
                <svg
                    width="48"
                    height="48"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
            </div>

            <p class="cart-eyebrow">SKZ STORE</p>
            <h2>Your Cart Is Empty</h2>
            <p>
                You haven't added any products yet.
                Explore our collection and find something you love.
            </p>

            <a href="index.php" class="btn cart-browse-button">
                Browse Products
                <span aria-hidden="true">&rarr;</span>
            </a>
        </section>

    <?php else: ?>

        <!-- Cart Items -->
        <section aria-labelledby="cart-items-heading">

            <div class="cart-items-heading">
                <div>
                    <h2 id="cart-items-heading">Cart Items</h2>
                    <p>Check your products and quantities below.</p>
                </div>

                <span class="cart-items-count">
                    <?php echo count($cart_items); ?>
                    <?php echo count($cart_items) === 1 ? 'item' : 'items'; ?>
                </span>
            </div>

            <div class="cart-content-card">

                <!-- Cart Update Form -->
                <form method="post" action="cart.php">

                    <?php echo csrf_field(); ?>

                    <input type="hidden" name="action" value="update">

                    <div class="table-responsive cart-table-wrap">

                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Price</th>
                                    <th scope="col">Quantity</th>
                                    <th scope="col">Subtotal</th>
                                    <th scope="col">Remove</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($cart_items as $item): ?>

                                    <?php
                                    $item_id = (int) $item['id'];
                                    $cart_key = $item['cart_key'];

                                    $item_image = basename(
                                        (string) ($item['image'] ?? '')
                                    );

                                    $item_image_path =
                                        __DIR__ . '/uploads/' . $item_image;

                                    $has_image = $item_image !== ''
                                        && is_file($item_image_path);

                                    $item_image_url =
                                        'uploads/' . rawurlencode($item_image);
                                    ?>

                                    <tr>
                                        <!-- Product -->
                                        <td class="cart-product-cell" data-label="Product">
                                            <div class="cart-product">
                                                <a
                                                    href="product.php?id=<?php echo $item_id; ?>"
                                                    class="cart-product-image"
                                                    aria-label="View <?php echo e($item['name']); ?>"
                                                >
                                                    <?php if ($has_image): ?>
                                                        <img
                                                            src="<?php echo e($item_image_url); ?>"
                                                            alt="<?php echo e($item['name']); ?>"
                                                            width="76"
                                                            height="76"
                                                            loading="lazy"
                                                            decoding="async"
                                                        >
                                                    <?php else: ?>
                                                        <span class="no-image">No Image</span>
                                                    <?php endif; ?>
                                                </a>

                                                <div class="cart-product-info">
                                                    <a
                                                        href="product.php?id=<?php echo $item_id; ?>"
                                                        class="cart-product-name"
                                                    >
                                                        <?php echo e($item['name']); ?>
                                                    </a>

                                                    <?php if (!empty($item['variant_name'])): ?>
                                                        <div style="font-size: 13px; color: var(--primary, #2563eb); font-weight: 600; margin-top: 2px;">
                                                            Type: <?php echo e($item['variant_name']); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <span class="cart-stock-info">
                                                        <?php echo (int) $item['stock']; ?> available
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Price -->
                                        <td data-label="Price">
                                            <span class="cart-mobile-label">Price</span>
                                            <span class="cart-price">
                                                Rs. <?php echo number_format((float)$item['effective_price'], 2); ?>
                                            </span>
                                        </td>

                                        <!-- Quantity -->
                                        <td data-label="Quantity">
                                            <label
                                                class="cart-mobile-label"
                                                for="quantity-<?php echo e($cart_key); ?>"
                                            >
                                                Quantity
                                            </label>

                                            <input
                                                type="number"
                                                id="quantity-<?php echo e($cart_key); ?>"
                                                name="quantities[<?php echo e($cart_key); ?>]"
                                                value="<?php echo (int) $item['cart_qty']; ?>"
                                                min="1"
                                                max="<?php echo (int) $item['stock']; ?>"
                                                step="1"
                                                required
                                                inputmode="numeric"
                                                class="form-control qty-input cart-quantity-input"
                                                aria-label="Quantity for <?php echo e($item['name']); ?>"
                                            >
                                        </td>

                                        <!-- Subtotal -->
                                        <td data-label="Subtotal">
                                            <span class="cart-mobile-label">Subtotal</span>
                                            <strong class="cart-line-total">
                                                Rs. <?php echo number_format((float) $item['subtotal'], 2); ?>
                                            </strong>
                                        </td>

                                        <!-- Remove -->
                                        <td data-label="Remove">
                                            <button
                                                type="submit"
                                                form="remove-form-<?php echo e($cart_key); ?>"
                                                class="btn btn-danger btn-sm cart-remove-button"
                                                aria-label="Remove <?php echo e($item['name']); ?> from cart"
                                            >
                                                Remove
                                            </button>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    </div>

                    <!-- Cart Actions and Summary -->
                    <div class="cart-bottom">
                        <div class="cart-management-actions">
                            <button
                                type="submit"
                                class="btn cart-update-button"
                            >
                                Update Quantities
                            </button>

                            <button
                                type="submit"
                                form="clear-cart-form"
                                class="btn btn-outline cart-clear-button"
                                onclick="return confirm('Clear all items from your cart?');"
                            >
                                Clear Cart
                            </button>
                        </div>

                        <aside
                            class="cart-summary"
                            aria-labelledby="cart-summary-heading"
                        >
                            <p class="cart-summary-eyebrow">ORDER DETAILS</p>
                            <h2 id="cart-summary-heading">Order Summary</h2>

                            <div class="cart-summary-row">
                                <span>Different Items</span>
                                <span><?php echo count($cart_items); ?></span>
                            </div>

                            <div class="cart-summary-row">
                                <span>Total Units</span>
                                <span><?php echo $total_quantity; ?></span>
                            </div>

                            <div class="cart-summary-row cart-total-row">
                                <span>Grand Total</span>
                                <strong>
                                    Rs. <?php echo number_format($grand_total, 2); ?>
                                </strong>
                            </div>

                            <p class="cart-cod-note">
                                <span aria-hidden="true">✓</span>
                                Cash on Delivery available
                            </p>

                            <a
                                href="checkout.php"
                                class="btn cart-checkout-button"
                            >
                                Proceed to Checkout
                                <span aria-hidden="true">&rarr;</span>
                            </a>

                            <a href="index.php" class="cart-back-shopping">
                                Continue Shopping
                            </a>
                        </aside>
                    </div>

                </form>

                <!-- Separate Remove Forms -->
                <?php foreach ($cart_items as $item): ?>
                    <?php $cart_key = $item['cart_key']; ?>
                    <form
                        id="remove-form-<?php echo e($cart_key); ?>"
                        method="post"
                        action="cart.php"
                        hidden
                    >
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="remove">
                        <input
                            type="hidden"
                            name="cart_key"
                            value="<?php echo e($cart_key); ?>"
                        >
                    </form>
                <?php endforeach; ?>

                <!-- Clear Cart Form -->
                <form
                    id="clear-cart-form"
                    method="post"
                    action="cart.php"
                    hidden
                >
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="clear">
                </form>

            </div>

        </section>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>