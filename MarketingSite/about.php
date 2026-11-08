<?php
$pageTitle = 'About';
require_once 'includes/header.php';
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>

<div class="container page">
    <div class="page-intro">
        <h1>About TechZone</h1>
        <p class="lead-text">TechZone is a computer parts shop in Hafeez Centre, Lahore. We sell components, peripherals, monitors, networking gear and office furniture to builders, gamers and businesses, and deliver across Pakistan.</p>
    </div>

    <div class="row g-4 g-lg-5 about">
        <div class="col-lg-8">
            <section class="about__block">
                <h2>What we do</h2>
                <p>We keep the parts people actually build with in stock: current processors and graphics cards, motherboards, memory, storage, power supplies, and the monitors, keyboards and mice that go with them. Every item is sourced through local distributors and sold with its official warranty.</p>
                <p>If you are planning a build, send us the parts list and we will check compatibility and stock before you order. We can also assemble and test the system at the shop.</p>
            </section>

            <section class="about__block" id="how-to-order">
                <h2>How to order</h2>
                <ol class="plain-list">
                    <li>Add products to your cart and sign in or create an account.</li>
                    <li>Enter your delivery address and choose cash on delivery or JazzCash.</li>
                    <li>We confirm the order by phone before it is dispatched.</li>
                </ol>
                <p>Promo codes are entered in the cart and applied to the order total.</p>
            </section>

            <section class="about__block" id="shipping">
                <h2>Shipping</h2>
                <p>Orders in Lahore are delivered in 2 to 4 working days, and in 3 to 6 working days to other cities through our courier partners. You can also pick up your order from the shop once we tell you it is ready.</p>
            </section>

            <section class="about__block" id="returns">
                <h2>Returns</h2>
                <p>You can return an unopened item within 7 days of delivery. If a product arrives damaged or does not work, contact us within 7 days and we will replace it or refund you after inspection.</p>
            </section>

            <section class="about__block" id="warranty">
                <h2>Warranty</h2>
                <p>Products carry the manufacturer's or distributor's official warranty for Pakistan. Bring or send the item to the shop with your order number and we will handle the claim with the distributor.</p>
            </section>
        </div>

        <div class="col-lg-4">
            <aside class="info-box">
                <h2 class="info-box__title">Visit the shop</h2>
                <dl class="info-list">
                    <div><dt>Address</dt><dd>Hafeez Centre, Gulberg III, Lahore</dd></div>
                    <div><dt>Hours</dt><dd>Monday to Saturday, 10am to 8pm</dd></div>
                    <div><dt>Phone</dt><dd><a href="tel:+923011427871">+92 301 1427871</a></dd></div>
                    <div><dt>Email</dt><dd><a href="mailto:info@techzone.pk">info@techzone.pk</a></dd></div>
                </dl>
                <a href="<?php echo $h(site_url('contact.php')); ?>" class="btn btn-line w-100">Contact us</a>
            </aside>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
