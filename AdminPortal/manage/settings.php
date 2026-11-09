<?php
/**
 * Settings: Store · Shipping · Payments · Staff · Activity, one URL per section (?tab=…).
 * Owners see every section; managers only Activity (and land there); staff get 403.
 * The APIs (api/settings.php, shipping.php, staff.php, activity.php) enforce the same capabilities.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/settings.php';
checkLogin();

$setTabs = [
    'store'    => ['Store', 'fa-store', 'settings.write', 'Name, contact details and the storefront notice'],
    'shipping' => ['Shipping', 'fa-truck', 'shipping.write', 'Zones, rates and free-delivery thresholds'],
    'payments' => ['Payments', 'fa-wallet', 'payments.write', 'Cash on delivery and JazzCash at checkout'],
    'staff'    => ['Staff & roles', 'fa-user-shield', 'staff.manage', 'Who can sign in to the admin and what they can do'],
    'activity' => ['Activity', 'fa-clock-rotate-left', 'activity.view', 'Every change, by whom and when'],
];
$setTab = (string) ($_GET['tab'] ?? '');
if (!isset($setTabs[$setTab])) {
    $setTab = can('settings.write') ? 'store' : 'activity';
}
$pageCap = $setTabs[$setTab][2];
$pageTitle = 'Settings';
$pageSubtitle = $setTabs[$setTab][3];
$useGrid = $setTab === 'staff';
require_once __DIR__ . '/../includes/header.php';
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$v = static fn(string $k) => $h(setting($k, store_setting_defaults()[$k] ?? ''));
?>
<div class="set-layout">
    <nav class="set-nav" aria-label="Settings sections">
        <?php foreach ($setTabs as $key => [$label, $icon, $cap]): if (!can($cap)) continue; ?>
            <a class="set-nav-link<?= $key === $setTab ? ' active' : '' ?>" href="<?= $h(admin_url('manage/settings.php?tab=' . $key)) ?>"<?= $key === $setTab ? ' aria-current="page"' : '' ?>>
                <i class="fas <?= $icon ?>" aria-hidden="true"></i><span><?= $h($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="set-main" data-set-tab="<?= $h($setTab) ?>">
<?php if ($setTab === 'store'): ?>
        <form id="storeForm" class="set-form" novalidate>
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Store details</h2><span class="panel-sub">Shown on invoices, the storefront footer and emails</span></div>
                <div class="panel-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="store_name">Store name</label>
                            <input class="form-control" id="store_name" name="store_name" maxlength="80" required value="<?= $v('store_name') ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="store_legal_name">Legal name <span class="muted">(optional)</span></label>
                            <input class="form-control" id="store_legal_name" name="store_legal_name" maxlength="120" placeholder="TechZone Traders (Pvt) Ltd" value="<?= $v('store_legal_name') ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="store_phone">Phone</label>
                            <input class="form-control tabular" id="store_phone" name="store_phone" maxlength="30" inputmode="tel" placeholder="042-35761234" value="<?= $v('store_phone') ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="store_whatsapp">WhatsApp number</label>
                            <input class="form-control tabular" id="store_whatsapp" name="store_whatsapp" maxlength="30" inputmode="tel" placeholder="+92 300 1234567" value="<?= $v('store_whatsapp') ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="store_email">Email</label>
                            <input class="form-control" type="email" id="store_email" name="store_email" maxlength="150" placeholder="support@techzone.pk" value="<?= $v('store_email') ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="store_address">Address</label>
                            <input class="form-control" id="store_address" name="store_address" maxlength="255" value="<?= $v('store_address') ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="store_city">City</label>
                            <input class="form-control" id="store_city" name="store_city" maxlength="60" value="<?= $v('store_city') ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="store_hours">Opening hours</label>
                            <input class="form-control" id="store_hours" name="store_hours" maxlength="200" placeholder="Mon–Sat 11:00–21:00 · Sunday closed" value="<?= $v('store_hours') ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </div>
            </section>
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Storefront and stock</h2></div>
                <div class="panel-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="store_notice">Store notice</label>
                            <input class="form-control" id="store_notice" name="store_notice" maxlength="160" value="<?= $v('store_notice') ?>" aria-describedby="noticeHelp">
                            <div class="invalid-feedback"></div>
                            <div class="set-help" id="noticeHelp"><span>The bar across the top of the storefront home page. Leave empty to hide it.</span><span class="tabular" id="noticeCount"></span></div>
                            <div class="set-notice-preview" id="noticePreview" aria-hidden="true"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="low_stock_threshold">Low-stock threshold</label>
                            <div class="input-group">
                                <input class="form-control tabular" type="number" min="0" max="1000" step="1" id="low_stock_threshold" name="low_stock_threshold" value="<?= (int) setting('low_stock_threshold', 5) ?>">
                                <span class="input-group-text">units</span>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="set-help"><span>Default for new products; each product can override it.</span></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="currency">Currency</label>
                            <input class="form-control" id="currency" value="PKR — Pakistani rupee (Rs)" readonly data-save-ignore>
                            <div class="set-help"><span>Prices, orders and reports are in rupees.</span></div>
                        </div>
                    </div>
                </div>
            </section>
        </form>

<?php elseif ($setTab === 'shipping'): ?>
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Shipping zones</h2>
                <span class="panel-sub">A city ships at its zone's rate; cities in no zone use the default zone.</span>
                <span class="spacer"></span>
                <button type="button" class="btn btn-sm btn-primary" id="zoneAdd"><i class="fas fa-plus"></i> Add zone</button>
            </div>
            <div class="set-zones" id="zoneList"><div class="set-loading">Loading zones…</div></div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Test a city</h2><span class="panel-sub">Exactly what checkout will charge</span></div>
            <div class="panel-body">
                <form class="set-test" id="cityTest" novalidate>
                    <div class="set-test-field">
                        <label class="form-label" for="testCity">City</label>
                        <input class="form-control" id="testCity" placeholder="Karachi" maxlength="100" autocomplete="off" list="testCityList" data-save-ignore>
                        <datalist id="testCityList"></datalist>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="set-test-field set-test-sub">
                        <label class="form-label" for="testSubtotal">Cart subtotal</label>
                        <div class="input-group"><span class="input-group-text">Rs</span><input class="form-control tabular" id="testSubtotal" type="number" min="0" step="1" value="3000" data-save-ignore></div>
                    </div>
                    <button type="submit" class="btn btn-secondary">Check</button>
                </form>
                <div class="set-test-result" id="cityResult" aria-live="polite" hidden></div>
            </div>
        </section>

        <div class="modal fade" id="zoneModal" tabindex="-1" aria-labelledby="zoneModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" id="zoneForm" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title" id="zoneModalTitle">Add zone</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="zoneId">
                        <div class="mb-3">
                            <label class="form-label" for="zoneName">Zone name</label>
                            <input class="form-control" id="zoneName" maxlength="80" placeholder="Sindh" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="zoneCityInput">Cities</label>
                            <div class="set-chips form-control" id="zoneCities" data-chip-box>
                                <input id="zoneCityInput" class="set-chip-input" placeholder="Type a city, press Enter" maxlength="60" autocomplete="off" aria-describedby="zoneCitiesHelp">
                            </div>
                            <div class="invalid-feedback" id="zoneCitiesError"></div>
                            <div class="set-help" id="zoneCitiesHelp"><span>Comma or Enter adds a city. A city listed in another zone moves here.</span></div>
                            <div class="set-move-note" id="zoneMoveNote" hidden></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label class="form-label" for="zoneAmount">Rate</label>
                                <div class="input-group"><span class="input-group-text">Rs</span><input class="form-control tabular" id="zoneAmount" type="number" min="0" step="1" required></div>
                                <div class="invalid-feedback d-block" id="zoneAmountError"></div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="zoneFree">Free over</label>
                                <div class="input-group"><span class="input-group-text">Rs</span><input class="form-control tabular" id="zoneFree" type="number" min="0" step="1" placeholder="Never"></div>
                                <div class="invalid-feedback d-block" id="zoneFreeError"></div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="zoneEta">Delivery time</label>
                                <input class="form-control" id="zoneEta" maxlength="40" placeholder="2–4 days">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="zoneDefault">
                            <label class="form-check-label" for="zoneDefault">Default zone <span class="muted">— used for any city not listed in a zone</span></label>
                        </div>
                        <div class="invalid-feedback d-block" id="zoneDefaultError"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="zoneSave">Add zone</button>
                    </div>
                </form>
            </div>
        </div>

<?php elseif ($setTab === 'payments'): ?>
        <form id="paymentsForm" class="set-form" novalidate>
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Payment methods</h2><span class="panel-sub">What customers can choose at checkout</span></div>
                <div class="set-methods">
                    <div class="set-method">
                        <span class="set-method-icon" aria-hidden="true"><i class="fas fa-money-bill-wave"></i></span>
                        <div class="set-method-text">
                            <label class="set-method-title" for="cod_enabled">Cash on delivery</label>
                            <p>The courier collects cash; record the payment on the order once it's in.</p>
                            <div class="set-method-extra">
                                <label class="form-label" for="cod_limit">COD limit</label>
                                <div class="input-group set-limit"><span class="input-group-text">Rs</span><input class="form-control tabular" id="cod_limit" name="cod_limit" type="number" min="0" step="1" placeholder="No limit"></div>
                                <div class="invalid-feedback d-block" id="cod_limitError"></div>
                                <div class="set-help"><span>Orders above this total must pay with JazzCash. Leave empty for no limit.</span></div>
                            </div>
                        </div>
                        <div class="form-check form-switch set-switch"><input class="form-check-input" type="checkbox" role="switch" id="cod_enabled" name="cod_enabled" aria-label="Cash on delivery"></div>
                    </div>
                    <div class="set-method">
                        <span class="set-method-icon set-jazz" aria-hidden="true"><i class="fas fa-mobile-screen"></i></span>
                        <div class="set-method-text">
                            <label class="set-method-title" for="jazzcash_enabled">JazzCash</label>
                            <p>Customers pay from their JazzCash mobile account.</p>
                            <div class="set-method-extra">
                                <label class="form-label" for="jazzcash_merchant_id">Merchant ID</label>
                                <div class="set-mid">
                                    <input class="form-control tabular" id="jazzcash_merchant_id" name="jazzcash_merchant_id" maxlength="40" autocomplete="off" spellcheck="false">
                                    <button type="button" class="btn btn-sm btn-link" id="midClear" hidden>Remove</button>
                                </div>
                                <div class="invalid-feedback d-block" id="jazzcash_merchant_idError"></div>
                                <div class="set-help"><span id="midHelp">Stored securely and shown masked. Type a new ID to replace it.</span></div>
                            </div>
                        </div>
                        <div class="form-check form-switch set-switch"><input class="form-check-input" type="checkbox" role="switch" id="jazzcash_enabled" name="jazzcash_enabled" aria-label="JazzCash"></div>
                    </div>
                </div>
                <div class="set-error" id="methodsError" role="alert" hidden></div>
            </section>
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">At checkout</h2><span class="panel-sub">Preview</span></div>
                <div class="panel-body"><div class="set-checkout-preview" id="checkoutPreview"></div></div>
            </section>
        </form>

<?php elseif ($setTab === 'staff'): ?>
        <div class="panel grid-panel">
            <div class="toolbar grid-toolbar">
                <label class="search mb-0">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" class="form-control" id="staffSearch" placeholder="Search staff…" aria-label="Search staff">
                </label>
                <div class="end">
                    <span class="grid-count" id="staffCount" aria-live="polite">Loading…</span>
                    <button type="button" class="btn btn-primary" id="staffInvite"><i class="fas fa-user-plus"></i> Invite staff</button>
                </div>
            </div>
            <div id="staffGrid" aria-label="Staff"></div>
        </div>
        <section class="panel set-roles">
            <div class="panel-head"><h2 class="panel-title">What each role can do</h2></div>
            <div class="set-roles-grid">
                <div><span class="role-badge role-owner">Owner</span><p>Everything, including settings, shipping, payments and staff.</p></div>
                <div><span class="role-badge role-manager">Manager</span><p>Orders incl. cancel and refund, products, inventory, discounts, customers, the activity log.</p></div>
                <div><span class="role-badge role-staff">Staff</span><p>Fulfil, ship and record payments; reviews and messages. Read-only elsewhere.</p></div>
            </div>
        </section>

        <div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" id="inviteForm" novalidate>
                    <div class="modal-header"><h2 class="modal-title" id="inviteTitle">Invite staff</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="inviteName">Name</label>
                            <input class="form-control" id="inviteName" maxlength="100" autocomplete="off" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="inviteEmail">Email</label>
                            <input class="form-control" id="inviteEmail" type="email" maxlength="150" autocomplete="off" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <fieldset>
                            <legend class="form-label">Role</legend>
                            <div class="set-role-pick" id="inviteRoles">
                                <label><input type="radio" name="inviteRole" value="staff" checked><span><strong>Staff</strong><small>Orders, reviews, messages</small></span></label>
                                <label><input type="radio" name="inviteRole" value="manager"><span><strong>Manager</strong><small>+ catalogue, discounts, activity</small></span></label>
                                <label><input type="radio" name="inviteRole" value="owner"><span><strong>Owner</strong><small>Full access incl. settings</small></span></label>
                            </div>
                        </fieldset>
                        <p class="set-help mt-3 mb-0"><span>We create the account with a temporary password that you share with them. It's shown once.</span></p>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="inviteSubmit">Create account</button></div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="tempModal" tabindex="-1" aria-labelledby="tempTitle" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h2 class="modal-title" id="tempTitle">Temporary password</h2></div>
                    <div class="modal-body">
                        <p class="mb-2" id="tempIntro"></p>
                        <div class="set-temp"><code id="tempPassword"></code><button type="button" class="btn btn-sm btn-secondary" id="tempCopy"><i class="fas fa-copy"></i> Copy</button></div>
                        <dl class="kv mt-3 mb-0"><dt>Sign in at</dt><dd id="tempUrl" class="tabular"></dd><dt>Email</dt><dd id="tempEmail"></dd></dl>
                        <div class="alert alert-warning mt-3 mb-0"><i class="fas fa-eye-slash"></i> This is the only time the password is shown. Ask them to change it on their Profile after signing in.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button></div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <form class="modal-content" id="roleForm">
                    <div class="modal-header"><h2 class="modal-title" id="roleTitle">Change role</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <input type="hidden" id="roleId">
                        <label class="form-label" for="roleSelect">Role for <strong id="roleName"></strong></label>
                        <select class="form-select" id="roleSelect">
                            <option value="owner">Owner</option><option value="manager">Manager</option><option value="staff">Staff</option>
                        </select>
                        <div class="invalid-feedback d-block" id="roleError"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Change role</button></div>
                </form>
            </div>
        </div>

<?php else: /* activity */ ?>
        <div class="panel act-panel">
            <div class="toolbar act-toolbar">
                <label class="search mb-0">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" class="form-control" id="actSearch" placeholder="Search activity…" aria-label="Search activity" maxlength="100">
                </label>
                <select class="form-select" id="actActor" aria-label="Filter by person"><option value="">Everyone</option></select>
                <select class="form-select" id="actEntity" aria-label="Filter by what changed"><option value="">All entities</option></select>
                <select class="form-select" id="actType" aria-label="Filter by action"><option value="">All actions</option></select>
                <div class="act-dates">
                    <input type="date" class="form-control" id="actFrom" aria-label="From date">
                    <span aria-hidden="true">–</span>
                    <input type="date" class="form-control" id="actTo" aria-label="To date">
                </div>
                <div class="end">
                    <button type="button" class="btn btn-link btn-sm" id="actClear" hidden>Clear filters</button>
                    <a class="btn btn-secondary" id="actExport" href="#"><i class="fas fa-file-csv"></i> Export CSV</a>
                </div>
            </div>
            <div class="act-invalid" id="actDateError" role="alert" hidden></div>
            <div class="act-list" id="actList" aria-live="polite"><div class="set-loading">Loading activity…</div></div>
            <div class="table-foot" id="actPager"></div>
        </div>
<?php endif; ?>
    </div>
</div>

<?php if ($setTab === 'activity'): ?>
<script src="<?= $tzAsset('assets/js/activity.js') ?>"></script>
<?php else: ?>
<script>window.TZ_SETTINGS = <?= json_encode(['tab' => $setTab, 'me' => (int) $_SESSION['admin_id'], 'loginUrl' => absolute_url(admin_url('index.php'))], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $tzAsset('assets/js/settings.js') ?>"></script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
