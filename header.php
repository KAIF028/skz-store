
<?php
require_once __DIR__ . '/db.php';

$cart_count = get_cart_count();

/*
|--------------------------------------------------------------------------
| Page SEO Metadata
|--------------------------------------------------------------------------
*/

$page_title = isset($page_title) && trim($page_title) !== ''
    ? trim($page_title) . ' — SKZ Store'
    : 'SKZ Store — Quality Products Delivered';

$meta_description = isset($meta_description) && trim($meta_description) !== ''
    ? trim($meta_description)
    : 'Shop quality products at SKZ Store. Explore our products and enjoy convenient Cash on Delivery.';

$meta_robots = isset($meta_robots) && trim($meta_robots) !== ''
    ? trim($meta_robots)
    : 'index, follow';

$canonical_url = isset($canonical_url) ? trim($canonical_url) : '';
$canonical_url = filter_var($canonical_url, FILTER_VALIDATE_URL)
    ? $canonical_url
    : '';

$site_name = 'SKZ Store';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo e($page_title); ?></title>

    <meta name="description" content="<?php echo e($meta_description); ?>">
    <meta name="robots" content="<?php echo e($meta_robots); ?>">

    <?php if ($canonical_url !== ''): ?>
        <link rel="canonical" href="<?php echo e($canonical_url); ?>">
    <?php endif; ?>

    <meta name="theme-color" content="#0f172a">

    <!-- Open Graph -->
    <meta property="og:site_name" content="<?php echo e($site_name); ?>">
    <meta property="og:title" content="<?php echo e($page_title); ?>">
    <meta property="og:description" content="<?php echo e($meta_description); ?>">
    <meta property="og:type" content="website">

    <?php if ($canonical_url !== ''): ?>
        <meta property="og:url" content="<?php echo e($canonical_url); ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<header class="site-header">
    <div class="container header-inner">

        <!-- Store Logo -->
        <a href="index.php" class="logo" aria-label="SKZ Store Home">
            SKZ <span>Store</span>
        </a>

        <!-- Mobile Menu Button -->
        <button
            type="button"
            class="menu-toggle"
            id="menuToggle"
            aria-label="Open navigation menu"
            aria-controls="mainNav"
            aria-expanded="false"
        >
            <svg
                class="menu-toggle-icon"
                viewBox="0 0 24 24"
                width="24"
                height="24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                aria-hidden="true"
            >
                <path d="M4 6h16"></path>
                <path d="M4 12h16"></path>
                <path d="M4 18h16"></path>
            </svg>
        </button>

        <!-- Main Navigation -->
        <nav class="main-nav" id="mainNav" aria-label="Main navigation">

            <a href="index.php" class="nav-link">
                Home
            </a>

            <a href="index.php#products" class="nav-link">
                Shop
            </a>

            <a
                href="cart.php"
                class="cart-link"
                aria-label="Shopping cart, <?php echo (int) $cart_count; ?> items"
            >
                <svg
                    class="icon-cart"
                    viewBox="0 0 24 24"
                    width="19"
                    height="19"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    aria-hidden="true"
                    focusable="false"
                >
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>

                <span>Cart</span>

                <span class="cart-badge">
                    <?php echo (int) $cart_count; ?>
                </span>
            </a>

        </nav>

    </div>
</header>

<main class="main-content container">
