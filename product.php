<?php
require_once __DIR__ . '/db.php';

/*
|--------------------------------------------------------------------------
| Product ID
|--------------------------------------------------------------------------
*/

$product_id = isset($_GET['id']) && is_numeric($_GET['id'])
    ? (int) $_GET['id']
    : 0;

$product = null;
$variants = [];

if ($product_id > 0) {
    $stmt = $conn->prepare(
        "SELECT * FROM products WHERE id = ? LIMIT 1"
    );

    if ($stmt) {
        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        $stmt->close();
    }

    // Fetch product variants if available
    if ($product) {
        $v_stmt = $conn->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
        if ($v_stmt) {
            $v_stmt->bind_param("i", $product_id);
            $v_stmt->execute();
            $v_res = $v_stmt->get_result();
            while ($v_row = $v_res->fetch_assoc()) {
                $variants[] = $v_row;
            }
            $v_stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| SEO Metadata
|--------------------------------------------------------------------------
*/

if ($product) {
    $product_name = (string) $product['name'];

    $page_title = $product_name . ' | SKZ Store';

    $description_text = trim(
        preg_replace(
            '/\s+/u',
            ' ',
            strip_tags((string) ($product['description'] ?? ''))
        )
    );

    if ($description_text === '') {
        $description_text = 'Explore ' . $product_name .
            ' at SKZ Store. Check product details, price and availability.';
    }

    $meta_description = function_exists('mb_strimwidth')
        ? mb_strimwidth($description_text, 0, 155, '...', 'UTF-8')
        : substr($description_text, 0, 152) . '...';

    $meta_robots = 'index, follow';
} else {
    $page_title = 'Product Not Found | SKZ Store';
    $meta_description =
        'This product is unavailable. Explore other products at SKZ Store.';
    $meta_robots = 'noindex, follow';
}

/*
|--------------------------------------------------------------------------
| Canonical URL
|--------------------------------------------------------------------------
| Add your real HTTPS domain after deployment.
| Keep empty on localhost.
*/

$site_url = '';

$canonical_url = (
    $product && $site_url !== ''
)
    ? rtrim($site_url, '/') .
        '/product.php?id=' . (int) $product['id']
    : '';

require_once __DIR__ . '/header.php';
?>

<div id="product-content" class="product-page">

    <!-- Breadcrumb -->
    <nav class="product-breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>

        <span class="breadcrumb-separator" aria-hidden="true">/</span>

        <?php if ($product): ?>
            <span aria-current="page">
                <?php echo e($product['name']); ?>
            </span>
        <?php else: ?>
            <span aria-current="page">Product Not Found</span>
        <?php endif; ?>
    </nav>

    <?php if (!$product): ?>

        <!-- Product Not Found -->
        <section class="product-not-found">

            <div class="not-found-icon" aria-hidden="true">!</div>

            <p class="product-store-label">SKZ STORE</p>

            <h1>Product Not Found</h1>

            <p>
                Sorry, this product does not exist or may have
                been removed from our store.
            </p>

            <a href="index.php" class="btn product-back-button">
                Explore Products
            </a>

        </section>

    <?php else: ?>

        <?php
        $product_name = (string) $product['name'];
        $product_description = (string) ($product['description'] ?? '');
        
        // Agar variants hon, toh default price pehle variant ki hogi
        $has_variants = !empty($variants);
        $initial_price = $has_variants ? (float)$variants[0]['price'] : (float)$product['price'];

        $product_stock = max(0, (int) $product['stock']);
        $product_image = basename((string) ($product['image'] ?? ''));

        $image_path = __DIR__ . '/uploads/' . $product_image;

        $has_image = $product_image !== ''
            && is_file($image_path);

        $image_url = 'uploads/' . rawurlencode($product_image);
        ?>

        <!-- Product Details -->
        <article class="product-detail-card">

            <!-- Product Image -->
            <div class="product-detail-img">

                <div class="product-image-frame">

                    <?php if ($has_image): ?>

                        <img
                            src="<?php echo e($image_url); ?>"
                            alt="<?php echo e($product_name); ?>"
                            width="600"
                            height="600"
                            fetchpriority="high"
                            decoding="async"
                        >

                    <?php else: ?>

                        <div class="product-image-placeholder">
                            <span class="placeholder-icon" aria-hidden="true">
                                ◇
                            </span>

                            <span>Image Coming Soon</span>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="image-caption">
                    <span class="image-caption-check" aria-hidden="true">✓</span>
                    Quality Products at SKZ Store
                </div>

            </div>

            <!-- Product Information -->
            <div class="product-detail-info">

                <p class="product-store-label">SKZ STORE</p>

                <!-- Stock Status -->
                <div class="product-status-row">

                    <?php if ($product_stock > 0): ?>

                        <span class="stock-badge in-stock">
                            <span aria-hidden="true">✓</span>
                            In Stock
                        </span>

                    <?php else: ?>

                        <span class="stock-badge out-stock">
                            Out of Stock
                        </span>

                    <?php endif; ?>

                </div>

                <!-- Product Name -->
                <h1 class="product-detail-title">
                    <?php echo e($product_name); ?>
                </h1>

                <!-- Price Panel -->
                <div class="product-price-panel">
                    <span class="product-price-label">Our Price</span>

                    <p class="product-detail-price">
                        <span class="sr-only">Price: </span>
                        Rs. <span id="display-price"><?php echo number_format($initial_price, 2); ?></span>
                    </p>

                    <span class="price-note">
                        Convenient Cash on Delivery
                    </span>
                </div>

                <!-- Description -->
                <section
                    class="product-description"
                    aria-labelledby="description-heading"
                >

                    <h2 id="description-heading">Product Description</h2>

                    <?php if (trim($product_description) !== ''): ?>

                        <div class="product-description-text">
                            <?php echo nl2br(e($product_description)); ?>
                        </div>

                    <?php else: ?>

                        <p>
                            Contact us for more information about this product.
                        </p>

                    <?php endif; ?>

                </section>

                <!-- Purchase Form -->
                <?php if ($product_stock > 0): ?>

                    <form
                        method="post"
                        action="cart.php"
                        class="product-purchase-form"
                    >

                        <?php echo csrf_field(); ?>

                        <input
                            type="hidden"
                            name="action"
                            value="add"
                        >

                        <input
                            type="hidden"
                            name="product_id"
                            value="<?php echo (int) $product['id']; ?>"
                        >

                        <!-- Variant / Ampere Selection Dropdown -->
                        <?php if ($has_variants): ?>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="variant_id" style="font-weight: 600; display: block; margin-bottom: 6px;">
                                    Select Rating / Option:
                                </label>
                                <select 
                                    name="variant_id" 
                                    id="variant_id" 
                                    class="form-control" 
                                    required 
                                    onchange="updateVariantPrice(this)"
                                    style="padding: 10px; width: 100%; max-width: 320px; font-size: 15px;"
                                >
                                    <?php foreach ($variants as $v): ?>
                                        <option value="<?php echo (int)$v['id']; ?>" data-price="<?php echo (float)$v['price']; ?>">
                                            <?php echo e($v['variant_name']); ?> — Rs. <?php echo number_format((float)$v['price'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="quantity-field">

                            <label for="quantity">Select Quantity</label>

                            <div class="quantity-input-wrap">
                                <input
                                    type="number"
                                    id="quantity"
                                    name="quantity"
                                    class="form-control qty-input"
                                    value="1"
                                    min="1"
                                    max="<?php echo $product_stock; ?>"
                                    step="1"
                                    required
                                    inputmode="numeric"
                                >
                            </div>

                            <small>
                                <?php echo $product_stock; ?> item(s) available
                            </small>

                        </div>

                        <button
                            type="submit"
                            class="btn product-add-button"
                        >
                            <span aria-hidden="true">🛒</span>
                            Add to Cart
                        </button>

                    </form>

                <?php else: ?>

                    <div class="alert alert-danger product-stock-alert"
                         role="status">
                        This product is currently out of stock.
                    </div>

                <?php endif; ?>

                <!-- Shopping Benefits -->
                <div class="product-benefits">

                    <div class="product-benefit">
                        <span class="benefit-icon" aria-hidden="true">✓</span>

                        <div>
                            <strong>Cash on Delivery</strong>
                            <p>Pay when your order arrives.</p>
                        </div>
                    </div>

                    <div class="product-benefit">
                        <span class="benefit-icon" aria-hidden="true">✓</span>

                        <div>
                            <strong>Easy Ordering</strong>
                            <p>Order directly through SKZ Store.</p>
                        </div>
                    </div>

                </div>

                <a href="index.php" class="continue-shopping-link">
                    <span aria-hidden="true">←</span>
                    Continue Shopping
                </a>

            </div>

        </article>

    <?php endif; ?>

</div>

<script>
function updateVariantPrice(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
    const formattedPrice = price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('display-price').innerText = formattedPrice;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>