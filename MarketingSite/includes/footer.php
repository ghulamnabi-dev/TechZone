<?php
if (!function_exists('site_url')) {
    require_once __DIR__ . '/../config/paths.php';
}
$tzF = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$tzFooterTree = $GLOBALS['tz_category_tree'] ?? [];
$tzFooterSet = static function (string $key, string $fallback = '') {
    if (!function_exists('setting')) return $fallback;
    try {
        $v = setting($key, $fallback);
    } catch (Throwable $e) {
        $v = $fallback;
    }
    return is_scalar($v) ? trim((string) $v) : $fallback;
};
$tzFootAddress = $tzFooterSet('store_address', 'Shop 214, 2nd floor, Hafeez Centre, Gulberg III, Lahore');
$tzFootHours = $tzFooterSet('store_hours', 'Mon–Sat 11 am – 10 pm, Sunday closed');
$tzFootPhone = $tzFooterSet('store_phone', '042-35761234');
$tzFootWa = $tzFooterSet('whatsapp_number', '923001234567');
$tzFootEmail = $tzFooterSet('store_email', 'support@techzone.pk');
$tzFootLegal = $tzFooterSet('store_legal_name', 'TechZone Traders');
?>
</main>

<div class="compare-bar" id="compareBar" hidden>
    <div class="container compare-bar__row">
        <p class="compare-bar__text" aria-live="polite"><strong class="compare-bar__count">0</strong> <span class="compare-bar__label">parts selected to compare</span></p>
        <button type="button" class="compare-bar__clear" data-compare-clear>Clear</button>
        <a class="btn--on-black compare-bar__go" href="<?php echo $tzF(site_url('compare.php')); ?>" data-compare-go>Compare (<span class="compare-bar__n">0</span>)</a>
    </div>
</div>

<footer class="site-footer">
    <div class="container">
        <div class="site-footer__top">
            <a class="wordmark wordmark--footer" href="<?php echo $tzF(site_url('index.php')); ?>"><span class="wordmark__mark" aria-hidden="true"></span><span class="wordmark__text">TechZone</span></a>
            <p class="site-footer__pitch">Today's rates on PC parts from Hafeez Centre and Hall Road, delivered anywhere in Pakistan.</p>
        </div>
        <div class="site-footer__cols">
            <div class="site-footer__col">
                <h2 class="site-footer__title">Shop</h2>
                <ul class="site-footer__list">
                    <?php foreach ($tzFooterTree as $c): ?>
                        <li><a href="<?php echo $tzF(site_url('shop/products.php?category=' . (int) $c['id'])); ?>"><?php echo $tzF($c['name']); ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?php echo $tzF(site_url('rates.php')); ?>">Today's rates</a></li>
                    <li><a href="<?php echo $tzF(site_url('deals.php')); ?>">Price drops</a></li>
                    <li><a href="<?php echo $tzF(site_url('builder.php')); ?>">Build a PC</a></li>
                </ul>
            </div>
            <div class="site-footer__col">
                <h2 class="site-footer__title">Help</h2>
                <ul class="site-footer__list">
                    <li><a href="<?php echo $tzF(site_url('help/how-to-order.php')); ?>">How to order</a></li>
                    <li><a href="<?php echo $tzF(site_url('help/shipping.php')); ?>">Shipping and delivery</a></li>
                    <li><a href="<?php echo $tzF(site_url('help/returns.php')); ?>">Returns</a></li>
                    <li><a href="<?php echo $tzF(site_url('help/warranty.php')); ?>">Warranty claims</a></li>
                    <li><a href="<?php echo $tzF(site_url('help/payments.php')); ?>">Payments and installments</a></li>
                    <li><a href="<?php echo $tzF(site_url('help/faq.php')); ?>">Questions and answers</a></li>
                    <li><a href="<?php echo $tzF(site_url('track.php')); ?>">Track your order</a></li>
                </ul>
            </div>
            <div class="site-footer__col">
                <h2 class="site-footer__title">Company</h2>
                <ul class="site-footer__list">
                    <li><a href="<?php echo $tzF(site_url('about.php')); ?>">About TechZone</a></li>
                    <li><a href="<?php echo $tzF(site_url('store.php')); ?>">Visit our store</a></li>
                    <li><a href="<?php echo $tzF(site_url('sellers/')); ?>">Sellers</a></li>
                    <li><a href="<?php echo $tzF(site_url('sell/')); ?>">Sell on TechZone</a></li>
                    <li><a href="<?php echo $tzF(site_url('contact.php')); ?>">Contact us</a></li>
                </ul>
            </div>
            <div class="site-footer__col site-footer__col--contact">
                <h2 class="site-footer__title">Contact</h2>
                <address class="site-footer__address">
                    <p><?php echo $tzF($tzFootAddress); ?></p>
                    <?php if ($tzFootHours !== ''): ?><p><?php echo $tzF($tzFootHours); ?></p><?php endif; ?>
                </address>
                <ul class="site-footer__list site-footer__contact">
                    <?php if ($tzFootPhone !== ''): ?><li><a href="tel:<?php echo $tzF(preg_replace('/[^\d+]/', '', $tzFootPhone)); ?>"><?php echo tz_icon('phone', 16); ?><?php echo $tzF($tzFootPhone); ?></a></li><?php endif; ?>
                    <?php if ($tzFootWa !== ''): ?><li><a href="https://wa.me/<?php echo $tzF(whatsapp_digits($tzFootWa)); ?>" rel="noopener"><?php echo tz_icon('whatsapp', 16); ?>WhatsApp <?php echo $tzF(format_phone_pk($tzFootWa)); ?></a></li><?php endif; ?>
                    <?php if ($tzFootEmail !== ''): ?><li><a href="mailto:<?php echo $tzF($tzFootEmail); ?>"><?php echo tz_icon('mail', 16); ?><?php echo $tzF($tzFootEmail); ?></a></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="site-footer__bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $tzF($tzFootLegal ?: 'TechZone'); ?>, Lahore. Prices are in Pakistani rupees and change with the market.</p>
            <ul class="pay-badges" aria-label="Ways to pay">
                <li>Cash on delivery</li>
                <li>JazzCash</li>
                <li>Bank installments</li>
            </ul>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
