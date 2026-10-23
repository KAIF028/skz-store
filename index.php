
<?php
require_once __DIR__ . '/db.php';

/*
|--------------------------------------------------------------------------
| SEO Settings
|--------------------------------------------------------------------------
*/

$page_title = 'SKZ Store | Shop Quality Products Online';
$meta_description = 'Shop quality products at SKZ Store. Explore our product collection, enjoy convenient Cash on Delivery, and order online.';
$meta_robots = 'index, follow';

$site_url = '';
$canonical_url = $site_url !== ''
    ? rtrim($site_url, '/') . '/'
    : '';

/*
|--------------------------------------------------------------------------
| Fetch Products
|--------------------------------------------------------------------------
*/

$products_res = $conn->query(
    "SELECT * FROM products ORDER BY id DESC"
);

require_once __DIR__ . '/header.php';
?>

<div class="home-page" id="main-content">

    <!-- Hero Section -->
    <section class="home-hero" aria-labelledby="home-heading">

        <div class="home-hero-content">

            <span class="home-eyebrow">
                WELCOME TO SKZ STORE
            </span>

            <h1 id="home-heading">
                Find Your Next
                <span>Favourite Product.</span>
            </h1>

            <p class="home-hero-description">
                Explore our latest collection, shop with confidence
                and enjoy convenient Cash on Delivery across your orders.
            </p>

            <div class="home-hero-actions">
                <a href="#products" class="btn home-primary-btn">
                    Explore Products <span aria-hidden="true">→</span>
                </a>

                <a href="cart.php" class="home-secondary-btn">
                    View Your Cart
                </a>
            </div>

            <div class="home-benefits">
                <span><span aria-hidden="true">✓</span> Easy Online Shopping</span>
                <span><span aria-hidden="true">✓</span> Cash on Delivery</span>
            </div>

        </div>

        <div class="home-hero-visual" aria-hidden="true">
            <div class="hero-decoration hero-decoration-one"></div>
            <div class="hero-decoration hero-decoration-two"></div>

            <div class="hero-shopping-card">
                <div class="hero-shopping-icon">S</div>
                <span class="hero-card-label">YOUR SHOPPING DESTINATION</span>
                <strong>Quality finds.<br>Everyday convenience.</strong>
                <span class="hero-card-bottom">Discover something you love <span>↗</span></span>
            </div>

            <div class="hero-floating-tag">
                <span class="hero-floating-check">✓</span>
                <span>
                    <strong>Shop with ease</strong>
                    <small>Your order, your way</small>
                </span>
            </div>
        </div>

    </section>


    <!-- Product Listing Section -->
    <section class="home-products-section"
             id="products"
             aria-labelledby="products-heading">

        <div class="home-products-heading">

            <div>
                <span class="home-section-eyebrow">OUR COLLECTION</span>

                <h2 id="products-heading">
                    Explore Our Products
                </h2>

                <p class="home-section-description">
                    Find the products you need, all in one place.
                </p>
            </div>

            <div class="home-product-count">
                <span aria-hidden="true">✦</span>
                Quality shopping starts here
            </div>

        </div>


        <!-- Product Search -->
        <div class="product-search-panel">

            <div class="product-search-label">
                <span class="search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="10.8" cy="10.8" r="6.8"
                                stroke="currentColor" stroke-width="1.8"/>
                        <path d="m16 16 4.2 4.2"
                              stroke="currentColor" stroke-width="1.8"
                              stroke-linecap="round"/>
                    </svg>
                </span>

                <div>
                    <strong>What are you looking for?</strong>
                    <span>Search by product name or description</span>
                </div>
            </div>

            <div class="product-search-input-wrap">

                <label for="productSearch" class="sr-only">
                    Search products
                </label>

                <input
                    type="search"
                    id="productSearch"
                    class="product-search-input"
                    placeholder="Search products..."
                    autocomplete="off"
                    aria-controls="homeProductGrid"
                >

                <button
                    type="button"
                    id="clearProductSearch"
                    class="clear-search-btn"
                    hidden
                >
                    Clear
                </button>

            </div>

        </div>


        <?php if ($products_res->num_rows === 0): ?>

            <div class="home-empty-state">
                <div class="home-empty-icon" aria-hidden="true">✦</div>
                <h3>Our Collection Is Coming Soon</h3>
                <p>We are updating our products. Please check back soon.</p>
            </div>

        <?php else: ?>

            <div class="product-grid home-product-grid" id="homeProductGrid">

                <?php while ($product = $products_res->fetch_assoc()): ?>

                    <?php
                    $product_id = (int) $product['id'];
                    $product_name = (string) $product['name'];
                    $product_description = (string) ($product['description'] ?? '');
                    $product_price = (float) $product['price'];
                    $product_stock = (int) $product['stock'];

                    $product_url = 'product.php?id=' . $product_id;

                    $image_name = basename((string) ($product['image'] ?? ''));
                    $image_path = __DIR__ . '/uploads/' . $image_name;

                    $has_image = $image_name !== ''
                        && is_file($image_path);

                    $short_description = mb_strimwidth(
                        $product_description,
                        0,
                        110,
                        '...',
                        'UTF-8'
                    );

                    $search_text = $product_name . ' ' . $product_description;
                    ?>

                    <article
                        class="product-card home-product-card"
                        data-search="<?php echo e($search_text); ?>"
                    >

                        <!-- Product Image -->
                        <a
                            href="<?php echo e($product_url); ?>"
                            class="product-thumb home-product-thumb"
                            aria-label="View <?php echo e($product_name); ?>"
                        >

                            <?php if ($has_image): ?>

                                <img
                                    src="uploads/<?php echo rawurlencode($image_name); ?>"
                                    alt="<?php echo e($product_name); ?>"
                                    loading="lazy"
                                    decoding="async"
                                    width="400"
                                    height="400"
                                >

                            <?php else: ?>

                                <span class="no-image">
                                    Image Coming Soon
                                </span>

                            <?php endif; ?>

                            <span class="product-image-label">
                                SKZ STORE
                            </span>

                        </a>


                        <!-- Product Information -->
                        <div class="product-body home-product-body">

                            <h3 class="product-title home-product-title">
                                <a href="<?php echo e($product_url); ?>">
                                    <?php echo e($product_name); ?>
                                </a>
                            </h3>

                            <p class="product-desc home-product-description">
                                <?php
                                echo e(
                                    $short_description !== ''
                                        ? $short_description
                                        : 'Discover more details about this product.'
                                );
                                ?>
                            </p>


                            <div class="home-product-meta">

                                <p class="product-price home-product-price">
                                    <span class="sr-only">Price:</span>
                                    Rs. <?php echo number_format($product_price, 2); ?>
                                </p>

                                <?php if ($product_stock > 0): ?>

                                    <span class="home-stock-status">
                                        <span aria-hidden="true">●</span>
                                        In Stock
                                    </span>

                                <?php else: ?>

                                    <span class="home-stock-status out-of-stock">
                                        <span aria-hidden="true">●</span>
                                        Out of Stock
                                    </span>

                                <?php endif; ?>

                            </div>


                            <!-- Product Actions -->
                            <div class="product-actions home-product-actions">

                                <a
                                    href="<?php echo e($product_url); ?>"
                                    class="btn btn-outline btn-sm home-details-btn"
                                >
                                    View Details
                                </a>

                                <?php if ($product_stock > 0): ?>

                                    <form
                                        method="post"
                                        action="cart.php"
                                        class="home-add-cart-form"
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
                                            value="<?php echo $product_id; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="quantity"
                                            value="1"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-block home-add-cart-btn"
                                        >
                                            Add to Cart <span aria-hidden="true">+</span>
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm btn-block home-disabled-btn"
                                        disabled
                                    >
                                        Unavailable
                                    </button>

                                <?php endif; ?>

                            </div>

                        </div>

                    </article>

                <?php endwhile; ?>

            </div>


            <!-- No Search Results -->
            <div id="noProductResults"
                 class="home-empty-state"
                 role="status"
                 aria-live="polite"
                 hidden>

                <div class="home-empty-icon" aria-hidden="true">⌕</div>

                <h3>No Products Found</h3>

                <p>
                    Try another product name or use a different search term.
                </p>

                <button type="button" id="resetProductSearch" class="btn">
                    Show All Products
                </button>

            </div>

        <?php endif; ?>

    </section>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('productSearch');
    const clearButton = document.getElementById('clearProductSearch');
    const resetButton = document.getElementById('resetProductSearch');
    const productGrid = document.getElementById('homeProductGrid');
    const noResults = document.getElementById('noProductResults');

    if (!searchInput || !productGrid) {
        return;
    }

    const products = Array.from(
        productGrid.querySelectorAll('.home-product-card')
    );

    function filterProducts() {
        const searchTerm = searchInput.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        products.forEach(function (product) {
            const searchableText = (
                product.getAttribute('data-search') || ''
            ).toLocaleLowerCase();

            const matches = searchableText.includes(searchTerm);

            product.hidden = !matches;

            if (matches) {
                visibleCount++;
            }
        });

        clearButton.hidden = searchTerm.length === 0;

        if (noResults) {
            noResults.hidden = visibleCount !== 0;
        }
    }

    searchInput.addEventListener('input', filterProducts);

    clearButton.addEventListener('click', function () {
        searchInput.value = '';
        filterProducts();
        searchInput.focus();
    });

    if (resetButton) {
        resetButton.addEventListener('click', function () {
            searchInput.value = '';
            filterProducts();
            searchInput.focus();
        });
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
