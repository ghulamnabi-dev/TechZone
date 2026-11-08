<?php
if (!function_exists('site_url')) {
    require_once __DIR__ . '/../config/paths.php';
}
$tzFooterCats = $GLOBALS['tz_categories'] ?? [];
$tzF = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row gy-5 site-footer__cols">
            <div class="col-6 col-lg-3">
                <h2 class="site-footer__title">Shop by category</h2>
                <ul class="site-footer__list">
                    <?php foreach ($tzFooterCats as $c): ?>
                        <li><a href="<?php echo $tzF(site_url('shop/products.php?category=' . (int) $c['id'])); ?>"><?php echo $tzF($c['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="site-footer__title">Help</h2>
                <ul class="site-footer__list">
                    <li><a href="<?php echo $tzF(site_url('about.php#how-to-order')); ?>">How to order</a></li>
                    <li><a href="<?php echo $tzF(site_url('about.php#shipping')); ?>">Shipping</a></li>
                    <li><a href="<?php echo $tzF(site_url('about.php#returns')); ?>">Returns</a></li>
                    <li><a href="<?php echo $tzF(site_url('about.php#warranty')); ?>">Warranty</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="site-footer__title">Company</h2>
                <ul class="site-footer__list">
                    <li><a href="<?php echo $tzF(site_url('about.php')); ?>">About</a></li>
                    <li><a href="<?php echo $tzF(site_url('contact.php')); ?>">Contact</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="site-footer__title">Contact</h2>
                <ul class="site-footer__list site-footer__contact">
                    <li><a href="tel:+923011427871">+92 301 1427871</a></li>
                    <li><a href="mailto:info@techzone.pk">info@techzone.pk</a></li>
                    <li>Hafeez Centre, Gulberg III, Lahore</li>
                </ul>
            </div>
        </div>
        <div class="site-footer__bottom">
            <p>&copy; <?php echo date('Y'); ?> TechZone.pk</p>
            <ul class="pay-badges" aria-label="Payment methods">
                <li>Cash on delivery</li>
                <li>JazzCash</li>
            </ul>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
