<?php
/**
 * Seller portal: store profile (seller.portal). The seller edits about, phone, WhatsApp and address
 * (save bar, inline errors); name, market, commission and status are TechZone's (read-only here).
 * Data: api/seller_portal.php (assets/js/store.js).
 */
$pageTitle = 'Store profile';
$pageSubtitle = 'How your shop appears on TechZone';
$pageCap = 'seller.portal';
$pageActions = '<button type="button" class="btn btn-primary" id="stSave" disabled><i class="fas fa-check"></i> Save</button>';
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= $tzAsset('assets/css/sellers.css') ?>">

<div class="prd-layout">
    <div class="prd-main">
        <form class="panel" id="stForm" novalidate aria-labelledby="stTitle">
            <div class="panel-head"><h2 class="panel-title" id="stTitle">Shop details</h2><span class="spacer"></span><span class="panel-sub">Shown on your seller page and products</span></div>
            <div class="panel-body prd-grid2">
                <div class="prd-field prd-span2">
                    <label class="form-label" for="stAbout">About your shop <span class="prd-count" id="stAboutCount"></span></label>
                    <textarea class="form-control" id="stAbout" name="about" rows="4" maxlength="1000" placeholder="What you sell, warranty, how fast you dispatch"></textarea>
                </div>
                <div class="prd-field">
                    <label class="form-label" for="stPhone">Shop phone</label>
                    <input type="tel" class="form-control tabular" id="stPhone" name="phone" maxlength="30" placeholder="042-35761234" autocomplete="tel">
                </div>
                <div class="prd-field">
                    <label class="form-label" for="stWhatsapp">WhatsApp</label>
                    <input type="tel" class="form-control tabular" id="stWhatsapp" name="whatsapp" maxlength="30" placeholder="923001234567">
                    <div class="form-text">With country code. 0300-1234567 is turned into 923001234567.</div>
                </div>
                <div class="prd-field prd-span2">
                    <label class="form-label" for="stAddress">Shop address</label>
                    <input type="text" class="form-control" id="stAddress" name="address" maxlength="255" placeholder="Shop, floor, building, market" autocomplete="street-address">
                </div>
            </div>
        </form>
    </div>
    <aside class="prd-side">
        <section class="panel" aria-labelledby="stSideTitle">
            <div class="panel-head"><h2 class="panel-title" id="stSideTitle">On TechZone</h2></div>
            <div class="panel-body">
                <dl class="kv" id="stFacts"><dt>Loading…</dt><dd></dd></dl>
                <p class="ord-hint mt-3 mb-0">To change your shop name, market or commission, contact TechZone.</p>
            </div>
        </section>
    </aside>
</div>

<script src="<?= $tzAsset('assets/js/form-helpers.js') ?>"></script>
<script src="<?= $tzAsset('assets/js/store.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
