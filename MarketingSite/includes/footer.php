<?php
if (!function_exists('site_url')) {
    require_once __DIR__ . '/../config/paths.php';
}
?>
</main>

<footer class="bg-dark text-white">
    <div class="container py-5">
        <div class="row gy-4">
            <div class="col-md-4 col-lg-3 text-center text-md-start">
                <h5 class="mb-2"><i class="fas fa-store me-2"></i>TechZone.pk</h5>
                <p class="mb-3 footer-text">
                    Premium computer accessories, delivered across Pakistan with local support you can trust.
                </p>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <h6 class="footer-title">Quick Links</h6>
                <a href="<?php echo htmlspecialchars(site_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="footer-link">Home</a>
                <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="footer-link">Products</a>
                <a href="<?php echo htmlspecialchars(site_url('about.php'), ENT_QUOTES, 'UTF-8'); ?>" class="footer-link">About Us</a>
                <a href="<?php echo htmlspecialchars(site_url('contact.php'), ENT_QUOTES, 'UTF-8'); ?>" class="footer-link">Contact</a>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <h6 class="footer-title">Customer Care</h6>
                <a href="#" class="footer-link">How to Order</a>
                <a href="#" class="footer-link">Shipping & Delivery</a>
                <a href="#" class="footer-link">Returns & Warranty</a>
                <a href="#" class="footer-link">FAQs</a>
            </div>
            <div class="col-md-4 col-lg-3 text-center text-md-start">
                <h6 class="footer-title">Contact</h6>
                <p class="footer-text mb-1">
                    <i class="fas fa-phone me-2"></i>+92 301 1427871
                </p>
                <p class="footer-text mb-3">
                    <i class="fas fa-envelope me-2"></i>info@techzone.pk
                </p>
                <div class="social-links">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>
        <hr style="border-color: rgba(255, 255, 255, 0.1); margin: 1.75rem 0;">
        <div class="row">
            <div class="col-12 text-center">
                <p class="mb-0 footer-text-small">© <?php echo date('Y'); ?> TechZone.pk. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
