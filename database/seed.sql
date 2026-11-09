-- =============================================================================
-- TechZone demo seed data (run AFTER database/schema.sql)
-- Staff logins:    admin@techzone.pk / Admin@1234          (owner)
--                  sana.manager@techzone.pk / Manager@1234 (manager)
--                  bilal.staff@techzone.pk / Staff@1234    (staff)
-- Seller logins:  rizwan@sellers.techzone.pk, alfatah@sellers.techzone.pk, gamersden@sellers.techzone.pk / Seller@1234
-- Customer logins: any seeded customer email / Customer@123 (e.g. ali.raza@example.pk)
-- Dates are relative to NOW() so dashboard charts always cover the last 12 months.
-- Re-runnable: wipes the demo tables first.
-- Storefront v2 (docs/STOREFRONT.md): taxonomy, 75 products with attributes, 12 weekly price points
-- per product (newest = products.price), marketplace sellers. Prices are demo data (Nov 2026 Lahore rates).
-- =============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE stock_alerts;
TRUNCATE TABLE wishlists;
TRUNCATE TABLE seller_payouts;
TRUNCATE TABLE seller_applications;
TRUNCATE TABLE sellers;
TRUNCATE TABLE price_history;
TRUNCATE TABLE product_attributes;
TRUNCATE TABLE audit_log;
TRUNCATE TABLE settings;
TRUNCATE TABLE shipping_rates;
TRUNCATE TABLE shipping_zones;
TRUNCATE TABLE customer_notes;
TRUNCATE TABLE customer_tag_links;
TRUNCATE TABLE customer_tags;
TRUNCATE TABLE inventory_adjustments;
TRUNCATE TABLE order_shipments;
TRUNCATE TABLE order_notes;
TRUNCATE TABLE order_events;
TRUNCATE TABLE login_attempts;
TRUNCATE TABLE admin_password_resets;
TRUNCATE TABLE customer_password_resets;
TRUNCATE TABLE contact_messages;
TRUNCATE TABLE promotions;
TRUNCATE TABLE reviews;
TRUNCATE TABLE order_items;
TRUNCATE TABLE orders;
TRUNCATE TABLE product_variations;
TRUNCATE TABLE products;
TRUNCATE TABLE categories;
TRUNCATE TABLE customers;
TRUNCATE TABLE admins;
SET FOREIGN_KEY_CHECKS = 1;

-- Staff (owner / manager / staff)
INSERT INTO admins (id, name, username, email, phone, password, role, is_active, last_login_at, created_at) VALUES
(1, 'TechZone Admin', 'admin', 'admin@techzone.pk', '0300-0000000', '$2y$10$i6i0oi7IQni0dozr8Z6P7es5Js2bjbV3w9nOG.iqp6aXHbhHfpiu6', 'owner', 1, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 400 DAY),
(2, 'Sana Mirza', 'sana_manager', 'sana.manager@techzone.pk', '0321-4455667', '$2y$10$NJFHi0k5ouBRxnXJ5L7YfOQkBvy7262Rqfyl37hOeWE0T3XC//SOS', 'manager', 1, NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 220 DAY),
(3, 'Bilal Hussain', 'bilal_staff', 'bilal.staff@techzone.pk', '0333-7788990', '$2y$10$8w.7GArAAnd7jLhh03CFIepwkT23MgOwIrF4Ogj4QK0UJT1u8O/Gy', 'staff', 1, NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 28 DAY);

-- Store settings (JSON-encoded values, core/settings.php)
INSERT INTO settings (setting_key, value, updated_by) VALUES
('store_name', '"TechZone"', 1),
('store_address', '"Shop 214, 2nd floor, Hafeez Centre, Gulberg III, Lahore"', 1),
('store_phone', '"042-35761234"', 1),
('store_email', '"support@techzone.pk"', 1),
('currency', '"PKR"', 1),
('cod_enabled', 'true', 1),
('jazzcash_enabled', 'true', 1),
('low_stock_threshold', '5', 1);
-- Settings → Store / Payments (phase 2c)
INSERT INTO settings (setting_key, value, updated_by) VALUES
('store_legal_name', '"TechZone Traders"', 1),
('store_whatsapp', '"+92 300 8243966"', 1),
('store_city', '"Lahore"', 1),
('store_hours', '"Mon–Sat 11 am – 10 pm, Sunday closed"', 1),
('store_notice', '"Cash on delivery across Pakistan. Free delivery on orders over Rs 5,000."', 1),
('cod_limit', '0', 1),
('jazzcash_merchant_id', '""', 1);
-- Storefront v2: installments (0% markup bank plans), WhatsApp (digits for wa.me links), the shop line
INSERT INTO settings (setting_key, value, updated_by) VALUES
('installment_plans', '{"banks":["Meezan","HBL","Alfalah"],"months":[3,6,12],"markup_pct":0,"min_amount":20000}', 1),
('whatsapp_number', '"923011427871"', 1),
('store_address_line', '"Hafeez Centre, Shop 214, 2nd floor"', 1);

-- Shipping zones (a city in no zone ships at the default zone's rate)
INSERT INTO shipping_zones (id, name, cities, is_default, sort_order) VALUES
(1, 'Lahore', '["Lahore"]', 0, 1),
(2, 'Punjab', '["Rawalpindi","Islamabad","Faisalabad","Multan","Gujranwala","Sialkot","Sargodha","Bahawalpur","Sheikhupura","Sahiwal","Gujrat","Jhelum","Kasur","Okara","Rahim Yar Khan"]', 0, 2),
(3, 'Nationwide', '["Karachi","Peshawar","Quetta","Hyderabad","Sukkur","Abbottabad","Mardan","Gilgit","Muzaffarabad"]', 1, 3);
INSERT INTO shipping_rates (zone_id, name, amount, free_over, eta) VALUES
(1, 'Standard', 250, 5000, '1–2 days'),
(2, 'Standard', 350, 8000, '2–4 days'),
(3, 'Standard', 500, 10000, '3–6 days');

-- Categories. "Components" (id 2, formerly "Computer Hardware") is the parent of the PC-builder part
-- categories; ids 1-8 keep their meaning for existing orders and promotions (TECH10 targets 4 = Monitors).
INSERT INTO categories (id, name, description, image, parent_id, slug, part_type, created_at) VALUES
(1, 'Computer Accessories', 'Mice, keyboards, webcams and everyday desk essentials.', 'Computer Accessories.png', NULL, 'computer-accessories', 'other', NOW() - INTERVAL 380 DAY),
(2, 'Components', 'Processors, motherboards, memory, graphics cards, power supplies, cases and cooling.', 'Computer Hardware.png', NULL, 'components', 'other', NOW() - INTERVAL 380 DAY),
(3, 'Furniture', 'Gaming chairs and ergonomic desks built for long sessions.', 'Furntiure.png', NULL, 'furniture', 'other', NOW() - INTERVAL 380 DAY),
(4, 'Monitors', 'Office, gaming and creator displays from 24" to 34" ultrawide.', 'Monitors.png', NULL, 'monitors', 'other', NOW() - INTERVAL 380 DAY),
(5, 'Networking', 'Wi-Fi 6 routers, mesh systems, switches and adapters.', 'network.png', NULL, 'networking', 'other', NOW() - INTERVAL 380 DAY),
(6, 'Storage Devices', 'NVMe drives, portable SSDs, hard disks and flash storage.', 'Storage Devices.png', NULL, 'storage', 'storage', NOW() - INTERVAL 380 DAY),
(7, 'Audio & Headsets', 'Gaming headsets, studio headphones and speakers.', '695e07b640c78_headphones.png', NULL, 'audio-headsets', 'other', NOW() - INTERVAL 380 DAY),
(8, 'Gaming Peripherals', 'High-DPI mice, mechanical keyboards and pro gaming gear.', '695d7e54382e4_mouse.png', NULL, 'gaming-peripherals', 'other', NOW() - INTERVAL 380 DAY),
(9, 'Processors', 'Intel Core and AMD Ryzen desktop CPUs for LGA1700, AM5 and AM4.', NULL, 2, 'processors', 'cpu', NOW() - INTERVAL 120 DAY),
(10, 'Motherboards', 'Intel B760 / H610 and AMD B650 / B550 boards in ATX and micro-ATX.', NULL, 2, 'motherboards', 'motherboard', NOW() - INTERVAL 120 DAY),
(11, 'Memory', 'DDR4 and DDR5 desktop memory kits.', NULL, 2, 'memory', 'memory', NOW() - INTERVAL 120 DAY),
(12, 'Graphics cards', 'GeForce RTX and Radeon RX graphics cards.', NULL, 2, 'graphics-cards', 'gpu', NOW() - INTERVAL 120 DAY),
(13, 'Power supplies', '80 Plus power supplies from 500 W to 850 W.', NULL, 2, 'power-supplies', 'psu', NOW() - INTERVAL 120 DAY),
(14, 'Cases', 'ATX and micro-ATX cases with room for big cards and coolers.', NULL, 2, 'cases', 'case', NOW() - INTERVAL 120 DAY),
(15, 'Cooling', 'Tower air coolers and all-in-one liquid coolers.', NULL, 2, 'cooling', 'cooler', NOW() - INTERVAL 120 DAY);

-- Customers (password: Customer@123)
INSERT INTO customers (id, name, email, password, phone, city, address, created_at) VALUES
(1, 'Ali Raza', 'ali.raza@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0300-1234567', 'Lahore', 'House 12, Street 4, DHA Phase 5', NOW() - INTERVAL 360 DAY),
(2, 'Ayesha Khan', 'ayesha.khan@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0321-9876543', 'Karachi', 'Flat 7B, Clifton Block 2', NOW() - INTERVAL 335 DAY),
(3, 'Usman Tariq', 'usman.tariq@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0333-4567890', 'Islamabad', 'House 45, Street 12, F-10/2', NOW() - INTERVAL 310 DAY),
(4, 'Fatima Noor', 'fatima.noor@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0345-1122334', 'Rawalpindi', 'Bahria Town Phase 4, Street 9', NOW() - INTERVAL 285 DAY),
(5, 'Hamza Siddiqui', 'hamza.s@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0312-5566778', 'Karachi', 'Gulshan-e-Iqbal Block 13-D', NOW() - INTERVAL 260 DAY),
(6, 'Zainab Hussain', 'zainab.h@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0301-2233445', 'Lahore', 'Model Town, Block C, House 88', NOW() - INTERVAL 235 DAY),
(7, 'Bilal Ahmed', 'bilal.ahmed@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0322-3344556', 'Faisalabad', 'Peoples Colony No. 1, House 21', NOW() - INTERVAL 210 DAY),
(8, 'Maryam Iqbal', 'maryam.iqbal@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0335-6677889', 'Multan', 'Gulgasht Colony, Street 3', NOW() - INTERVAL 185 DAY),
(9, 'Saad Malik', 'saad.malik@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0300-9988776', 'Peshawar', 'University Town, Road 2', NOW() - INTERVAL 160 DAY),
(10, 'Hira Shah', 'hira.shah@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0346-4433221', 'Islamabad', 'G-11/3, Street 27, House 6', NOW() - INTERVAL 135 DAY),
(11, 'Omar Farooq', 'omar.farooq@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0313-7766554', 'Lahore', 'Johar Town, Block R2', NOW() - INTERVAL 110 DAY),
(12, 'Sana Javed', 'sana.javed@example.pk', '$2y$10$Pu/7AWT64A43velU/ScPt.MTiLLUVqC8c.Iu7TuOct5jT2xlnWA1y', '0324-1212343', 'Karachi', 'PECHS Block 6, Flat 3', NOW() - INTERVAL 85 DAY);

-- Products
INSERT INTO products (id, category_id, name, description, price, imagelink, inventory, sold, is_active, created_at) VALUES
(1, 1, 'Logitech M331 Silent Plus Wireless Mouse', 'Silent clicks, 24-month battery life and a contoured right-hand shape.', 4499, 'uploads/catalog/01-logitech-m331-silent-plus-wireless-mouse.webp', 42, 10, 1, NOW() - INTERVAL 370 DAY),
(2, 1, 'Logitech K380 Multi-Device Bluetooth Keyboard', 'Compact keyboard that pairs with three devices and switches instantly.', 9999, 'uploads/catalog/02-logitech-k380-multi-device-bluetooth-keyboa.webp', 25, 8, 1, NOW() - INTERVAL 361 DAY),
(3, 5, 'Xiaomi Mi Router 4A Gigabit Edition', 'AC1200 dual-band with gigabit ports and Mi Wi-Fi app control.', 6999, 'uploads/catalog/03-xiaomi-mi-router-4a-gigabit-edition.webp', 3, 21, 1, NOW() - INTERVAL 352 DAY),
(4, 4, 'AOC 24G2 24" 144Hz IPS Gaming Monitor', '1080p IPS panel, 144Hz, 1ms MPRT and FreeSync Premium on a height-adjustable stand.', 42500, 'uploads/catalog/04-aoc-24g2-24-144hz-ips-gaming-monitor.webp', 14, 22, 1, NOW() - INTERVAL 343 DAY),
(5, 10, 'MSI PRO B760M-E DDR4 Motherboard', 'Micro-ATX LGA1700 board for 12th/13th/14th gen Intel with M.2 and 2.5G LAN.', 32500, 'uploads/catalog/05-msi-pro-b760m-e-ddr4-motherboard.webp', 9, 24, 1, NOW() - INTERVAL 334 DAY),
(6, 10, 'Gigabyte B760M DS3H DDR4 Motherboard', 'Micro-ATX LGA1700 board with four DIMM slots, PCIe 4.0 M.2 and 2.5GbE.', 36500, 'uploads/catalog/06-gigabyte-b760m-ds3h-ddr4-motherboard.webp', 11, 42, 1, NOW() - INTERVAL 325 DAY),
(7, 10, 'ASUS PRIME H610M-K D4 Motherboard', 'Entry-level LGA1700 micro-ATX with M.2, HDMI/VGA and ASUS 5X Protection.', 23500, 'uploads/catalog/07-asus-prime-h610m-k-d4-motherboard.webp', 30, 25, 1, NOW() - INTERVAL 316 DAY),
(8, 10, 'ASRock B650M-HDV/M.2 Motherboard', 'AM5 micro-ATX for Ryzen 7000/9000, PCIe 4.0 x16, two M.2 slots, DDR5.', 44500, 'uploads/catalog/08-asrock-b650m-hdv-m-2-motherboard.webp', 4, 38, 1, NOW() - INTERVAL 307 DAY),
(9, 10, 'MSI MAG B650 Tomahawk WiFi Motherboard', 'ATX AM5 board with Wi-Fi 6E, 2.5GbE, three M.2 slots and 14+2+1 power stages.', 69500, 'uploads/catalog/09-msi-mag-b650-tomahawk-wifi-motherboard.webp', 16, 20, 1, NOW() - INTERVAL 298 DAY),
(10, 3, 'Arozzi Verona Pro V2 Gaming Chair', 'Wide seat, adjustable lumbar and neck pillows, 4D armrests.', 68000, 'uploads/catalog/10-arozzi-verona-pro-v2-gaming-chair.webp', 7, 12, 1, NOW() - INTERVAL 289 DAY),
(11, 3, 'FlexiSpot E7 Electric Standing Desk', 'Dual-motor height adjustable frame with memory presets.', 145000, 'uploads/catalog/11-flexispot-e7-electric-standing-desk.webp', 2, 16, 1, NOW() - INTERVAL 280 DAY),
(12, 3, 'TechZone Ergo Office Chair', 'Breathable mesh back, synchro-tilt mechanism and adjustable headrest.', 38500, 'uploads/catalog/12-techzone-ergo-office-chair.webp', 12, 31, 1, NOW() - INTERVAL 271 DAY),
(13, 3, 'Compact Writing Desk 120cm', 'Engineered wood top with steel frame and cable grommet.', 24999, 'uploads/catalog/13-compact-writing-desk-120cm.webp', 8, 19, 0, NOW() - INTERVAL 262 DAY),
(14, 4, 'Dell S2421H 24" IPS Monitor', 'Full HD IPS, 75Hz, built-in speakers and AMD FreeSync.', 42000, 'uploads/catalog/14-dell-s2421h-24-ips-monitor.webp', 13, 14, 1, NOW() - INTERVAL 253 DAY),
(15, 4, 'Samsung Odyssey G5 27" 144Hz Curved', 'QHD 1000R curved VA panel with 1ms response time.', 79999, 'uploads/catalog/15-samsung-odyssey-g5-27-144hz-curved.webp', 6, 41, 1, NOW() - INTERVAL 244 DAY),
(16, 4, 'LG 27UP850 27" 4K USB-C Monitor', 'UHD IPS, DCI-P3 95%, HDR400 and 90W USB-C power delivery.', 132000, 'uploads/catalog/16-lg-27up850-27-4k-usb-c-monitor.webp', 5, 7, 1, NOW() - INTERVAL 235 DAY),
(17, 4, 'LG UltraWide 34WP65C 34" Curved', '21:9 QHD ultrawide, 160Hz, HDR10 for productivity and gaming.', 115000, 'uploads/catalog/17-lg-ultrawide-34wp65c-34-curved.webp', 3, 21, 1, NOW() - INTERVAL 226 DAY),
(18, 5, 'ASUS RT-AC86U Dual-Band Gaming Router', 'AC2900 with adaptive QoS, AiProtection and three-level game acceleration.', 34999, 'uploads/catalog/18-asus-rt-ac86u-dual-band-gaming-router.webp', 10, 32, 1, NOW() - INTERVAL 217 DAY),
(19, 5, 'TP-Link Archer AX55 Wi-Fi 6 Router', 'AX3000 dual-band Wi-Fi 6 with OneMesh support.', 21500, 'uploads/catalog/19-tp-link-archer-ax55-wi-fi-6-router.webp', 18, 23, 1, NOW() - INTERVAL 208 DAY),
(20, 5, 'TP-Link Archer C6 AC1200 Router', 'Dual-band AC1200 with four gigabit ports, MU-MIMO and beamforming.', 9500, 'uploads/catalog/20-tp-link-archer-c6-ac1200-router.webp', 5, 37, 1, NOW() - INTERVAL 199 DAY),
(21, 5, 'Tenda AC10 AC1200 Dual-Band Router', 'Gigabit WAN/LAN, four 6dBi antennas and app-based setup.', 7500, 'uploads/catalog/21-tenda-ac10-ac1200-dual-band-router.webp', 35, 23, 1, NOW() - INTERVAL 190 DAY),
(22, 6, 'Transcend ESD380C 1TB Portable SSD', 'USB 3.2 Gen 2x2 up to 2000 MB/s with military-grade shock resistance.', 28500, 'uploads/catalog/22-transcend-esd380c-1tb-portable-ssd.webp', 15, 34, 1, NOW() - INTERVAL 181 DAY),
(23, 6, 'Samsung T7 1TB Portable SSD', 'USB 3.2 Gen 2, up to 1,050 MB/s read, shock-resistant metal body.', 28500, 'uploads/catalog/23-samsung-t7-1tb-portable-ssd.webp', 1, 30, 1, NOW() - INTERVAL 172 DAY),
(24, 6, 'WD My Passport 2TB Portable Hard Drive', 'Slim USB 3.0 drive with hardware encryption and WD Backup software.', 19500, 'uploads/catalog/24-wd-my-passport-2tb-portable-hard-drive.webp', 22, 37, 1, NOW() - INTERVAL 163 DAY),
(25, 6, 'SanDisk Extreme 500GB Portable SSD', 'IP65 water and dust resistance, up to 1,050 MB/s, carabiner loop.', 21500, 'uploads/catalog/25-sandisk-extreme-500gb-portable-ssd.webp', 60, 14, 1, NOW() - INTERVAL 154 DAY),
(26, 7, 'HyperX Cloud II Gaming Headset', 'Virtual 7.1 surround, memory-foam ear cushions and a detachable noise-cancelling mic.', 22999, 'uploads/catalog/26-hyperx-cloud-ii-gaming-headset.webp', 19, 34, 1, NOW() - INTERVAL 145 DAY),
(27, 7, 'Redragon H510 Zeus Wired Gaming Headset', '53mm drivers, 7.1 surround over USB, detachable mic and braided cable.', 11500, 'uploads/catalog/27-redragon-h510-zeus-wired-gaming-headset.webp', 4, 39, 1, NOW() - INTERVAL 136 DAY),
(28, 7, 'Logitech G331 Stereo Gaming Headset', '50mm drivers, flip-to-mute mic and a lightweight frame for long sessions.', 12500, 'uploads/catalog/28-logitech-g331-stereo-gaming-headset.webp', 9, 36, 1, NOW() - INTERVAL 127 DAY),
(29, 8, 'Logitech G502 HERO Gaming Mouse', '25K DPI HERO sensor, 11 programmable buttons and adjustable weights.', 12999, 'uploads/catalog/29-logitech-g502-hero-gaming-mouse.webp', 27, 14, 1, NOW() - INTERVAL 118 DAY),
(30, 8, 'Redragon K552 Kumara Mechanical Keyboard', 'TKL layout, Outemu Blue switches and red LED backlight.', 8999, 'uploads/catalog/30-redragon-k552-kumara-mechanical-keyboard.webp', 33, 43, 1, NOW() - INTERVAL 109 DAY),
(31, 8, 'Razer BlackWidow V4 Pro Keyboard', 'Green mechanical switches, command dial and Chroma RGB underglow.', 68500, 'uploads/catalog/31-razer-blackwidow-v4-pro-keyboard.webp', 0, 22, 1, NOW() - INTERVAL 100 DAY),
(32, 10, 'ASUS TUF Gaming B760M-Plus WiFi D4', 'Military-grade components, Wi-Fi 6, PCIe 5.0 x16 and Aura Sync lighting.', 52500, 'uploads/catalog/32-asus-tuf-gaming-b760m-plus-wifi-d4.webp', 2, 13, 1, NOW() - INTERVAL 91 DAY),
-- PC-builder parts
(41, 9, 'Intel Core i3-12100F', '4 cores and 8 threads up to 4.3 GHz on LGA1700. Boxed with the Intel Laminar cooler; needs a graphics card.', 27500, 'uploads/catalog/41-intel-core-i3-12100f.webp', 22, 14, 1, NOW() - INTERVAL 88 DAY),
(42, 9, 'Intel Core i5-12400F', '6 performance cores up to 4.4 GHz, LGA1700, boxed cooler included. The value pick for 1080p gaming; needs a graphics card.', 36500, 'uploads/catalog/42-intel-core-i5-12400f.webp', 18, 21, 1, NOW() - INTERVAL 86 DAY),
(43, 9, 'Intel Core i5-13400F', '10 cores (6P + 4E) and 16 threads up to 4.6 GHz on LGA1700, boxed cooler. Needs a graphics card.', 54000, 'uploads/catalog/43-intel-core-i5-13400f.webp', 15, 26, 1, NOW() - INTERVAL 83 DAY),
(44, 9, 'Intel Core i5-14600K', '14 cores (6P + 8E) up to 5.3 GHz, unlocked, Intel UHD 770 graphics. No cooler in the box.', 82000, 'uploads/catalog/44-intel-core-i5-14600k.webp', 7, 9, 1, NOW() - INTERVAL 81 DAY),
(45, 9, 'Intel Core i7-14700K', '20 cores (8P + 12E) and 28 threads up to 5.6 GHz, unlocked, UHD 770 graphics. No cooler in the box.', 118000, 'uploads/catalog/45-intel-core-i7-14700k.webp', 0, 6, 1, NOW() - INTERVAL 78 DAY),
(46, 9, 'AMD Ryzen 5 7600', '6 Zen 4 cores up to 5.1 GHz on AM5 with Radeon graphics and the Wraith Stealth cooler in the box. DDR5 only.', 54000, 'uploads/catalog/46-amd-ryzen-5-7600.webp', 19, 31, 1, NOW() - INTERVAL 76 DAY),
(47, 9, 'AMD Ryzen 7 7800X3D', '8 cores with 96 MB of 3D V-Cache, the fastest gaming CPU on AM5. 120 W, no cooler in the box.', 135000, 'uploads/catalog/47-amd-ryzen-7-7800x3d.webp', 3, 11, 1, NOW() - INTERVAL 73 DAY),
(48, 9, 'AMD Ryzen 5 8600G', '6 Zen 4 cores with Radeon 760M graphics strong enough to game without a card. AM5, Wraith Stealth included.', 52000, 'uploads/catalog/48-amd-ryzen-5-8600g.webp', 9, 7, 1, NOW() - INTERVAL 71 DAY),
(49, 9, 'AMD Ryzen 5 5600', '6 cores and 12 threads up to 4.4 GHz on AM4 with the Wraith Stealth cooler. Pairs with DDR4 B550 boards.', 31500, 'uploads/catalog/49-amd-ryzen-5-5600.webp', 24, 18, 1, NOW() - INTERVAL 68 DAY),
(50, 10, 'MSI B550M PRO-VDH WIFI Motherboard', 'Micro-ATX AM4 board for Ryzen 5000 with Wi-Fi, two M.2 slots and four DDR4 slots.', 34500, 'uploads/catalog/50-msi-b550m-pro-vdh-wifi.webp', 11, 8, 1, NOW() - INTERVAL 66 DAY),
(51, 11, 'Corsair Vengeance LPX 16GB (2x8) DDR4 3200', 'Low-profile 2 x 8 GB DDR4-3200 CL16 kit with XMP 2.0; clears almost any air cooler.', 12500, 'uploads/catalog/51-corsair-vengeance-lpx-16gb-ddr4-3200.webp', 30, 19, 1, NOW() - INTERVAL 63 DAY),
(52, 11, 'Kingston FURY Beast 16GB (2x8) DDR4 3200', '2 x 8 GB DDR4-3200 CL16 with plug-and-play overclocking and XMP profiles.', 13000, 'uploads/catalog/52-kingston-fury-beast-16gb-ddr4-3200.webp', 26, 15, 1, NOW() - INTERVAL 61 DAY),
(53, 11, 'Kingston FURY Beast 32GB (2x16) DDR5 6000', '2 x 16 GB DDR5-6000 CL36 with Intel XMP 3.0 and AMD EXPO, the sweet spot for AM5.', 38500, 'uploads/catalog/53-kingston-fury-beast-32gb-ddr5-6000.webp', 14, 22, 1, NOW() - INTERVAL 58 DAY),
(54, 11, 'Corsair Vengeance 32GB (2x16) DDR5 5600', '2 x 16 GB DDR5-5600 CL36 with XMP 3.0; compact heatspreader for small builds.', 35000, 'uploads/catalog/54-corsair-vengeance-32gb-ddr5-5600.webp', 12, 9, 1, NOW() - INTERVAL 56 DAY),
(55, 12, 'ASUS Dual GeForce RTX 4060 8GB', 'RTX 4060 with DLSS 3 and two Axial-tech fans in a 227 mm, 2.5-slot card. 115 W.', 98500, 'uploads/catalog/55-asus-dual-rtx-4060-8gb.webp', 12, 34, 1, NOW() - INTERVAL 53 DAY),
(56, 12, 'MSI GeForce RTX 4060 Ti Ventus 2X 8GB', 'RTX 4060 Ti in a compact 199 mm dual-fan card; strong 1080p and solid 1440p.', 128000, 'uploads/catalog/56-msi-ventus-rtx-4060-ti-8gb.webp', 6, 10, 1, NOW() - INTERVAL 51 DAY),
(57, 12, 'Gigabyte GeForce RTX 4070 SUPER WINDFORCE OC 12GB', '12 GB RTX 4070 SUPER with three WINDFORCE fans, 261 mm long, built for 1440p at high refresh.', 205000, 'uploads/catalog/57-gigabyte-rtx-4070-super-windforce.webp', 2, 8, 1, NOW() - INTERVAL 48 DAY),
(58, 12, 'Sapphire PULSE Radeon RX 7600 8GB', 'RDNA 3 card for 1080p gaming with a dual-fan PULSE cooler, 240 mm long.', 82000, 'uploads/catalog/58-sapphire-pulse-rx-7600.webp', 9, 12, 1, NOW() - INTERVAL 46 DAY),
(59, 12, 'Sapphire PULSE Radeon RX 7800 XT 16GB', '16 GB RDNA 3 for 1440p, 280 mm dual-fan PULSE cooler. Needs a 700 W supply.', 162000, 'uploads/catalog/59-sapphire-pulse-rx-7800-xt.webp', 4, 5, 1, NOW() - INTERVAL 43 DAY),
(60, 13, 'Cooler Master MWE 650 Bronze V2 PSU', '650 W 80 Plus Bronze, non-modular, 120 mm fan and a 5-year warranty.', 17500, 'uploads/catalog/60-cooler-master-mwe-650-bronze-v2.webp', 21, 16, 1, NOW() - INTERVAL 41 DAY),
(61, 13, 'Corsair CX750 PSU', '750 W 80 Plus Bronze with a quiet 120 mm fan; enough for an RTX 4070-class build.', 24500, 'uploads/catalog/61-corsair-cx750.webp', 17, 20, 1, NOW() - INTERVAL 38 DAY),
(62, 13, 'Thermaltake Smart 500W PSU', '500 W 80 Plus White, non-modular. For office builds and entry-level graphics only.', 10500, 'uploads/catalog/62-thermaltake-smart-500w.webp', 28, 25, 1, NOW() - INTERVAL 36 DAY),
(63, 13, 'Corsair RM850e PSU', '850 W 80 Plus Gold, fully modular, ATX 3.0 with a native 12VHPWR cable.', 36000, 'uploads/catalog/63-corsair-rm850e.webp', 8, 7, 1, NOW() - INTERVAL 33 DAY),
(64, 14, 'NZXT H5 Flow Case', 'Mid-tower with a perforated front panel and two fans included. Fits 365 mm cards and 165 mm coolers.', 29500, 'uploads/catalog/64-nzxt-h5-flow.webp', 7, 6, 1, NOW() - INTERVAL 31 DAY),
(65, 14, 'Lian Li LANCOOL 216 Case', 'High-airflow mid-tower with two 160 mm front fans; room for 392 mm GPUs and 180 mm coolers.', 32000, 'uploads/catalog/65-lian-li-lancool-216.webp', 4, 9, 1, NOW() - INTERVAL 28 DAY),
(66, 14, 'Cooler Master MasterBox Q300L Case', 'Compact micro-ATX tower with magnetic dust filters and a reversible I/O panel.', 13500, 'uploads/catalog/66-cooler-master-masterbox-q300l.webp', 16, 14, 1, NOW() - INTERVAL 26 DAY),
(67, 14, 'DeepCool CH510 Case', 'ATX mid-tower with a mesh-free clean front, tempered glass and a pre-installed 120 mm fan.', 19500, 'uploads/catalog/67-deepcool-ch510.webp', 10, 8, 1, NOW() - INTERVAL 23 DAY),
(68, 15, 'DeepCool AK400 CPU Cooler', 'Single-tower air cooler with four heat pipes, rated for 220 W, 155 mm tall. LGA1700, AM5 and AM4.', 8500, 'uploads/catalog/68-deepcool-ak400.webp', 25, 23, 1, NOW() - INTERVAL 21 DAY),
(69, 15, 'Cooler Master Hyper 212 Black Edition', 'The classic tower cooler in black, four heat pipes and a 120 mm fan, 159 mm tall.', 9500, 'uploads/catalog/69-cooler-master-hyper-212-black.webp', 13, 12, 1, NOW() - INTERVAL 18 DAY),
(70, 15, 'Thermalright Peerless Assassin 120 SE', 'Dual-tower air cooler with six heat pipes and two 120 mm fans; handles 245 W at 155 mm.', 11500, 'uploads/catalog/70-thermalright-peerless-assassin-120-se.webp', 9, 17, 1, NOW() - INTERVAL 16 DAY),
(71, 15, 'ARCTIC Liquid Freezer II 240', '240 mm all-in-one liquid cooler with a VRM fan on the pump, rated for 300 W.', 24000, 'uploads/catalog/71-arctic-liquid-freezer-ii-240.webp', 0, 4, 1, NOW() - INTERVAL 13 DAY),
(72, 6, 'Kingston NV2 1TB NVMe SSD', 'M.2 2280 PCIe 4.0 NVMe drive up to 3,500 MB/s. Affordable boot drive for any modern board.', 17500, 'uploads/catalog/72-kingston-nv2-1tb-nvme.webp', 32, 27, 1, NOW() - INTERVAL 11 DAY),
(73, 6, 'WD_BLACK SN770 1TB NVMe SSD', 'M.2 2280 PCIe 4.0 NVMe up to 5,150 MB/s without DRAM, runs cool without a heatsink.', 22500, 'uploads/catalog/73-wd-black-sn770-1tb.webp', 15, 13, 1, NOW() - INTERVAL 8 DAY),
(74, 6, 'Samsung 980 PRO 1TB NVMe SSD', 'M.2 2280 PCIe 4.0 NVMe up to 7,000 MB/s with Samsung Magician software.', 31000, 'uploads/catalog/74-samsung-980-pro-1tb.webp', 10, 18, 1, NOW() - INTERVAL 6 DAY),
(75, 6, 'Crucial P3 2TB NVMe SSD', '2 TB M.2 2280 PCIe 3.0 NVMe up to 3,500 MB/s for big game libraries.', 30500, 'uploads/catalog/75-crucial-p3-2tb.webp', 11, 6, 1, NOW() - INTERVAL 3 DAY);

-- Product variations
INSERT INTO product_variations (product_id, variation_type, variation_value, price_modifier, inventory, is_active, is_default, display_order) VALUES
(30, 'color', 'Black', 0, 20, 1, 1, 1),
(30, 'color', 'White', 500, 13, 1, 0, 2),
(10, 'color', 'Black', 0, 4, 1, 1, 1),
(10, 'color', 'Red', 2000, 3, 1, 0, 2),
(7,  'size', '16GB (2x8GB)', 0, 18, 1, 1, 1),
(7,  'size', '32GB (2x16GB)', 12500, 12, 1, 0, 2),
(22, 'size', '1TB', 0, 10, 1, 1, 1),
(22, 'size', '2TB', 19500, 5, 1, 0, 2),
(13, 'size', '100cm', -3000, 4, 1, 0, 1),
(13, 'size', '120cm', 0, 4, 1, 1, 2);

-- Orders (last 12 months)
INSERT INTO orders (id, customer_id, customer_name, customer_email, customer_phone, city, total, status, payment_method, promo_code, order_date) VALUES
(1, 1, 'Ali Raza', 'ali.raza@example.pk', '0300-1234567', 'Lahore', 58500, 'Delivered', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 324 DAY, '%Y-%m-%d 21:43:00')),
(2, 10, 'Hira Shah', 'hira.shah@example.pk', '0346-4433221', 'Islamabad', 470500, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 312 DAY, '%Y-%m-%d 19:52:00')),
(3, 9, 'Saad Malik', 'saad.malik@example.pk', '0300-9988776', 'Peshawar', 138499, 'Delivered', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 299 DAY, '%Y-%m-%d 14:10:00')),
(4, 3, 'Usman Tariq', 'usman.tariq@example.pk', '0333-4567890', 'Islamabad', 89500, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 276 DAY, '%Y-%m-%d 13:9:00')),
(5, 2, 'Ayesha Khan', 'ayesha.khan@example.pk', '0321-9876543', 'Karachi', 53997, 'Delivered', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 265 DAY, '%Y-%m-%d 17:42:00')),
(6, 2, 'Ayesha Khan', 'ayesha.khan@example.pk', '0321-9876543', 'Karachi', 404500, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 232 DAY, '%Y-%m-%d 21:14:00')),
(7, 3, 'Usman Tariq', 'usman.tariq@example.pk', '0333-4567890', 'Islamabad', 144498, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 230 DAY, '%Y-%m-%d 10:22:00')),
(8, 5, 'Hamza Siddiqui', 'hamza.s@example.pk', '0312-5566778', 'Karachi', 194499, 'Cancelled', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 210 DAY, '%Y-%m-%d 22:28:00')),
(9, 1, 'Ali Raza', 'ali.raza@example.pk', '0300-1234567', 'Lahore', 217000, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 206 DAY, '%Y-%m-%d 17:26:00')),
(10, 7, 'Bilal Ahmed', 'bilal.ahmed@example.pk', '0322-3344556', 'Faisalabad', 609999, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 203 DAY, '%Y-%m-%d 20:28:00')),
(11, 11, 'Omar Farooq', 'omar.farooq@example.pk', '0313-7766554', 'Lahore', 37000, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 186 DAY, '%Y-%m-%d 13:39:00')),
(12, 9, 'Saad Malik', 'saad.malik@example.pk', '0300-9988776', 'Peshawar', 291997, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 178 DAY, '%Y-%m-%d 20:9:00')),
(13, 10, 'Hira Shah', 'hira.shah@example.pk', '0346-4433221', 'Islamabad', 228995, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 174 DAY, '%Y-%m-%d 16:59:00')),
(14, 1, 'Ali Raza', 'ali.raza@example.pk', '0300-1234567', 'Lahore', 7500, 'Cancelled', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 154 DAY, '%Y-%m-%d 12:50:00')),
(15, 8, 'Maryam Iqbal', 'maryam.iqbal@example.pk', '0335-6677889', 'Multan', 127999, 'Delivered', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 153 DAY, '%Y-%m-%d 18:6:00')),
(16, 12, 'Sana Javed', 'sana.javed@example.pk', '0324-1212343', 'Karachi', 163997, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 148 DAY, '%Y-%m-%d 18:51:00')),
(17, 12, 'Sana Javed', 'sana.javed@example.pk', '0324-1212343', 'Karachi', 207998, 'Delivered', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 124 DAY, '%Y-%m-%d 16:58:00')),
(18, 5, 'Hamza Siddiqui', 'hamza.s@example.pk', '0312-5566778', 'Karachi', 68500, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 111 DAY, '%Y-%m-%d 21:19:00')),
(19, 5, 'Hamza Siddiqui', 'hamza.s@example.pk', '0312-5566778', 'Karachi', 79999, 'Cancelled', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 110 DAY, '%Y-%m-%d 21:33:00')),
(20, 12, 'Sana Javed', 'sana.javed@example.pk', '0324-1212343', 'Karachi', 185000, 'Delivered', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 108 DAY, '%Y-%m-%d 15:28:00')),
(21, 7, 'Bilal Ahmed', 'bilal.ahmed@example.pk', '0322-3344556', 'Faisalabad', 256000, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 102 DAY, '%Y-%m-%d 11:21:00')),
(22, 3, 'Usman Tariq', 'usman.tariq@example.pk', '0333-4567890', 'Islamabad', 239000, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 100 DAY, '%Y-%m-%d 11:14:00')),
(23, 2, 'Ayesha Khan', 'ayesha.khan@example.pk', '0321-9876543', 'Karachi', 120000, 'Cancelled', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 92 DAY, '%Y-%m-%d 11:21:00')),
(24, 12, 'Sana Javed', 'sana.javed@example.pk', '0324-1212343', 'Karachi', 55500, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 89 DAY, '%Y-%m-%d 15:53:00')),
(25, 1, 'Ali Raza', 'ali.raza@example.pk', '0300-1234567', 'Lahore', 112999, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 71 DAY, '%Y-%m-%d 15:22:00')),
(26, 2, 'Ayesha Khan', 'ayesha.khan@example.pk', '0321-9876543', 'Karachi', 28500, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 71 DAY, '%Y-%m-%d 13:19:00')),
(27, 10, 'Hira Shah', 'hira.shah@example.pk', '0346-4433221', 'Islamabad', 40500, 'Delivered', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 66 DAY, '%Y-%m-%d 14:19:00')),
(28, 8, 'Maryam Iqbal', 'maryam.iqbal@example.pk', '0335-6677889', 'Multan', 280499, 'Delivered', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 62 DAY, '%Y-%m-%d 13:11:00')),
(29, 10, 'Hira Shah', 'hira.shah@example.pk', '0346-4433221', 'Islamabad', 24999, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 49 DAY, '%Y-%m-%d 17:27:00')),
(30, 7, 'Bilal Ahmed', 'bilal.ahmed@example.pk', '0322-3344556', 'Faisalabad', 64500, 'Cancelled', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 37 DAY, '%Y-%m-%d 17:13:00')),
(31, 1, 'Ali Raza', 'ali.raza@example.pk', '0300-1234567', 'Lahore', 115998, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 34 DAY, '%Y-%m-%d 13:44:00')),
(32, 12, 'Sana Javed', 'sana.javed@example.pk', '0324-1212343', 'Karachi', 223000, 'Shipped', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 32 DAY, '%Y-%m-%d 19:50:00')),
(33, 8, 'Maryam Iqbal', 'maryam.iqbal@example.pk', '0335-6677889', 'Multan', 65000, 'Shipped', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 29 DAY, '%Y-%m-%d 22:6:00')),
(34, 11, 'Omar Farooq', 'omar.farooq@example.pk', '0313-7766554', 'Lahore', 385000, 'Shipped', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 20 DAY, '%Y-%m-%d 14:19:00')),
(35, 8, 'Maryam Iqbal', 'maryam.iqbal@example.pk', '0335-6677889', 'Multan', 97500, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 19 DAY, '%Y-%m-%d 19:27:00')),
(36, 8, 'Maryam Iqbal', 'maryam.iqbal@example.pk', '0335-6677889', 'Multan', 43000, 'Pending', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 5 DAY, '%Y-%m-%d 17:9:00')),
(37, 1, 'Ali Raza', 'ali.raza@example.pk', '0300-1234567', 'Lahore', 573997, 'Processing', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 5 DAY, '%Y-%m-%d 10:35:00')),
(38, 4, 'Fatima Noor', 'fatima.noor@example.pk', '0345-1122334', 'Rawalpindi', 80500, 'Processing', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 3 DAY, '%Y-%m-%d 12:3:00')),
(39, 8, 'Maryam Iqbal', 'maryam.iqbal@example.pk', '0335-6677889', 'Multan', 555500, 'Pending', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 2 DAY, '%Y-%m-%d 10:38:00')),
(40, 10, 'Hira Shah', 'hira.shah@example.pk', '0346-4433221', 'Islamabad', 77000, 'Processing', 'cash_on_delivery', NULL, NOW() - INTERVAL 150 MINUTE),
-- builds with PC-builder parts (prices = that week's rate)
(41, 11, 'Omar Farooq', 'omar.farooq@example.pk', '0313-7766554', 'Lahore', 261400, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 16 DAY, '%Y-%m-%d 20:41:00')),
(42, 3, 'Usman Tariq', 'usman.tariq@example.pk', '0333-4567890', 'Islamabad', 203000, 'Shipped', 'jazzcash', NULL, DATE_FORMAT(NOW() - INTERVAL 6 DAY, '%Y-%m-%d 13:12:00')),
(43, 9, 'Saad Malik', 'saad.malik@example.pk', '0300-9988776', 'Peshawar', 499200, 'Processing', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 2 DAY, '%Y-%m-%d 22:05:00')),
(44, 6, 'Zainab Hussain', 'zainab.h@example.pk', '0301-2233445', 'Lahore', 161800, 'Pending', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 1 DAY, '%Y-%m-%d 16:30:00')),
(45, 2, 'Ayesha Khan', 'ayesha.khan@example.pk', '0321-9876543', 'Karachi', 129700, 'Delivered', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 11 DAY, '%Y-%m-%d 11:48:00'));

INSERT INTO order_items (id, order_id, product_id, product_name, price, quantity, subtotal, size, color, created_at) VALUES
(1, 1, 24, 'WD My Passport 2TB Portable Hard Drive', 19500, 3, 58500, NULL, NULL, NOW() - INTERVAL 324 DAY),
(2, 2, 32, 'ASUS TUF Gaming B760M-Plus WiFi D4', 52500, 1, 52500, NULL, NULL, NOW() - INTERVAL 312 DAY),
(3, 2, 22, 'Transcend ESD380C 1TB Portable SSD', 28500, 3, 85500, NULL, NULL, NOW() - INTERVAL 312 DAY),
(4, 3, 24, 'WD My Passport 2TB Portable Hard Drive', 19500, 3, 58500, NULL, NULL, NOW() - INTERVAL 299 DAY),
(5, 3, 15, 'Samsung Odyssey G5 27" 144Hz Curved', 79999, 1, 79999, NULL, NULL, NOW() - INTERVAL 299 DAY),
(6, 4, 10, 'Arozzi Verona Pro V2 Gaming Chair', 68000, 1, 68000, NULL, 'Black', NOW() - INTERVAL 276 DAY),
(7, 4, 19, 'TP-Link Archer AX55 Wi-Fi 6 Router', 21500, 1, 21500, NULL, NULL, NOW() - INTERVAL 276 DAY),
(8, 5, 9, 'MSI MAG B650 Tomahawk WiFi Motherboard', 69500, 3, 208500, NULL, NULL, NOW() - INTERVAL 265 DAY),
(9, 6, 24, 'WD My Passport 2TB Portable Hard Drive', 19500, 1, 19500, NULL, NULL, NOW() - INTERVAL 232 DAY),
(10, 6, 32, 'ASUS TUF Gaming B760M-Plus WiFi D4', 52500, 1, 52500, NULL, NULL, NOW() - INTERVAL 232 DAY),
(11, 7, 26, 'HyperX Cloud II Gaming Headset', 22999, 2, 45998, NULL, NULL, NOW() - INTERVAL 230 DAY),
(12, 7, 8, 'ASRock B650M-HDV/M.2 Motherboard', 44500, 1, 44500, NULL, NULL, NOW() - INTERVAL 230 DAY),
(13, 8, 16, 'LG 27UP850 27" 4K USB-C Monitor', 132000, 1, 132000, NULL, NULL, NOW() - INTERVAL 210 DAY),
(14, 8, 1, 'Logitech M331 Silent Plus Wireless Mouse', 4499, 1, 4499, NULL, NULL, NOW() - INTERVAL 210 DAY),
(15, 8, 20, 'TP-Link Archer C6 AC1200 Router', 9500, 1, 9500, NULL, NULL, NOW() - INTERVAL 210 DAY),
(16, 9, 16, 'LG 27UP850 27" 4K USB-C Monitor', 132000, 1, 132000, NULL, NULL, NOW() - INTERVAL 206 DAY),
(17, 9, 28, 'Logitech G331 Stereo Gaming Headset', 12500, 2, 25000, NULL, NULL, NOW() - INTERVAL 206 DAY),
(18, 10, 11, 'FlexiSpot E7 Electric Standing Desk', 145000, 1, 145000, NULL, NULL, NOW() - INTERVAL 203 DAY),
(19, 10, 32, 'ASUS TUF Gaming B760M-Plus WiFi D4', 52500, 1, 52500, NULL, NULL, NOW() - INTERVAL 203 DAY),
(20, 10, 15, 'Samsung Odyssey G5 27" 144Hz Curved', 79999, 1, 79999, NULL, NULL, NOW() - INTERVAL 203 DAY),
(21, 11, 4, 'AOC 24G2 24" 144Hz IPS Gaming Monitor', 42500, 2, 85000, NULL, NULL, NOW() - INTERVAL 186 DAY),
(22, 12, 18, 'ASUS RT-AC86U Dual-Band Gaming Router', 34999, 3, 104997, NULL, NULL, NOW() - INTERVAL 178 DAY),
(23, 12, 11, 'FlexiSpot E7 Electric Standing Desk', 145000, 1, 145000, NULL, NULL, NOW() - INTERVAL 178 DAY),
(24, 12, 14, 'Dell S2421H 24" IPS Monitor', 42000, 1, 42000, NULL, NULL, NOW() - INTERVAL 178 DAY),
(25, 13, 18, 'ASUS RT-AC86U Dual-Band Gaming Router', 34999, 3, 104997, NULL, NULL, NOW() - INTERVAL 174 DAY),
(26, 13, 1, 'Logitech M331 Silent Plus Wireless Mouse', 4499, 2, 8998, NULL, NULL, NOW() - INTERVAL 174 DAY),
(27, 13, 17, 'LG UltraWide 34WP65C 34" Curved', 115000, 1, 115000, NULL, NULL, NOW() - INTERVAL 174 DAY),
(28, 14, 25, 'SanDisk Extreme 500GB Portable SSD', 21500, 3, 64500, NULL, NULL, NOW() - INTERVAL 154 DAY),
(29, 15, 29, 'Logitech G502 HERO Gaming Mouse', 12999, 1, 12999, NULL, NULL, NOW() - INTERVAL 153 DAY),
(30, 15, 17, 'LG UltraWide 34WP65C 34" Curved', 115000, 1, 115000, NULL, NULL, NOW() - INTERVAL 153 DAY),
(31, 16, 29, 'Logitech G502 HERO Gaming Mouse', 12999, 3, 38997, NULL, NULL, NOW() - INTERVAL 148 DAY),
(32, 16, 7, 'ASUS PRIME H610M-K D4 Motherboard', 23500, 2, 47000, NULL, NULL, NOW() - INTERVAL 148 DAY),
(33, 16, 27, 'Redragon H510 Zeus Wired Gaming Headset', 11500, 1, 11500, NULL, NULL, NOW() - INTERVAL 148 DAY),
(34, 17, 26, 'HyperX Cloud II Gaming Headset', 22999, 2, 45998, NULL, NULL, NOW() - INTERVAL 124 DAY),
(35, 17, 6, 'Gigabyte B760M DS3H DDR4 Motherboard', 36500, 3, 109500, NULL, NULL, NOW() - INTERVAL 124 DAY),
(36, 18, 31, 'Razer BlackWidow V4 Pro Keyboard', 68500, 1, 68500, NULL, NULL, NOW() - INTERVAL 111 DAY),
(37, 19, 15, 'Samsung Odyssey G5 27" 144Hz Curved', 79999, 1, 79999, NULL, NULL, NOW() - INTERVAL 110 DAY),
(38, 20, 6, 'Gigabyte B760M DS3H DDR4 Motherboard', 36500, 2, 73000, NULL, NULL, NOW() - INTERVAL 108 DAY),
(39, 20, 12, 'TechZone Ergo Office Chair', 38500, 2, 77000, NULL, NULL, NOW() - INTERVAL 108 DAY),
(40, 21, 28, 'Logitech G331 Stereo Gaming Headset', 12500, 1, 12500, NULL, NULL, NOW() - INTERVAL 102 DAY),
(41, 21, 8, 'ASRock B650M-HDV/M.2 Motherboard', 44500, 1, 44500, NULL, NULL, NOW() - INTERVAL 102 DAY),
(42, 21, 17, 'LG UltraWide 34WP65C 34" Curved', 115000, 1, 115000, NULL, NULL, NOW() - INTERVAL 102 DAY),
(43, 22, 27, 'Redragon H510 Zeus Wired Gaming Headset', 11500, 1, 11500, NULL, NULL, NOW() - INTERVAL 100 DAY),
(44, 22, 28, 'Logitech G331 Stereo Gaming Headset', 12500, 1, 12500, NULL, NULL, NOW() - INTERVAL 100 DAY),
(45, 22, 8, 'ASRock B650M-HDV/M.2 Motherboard', 44500, 1, 44500, NULL, NULL, NOW() - INTERVAL 100 DAY),
(46, 23, 4, 'AOC 24G2 24" 144Hz IPS Gaming Monitor', 42500, 3, 127500, NULL, NULL, NOW() - INTERVAL 92 DAY),
(47, 23, 19, 'TP-Link Archer AX55 Wi-Fi 6 Router', 21500, 3, 64500, NULL, NULL, NOW() - INTERVAL 92 DAY),
(48, 24, 4, 'AOC 24G2 24" 144Hz IPS Gaming Monitor', 42500, 3, 127500, NULL, NULL, NOW() - INTERVAL 89 DAY),
(49, 25, 23, 'Samsung T7 1TB Portable SSD', 28500, 1, 28500, NULL, NULL, NOW() - INTERVAL 71 DAY),
(50, 25, 18, 'ASUS RT-AC86U Dual-Band Gaming Router', 34999, 1, 34999, NULL, NULL, NOW() - INTERVAL 71 DAY),
(51, 25, 14, 'Dell S2421H 24" IPS Monitor', 42000, 1, 42000, NULL, NULL, NOW() - INTERVAL 71 DAY),
(52, 26, 22, 'Transcend ESD380C 1TB Portable SSD', 28500, 1, 28500, NULL, NULL, NOW() - INTERVAL 71 DAY),
(53, 27, 7, 'ASUS PRIME H610M-K D4 Motherboard', 23500, 3, 70500, NULL, NULL, NOW() - INTERVAL 66 DAY),
(54, 28, 16, 'LG 27UP850 27" 4K USB-C Monitor', 132000, 1, 132000, NULL, NULL, NOW() - INTERVAL 62 DAY),
(55, 28, 31, 'Razer BlackWidow V4 Pro Keyboard', 68500, 1, 68500, NULL, NULL, NOW() - INTERVAL 62 DAY),
(56, 28, 15, 'Samsung Odyssey G5 27" 144Hz Curved', 79999, 1, 79999, NULL, NULL, NOW() - INTERVAL 62 DAY),
(57, 29, 13, 'Compact Writing Desk 120cm', 24999, 1, 24999, NULL, NULL, NOW() - INTERVAL 49 DAY),
(58, 30, 19, 'TP-Link Archer AX55 Wi-Fi 6 Router', 21500, 3, 64500, NULL, NULL, NOW() - INTERVAL 37 DAY),
(59, 31, 27, 'Redragon H510 Zeus Wired Gaming Headset', 11500, 1, 11500, NULL, NULL, NOW() - INTERVAL 34 DAY),
(60, 31, 30, 'Redragon K552 Kumara Mechanical Keyboard', 8999, 2, 17998, NULL, 'Black', NOW() - INTERVAL 34 DAY),
(61, 32, 6, 'Gigabyte B760M DS3H DDR4 Motherboard', 36500, 2, 73000, NULL, NULL, NOW() - INTERVAL 32 DAY),
(62, 32, 17, 'LG UltraWide 34WP65C 34" Curved', 115000, 1, 115000, NULL, NULL, NOW() - INTERVAL 32 DAY),
(63, 33, 5, 'MSI PRO B760M-E DDR4 Motherboard', 32500, 2, 65000, NULL, NULL, NOW() - INTERVAL 29 DAY),
(64, 34, 32, 'ASUS TUF Gaming B760M-Plus WiFi D4', 52500, 1, 52500, NULL, NULL, NOW() - INTERVAL 20 DAY),
(65, 35, 5, 'MSI PRO B760M-E DDR4 Motherboard', 32500, 3, 97500, NULL, NULL, NOW() - INTERVAL 19 DAY),
(66, 36, 19, 'TP-Link Archer AX55 Wi-Fi 6 Router', 21500, 2, 43000, NULL, NULL, NOW() - INTERVAL 5 DAY),
(67, 37, 30, 'Redragon K552 Kumara Mechanical Keyboard', 8999, 3, 26997, NULL, 'White', NOW() - INTERVAL 5 DAY),
(68, 37, 32, 'ASUS TUF Gaming B760M-Plus WiFi D4', 52500, 1, 52500, NULL, NULL, NOW() - INTERVAL 5 DAY),
(69, 37, 6, 'Gigabyte B760M DS3H DDR4 Motherboard', 36500, 3, 109500, NULL, NULL, NOW() - INTERVAL 5 DAY),
(70, 38, 7, 'ASUS PRIME H610M-K D4 Motherboard', 23500, 3, 70500, NULL, NULL, NOW() - INTERVAL 3 DAY),
(71, 38, 4, 'AOC 24G2 24" 144Hz IPS Gaming Monitor', 42500, 1, 42500, NULL, NULL, NOW() - INTERVAL 3 DAY),
(72, 38, 19, 'TP-Link Archer AX55 Wi-Fi 6 Router', 21500, 1, 21500, NULL, NULL, NOW() - INTERVAL 3 DAY),
(73, 39, 28, 'Logitech G331 Stereo Gaming Headset', 12500, 3, 37500, NULL, NULL, NOW() - INTERVAL 2 DAY),
(74, 39, 19, 'TP-Link Archer AX55 Wi-Fi 6 Router', 21500, 2, 43000, NULL, NULL, NOW() - INTERVAL 2 DAY),
(75, 39, 32, 'ASUS TUF Gaming B760M-Plus WiFi D4', 52500, 1, 52500, NULL, NULL, NOW() - INTERVAL 2 DAY),
(76, 40, 12, 'TechZone Ergo Office Chair', 38500, 2, 77000, NULL, NULL, NOW() - INTERVAL 150 MINUTE),
(77, 41, 46, 'AMD Ryzen 5 7600', 51200, 1, 51200, NULL, NULL, NOW() - INTERVAL 16 DAY),
(78, 41, 8, 'ASRock B650M-HDV/M.2 Motherboard', 43800, 1, 43800, NULL, NULL, NOW() - INTERVAL 16 DAY),
(79, 41, 53, 'Kingston FURY Beast 32GB (2x16) DDR5 6000', 40600, 1, 40600, NULL, NULL, NOW() - INTERVAL 16 DAY),
(80, 41, 55, 'ASUS Dual GeForce RTX 4060 8GB', 100500, 1, 100500, NULL, NULL, NOW() - INTERVAL 16 DAY),
(81, 41, 61, 'Corsair CX750 PSU', 25300, 1, 25300, NULL, NULL, NOW() - INTERVAL 16 DAY),
(82, 42, 43, 'Intel Core i5-13400F', 54000, 1, 54000, NULL, NULL, NOW() - INTERVAL 6 DAY),
(83, 42, 6, 'Gigabyte B760M DS3H DDR4 Motherboard', 36500, 1, 36500, NULL, NULL, NOW() - INTERVAL 6 DAY),
(84, 42, 52, 'Kingston FURY Beast 16GB (2x8) DDR4 3200', 13000, 1, 13000, NULL, NULL, NOW() - INTERVAL 6 DAY),
(85, 42, 58, 'Sapphire PULSE Radeon RX 7600 8GB', 82000, 1, 82000, NULL, NULL, NOW() - INTERVAL 6 DAY),
(86, 42, 60, 'Cooler Master MWE 650 Bronze V2 PSU', 17500, 1, 17500, NULL, NULL, NOW() - INTERVAL 6 DAY),
(87, 43, 47, 'AMD Ryzen 7 7800X3D', 138500, 1, 138500, NULL, NULL, NOW() - INTERVAL 2 DAY),
(88, 43, 9, 'MSI MAG B650 Tomahawk WiFi Motherboard', 71000, 1, 71000, NULL, NULL, NOW() - INTERVAL 2 DAY),
(89, 43, 57, 'Gigabyte GeForce RTX 4070 SUPER WINDFORCE OC 12GB', 209000, 1, 209000, NULL, NULL, NOW() - INTERVAL 2 DAY),
(90, 43, 63, 'Corsair RM850e PSU', 37200, 1, 37200, NULL, NULL, NOW() - INTERVAL 2 DAY),
(91, 43, 65, 'Lian Li LANCOOL 216 Case', 32000, 1, 32000, NULL, NULL, NOW() - INTERVAL 2 DAY),
(92, 43, 70, 'Thermalright Peerless Assassin 120 SE', 11500, 1, 11500, NULL, NULL, NOW() - INTERVAL 2 DAY),
(93, 44, 72, 'Kingston NV2 1TB NVMe SSD', 16900, 2, 33800, NULL, NULL, NOW() - INTERVAL 1 DAY),
(94, 44, 56, 'MSI GeForce RTX 4060 Ti Ventus 2X 8GB', 128000, 1, 128000, NULL, NULL, NOW() - INTERVAL 1 DAY),
(95, 45, 55, 'ASUS Dual GeForce RTX 4060 8GB', 96700, 1, 96700, NULL, NULL, NOW() - INTERVAL 11 DAY),
(96, 45, 74, 'Samsung 980 PRO 1TB NVMe SSD', 33000, 1, 33000, NULL, NULL, NOW() - INTERVAL 11 DAY);

-- Reviews
INSERT INTO reviews (product_id, customer_id, customer_name, rating, comment, status, admin_response, created_at) VALUES
(29, 1, 'Ali Raza', 5, 'Excellent sensor and the weights make it feel premium. Fast delivery to Lahore.', 'Approved', 'Thank you Ali! Enjoy your new mouse.', NOW() - INTERVAL 200 DAY),
(1, 2, 'Ayesha Khan', 4, 'Really is silent. Battery still on the first AA after months.', 'Approved', NULL, NOW() - INTERVAL 188 DAY),
(15, 3, 'Usman Tariq', 5, 'Colours are punchy and 144Hz is buttery smooth. Packaging was solid.', 'Approved', NULL, NOW() - INTERVAL 176 DAY),
(26, 4, 'Fatima Noor', 5, 'Comfortable for long gaming sessions, mic quality is clear.', 'Approved', NULL, NOW() - INTERVAL 164 DAY),
(10, 5, 'Hamza Siddiqui', 4, 'Great chair, assembly took 30 minutes. Lumbar pillow could be firmer.', 'Approved', 'Thanks for the feedback Hamza!', NOW() - INTERVAL 152 DAY),
(19, 6, 'Zainab Hussain', 5, 'Wi-Fi 6 coverage in a 10 marla house without dead zones.', 'Approved', NULL, NOW() - INTERVAL 140 DAY),
(22, 7, 'Bilal Ahmed', 5, 'Insanely fast transfers for video projects. Highly recommended.', 'Approved', NULL, NOW() - INTERVAL 128 DAY),
(14, 8, 'Maryam Iqbal', 4, 'Good office monitor for the price. Speakers are basic.', 'Approved', NULL, NOW() - INTERVAL 116 DAY),
(30, 9, 'Saad Malik', 3, 'Switches are loud as expected, keycaps feel a bit cheap.', 'Approved', NULL, NOW() - INTERVAL 104 DAY),
(5, 10, 'Hira Shah', 5, 'Stable board, BIOS update was easy. Works great with the 13400F.', 'Pending', NULL, NOW() - INTERVAL 92 DAY),
(27, 11, 'Omar Farooq', 5, 'Noise cancellation is unreal on flights. Worth every rupee.', 'Pending', NULL, NOW() - INTERVAL 80 DAY),
(11, 12, 'Sana Javed', 4, 'Motors are quiet and smooth. Delivery took a week to Karachi.', 'Pending', NULL, NOW() - INTERVAL 68 DAY),
(23, 1, 'Ali Raza', 2, 'Arrived late and the box was dented, drive works fine though.', 'Rejected', NULL, NOW() - INTERVAL 56 DAY),
(18, 3, 'Usman Tariq', 4, 'Range is great, app setup is a little confusing.', 'Pending', NULL, NOW() - INTERVAL 44 DAY),
(2, 6, 'Zainab Hussain', 5, 'Switching between laptop, tablet and phone is seamless.', 'Approved', NULL, NOW() - INTERVAL 32 DAY);

-- Promotions / discounts (one ended). kind + applies_to drive core/discounts.php.
INSERT INTO promotions (id, title, description, image_url, discount_percentage, discount_amount, promo_code, kind, applies_to, target_id, min_order, usage_limit, used_count, starts_at, ends_at, start_date, end_date, is_active, display_order, link_url, created_at) VALUES
(1, 'Monitor Mega Sale', 'Save 10% on every monitor this month.', 'uploads/695e07fd24255_monitor.png', 10.00, 0.00, 'TECH10', 'percent', 'category', 4, 0, NULL, 0, TIMESTAMP(CURDATE() - INTERVAL 10 DAY), TIMESTAMP(CURDATE() + INTERVAL 30 DAY, '23:59:59'), CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 30 DAY, 1, 1, '/MarketingSite/shop/products.php?category=4', NOW() - INTERVAL 10 DAY),
(2, 'Welcome Offer', 'Rs 1,000 off your first order over Rs 10,000.', 'uploads/695e07b640c78_headphones.png', 0.00, 1000.00, 'WELCOME1000', 'fixed', 'all', NULL, 10000, 500, 0, TIMESTAMP(CURDATE() - INTERVAL 60 DAY), TIMESTAMP(CURDATE() + INTERVAL 120 DAY, '23:59:59'), CURDATE() - INTERVAL 60 DAY, CURDATE() + INTERVAL 120 DAY, 1, 2, '/MarketingSite/shop/products.php', NOW() - INTERVAL 60 DAY),
(3, 'Summer Gaming Fest', '15% off gaming peripherals (ended).', 'uploads/695d7e54382e4_mouse.png', 15.00, 0.00, 'SUMMER15', 'percent', 'category', 8, 0, NULL, 0, TIMESTAMP(CURDATE() - INTERVAL 150 DAY), TIMESTAMP(CURDATE() - INTERVAL 90 DAY, '23:59:59'), CURDATE() - INTERVAL 150 DAY, CURDATE() - INTERVAL 90 DAY, 1, 3, '/MarketingSite/shop/products.php?category=8', NOW() - INTERVAL 150 DAY),
(4, 'Free Delivery', 'Free shipping anywhere in Pakistan on orders over Rs 3,000.', 'uploads/695e08dbaed9c_Screenshot_2026-01-07_121813.png', 0.00, 0.00, 'FREESHIP', 'free_shipping', 'all', NULL, 3000, NULL, 0, TIMESTAMP(CURDATE() - INTERVAL 14 DAY), TIMESTAMP(CURDATE() + INTERVAL 45 DAY, '23:59:59'), CURDATE() - INTERVAL 14 DAY, CURDATE() + INTERVAL 45 DAY, 1, 4, '/MarketingSite/shop/products.php', NOW() - INTERVAL 14 DAY);

-- Contact messages
INSERT INTO contact_messages (name, email, subject, message, status, created_at) VALUES
('Kamran Akhtar', 'kamran.akhtar@example.pk', 'Bulk order for office', 'We need 15 ergonomic chairs and 15 standing desks for our new office in Lahore. Can you share a corporate quote?', 'new', NOW() - INTERVAL 1 DAY),
('Nadia Rehman', 'nadia.r@example.pk', 'Warranty claim', 'My HyperX headset left earcup stopped working after 5 months. How do I claim the warranty?', 'new', NOW() - INTERVAL 3 DAY),
('Ahmed Ali', 'ahmed.ali@example.pk', 'Delivery to Quetta?', 'Do you deliver to Quetta, and what are the shipping charges for a monitor?', 'read', NOW() - INTERVAL 9 DAY),
('Sara Qureshi', 'sara.q@example.pk', 'JazzCash payment failed', 'Amount was deducted from my JazzCash wallet but the order shows pending. Please check order #38.', 'replied', NOW() - INTERVAL 15 DAY),
('Imran Butt', 'imran.butt@example.pk', 'Feedback', 'Great service and quick delivery. Please add more AMD processors to the catalogue!', 'archived', NOW() - INTERVAL 40 DAY);

-- -----------------------------------------------------------------------------
-- Catalogue fields (status, SKU, compare-at, cost, thresholds)
-- -----------------------------------------------------------------------------
-- a product cannot be created after its first sale
UPDATE products p
JOIN (SELECT i.product_id, MIN(o.order_date) AS first_sale FROM order_items i JOIN orders o ON o.id = i.order_id GROUP BY i.product_id) f ON f.product_id = p.id
SET p.created_at = LEAST(p.created_at, f.first_sale - INTERVAL 14 DAY);

UPDATE products SET
    sku = CONCAT('TZ-', LPAD(id, 4, '0')),
    status = IF(is_active = 1, 'active', 'draft'),
    cost_price = ROUND(price * (0.70 + MOD(id * 7, 11) / 100), -1),
    low_stock_threshold = 5;
-- superseded by the Archer AX55: archived (kept for order history, hidden from the shop)
UPDATE products SET status = 'archived', is_active = 0 WHERE id = 20;
UPDATE products SET compare_at_price = CASE id
    WHEN 4 THEN 47500 WHEN 15 THEN 89999 WHEN 19 THEN 24999
    WHEN 23 THEN 32999 WHEN 26 THEN 26999 WHEN 29 THEN 15499 END
WHERE id IN (4, 15, 19, 23, 26, 29);
UPDATE products SET seo_title = CONCAT(name, ' Price in Pakistan | TechZone'),
    seo_description = CONCAT('Buy the ', name, ' at TechZone Lahore. Cash on delivery and JazzCash, delivery across Pakistan.')
WHERE id IN (4, 15, 26, 29, 30);

-- -----------------------------------------------------------------------------
-- Marketplace: sellers (1 = TechZone, the house), ~25% of the catalogue sold by partner shops
-- -----------------------------------------------------------------------------
INSERT INTO sellers (id, slug, name, market, address, city, phone, whatsapp, rating, about, joined_at, status, commission_pct) VALUES
(1, 'techzone', 'TechZone', 'Hafeez Centre', 'Shop 214, 2nd floor, Hafeez Centre, Gulberg III', 'Lahore', '042-35761234', '923011427871', 4.8, 'Our own shop in Hafeez Centre since 2019. Every part is checked before dispatch.', CURDATE() - INTERVAL 400 DAY, 'active', 0.00),
(2, 'rizwan-computers', 'Rizwan Computers', 'Hall Road', 'Shop 7, Rex Centre, Hall Road', 'Lahore', '042-37321458', '923214567812', 4.5, 'Hall Road wholesaler for processors, boards, memory and power supplies.', CURDATE() - INTERVAL 150 DAY, 'active', 8.00),
(3, 'al-fatah-tech', 'Al-Fatah Tech', 'Hafeez Centre', 'Shop 31, Ground floor, Hafeez Centre, Gulberg III', 'Lahore', '042-35870332', '923334455661', 4.3, 'Monitors, graphics cards and storage with official warranty.', CURDATE() - INTERVAL 110 DAY, 'active', 8.00),
(4, 'gamers-den-pk', 'Gamers Den PK', 'DHA Phase 5', '58-CCA, Commercial Area, DHA Phase 5', 'Lahore', '0321-4440987', '923214440987', 4.7, 'Gaming builds, cases, cooling and peripherals. Build advice on WhatsApp.', CURDATE() - INTERVAL 75 DAY, 'active', 8.00);
UPDATE products SET seller_id = CASE
    WHEN id IN (21, 42, 49, 50, 52, 60, 62, 72) THEN 2
    WHEN id IN (14, 17, 24, 58, 74, 75) THEN 3
    WHEN id IN (27, 30, 56, 64, 70) THEN 4
    ELSE 1 END;

-- One seller account per marketplace seller (role 'seller', scoped to that seller's products and orders)
INSERT INTO admins (id, name, username, email, phone, password, role, is_active, seller_id, last_login_at, created_at) VALUES
(4, 'Rizwan Akhtar', 'rizwan', 'rizwan@sellers.techzone.pk', '042-37321458', '$2y$10$3pBzE4/2hEQCj2ovDPy7fOn6sLP0eRCkgY6J0nqy9nnSxVuJONL0C', 'seller', 1, 2, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 150 DAY),
(5, 'Usman Fatah', 'alfatah', 'alfatah@sellers.techzone.pk', '042-35870332', '$2y$10$3pBzE4/2hEQCj2ovDPy7fOn6sLP0eRCkgY6J0nqy9nnSxVuJONL0C', 'seller', 1, 3, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 110 DAY),
(6, 'Hamza Qureshi', 'gamersden', 'gamersden@sellers.techzone.pk', '0321-4440987', '$2y$10$3pBzE4/2hEQCj2ovDPy7fOn6sLP0eRCkgY6J0nqy9nnSxVuJONL0C', 'seller', 1, 4, NOW() - INTERVAL 6 HOUR, NOW() - INTERVAL 75 DAY);

-- Applications: the three partner shops (approved), one rejected, two waiting for review
INSERT INTO seller_applications (shop_name, owner_name, phone, email, market, city, categories, ntn, message, status, review_note, seller_id, reviewed_by, reviewed_at, created_at) VALUES
('Rizwan Computers', 'Rizwan Akhtar', '042-37321458', 'rizwan@sellers.techzone.pk', 'Hall Road', 'Lahore', 'Processors, Motherboards, Memory, Power supplies', '3520147-8', 'Wholesale on Hall Road since 2011.', 'approved', NULL, 2, 1, NOW() - INTERVAL 151 DAY, NOW() - INTERVAL 155 DAY),
('Al-Fatah Tech', 'Usman Fatah', '042-35870332', 'alfatah@sellers.techzone.pk', 'Hafeez Centre', 'Lahore', 'Monitors, Graphics cards, Storage', NULL, 'Ground floor of Hafeez Centre, official warranty on everything we sell.', 'approved', NULL, 3, 1, NOW() - INTERVAL 111 DAY, NOW() - INTERVAL 114 DAY),
('Gamers Den PK', 'Hamza Qureshi', '0321-4440987', 'gamersden@sellers.techzone.pk', 'DHA Phase 5', 'Lahore', 'Cases, Cooling, Peripherals', '4410298-1', 'We build gaming PCs and sell cases, coolers and peripherals.', 'approved', NULL, 4, 2, NOW() - INTERVAL 76 DAY, NOW() - INTERVAL 80 DAY),
('Cheap Laptops Hub', 'Adeel Butt', '0302-1112233', 'adeel.butt@example.pk', 'Hall Road', 'Lahore', 'Laptops', NULL, 'Used and refurbished laptops.', 'rejected', 'We only list new, boxed parts with warranty; refurbished laptops are out of scope.', NULL, 2, NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 42 DAY);
INSERT INTO seller_applications (shop_name, owner_name, phone, email, market, city, categories, ntn, message, status, created_at) VALUES
('PC Zone Saddar', 'Kashif Mehmood', '0300-2145566', 'kashif@pczonesaddar.pk', 'Saddar', 'Rawalpindi', 'Graphics cards, Processors, Memory', '4219876-3', 'Two shops in Saddar Rawalpindi, 9 years in business. We want to list our GPU stock.', 'pending', NOW() - INTERVAL 3 DAY),
('Techno Hub', 'Naveed Anjum', '0333-8812290', 'naveed.anjum@technohub.pk', 'Hafeez Centre', 'Lahore', 'Monitors, Networking', NULL, 'Shop 112 in Hafeez Centre. Mainly monitors and routers, can ship same day in Lahore.', 'pending', NOW() - INTERVAL 20 HOUR);

-- -----------------------------------------------------------------------------
-- Specs and PC-builder attributes (every product; keys per part type in STOREFRONT.md)
-- -----------------------------------------------------------------------------
INSERT INTO product_attributes (product_id, attr_key, attr_value, sort_order) VALUES
(1, 'type', 'mouse', 0),
(1, 'connection', 'Wireless 2.4 GHz', 1),
(1, 'dpi', '1000', 2),
(1, 'battery', '24 months (AA)', 3),
(1, 'key_spec_1', 'Wireless', 4),
(1, 'key_spec_2', 'Silent clicks', 5),
(2, 'type', 'keyboard', 0),
(2, 'connection', 'Bluetooth', 1),
(2, 'devices', '3', 2),
(2, 'layout', 'Compact', 3),
(2, 'key_spec_1', 'Bluetooth', 4),
(2, 'key_spec_2', '3 devices', 5),
(3, 'type', 'router', 0),
(3, 'wifi_standard', 'Wi-Fi 5', 1),
(3, 'speed_class', 'AC1200', 2),
(3, 'lan_ports', '2', 3),
(3, 'gigabit', '1', 4),
(3, 'key_spec_1', 'Wi-Fi 5', 5),
(3, 'key_spec_2', 'AC1200', 6),
(4, 'size_in', '24', 0),
(4, 'refresh_hz', '144', 1),
(4, 'resolution', '1920x1080', 2),
(4, 'panel', 'IPS', 3),
(4, 'key_spec_1', '24-inch', 4),
(4, 'key_spec_2', '144 Hz', 5),
(5, 'socket', 'LGA1700', 0),
(5, 'chipset', 'B760', 1),
(5, 'memory_type', 'DDR4', 2),
(5, 'form_factor', 'mATX', 3),
(5, 'm2_slots', '1', 4),
(5, 'wifi', '0', 5),
(5, 'key_spec_1', 'LGA1700', 6),
(5, 'key_spec_2', 'DDR4 mATX', 7),
(6, 'socket', 'LGA1700', 0),
(6, 'chipset', 'B760', 1),
(6, 'memory_type', 'DDR4', 2),
(6, 'form_factor', 'mATX', 3),
(6, 'm2_slots', '2', 4),
(6, 'wifi', '0', 5),
(6, 'key_spec_1', 'LGA1700', 6),
(6, 'key_spec_2', 'DDR4 mATX', 7),
(7, 'socket', 'LGA1700', 0),
(7, 'chipset', 'H610', 1),
(7, 'memory_type', 'DDR4', 2),
(7, 'form_factor', 'mATX', 3),
(7, 'm2_slots', '1', 4),
(7, 'wifi', '0', 5),
(7, 'key_spec_1', 'LGA1700', 6),
(7, 'key_spec_2', 'DDR4 mATX', 7),
(8, 'socket', 'AM5', 0),
(8, 'chipset', 'B650', 1),
(8, 'memory_type', 'DDR5', 2),
(8, 'form_factor', 'mATX', 3),
(8, 'm2_slots', '2', 4),
(8, 'wifi', '0', 5),
(8, 'key_spec_1', 'AM5', 6),
(8, 'key_spec_2', 'DDR5 mATX', 7),
(9, 'socket', 'AM5', 0),
(9, 'chipset', 'B650', 1),
(9, 'memory_type', 'DDR5', 2),
(9, 'form_factor', 'ATX', 3),
(9, 'm2_slots', '3', 4),
(9, 'wifi', '1', 5),
(9, 'key_spec_1', 'AM5', 6),
(9, 'key_spec_2', 'DDR5 ATX', 7),
(10, 'type', 'gaming chair', 0),
(10, 'max_load_kg', '145', 1),
(10, 'armrests', '4D', 2),
(10, 'recline_deg', '165', 3),
(10, 'key_spec_1', '4D armrests', 4),
(10, 'key_spec_2', 'Up to 145 kg', 5),
(11, 'type', 'standing desk', 0),
(11, 'motors', '2', 1),
(11, 'height_range_cm', '58-123', 2),
(11, 'max_load_kg', '125', 3),
(11, 'key_spec_1', 'Dual motor', 4),
(11, 'key_spec_2', '58–123 cm', 5),
(12, 'type', 'office chair', 0),
(12, 'back', 'Mesh', 1),
(12, 'mechanism', 'Synchro-tilt', 2),
(12, 'headrest', '1', 3),
(12, 'key_spec_1', 'Mesh back', 4),
(12, 'key_spec_2', 'Synchro-tilt', 5),
(13, 'type', 'desk', 0),
(13, 'width_cm', '120', 1),
(13, 'top', 'Engineered wood', 2),
(13, 'frame', 'Steel', 3),
(13, 'key_spec_1', '120 cm', 4),
(13, 'key_spec_2', 'Steel frame', 5),
(14, 'size_in', '24', 0),
(14, 'refresh_hz', '75', 1),
(14, 'resolution', '1920x1080', 2),
(14, 'panel', 'IPS', 3),
(14, 'key_spec_1', '24-inch', 4),
(14, 'key_spec_2', '75 Hz', 5),
(15, 'size_in', '27', 0),
(15, 'refresh_hz', '144', 1),
(15, 'resolution', '2560x1440', 2),
(15, 'panel', 'VA', 3),
(15, 'key_spec_1', '27-inch', 4),
(15, 'key_spec_2', '144 Hz', 5),
(16, 'size_in', '27', 0),
(16, 'refresh_hz', '60', 1),
(16, 'resolution', '3840x2160', 2),
(16, 'panel', 'IPS', 3),
(16, 'key_spec_1', '27-inch', 4),
(16, 'key_spec_2', '60 Hz', 5),
(17, 'size_in', '34', 0),
(17, 'refresh_hz', '160', 1),
(17, 'resolution', '3440x1440', 2),
(17, 'panel', 'VA', 3),
(17, 'key_spec_1', '34-inch', 4),
(17, 'key_spec_2', '160 Hz', 5),
(18, 'type', 'router', 0),
(18, 'wifi_standard', 'Wi-Fi 5', 1),
(18, 'speed_class', 'AC2900', 2),
(18, 'lan_ports', '4', 3),
(18, 'gigabit', '1', 4),
(18, 'key_spec_1', 'Wi-Fi 5', 5),
(18, 'key_spec_2', 'AC2900', 6),
(19, 'type', 'router', 0),
(19, 'wifi_standard', 'Wi-Fi 6', 1),
(19, 'speed_class', 'AX3000', 2),
(19, 'lan_ports', '4', 3),
(19, 'gigabit', '1', 4),
(19, 'key_spec_1', 'Wi-Fi 6', 5),
(19, 'key_spec_2', 'AX3000', 6),
(20, 'type', 'router', 0),
(20, 'wifi_standard', 'Wi-Fi 5', 1),
(20, 'speed_class', 'AC1200', 2),
(20, 'lan_ports', '4', 3),
(20, 'gigabit', '1', 4),
(20, 'key_spec_1', 'Wi-Fi 5', 5),
(20, 'key_spec_2', 'AC1200', 6),
(21, 'type', 'router', 0),
(21, 'wifi_standard', 'Wi-Fi 5', 1),
(21, 'speed_class', 'AC1200', 2),
(21, 'lan_ports', '3', 3),
(21, 'gigabit', '1', 4),
(21, 'key_spec_1', 'Wi-Fi 5', 5),
(21, 'key_spec_2', 'AC1200', 6),
(22, 'interface', 'USB 3.2 Gen 2x2', 0),
(22, 'capacity_gb', '1000', 1),
(22, 'read_mbs', '2000', 2),
(22, 'internal', '0', 3),
(22, 'key_spec_1', '1 TB', 4),
(22, 'key_spec_2', '2,000 MB/s', 5),
(23, 'interface', 'USB 3.2 Gen 2', 0),
(23, 'capacity_gb', '1000', 1),
(23, 'read_mbs', '1050', 2),
(23, 'internal', '0', 3),
(23, 'key_spec_1', '1 TB', 4),
(23, 'key_spec_2', '1,050 MB/s', 5),
(24, 'interface', 'USB 3.0', 0),
(24, 'capacity_gb', '2000', 1),
(24, 'read_mbs', '140', 2),
(24, 'internal', '0', 3),
(24, 'key_spec_1', '2 TB', 4),
(24, 'key_spec_2', '140 MB/s', 5),
(25, 'interface', 'USB 3.2 Gen 2', 0),
(25, 'capacity_gb', '500', 1),
(25, 'read_mbs', '1050', 2),
(25, 'internal', '0', 3),
(25, 'key_spec_1', '500 GB', 4),
(25, 'key_spec_2', '1,050 MB/s', 5),
(26, 'type', 'headset', 0),
(26, 'connection', 'USB / 3.5 mm', 1),
(26, 'surround', 'Virtual 7.1', 2),
(26, 'driver_mm', '53', 3),
(26, 'key_spec_1', '7.1 surround', 4),
(26, 'key_spec_2', 'USB and 3.5 mm', 5),
(27, 'type', 'headset', 0),
(27, 'connection', 'USB', 1),
(27, 'surround', 'Virtual 7.1', 2),
(27, 'driver_mm', '53', 3),
(27, 'key_spec_1', '7.1 surround', 4),
(27, 'key_spec_2', 'USB', 5),
(28, 'type', 'headset', 0),
(28, 'connection', '3.5 mm', 1),
(28, 'surround', 'Stereo', 2),
(28, 'driver_mm', '50', 3),
(28, 'key_spec_1', 'Stereo', 4),
(28, 'key_spec_2', '3.5 mm', 5),
(29, 'type', 'mouse', 0),
(29, 'connection', 'Wired USB', 1),
(29, 'dpi', '25600', 2),
(29, 'buttons', '11', 3),
(29, 'key_spec_1', '25,600 DPI', 4),
(29, 'key_spec_2', 'Wired', 5),
(30, 'type', 'keyboard', 0),
(30, 'connection', 'Wired USB', 1),
(30, 'layout', 'TKL', 2),
(30, 'switches', 'Outemu Blue', 3),
(30, 'key_spec_1', 'TKL', 4),
(30, 'key_spec_2', 'Outemu Blue', 5),
(31, 'type', 'keyboard', 0),
(31, 'connection', 'Wired USB', 1),
(31, 'layout', 'Full size', 2),
(31, 'switches', 'Razer Green', 3),
(31, 'key_spec_1', 'Full size', 4),
(31, 'key_spec_2', 'Razer Green', 5),
(32, 'socket', 'LGA1700', 0),
(32, 'chipset', 'B760', 1),
(32, 'memory_type', 'DDR4', 2),
(32, 'form_factor', 'mATX', 3),
(32, 'm2_slots', '2', 4),
(32, 'wifi', '1', 5),
(32, 'key_spec_1', 'LGA1700', 6),
(32, 'key_spec_2', 'DDR4 mATX', 7),
(41, 'socket', 'LGA1700', 0),
(41, 'cores', '4', 1),
(41, 'threads', '8', 2),
(41, 'base_ghz', '3.3', 3),
(41, 'boost_ghz', '4.3', 4),
(41, 'tdp_w', '58', 5),
(41, 'igpu', '0', 6),
(41, 'bundled_cooler', '1', 7),
(41, 'key_spec_1', 'LGA1700', 8),
(41, 'key_spec_2', '4 cores', 9),
(42, 'socket', 'LGA1700', 0),
(42, 'cores', '6', 1),
(42, 'threads', '12', 2),
(42, 'base_ghz', '2.5', 3),
(42, 'boost_ghz', '4.4', 4),
(42, 'tdp_w', '65', 5),
(42, 'igpu', '0', 6),
(42, 'bundled_cooler', '1', 7),
(42, 'key_spec_1', 'LGA1700', 8),
(42, 'key_spec_2', '6 cores', 9),
(43, 'socket', 'LGA1700', 0),
(43, 'cores', '10', 1),
(43, 'threads', '16', 2),
(43, 'base_ghz', '2.5', 3),
(43, 'boost_ghz', '4.6', 4),
(43, 'tdp_w', '65', 5),
(43, 'igpu', '0', 6),
(43, 'bundled_cooler', '1', 7),
(43, 'key_spec_1', 'LGA1700', 8),
(43, 'key_spec_2', '10 cores', 9),
(44, 'socket', 'LGA1700', 0),
(44, 'cores', '14', 1),
(44, 'threads', '20', 2),
(44, 'base_ghz', '3.5', 3),
(44, 'boost_ghz', '5.3', 4),
(44, 'tdp_w', '125', 5),
(44, 'igpu', '1', 6),
(44, 'bundled_cooler', '0', 7),
(44, 'key_spec_1', 'LGA1700', 8),
(44, 'key_spec_2', '14 cores', 9),
(45, 'socket', 'LGA1700', 0),
(45, 'cores', '20', 1),
(45, 'threads', '28', 2),
(45, 'base_ghz', '3.4', 3),
(45, 'boost_ghz', '5.6', 4),
(45, 'tdp_w', '125', 5),
(45, 'igpu', '1', 6),
(45, 'bundled_cooler', '0', 7),
(45, 'key_spec_1', 'LGA1700', 8),
(45, 'key_spec_2', '20 cores', 9),
(46, 'socket', 'AM5', 0),
(46, 'cores', '6', 1),
(46, 'threads', '12', 2),
(46, 'base_ghz', '3.8', 3),
(46, 'boost_ghz', '5.1', 4),
(46, 'tdp_w', '65', 5),
(46, 'igpu', '1', 6),
(46, 'bundled_cooler', '1', 7),
(46, 'key_spec_1', 'AM5', 8),
(46, 'key_spec_2', '6 cores', 9),
(47, 'socket', 'AM5', 0),
(47, 'cores', '8', 1),
(47, 'threads', '16', 2),
(47, 'base_ghz', '4.2', 3),
(47, 'boost_ghz', '5', 4),
(47, 'tdp_w', '120', 5),
(47, 'igpu', '1', 6),
(47, 'bundled_cooler', '0', 7),
(47, 'key_spec_1', 'AM5', 8),
(47, 'key_spec_2', '8 cores', 9),
(48, 'socket', 'AM5', 0),
(48, 'cores', '6', 1),
(48, 'threads', '12', 2),
(48, 'base_ghz', '4.3', 3),
(48, 'boost_ghz', '5', 4),
(48, 'tdp_w', '65', 5),
(48, 'igpu', '1', 6),
(48, 'bundled_cooler', '1', 7),
(48, 'key_spec_1', 'AM5', 8),
(48, 'key_spec_2', '6 cores', 9),
(49, 'socket', 'AM4', 0),
(49, 'cores', '6', 1),
(49, 'threads', '12', 2),
(49, 'base_ghz', '3.5', 3),
(49, 'boost_ghz', '4.4', 4),
(49, 'tdp_w', '65', 5),
(49, 'igpu', '0', 6),
(49, 'bundled_cooler', '1', 7),
(49, 'key_spec_1', 'AM4', 8),
(49, 'key_spec_2', '6 cores', 9),
(50, 'socket', 'AM4', 0),
(50, 'chipset', 'B550', 1),
(50, 'memory_type', 'DDR4', 2),
(50, 'form_factor', 'mATX', 3),
(50, 'm2_slots', '2', 4),
(50, 'wifi', '1', 5),
(50, 'key_spec_1', 'AM4', 6),
(50, 'key_spec_2', 'DDR4 mATX', 7),
(51, 'memory_type', 'DDR4', 0),
(51, 'capacity_gb', '16', 1),
(51, 'modules', '2', 2),
(51, 'speed_mts', '3200', 3),
(51, 'key_spec_1', 'DDR4', 4),
(51, 'key_spec_2', '16 GB', 5),
(52, 'memory_type', 'DDR4', 0),
(52, 'capacity_gb', '16', 1),
(52, 'modules', '2', 2),
(52, 'speed_mts', '3200', 3),
(52, 'key_spec_1', 'DDR4', 4),
(52, 'key_spec_2', '16 GB', 5),
(53, 'memory_type', 'DDR5', 0),
(53, 'capacity_gb', '32', 1),
(53, 'modules', '2', 2),
(53, 'speed_mts', '6000', 3),
(53, 'key_spec_1', 'DDR5', 4),
(53, 'key_spec_2', '32 GB', 5),
(54, 'memory_type', 'DDR5', 0),
(54, 'capacity_gb', '32', 1),
(54, 'modules', '2', 2),
(54, 'speed_mts', '5600', 3),
(54, 'key_spec_1', 'DDR5', 4),
(54, 'key_spec_2', '32 GB', 5),
(55, 'chip', 'GeForce RTX 4060', 0),
(55, 'vram_gb', '8', 1),
(55, 'tdp_w', '115', 2),
(55, 'length_mm', '227', 3),
(55, 'min_psu_w', '550', 4),
(55, 'key_spec_1', '8 GB', 5),
(55, 'key_spec_2', '115 W', 6),
(55, 'brand', 'ASUS', 7),
(56, 'chip', 'GeForce RTX 4060 Ti', 0),
(56, 'vram_gb', '8', 1),
(56, 'tdp_w', '160', 2),
(56, 'length_mm', '199', 3),
(56, 'min_psu_w', '550', 4),
(56, 'key_spec_1', '8 GB', 5),
(56, 'key_spec_2', '160 W', 6),
(56, 'brand', 'MSI', 7),
(57, 'chip', 'GeForce RTX 4070 SUPER', 0),
(57, 'vram_gb', '12', 1),
(57, 'tdp_w', '220', 2),
(57, 'length_mm', '261', 3),
(57, 'min_psu_w', '650', 4),
(57, 'key_spec_1', '12 GB', 5),
(57, 'key_spec_2', '220 W', 6),
(57, 'brand', 'Gigabyte', 7),
(58, 'chip', 'Radeon RX 7600', 0),
(58, 'vram_gb', '8', 1),
(58, 'tdp_w', '165', 2),
(58, 'length_mm', '240', 3),
(58, 'min_psu_w', '550', 4),
(58, 'key_spec_1', '8 GB', 5),
(58, 'key_spec_2', '165 W', 6),
(58, 'brand', 'Sapphire', 7),
(59, 'chip', 'Radeon RX 7800 XT', 0),
(59, 'vram_gb', '16', 1),
(59, 'tdp_w', '263', 2),
(59, 'length_mm', '280', 3),
(59, 'min_psu_w', '700', 4),
(59, 'key_spec_1', '16 GB', 5),
(59, 'key_spec_2', '263 W', 6),
(59, 'brand', 'Sapphire', 7),
(60, 'wattage', '650', 0),
(60, 'efficiency', '80 Plus Bronze', 1),
(60, 'modular', '0', 2),
(60, 'key_spec_1', '650 W', 3),
(60, 'key_spec_2', '80 Plus Bronze', 4),
(61, 'wattage', '750', 0),
(61, 'efficiency', '80 Plus Bronze', 1),
(61, 'modular', '0', 2),
(61, 'key_spec_1', '750 W', 3),
(61, 'key_spec_2', '80 Plus Bronze', 4),
(62, 'wattage', '500', 0),
(62, 'efficiency', '80 Plus White', 1),
(62, 'modular', '0', 2),
(62, 'key_spec_1', '500 W', 3),
(62, 'key_spec_2', '80 Plus White', 4),
(63, 'wattage', '850', 0),
(63, 'efficiency', '80 Plus Gold', 1),
(63, 'modular', '1', 2),
(63, 'key_spec_1', '850 W', 3),
(63, 'key_spec_2', '80 Plus Gold', 4),
(64, 'form_factors', 'ATX,mATX,ITX', 0),
(64, 'max_gpu_mm', '365', 1),
(64, 'max_cooler_mm', '165', 2),
(64, 'key_spec_1', 'ATX', 3),
(64, 'key_spec_2', 'GPU up to 365 mm', 4),
(65, 'form_factors', 'ATX,mATX,ITX', 0),
(65, 'max_gpu_mm', '392', 1),
(65, 'max_cooler_mm', '180', 2),
(65, 'key_spec_1', 'ATX', 3),
(65, 'key_spec_2', 'GPU up to 392 mm', 4),
(66, 'form_factors', 'mATX,ITX', 0),
(66, 'max_gpu_mm', '360', 1),
(66, 'max_cooler_mm', '159', 2),
(66, 'key_spec_1', 'mATX', 3),
(66, 'key_spec_2', 'GPU up to 360 mm', 4),
(67, 'form_factors', 'ATX,mATX,ITX', 0),
(67, 'max_gpu_mm', '380', 1),
(67, 'max_cooler_mm', '175', 2),
(67, 'key_spec_1', 'ATX', 3),
(67, 'key_spec_2', 'GPU up to 380 mm', 4),
(68, 'type', 'air', 0),
(68, 'sockets', 'LGA1700,AM5,AM4', 1),
(68, 'max_tdp_w', '220', 2),
(68, 'height_mm', '155', 3),
(68, 'key_spec_1', 'Air cooler', 4),
(68, 'key_spec_2', 'Up to 220 W', 5),
(69, 'type', 'air', 0),
(69, 'sockets', 'LGA1700,AM5,AM4', 1),
(69, 'max_tdp_w', '150', 2),
(69, 'height_mm', '159', 3),
(69, 'key_spec_1', 'Air cooler', 4),
(69, 'key_spec_2', 'Up to 150 W', 5),
(70, 'type', 'air', 0),
(70, 'sockets', 'LGA1700,AM5,AM4', 1),
(70, 'max_tdp_w', '245', 2),
(70, 'height_mm', '155', 3),
(70, 'key_spec_1', 'Air cooler', 4),
(70, 'key_spec_2', 'Up to 245 W', 5),
(71, 'type', 'aio', 0),
(71, 'sockets', 'LGA1700,AM5,AM4', 1),
(71, 'max_tdp_w', '300', 2),
(71, 'height_mm', '0', 3),
(71, 'radiator_mm', '240', 4),
(71, 'key_spec_1', '240 mm AIO', 5),
(71, 'key_spec_2', 'Up to 300 W', 6),
(72, 'interface', 'NVMe PCIe 4.0', 0),
(72, 'capacity_gb', '1000', 1),
(72, 'read_mbs', '3500', 2),
(72, 'internal', '1', 3),
(72, 'key_spec_1', '1 TB', 4),
(72, 'key_spec_2', '3,500 MB/s', 5),
(73, 'interface', 'NVMe PCIe 4.0', 0),
(73, 'capacity_gb', '1000', 1),
(73, 'read_mbs', '5150', 2),
(73, 'internal', '1', 3),
(73, 'key_spec_1', '1 TB', 4),
(73, 'key_spec_2', '5,150 MB/s', 5),
(74, 'interface', 'NVMe PCIe 4.0', 0),
(74, 'capacity_gb', '1000', 1),
(74, 'read_mbs', '7000', 2),
(74, 'internal', '1', 3),
(74, 'key_spec_1', '1 TB', 4),
(74, 'key_spec_2', '7,000 MB/s', 5),
(75, 'interface', 'NVMe PCIe 3.0', 0),
(75, 'capacity_gb', '2000', 1),
(75, 'read_mbs', '3500', 2),
(75, 'internal', '1', 3),
(75, 'key_spec_1', '2 TB', 4),
(75, 'key_spec_2', '3,500 MB/s', 5);

-- -----------------------------------------------------------------------------
-- Rate history: 12 weekly points per product (77 days ago .. today), newest = products.price.
-- This week: 8 drops, 4 rises, the rest hold. Generated deterministically (seeded by product id).
-- -----------------------------------------------------------------------------
INSERT INTO price_history (product_id, price, recorded_at) VALUES
(1, 4300.00, CURDATE() - INTERVAL 77 DAY), (1, 4400.00, CURDATE() - INTERVAL 70 DAY), (1, 4500.00, CURDATE() - INTERVAL 63 DAY), (1, 4600.00, CURDATE() - INTERVAL 56 DAY), (1, 4700.00, CURDATE() - INTERVAL 49 DAY), (1, 4600.00, CURDATE() - INTERVAL 42 DAY), (1, 4700.00, CURDATE() - INTERVAL 35 DAY), (1, 4600.00, CURDATE() - INTERVAL 28 DAY), (1, 4500.00, CURDATE() - INTERVAL 21 DAY), (1, 4600.00, CURDATE() - INTERVAL 14 DAY), (1, 4499.00, CURDATE() - INTERVAL 7 DAY), (1, 4499.00, CURDATE() - INTERVAL 0 DAY),
(2, 10500.00, CURDATE() - INTERVAL 77 DAY), (2, 10800.00, CURDATE() - INTERVAL 70 DAY), (2, 10400.00, CURDATE() - INTERVAL 63 DAY), (2, 10200.00, CURDATE() - INTERVAL 56 DAY), (2, 9900.00, CURDATE() - INTERVAL 49 DAY), (2, 9800.00, CURDATE() - INTERVAL 42 DAY), (2, 9500.00, CURDATE() - INTERVAL 35 DAY), (2, 9700.00, CURDATE() - INTERVAL 28 DAY), (2, 9700.00, CURDATE() - INTERVAL 21 DAY), (2, 9600.00, CURDATE() - INTERVAL 14 DAY), (2, 9999.00, CURDATE() - INTERVAL 7 DAY), (2, 9999.00, CURDATE() - INTERVAL 0 DAY),
(3, 7300.00, CURDATE() - INTERVAL 77 DAY), (3, 7200.00, CURDATE() - INTERVAL 70 DAY), (3, 7200.00, CURDATE() - INTERVAL 63 DAY), (3, 7000.00, CURDATE() - INTERVAL 56 DAY), (3, 6900.00, CURDATE() - INTERVAL 49 DAY), (3, 7100.00, CURDATE() - INTERVAL 42 DAY), (3, 7000.00, CURDATE() - INTERVAL 35 DAY), (3, 6800.00, CURDATE() - INTERVAL 28 DAY), (3, 7000.00, CURDATE() - INTERVAL 21 DAY), (3, 7000.00, CURDATE() - INTERVAL 14 DAY), (3, 6999.00, CURDATE() - INTERVAL 7 DAY), (3, 6999.00, CURDATE() - INTERVAL 0 DAY),
(4, 39200.00, CURDATE() - INTERVAL 77 DAY), (4, 40300.00, CURDATE() - INTERVAL 70 DAY), (4, 40100.00, CURDATE() - INTERVAL 63 DAY), (4, 39400.00, CURDATE() - INTERVAL 56 DAY), (4, 39800.00, CURDATE() - INTERVAL 49 DAY), (4, 40800.00, CURDATE() - INTERVAL 42 DAY), (4, 41100.00, CURDATE() - INTERVAL 35 DAY), (4, 42300.00, CURDATE() - INTERVAL 28 DAY), (4, 41900.00, CURDATE() - INTERVAL 21 DAY), (4, 43400.00, CURDATE() - INTERVAL 14 DAY), (4, 41800.00, CURDATE() - INTERVAL 7 DAY), (4, 42500.00, CURDATE() - INTERVAL 0 DAY),
(5, 28300.00, CURDATE() - INTERVAL 77 DAY), (5, 29200.00, CURDATE() - INTERVAL 70 DAY), (5, 30300.00, CURDATE() - INTERVAL 63 DAY), (5, 29800.00, CURDATE() - INTERVAL 56 DAY), (5, 29500.00, CURDATE() - INTERVAL 49 DAY), (5, 30000.00, CURDATE() - INTERVAL 42 DAY), (5, 30800.00, CURDATE() - INTERVAL 35 DAY), (5, 31700.00, CURDATE() - INTERVAL 28 DAY), (5, 33000.00, CURDATE() - INTERVAL 21 DAY), (5, 33700.00, CURDATE() - INTERVAL 14 DAY), (5, 32500.00, CURDATE() - INTERVAL 7 DAY), (5, 32500.00, CURDATE() - INTERVAL 0 DAY),
(6, 34700.00, CURDATE() - INTERVAL 77 DAY), (6, 35600.00, CURDATE() - INTERVAL 70 DAY), (6, 34900.00, CURDATE() - INTERVAL 63 DAY), (6, 35300.00, CURDATE() - INTERVAL 56 DAY), (6, 34100.00, CURDATE() - INTERVAL 49 DAY), (6, 33100.00, CURDATE() - INTERVAL 42 DAY), (6, 32200.00, CURDATE() - INTERVAL 35 DAY), (6, 33500.00, CURDATE() - INTERVAL 28 DAY), (6, 34600.00, CURDATE() - INTERVAL 21 DAY), (6, 35900.00, CURDATE() - INTERVAL 14 DAY), (6, 36500.00, CURDATE() - INTERVAL 7 DAY), (6, 36500.00, CURDATE() - INTERVAL 0 DAY),
(7, 21800.00, CURDATE() - INTERVAL 77 DAY), (7, 22400.00, CURDATE() - INTERVAL 70 DAY), (7, 23000.00, CURDATE() - INTERVAL 63 DAY), (7, 23500.00, CURDATE() - INTERVAL 56 DAY), (7, 23800.00, CURDATE() - INTERVAL 49 DAY), (7, 24200.00, CURDATE() - INTERVAL 42 DAY), (7, 23400.00, CURDATE() - INTERVAL 35 DAY), (7, 22900.00, CURDATE() - INTERVAL 28 DAY), (7, 23200.00, CURDATE() - INTERVAL 21 DAY), (7, 22800.00, CURDATE() - INTERVAL 14 DAY), (7, 23500.00, CURDATE() - INTERVAL 7 DAY), (7, 23500.00, CURDATE() - INTERVAL 0 DAY),
(8, 43900.00, CURDATE() - INTERVAL 77 DAY), (8, 43300.00, CURDATE() - INTERVAL 70 DAY), (8, 41700.00, CURDATE() - INTERVAL 63 DAY), (8, 41600.00, CURDATE() - INTERVAL 56 DAY), (8, 42200.00, CURDATE() - INTERVAL 49 DAY), (8, 42700.00, CURDATE() - INTERVAL 42 DAY), (8, 42300.00, CURDATE() - INTERVAL 35 DAY), (8, 42600.00, CURDATE() - INTERVAL 28 DAY), (8, 43800.00, CURDATE() - INTERVAL 21 DAY), (8, 44700.00, CURDATE() - INTERVAL 14 DAY), (8, 44500.00, CURDATE() - INTERVAL 7 DAY), (8, 44500.00, CURDATE() - INTERVAL 0 DAY),
(9, 73100.00, CURDATE() - INTERVAL 77 DAY), (9, 74700.00, CURDATE() - INTERVAL 70 DAY), (9, 75600.00, CURDATE() - INTERVAL 63 DAY), (9, 74900.00, CURDATE() - INTERVAL 56 DAY), (9, 74000.00, CURDATE() - INTERVAL 49 DAY), (9, 73000.00, CURDATE() - INTERVAL 42 DAY), (9, 74000.00, CURDATE() - INTERVAL 35 DAY), (9, 73500.00, CURDATE() - INTERVAL 28 DAY), (9, 76000.00, CURDATE() - INTERVAL 21 DAY), (9, 73600.00, CURDATE() - INTERVAL 14 DAY), (9, 71000.00, CURDATE() - INTERVAL 7 DAY), (9, 69500.00, CURDATE() - INTERVAL 0 DAY),
(10, 65800.00, CURDATE() - INTERVAL 77 DAY), (10, 67900.00, CURDATE() - INTERVAL 70 DAY), (10, 67300.00, CURDATE() - INTERVAL 63 DAY), (10, 68300.00, CURDATE() - INTERVAL 56 DAY), (10, 69000.00, CURDATE() - INTERVAL 49 DAY), (10, 67800.00, CURDATE() - INTERVAL 42 DAY), (10, 68100.00, CURDATE() - INTERVAL 35 DAY), (10, 68600.00, CURDATE() - INTERVAL 28 DAY), (10, 67600.00, CURDATE() - INTERVAL 21 DAY), (10, 69400.00, CURDATE() - INTERVAL 14 DAY), (10, 68000.00, CURDATE() - INTERVAL 7 DAY), (10, 68000.00, CURDATE() - INTERVAL 0 DAY),
(11, 161000.00, CURDATE() - INTERVAL 77 DAY), (11, 163500.00, CURDATE() - INTERVAL 70 DAY), (11, 162700.00, CURDATE() - INTERVAL 63 DAY), (11, 162000.00, CURDATE() - INTERVAL 56 DAY), (11, 163400.00, CURDATE() - INTERVAL 49 DAY), (11, 161900.00, CURDATE() - INTERVAL 42 DAY), (11, 156400.00, CURDATE() - INTERVAL 35 DAY), (11, 158700.00, CURDATE() - INTERVAL 28 DAY), (11, 153500.00, CURDATE() - INTERVAL 21 DAY), (11, 149900.00, CURDATE() - INTERVAL 14 DAY), (11, 145000.00, CURDATE() - INTERVAL 7 DAY), (11, 145000.00, CURDATE() - INTERVAL 0 DAY),
(12, 40000.00, CURDATE() - INTERVAL 77 DAY), (12, 40700.00, CURDATE() - INTERVAL 70 DAY), (12, 40700.00, CURDATE() - INTERVAL 63 DAY), (12, 40000.00, CURDATE() - INTERVAL 56 DAY), (12, 38800.00, CURDATE() - INTERVAL 49 DAY), (12, 38200.00, CURDATE() - INTERVAL 42 DAY), (12, 39100.00, CURDATE() - INTERVAL 35 DAY), (12, 40400.00, CURDATE() - INTERVAL 28 DAY), (12, 38900.00, CURDATE() - INTERVAL 21 DAY), (12, 39900.00, CURDATE() - INTERVAL 14 DAY), (12, 38500.00, CURDATE() - INTERVAL 7 DAY), (12, 38500.00, CURDATE() - INTERVAL 0 DAY),
(13, 23600.00, CURDATE() - INTERVAL 77 DAY), (13, 23300.00, CURDATE() - INTERVAL 70 DAY), (13, 23800.00, CURDATE() - INTERVAL 63 DAY), (13, 24400.00, CURDATE() - INTERVAL 56 DAY), (13, 25400.00, CURDATE() - INTERVAL 49 DAY), (13, 25900.00, CURDATE() - INTERVAL 42 DAY), (13, 25800.00, CURDATE() - INTERVAL 35 DAY), (13, 25200.00, CURDATE() - INTERVAL 28 DAY), (13, 25400.00, CURDATE() - INTERVAL 21 DAY), (13, 24400.00, CURDATE() - INTERVAL 14 DAY), (13, 24999.00, CURDATE() - INTERVAL 7 DAY), (13, 24999.00, CURDATE() - INTERVAL 0 DAY),
(14, 42100.00, CURDATE() - INTERVAL 77 DAY), (14, 43300.00, CURDATE() - INTERVAL 70 DAY), (14, 42600.00, CURDATE() - INTERVAL 63 DAY), (14, 43200.00, CURDATE() - INTERVAL 56 DAY), (14, 44000.00, CURDATE() - INTERVAL 49 DAY), (14, 45200.00, CURDATE() - INTERVAL 42 DAY), (14, 43600.00, CURDATE() - INTERVAL 35 DAY), (14, 42000.00, CURDATE() - INTERVAL 28 DAY), (14, 41600.00, CURDATE() - INTERVAL 21 DAY), (14, 42700.00, CURDATE() - INTERVAL 14 DAY), (14, 42000.00, CURDATE() - INTERVAL 7 DAY), (14, 42000.00, CURDATE() - INTERVAL 0 DAY),
(15, 87500.00, CURDATE() - INTERVAL 77 DAY), (15, 85400.00, CURDATE() - INTERVAL 70 DAY), (15, 85600.00, CURDATE() - INTERVAL 63 DAY), (15, 85800.00, CURDATE() - INTERVAL 56 DAY), (15, 84900.00, CURDATE() - INTERVAL 49 DAY), (15, 88300.00, CURDATE() - INTERVAL 42 DAY), (15, 86900.00, CURDATE() - INTERVAL 35 DAY), (15, 84999.00, CURDATE() - INTERVAL 28 DAY), (15, 79999.00, CURDATE() - INTERVAL 21 DAY), (15, 79999.00, CURDATE() - INTERVAL 14 DAY), (15, 79999.00, CURDATE() - INTERVAL 7 DAY), (15, 79999.00, CURDATE() - INTERVAL 0 DAY),
(16, 139700.00, CURDATE() - INTERVAL 77 DAY), (16, 135000.00, CURDATE() - INTERVAL 70 DAY), (16, 132300.00, CURDATE() - INTERVAL 63 DAY), (16, 136100.00, CURDATE() - INTERVAL 56 DAY), (16, 133000.00, CURDATE() - INTERVAL 49 DAY), (16, 133500.00, CURDATE() - INTERVAL 42 DAY), (16, 130100.00, CURDATE() - INTERVAL 35 DAY), (16, 130700.00, CURDATE() - INTERVAL 28 DAY), (16, 130400.00, CURDATE() - INTERVAL 21 DAY), (16, 128500.00, CURDATE() - INTERVAL 14 DAY), (16, 132000.00, CURDATE() - INTERVAL 7 DAY), (16, 132000.00, CURDATE() - INTERVAL 0 DAY),
(17, 131600.00, CURDATE() - INTERVAL 77 DAY), (17, 135200.00, CURDATE() - INTERVAL 70 DAY), (17, 132500.00, CURDATE() - INTERVAL 63 DAY), (17, 127900.00, CURDATE() - INTERVAL 56 DAY), (17, 127100.00, CURDATE() - INTERVAL 49 DAY), (17, 127500.00, CURDATE() - INTERVAL 42 DAY), (17, 123700.00, CURDATE() - INTERVAL 35 DAY), (17, 119500.00, CURDATE() - INTERVAL 28 DAY), (17, 119200.00, CURDATE() - INTERVAL 21 DAY), (17, 115600.00, CURDATE() - INTERVAL 14 DAY), (17, 115000.00, CURDATE() - INTERVAL 7 DAY), (17, 115000.00, CURDATE() - INTERVAL 0 DAY),
(18, 36100.00, CURDATE() - INTERVAL 77 DAY), (18, 36500.00, CURDATE() - INTERVAL 70 DAY), (18, 35900.00, CURDATE() - INTERVAL 63 DAY), (18, 36200.00, CURDATE() - INTERVAL 56 DAY), (18, 34900.00, CURDATE() - INTERVAL 49 DAY), (18, 35600.00, CURDATE() - INTERVAL 42 DAY), (18, 35700.00, CURDATE() - INTERVAL 35 DAY), (18, 34900.00, CURDATE() - INTERVAL 28 DAY), (18, 34800.00, CURDATE() - INTERVAL 21 DAY), (18, 34700.00, CURDATE() - INTERVAL 14 DAY), (18, 34999.00, CURDATE() - INTERVAL 7 DAY), (18, 34999.00, CURDATE() - INTERVAL 0 DAY),
(19, 23700.00, CURDATE() - INTERVAL 77 DAY), (19, 24300.00, CURDATE() - INTERVAL 70 DAY), (19, 23500.00, CURDATE() - INTERVAL 63 DAY), (19, 22800.00, CURDATE() - INTERVAL 56 DAY), (19, 22700.00, CURDATE() - INTERVAL 49 DAY), (19, 22900.00, CURDATE() - INTERVAL 42 DAY), (19, 22900.00, CURDATE() - INTERVAL 35 DAY), (19, 22800.00, CURDATE() - INTERVAL 28 DAY), (19, 22500.00, CURDATE() - INTERVAL 21 DAY), (19, 21500.00, CURDATE() - INTERVAL 14 DAY), (19, 21500.00, CURDATE() - INTERVAL 7 DAY), (19, 21500.00, CURDATE() - INTERVAL 0 DAY),
(20, 9300.00, CURDATE() - INTERVAL 77 DAY), (20, 9400.00, CURDATE() - INTERVAL 70 DAY), (20, 9400.00, CURDATE() - INTERVAL 63 DAY), (20, 9600.00, CURDATE() - INTERVAL 56 DAY), (20, 9800.00, CURDATE() - INTERVAL 49 DAY), (20, 9800.00, CURDATE() - INTERVAL 42 DAY), (20, 9800.00, CURDATE() - INTERVAL 35 DAY), (20, 10200.00, CURDATE() - INTERVAL 28 DAY), (20, 9900.00, CURDATE() - INTERVAL 21 DAY), (20, 9600.00, CURDATE() - INTERVAL 14 DAY), (20, 9500.00, CURDATE() - INTERVAL 7 DAY), (20, 9500.00, CURDATE() - INTERVAL 0 DAY),
(21, 8200.00, CURDATE() - INTERVAL 77 DAY), (21, 7900.00, CURDATE() - INTERVAL 70 DAY), (21, 8200.00, CURDATE() - INTERVAL 63 DAY), (21, 8500.00, CURDATE() - INTERVAL 56 DAY), (21, 8200.00, CURDATE() - INTERVAL 49 DAY), (21, 7900.00, CURDATE() - INTERVAL 42 DAY), (21, 7600.00, CURDATE() - INTERVAL 35 DAY), (21, 7500.00, CURDATE() - INTERVAL 28 DAY), (21, 7700.00, CURDATE() - INTERVAL 21 DAY), (21, 7700.00, CURDATE() - INTERVAL 14 DAY), (21, 7500.00, CURDATE() - INTERVAL 7 DAY), (21, 7500.00, CURDATE() - INTERVAL 0 DAY),
(22, 29000.00, CURDATE() - INTERVAL 77 DAY), (22, 28000.00, CURDATE() - INTERVAL 70 DAY), (22, 29100.00, CURDATE() - INTERVAL 63 DAY), (22, 28800.00, CURDATE() - INTERVAL 56 DAY), (22, 28700.00, CURDATE() - INTERVAL 49 DAY), (22, 29100.00, CURDATE() - INTERVAL 42 DAY), (22, 29500.00, CURDATE() - INTERVAL 35 DAY), (22, 29200.00, CURDATE() - INTERVAL 28 DAY), (22, 28900.00, CURDATE() - INTERVAL 21 DAY), (22, 29400.00, CURDATE() - INTERVAL 14 DAY), (22, 28500.00, CURDATE() - INTERVAL 7 DAY), (22, 28500.00, CURDATE() - INTERVAL 0 DAY),
(23, 31000.00, CURDATE() - INTERVAL 77 DAY), (23, 31200.00, CURDATE() - INTERVAL 70 DAY), (23, 31500.00, CURDATE() - INTERVAL 63 DAY), (23, 30800.00, CURDATE() - INTERVAL 56 DAY), (23, 31800.00, CURDATE() - INTERVAL 49 DAY), (23, 32200.00, CURDATE() - INTERVAL 42 DAY), (23, 31100.00, CURDATE() - INTERVAL 35 DAY), (23, 31000.00, CURDATE() - INTERVAL 28 DAY), (23, 29800.00, CURDATE() - INTERVAL 21 DAY), (23, 29500.00, CURDATE() - INTERVAL 14 DAY), (23, 29500.00, CURDATE() - INTERVAL 7 DAY), (23, 28500.00, CURDATE() - INTERVAL 0 DAY),
(24, 21400.00, CURDATE() - INTERVAL 77 DAY), (24, 22200.00, CURDATE() - INTERVAL 70 DAY), (24, 22000.00, CURDATE() - INTERVAL 63 DAY), (24, 21800.00, CURDATE() - INTERVAL 56 DAY), (24, 21700.00, CURDATE() - INTERVAL 49 DAY), (24, 21700.00, CURDATE() - INTERVAL 42 DAY), (24, 20900.00, CURDATE() - INTERVAL 35 DAY), (24, 20200.00, CURDATE() - INTERVAL 28 DAY), (24, 20500.00, CURDATE() - INTERVAL 21 DAY), (24, 19800.00, CURDATE() - INTERVAL 14 DAY), (24, 19500.00, CURDATE() - INTERVAL 7 DAY), (24, 19500.00, CURDATE() - INTERVAL 0 DAY),
(25, 21000.00, CURDATE() - INTERVAL 77 DAY), (25, 20900.00, CURDATE() - INTERVAL 70 DAY), (25, 20900.00, CURDATE() - INTERVAL 63 DAY), (25, 20400.00, CURDATE() - INTERVAL 56 DAY), (25, 19700.00, CURDATE() - INTERVAL 49 DAY), (25, 20200.00, CURDATE() - INTERVAL 42 DAY), (25, 20400.00, CURDATE() - INTERVAL 35 DAY), (25, 19900.00, CURDATE() - INTERVAL 28 DAY), (25, 20100.00, CURDATE() - INTERVAL 21 DAY), (25, 20600.00, CURDATE() - INTERVAL 14 DAY), (25, 21500.00, CURDATE() - INTERVAL 7 DAY), (25, 21500.00, CURDATE() - INTERVAL 0 DAY),
(26, 23900.00, CURDATE() - INTERVAL 77 DAY), (26, 24600.00, CURDATE() - INTERVAL 70 DAY), (26, 24800.00, CURDATE() - INTERVAL 63 DAY), (26, 24400.00, CURDATE() - INTERVAL 56 DAY), (26, 25400.00, CURDATE() - INTERVAL 49 DAY), (26, 24700.00, CURDATE() - INTERVAL 42 DAY), (26, 24600.00, CURDATE() - INTERVAL 35 DAY), (26, 24200.00, CURDATE() - INTERVAL 28 DAY), (26, 23700.00, CURDATE() - INTERVAL 21 DAY), (26, 23700.00, CURDATE() - INTERVAL 14 DAY), (26, 22999.00, CURDATE() - INTERVAL 7 DAY), (26, 22999.00, CURDATE() - INTERVAL 0 DAY),
(27, 14100.00, CURDATE() - INTERVAL 77 DAY), (27, 13700.00, CURDATE() - INTERVAL 70 DAY), (27, 13200.00, CURDATE() - INTERVAL 63 DAY), (27, 13600.00, CURDATE() - INTERVAL 56 DAY), (27, 13400.00, CURDATE() - INTERVAL 49 DAY), (27, 13200.00, CURDATE() - INTERVAL 42 DAY), (27, 12800.00, CURDATE() - INTERVAL 35 DAY), (27, 12300.00, CURDATE() - INTERVAL 28 DAY), (27, 11900.00, CURDATE() - INTERVAL 21 DAY), (27, 11700.00, CURDATE() - INTERVAL 14 DAY), (27, 11500.00, CURDATE() - INTERVAL 7 DAY), (27, 11500.00, CURDATE() - INTERVAL 0 DAY),
(28, 13100.00, CURDATE() - INTERVAL 77 DAY), (28, 12900.00, CURDATE() - INTERVAL 70 DAY), (28, 13000.00, CURDATE() - INTERVAL 63 DAY), (28, 13300.00, CURDATE() - INTERVAL 56 DAY), (28, 12900.00, CURDATE() - INTERVAL 49 DAY), (28, 12900.00, CURDATE() - INTERVAL 42 DAY), (28, 12700.00, CURDATE() - INTERVAL 35 DAY), (28, 12500.00, CURDATE() - INTERVAL 28 DAY), (28, 12500.00, CURDATE() - INTERVAL 21 DAY), (28, 12400.00, CURDATE() - INTERVAL 14 DAY), (28, 12500.00, CURDATE() - INTERVAL 7 DAY), (28, 12500.00, CURDATE() - INTERVAL 0 DAY),
(29, 12600.00, CURDATE() - INTERVAL 77 DAY), (29, 12300.00, CURDATE() - INTERVAL 70 DAY), (29, 12800.00, CURDATE() - INTERVAL 63 DAY), (29, 13000.00, CURDATE() - INTERVAL 56 DAY), (29, 13400.00, CURDATE() - INTERVAL 49 DAY), (29, 13700.00, CURDATE() - INTERVAL 42 DAY), (29, 13600.00, CURDATE() - INTERVAL 35 DAY), (29, 13400.00, CURDATE() - INTERVAL 28 DAY), (29, 13000.00, CURDATE() - INTERVAL 21 DAY), (29, 13400.00, CURDATE() - INTERVAL 14 DAY), (29, 12999.00, CURDATE() - INTERVAL 7 DAY), (29, 12999.00, CURDATE() - INTERVAL 0 DAY),
(30, 9100.00, CURDATE() - INTERVAL 77 DAY), (30, 8800.00, CURDATE() - INTERVAL 70 DAY), (30, 9000.00, CURDATE() - INTERVAL 63 DAY), (30, 8800.00, CURDATE() - INTERVAL 56 DAY), (30, 9100.00, CURDATE() - INTERVAL 49 DAY), (30, 9100.00, CURDATE() - INTERVAL 42 DAY), (30, 9100.00, CURDATE() - INTERVAL 35 DAY), (30, 9400.00, CURDATE() - INTERVAL 28 DAY), (30, 9300.00, CURDATE() - INTERVAL 21 DAY), (30, 8900.00, CURDATE() - INTERVAL 14 DAY), (30, 8999.00, CURDATE() - INTERVAL 7 DAY), (30, 8999.00, CURDATE() - INTERVAL 0 DAY),
(31, 66900.00, CURDATE() - INTERVAL 77 DAY), (31, 66900.00, CURDATE() - INTERVAL 70 DAY), (31, 66300.00, CURDATE() - INTERVAL 63 DAY), (31, 67000.00, CURDATE() - INTERVAL 56 DAY), (31, 69100.00, CURDATE() - INTERVAL 49 DAY), (31, 68400.00, CURDATE() - INTERVAL 42 DAY), (31, 69900.00, CURDATE() - INTERVAL 35 DAY), (31, 69000.00, CURDATE() - INTERVAL 28 DAY), (31, 66900.00, CURDATE() - INTERVAL 21 DAY), (31, 67500.00, CURDATE() - INTERVAL 14 DAY), (31, 68500.00, CURDATE() - INTERVAL 7 DAY), (31, 68500.00, CURDATE() - INTERVAL 0 DAY),
(32, 53200.00, CURDATE() - INTERVAL 77 DAY), (32, 51400.00, CURDATE() - INTERVAL 70 DAY), (32, 50900.00, CURDATE() - INTERVAL 63 DAY), (32, 50700.00, CURDATE() - INTERVAL 56 DAY), (32, 52400.00, CURDATE() - INTERVAL 49 DAY), (32, 53000.00, CURDATE() - INTERVAL 42 DAY), (32, 52000.00, CURDATE() - INTERVAL 35 DAY), (32, 52100.00, CURDATE() - INTERVAL 28 DAY), (32, 51800.00, CURDATE() - INTERVAL 21 DAY), (32, 52700.00, CURDATE() - INTERVAL 14 DAY), (32, 52500.00, CURDATE() - INTERVAL 7 DAY), (32, 52500.00, CURDATE() - INTERVAL 0 DAY),
(41, 22900.00, CURDATE() - INTERVAL 77 DAY), (41, 22900.00, CURDATE() - INTERVAL 70 DAY), (41, 22300.00, CURDATE() - INTERVAL 63 DAY), (41, 23100.00, CURDATE() - INTERVAL 56 DAY), (41, 23700.00, CURDATE() - INTERVAL 49 DAY), (41, 24600.00, CURDATE() - INTERVAL 42 DAY), (41, 25200.00, CURDATE() - INTERVAL 35 DAY), (41, 25900.00, CURDATE() - INTERVAL 28 DAY), (41, 26600.00, CURDATE() - INTERVAL 21 DAY), (41, 27500.00, CURDATE() - INTERVAL 14 DAY), (41, 27500.00, CURDATE() - INTERVAL 7 DAY), (41, 27500.00, CURDATE() - INTERVAL 0 DAY),
(42, 38500.00, CURDATE() - INTERVAL 77 DAY), (42, 38800.00, CURDATE() - INTERVAL 70 DAY), (42, 39400.00, CURDATE() - INTERVAL 63 DAY), (42, 39200.00, CURDATE() - INTERVAL 56 DAY), (42, 38500.00, CURDATE() - INTERVAL 49 DAY), (42, 38500.00, CURDATE() - INTERVAL 42 DAY), (42, 38400.00, CURDATE() - INTERVAL 35 DAY), (42, 37300.00, CURDATE() - INTERVAL 28 DAY), (42, 36700.00, CURDATE() - INTERVAL 21 DAY), (42, 37000.00, CURDATE() - INTERVAL 14 DAY), (42, 36500.00, CURDATE() - INTERVAL 7 DAY), (42, 36500.00, CURDATE() - INTERVAL 0 DAY),
(43, 52500.00, CURDATE() - INTERVAL 77 DAY), (43, 54600.00, CURDATE() - INTERVAL 70 DAY), (43, 56100.00, CURDATE() - INTERVAL 63 DAY), (43, 54200.00, CURDATE() - INTERVAL 56 DAY), (43, 52800.00, CURDATE() - INTERVAL 49 DAY), (43, 54800.00, CURDATE() - INTERVAL 42 DAY), (43, 54000.00, CURDATE() - INTERVAL 35 DAY), (43, 52000.00, CURDATE() - INTERVAL 28 DAY), (43, 51000.00, CURDATE() - INTERVAL 21 DAY), (43, 52100.00, CURDATE() - INTERVAL 14 DAY), (43, 54000.00, CURDATE() - INTERVAL 7 DAY), (43, 54000.00, CURDATE() - INTERVAL 0 DAY),
(44, 94700.00, CURDATE() - INTERVAL 77 DAY), (44, 91900.00, CURDATE() - INTERVAL 70 DAY), (44, 88700.00, CURDATE() - INTERVAL 63 DAY), (44, 87800.00, CURDATE() - INTERVAL 56 DAY), (44, 87500.00, CURDATE() - INTERVAL 49 DAY), (44, 88900.00, CURDATE() - INTERVAL 42 DAY), (44, 86300.00, CURDATE() - INTERVAL 35 DAY), (44, 88000.00, CURDATE() - INTERVAL 28 DAY), (44, 85200.00, CURDATE() - INTERVAL 21 DAY), (44, 84300.00, CURDATE() - INTERVAL 14 DAY), (44, 82000.00, CURDATE() - INTERVAL 7 DAY), (44, 82000.00, CURDATE() - INTERVAL 0 DAY),
(45, 116900.00, CURDATE() - INTERVAL 77 DAY), (45, 114600.00, CURDATE() - INTERVAL 70 DAY), (45, 114500.00, CURDATE() - INTERVAL 63 DAY), (45, 114800.00, CURDATE() - INTERVAL 56 DAY), (45, 113300.00, CURDATE() - INTERVAL 49 DAY), (45, 115600.00, CURDATE() - INTERVAL 42 DAY), (45, 117600.00, CURDATE() - INTERVAL 35 DAY), (45, 113900.00, CURDATE() - INTERVAL 28 DAY), (45, 114200.00, CURDATE() - INTERVAL 21 DAY), (45, 117500.00, CURDATE() - INTERVAL 14 DAY), (45, 118000.00, CURDATE() - INTERVAL 7 DAY), (45, 118000.00, CURDATE() - INTERVAL 0 DAY),
(46, 54700.00, CURDATE() - INTERVAL 77 DAY), (46, 55300.00, CURDATE() - INTERVAL 70 DAY), (46, 53800.00, CURDATE() - INTERVAL 63 DAY), (46, 52100.00, CURDATE() - INTERVAL 56 DAY), (46, 54200.00, CURDATE() - INTERVAL 49 DAY), (46, 54000.00, CURDATE() - INTERVAL 42 DAY), (46, 52000.00, CURDATE() - INTERVAL 35 DAY), (46, 52700.00, CURDATE() - INTERVAL 28 DAY), (46, 51200.00, CURDATE() - INTERVAL 21 DAY), (46, 51800.00, CURDATE() - INTERVAL 14 DAY), (46, 53200.00, CURDATE() - INTERVAL 7 DAY), (46, 54000.00, CURDATE() - INTERVAL 0 DAY),
(47, 135700.00, CURDATE() - INTERVAL 77 DAY), (47, 132500.00, CURDATE() - INTERVAL 70 DAY), (47, 137500.00, CURDATE() - INTERVAL 63 DAY), (47, 139300.00, CURDATE() - INTERVAL 56 DAY), (47, 136500.00, CURDATE() - INTERVAL 49 DAY), (47, 140300.00, CURDATE() - INTERVAL 42 DAY), (47, 145600.00, CURDATE() - INTERVAL 35 DAY), (47, 143300.00, CURDATE() - INTERVAL 28 DAY), (47, 141500.00, CURDATE() - INTERVAL 21 DAY), (47, 138100.00, CURDATE() - INTERVAL 14 DAY), (47, 138500.00, CURDATE() - INTERVAL 7 DAY), (47, 135000.00, CURDATE() - INTERVAL 0 DAY),
(48, 50400.00, CURDATE() - INTERVAL 77 DAY), (48, 51200.00, CURDATE() - INTERVAL 70 DAY), (48, 49700.00, CURDATE() - INTERVAL 63 DAY), (48, 51300.00, CURDATE() - INTERVAL 56 DAY), (48, 52100.00, CURDATE() - INTERVAL 49 DAY), (48, 50600.00, CURDATE() - INTERVAL 42 DAY), (48, 49800.00, CURDATE() - INTERVAL 35 DAY), (48, 49400.00, CURDATE() - INTERVAL 28 DAY), (48, 48800.00, CURDATE() - INTERVAL 21 DAY), (48, 50800.00, CURDATE() - INTERVAL 14 DAY), (48, 52000.00, CURDATE() - INTERVAL 7 DAY), (48, 52000.00, CURDATE() - INTERVAL 0 DAY),
(49, 29700.00, CURDATE() - INTERVAL 77 DAY), (49, 29900.00, CURDATE() - INTERVAL 70 DAY), (49, 29400.00, CURDATE() - INTERVAL 63 DAY), (49, 30500.00, CURDATE() - INTERVAL 56 DAY), (49, 30600.00, CURDATE() - INTERVAL 49 DAY), (49, 31300.00, CURDATE() - INTERVAL 42 DAY), (49, 30700.00, CURDATE() - INTERVAL 35 DAY), (49, 30800.00, CURDATE() - INTERVAL 28 DAY), (49, 31700.00, CURDATE() - INTERVAL 21 DAY), (49, 31400.00, CURDATE() - INTERVAL 14 DAY), (49, 31500.00, CURDATE() - INTERVAL 7 DAY), (49, 31500.00, CURDATE() - INTERVAL 0 DAY),
(50, 32000.00, CURDATE() - INTERVAL 77 DAY), (50, 31600.00, CURDATE() - INTERVAL 70 DAY), (50, 32300.00, CURDATE() - INTERVAL 63 DAY), (50, 33000.00, CURDATE() - INTERVAL 56 DAY), (50, 32500.00, CURDATE() - INTERVAL 49 DAY), (50, 32800.00, CURDATE() - INTERVAL 42 DAY), (50, 32200.00, CURDATE() - INTERVAL 35 DAY), (50, 32600.00, CURDATE() - INTERVAL 28 DAY), (50, 33500.00, CURDATE() - INTERVAL 21 DAY), (50, 33700.00, CURDATE() - INTERVAL 14 DAY), (50, 34500.00, CURDATE() - INTERVAL 7 DAY), (50, 34500.00, CURDATE() - INTERVAL 0 DAY),
(51, 13800.00, CURDATE() - INTERVAL 77 DAY), (51, 13600.00, CURDATE() - INTERVAL 70 DAY), (51, 13500.00, CURDATE() - INTERVAL 63 DAY), (51, 13700.00, CURDATE() - INTERVAL 56 DAY), (51, 13800.00, CURDATE() - INTERVAL 49 DAY), (51, 13500.00, CURDATE() - INTERVAL 42 DAY), (51, 13500.00, CURDATE() - INTERVAL 35 DAY), (51, 13300.00, CURDATE() - INTERVAL 28 DAY), (51, 13000.00, CURDATE() - INTERVAL 21 DAY), (51, 12700.00, CURDATE() - INTERVAL 14 DAY), (51, 12500.00, CURDATE() - INTERVAL 7 DAY), (51, 12500.00, CURDATE() - INTERVAL 0 DAY),
(52, 13300.00, CURDATE() - INTERVAL 77 DAY), (52, 13800.00, CURDATE() - INTERVAL 70 DAY), (52, 13500.00, CURDATE() - INTERVAL 63 DAY), (52, 13600.00, CURDATE() - INTERVAL 56 DAY), (52, 13300.00, CURDATE() - INTERVAL 49 DAY), (52, 13200.00, CURDATE() - INTERVAL 42 DAY), (52, 13200.00, CURDATE() - INTERVAL 35 DAY), (52, 13600.00, CURDATE() - INTERVAL 28 DAY), (52, 13400.00, CURDATE() - INTERVAL 21 DAY), (52, 13300.00, CURDATE() - INTERVAL 14 DAY), (52, 13000.00, CURDATE() - INTERVAL 7 DAY), (52, 13000.00, CURDATE() - INTERVAL 0 DAY),
(53, 43400.00, CURDATE() - INTERVAL 77 DAY), (53, 44400.00, CURDATE() - INTERVAL 70 DAY), (53, 43300.00, CURDATE() - INTERVAL 63 DAY), (53, 44100.00, CURDATE() - INTERVAL 56 DAY), (53, 42600.00, CURDATE() - INTERVAL 49 DAY), (53, 41800.00, CURDATE() - INTERVAL 42 DAY), (53, 41500.00, CURDATE() - INTERVAL 35 DAY), (53, 41900.00, CURDATE() - INTERVAL 28 DAY), (53, 40600.00, CURDATE() - INTERVAL 21 DAY), (53, 39200.00, CURDATE() - INTERVAL 14 DAY), (53, 40500.00, CURDATE() - INTERVAL 7 DAY), (53, 38500.00, CURDATE() - INTERVAL 0 DAY),
(54, 33400.00, CURDATE() - INTERVAL 77 DAY), (54, 34600.00, CURDATE() - INTERVAL 70 DAY), (54, 34900.00, CURDATE() - INTERVAL 63 DAY), (54, 34100.00, CURDATE() - INTERVAL 56 DAY), (54, 33300.00, CURDATE() - INTERVAL 49 DAY), (54, 34000.00, CURDATE() - INTERVAL 42 DAY), (54, 35300.00, CURDATE() - INTERVAL 35 DAY), (54, 36100.00, CURDATE() - INTERVAL 28 DAY), (54, 35100.00, CURDATE() - INTERVAL 21 DAY), (54, 33800.00, CURDATE() - INTERVAL 14 DAY), (54, 35000.00, CURDATE() - INTERVAL 7 DAY), (54, 35000.00, CURDATE() - INTERVAL 0 DAY),
(55, 109300.00, CURDATE() - INTERVAL 77 DAY), (55, 109500.00, CURDATE() - INTERVAL 70 DAY), (55, 105400.00, CURDATE() - INTERVAL 63 DAY), (55, 107800.00, CURDATE() - INTERVAL 56 DAY), (55, 109600.00, CURDATE() - INTERVAL 49 DAY), (55, 105500.00, CURDATE() - INTERVAL 42 DAY), (55, 101700.00, CURDATE() - INTERVAL 35 DAY), (55, 100700.00, CURDATE() - INTERVAL 28 DAY), (55, 100500.00, CURDATE() - INTERVAL 21 DAY), (55, 96700.00, CURDATE() - INTERVAL 14 DAY), (55, 100000.00, CURDATE() - INTERVAL 7 DAY), (55, 98500.00, CURDATE() - INTERVAL 0 DAY),
(56, 116200.00, CURDATE() - INTERVAL 77 DAY), (56, 117400.00, CURDATE() - INTERVAL 70 DAY), (56, 122000.00, CURDATE() - INTERVAL 63 DAY), (56, 118500.00, CURDATE() - INTERVAL 56 DAY), (56, 122900.00, CURDATE() - INTERVAL 49 DAY), (56, 124900.00, CURDATE() - INTERVAL 42 DAY), (56, 127600.00, CURDATE() - INTERVAL 35 DAY), (56, 126900.00, CURDATE() - INTERVAL 28 DAY), (56, 131600.00, CURDATE() - INTERVAL 21 DAY), (56, 126600.00, CURDATE() - INTERVAL 14 DAY), (56, 128000.00, CURDATE() - INTERVAL 7 DAY), (56, 128000.00, CURDATE() - INTERVAL 0 DAY),
(57, 200200.00, CURDATE() - INTERVAL 77 DAY), (57, 202200.00, CURDATE() - INTERVAL 70 DAY), (57, 206400.00, CURDATE() - INTERVAL 63 DAY), (57, 205500.00, CURDATE() - INTERVAL 56 DAY), (57, 200200.00, CURDATE() - INTERVAL 49 DAY), (57, 198400.00, CURDATE() - INTERVAL 42 DAY), (57, 200100.00, CURDATE() - INTERVAL 35 DAY), (57, 205600.00, CURDATE() - INTERVAL 28 DAY), (57, 214100.00, CURDATE() - INTERVAL 21 DAY), (57, 216000.00, CURDATE() - INTERVAL 14 DAY), (57, 209000.00, CURDATE() - INTERVAL 7 DAY), (57, 205000.00, CURDATE() - INTERVAL 0 DAY),
(58, 100800.00, CURDATE() - INTERVAL 77 DAY), (58, 97100.00, CURDATE() - INTERVAL 70 DAY), (58, 93400.00, CURDATE() - INTERVAL 63 DAY), (58, 90000.00, CURDATE() - INTERVAL 56 DAY), (58, 87600.00, CURDATE() - INTERVAL 49 DAY), (58, 88000.00, CURDATE() - INTERVAL 42 DAY), (58, 84700.00, CURDATE() - INTERVAL 35 DAY), (58, 83000.00, CURDATE() - INTERVAL 28 DAY), (58, 81000.00, CURDATE() - INTERVAL 21 DAY), (58, 80000.00, CURDATE() - INTERVAL 14 DAY), (58, 82000.00, CURDATE() - INTERVAL 7 DAY), (58, 82000.00, CURDATE() - INTERVAL 0 DAY),
(59, 144600.00, CURDATE() - INTERVAL 77 DAY), (59, 147400.00, CURDATE() - INTERVAL 70 DAY), (59, 152300.00, CURDATE() - INTERVAL 63 DAY), (59, 158000.00, CURDATE() - INTERVAL 56 DAY), (59, 153600.00, CURDATE() - INTERVAL 49 DAY), (59, 156400.00, CURDATE() - INTERVAL 42 DAY), (59, 155800.00, CURDATE() - INTERVAL 35 DAY), (59, 155700.00, CURDATE() - INTERVAL 28 DAY), (59, 152400.00, CURDATE() - INTERVAL 21 DAY), (59, 152900.00, CURDATE() - INTERVAL 14 DAY), (59, 158500.00, CURDATE() - INTERVAL 7 DAY), (59, 162000.00, CURDATE() - INTERVAL 0 DAY),
(60, 16200.00, CURDATE() - INTERVAL 77 DAY), (60, 16800.00, CURDATE() - INTERVAL 70 DAY), (60, 16800.00, CURDATE() - INTERVAL 63 DAY), (60, 16200.00, CURDATE() - INTERVAL 56 DAY), (60, 16000.00, CURDATE() - INTERVAL 49 DAY), (60, 16500.00, CURDATE() - INTERVAL 42 DAY), (60, 16900.00, CURDATE() - INTERVAL 35 DAY), (60, 17200.00, CURDATE() - INTERVAL 28 DAY), (60, 16800.00, CURDATE() - INTERVAL 21 DAY), (60, 16900.00, CURDATE() - INTERVAL 14 DAY), (60, 17500.00, CURDATE() - INTERVAL 7 DAY), (60, 17500.00, CURDATE() - INTERVAL 0 DAY),
(61, 24400.00, CURDATE() - INTERVAL 77 DAY), (61, 24500.00, CURDATE() - INTERVAL 70 DAY), (61, 24100.00, CURDATE() - INTERVAL 63 DAY), (61, 24900.00, CURDATE() - INTERVAL 56 DAY), (61, 25700.00, CURDATE() - INTERVAL 49 DAY), (61, 25300.00, CURDATE() - INTERVAL 42 DAY), (61, 26200.00, CURDATE() - INTERVAL 35 DAY), (61, 25500.00, CURDATE() - INTERVAL 28 DAY), (61, 25300.00, CURDATE() - INTERVAL 21 DAY), (61, 24400.00, CURDATE() - INTERVAL 14 DAY), (61, 24500.00, CURDATE() - INTERVAL 7 DAY), (61, 24500.00, CURDATE() - INTERVAL 0 DAY),
(62, 11200.00, CURDATE() - INTERVAL 77 DAY), (62, 11300.00, CURDATE() - INTERVAL 70 DAY), (62, 11700.00, CURDATE() - INTERVAL 63 DAY), (62, 11300.00, CURDATE() - INTERVAL 56 DAY), (62, 11000.00, CURDATE() - INTERVAL 49 DAY), (62, 10600.00, CURDATE() - INTERVAL 42 DAY), (62, 10800.00, CURDATE() - INTERVAL 35 DAY), (62, 10800.00, CURDATE() - INTERVAL 28 DAY), (62, 11000.00, CURDATE() - INTERVAL 21 DAY), (62, 10600.00, CURDATE() - INTERVAL 14 DAY), (62, 10500.00, CURDATE() - INTERVAL 7 DAY), (62, 10500.00, CURDATE() - INTERVAL 0 DAY),
(63, 34600.00, CURDATE() - INTERVAL 77 DAY), (63, 33900.00, CURDATE() - INTERVAL 70 DAY), (63, 33300.00, CURDATE() - INTERVAL 63 DAY), (63, 34400.00, CURDATE() - INTERVAL 56 DAY), (63, 35700.00, CURDATE() - INTERVAL 49 DAY), (63, 34600.00, CURDATE() - INTERVAL 42 DAY), (63, 35300.00, CURDATE() - INTERVAL 35 DAY), (63, 36000.00, CURDATE() - INTERVAL 28 DAY), (63, 36200.00, CURDATE() - INTERVAL 21 DAY), (63, 36100.00, CURDATE() - INTERVAL 14 DAY), (63, 37200.00, CURDATE() - INTERVAL 7 DAY), (63, 36000.00, CURDATE() - INTERVAL 0 DAY),
(64, 26200.00, CURDATE() - INTERVAL 77 DAY), (64, 26800.00, CURDATE() - INTERVAL 70 DAY), (64, 27400.00, CURDATE() - INTERVAL 63 DAY), (64, 27500.00, CURDATE() - INTERVAL 56 DAY), (64, 27700.00, CURDATE() - INTERVAL 49 DAY), (64, 27700.00, CURDATE() - INTERVAL 42 DAY), (64, 27400.00, CURDATE() - INTERVAL 35 DAY), (64, 27300.00, CURDATE() - INTERVAL 28 DAY), (64, 28000.00, CURDATE() - INTERVAL 21 DAY), (64, 28500.00, CURDATE() - INTERVAL 14 DAY), (64, 29500.00, CURDATE() - INTERVAL 7 DAY), (64, 29500.00, CURDATE() - INTERVAL 0 DAY),
(65, 28900.00, CURDATE() - INTERVAL 77 DAY), (65, 29600.00, CURDATE() - INTERVAL 70 DAY), (65, 30300.00, CURDATE() - INTERVAL 63 DAY), (65, 31300.00, CURDATE() - INTERVAL 56 DAY), (65, 31800.00, CURDATE() - INTERVAL 49 DAY), (65, 32200.00, CURDATE() - INTERVAL 42 DAY), (65, 32900.00, CURDATE() - INTERVAL 35 DAY), (65, 32500.00, CURDATE() - INTERVAL 28 DAY), (65, 31900.00, CURDATE() - INTERVAL 21 DAY), (65, 32700.00, CURDATE() - INTERVAL 14 DAY), (65, 32000.00, CURDATE() - INTERVAL 7 DAY), (65, 32000.00, CURDATE() - INTERVAL 0 DAY),
(66, 12600.00, CURDATE() - INTERVAL 77 DAY), (66, 12400.00, CURDATE() - INTERVAL 70 DAY), (66, 12700.00, CURDATE() - INTERVAL 63 DAY), (66, 12700.00, CURDATE() - INTERVAL 56 DAY), (66, 13200.00, CURDATE() - INTERVAL 49 DAY), (66, 12800.00, CURDATE() - INTERVAL 42 DAY), (66, 12500.00, CURDATE() - INTERVAL 35 DAY), (66, 12700.00, CURDATE() - INTERVAL 28 DAY), (66, 13100.00, CURDATE() - INTERVAL 21 DAY), (66, 13300.00, CURDATE() - INTERVAL 14 DAY), (66, 13500.00, CURDATE() - INTERVAL 7 DAY), (66, 13500.00, CURDATE() - INTERVAL 0 DAY),
(67, 19500.00, CURDATE() - INTERVAL 77 DAY), (67, 19600.00, CURDATE() - INTERVAL 70 DAY), (67, 19100.00, CURDATE() - INTERVAL 63 DAY), (67, 19200.00, CURDATE() - INTERVAL 56 DAY), (67, 18800.00, CURDATE() - INTERVAL 49 DAY), (67, 18900.00, CURDATE() - INTERVAL 42 DAY), (67, 19300.00, CURDATE() - INTERVAL 35 DAY), (67, 18800.00, CURDATE() - INTERVAL 28 DAY), (67, 18500.00, CURDATE() - INTERVAL 21 DAY), (67, 18800.00, CURDATE() - INTERVAL 14 DAY), (67, 19500.00, CURDATE() - INTERVAL 7 DAY), (67, 19500.00, CURDATE() - INTERVAL 0 DAY),
(68, 9000.00, CURDATE() - INTERVAL 77 DAY), (68, 8800.00, CURDATE() - INTERVAL 70 DAY), (68, 8700.00, CURDATE() - INTERVAL 63 DAY), (68, 8900.00, CURDATE() - INTERVAL 56 DAY), (68, 8600.00, CURDATE() - INTERVAL 49 DAY), (68, 8800.00, CURDATE() - INTERVAL 42 DAY), (68, 8900.00, CURDATE() - INTERVAL 35 DAY), (68, 8800.00, CURDATE() - INTERVAL 28 DAY), (68, 8500.00, CURDATE() - INTERVAL 21 DAY), (68, 8600.00, CURDATE() - INTERVAL 14 DAY), (68, 8500.00, CURDATE() - INTERVAL 7 DAY), (68, 8500.00, CURDATE() - INTERVAL 0 DAY),
(69, 9800.00, CURDATE() - INTERVAL 77 DAY), (69, 9500.00, CURDATE() - INTERVAL 70 DAY), (69, 9800.00, CURDATE() - INTERVAL 63 DAY), (69, 10000.00, CURDATE() - INTERVAL 56 DAY), (69, 9800.00, CURDATE() - INTERVAL 49 DAY), (69, 9800.00, CURDATE() - INTERVAL 42 DAY), (69, 9700.00, CURDATE() - INTERVAL 35 DAY), (69, 9400.00, CURDATE() - INTERVAL 28 DAY), (69, 9200.00, CURDATE() - INTERVAL 21 DAY), (69, 9300.00, CURDATE() - INTERVAL 14 DAY), (69, 9500.00, CURDATE() - INTERVAL 7 DAY), (69, 9500.00, CURDATE() - INTERVAL 0 DAY),
(70, 11100.00, CURDATE() - INTERVAL 77 DAY), (70, 11500.00, CURDATE() - INTERVAL 70 DAY), (70, 11900.00, CURDATE() - INTERVAL 63 DAY), (70, 12000.00, CURDATE() - INTERVAL 56 DAY), (70, 11600.00, CURDATE() - INTERVAL 49 DAY), (70, 11500.00, CURDATE() - INTERVAL 42 DAY), (70, 11900.00, CURDATE() - INTERVAL 35 DAY), (70, 11800.00, CURDATE() - INTERVAL 28 DAY), (70, 11800.00, CURDATE() - INTERVAL 21 DAY), (70, 11600.00, CURDATE() - INTERVAL 14 DAY), (70, 11500.00, CURDATE() - INTERVAL 7 DAY), (70, 11500.00, CURDATE() - INTERVAL 0 DAY),
(71, 21600.00, CURDATE() - INTERVAL 77 DAY), (71, 21600.00, CURDATE() - INTERVAL 70 DAY), (71, 21900.00, CURDATE() - INTERVAL 63 DAY), (71, 21200.00, CURDATE() - INTERVAL 56 DAY), (71, 21900.00, CURDATE() - INTERVAL 49 DAY), (71, 22500.00, CURDATE() - INTERVAL 42 DAY), (71, 22800.00, CURDATE() - INTERVAL 35 DAY), (71, 22900.00, CURDATE() - INTERVAL 28 DAY), (71, 23000.00, CURDATE() - INTERVAL 21 DAY), (71, 23300.00, CURDATE() - INTERVAL 14 DAY), (71, 24000.00, CURDATE() - INTERVAL 7 DAY), (71, 24000.00, CURDATE() - INTERVAL 0 DAY),
(72, 20100.00, CURDATE() - INTERVAL 77 DAY), (72, 19700.00, CURDATE() - INTERVAL 70 DAY), (72, 19100.00, CURDATE() - INTERVAL 63 DAY), (72, 18500.00, CURDATE() - INTERVAL 56 DAY), (72, 17900.00, CURDATE() - INTERVAL 49 DAY), (72, 17600.00, CURDATE() - INTERVAL 42 DAY), (72, 17300.00, CURDATE() - INTERVAL 35 DAY), (72, 17200.00, CURDATE() - INTERVAL 28 DAY), (72, 17000.00, CURDATE() - INTERVAL 21 DAY), (72, 17600.00, CURDATE() - INTERVAL 14 DAY), (72, 16900.00, CURDATE() - INTERVAL 7 DAY), (72, 17500.00, CURDATE() - INTERVAL 0 DAY),
(73, 25800.00, CURDATE() - INTERVAL 77 DAY), (73, 25000.00, CURDATE() - INTERVAL 70 DAY), (73, 25000.00, CURDATE() - INTERVAL 63 DAY), (73, 24100.00, CURDATE() - INTERVAL 56 DAY), (73, 23600.00, CURDATE() - INTERVAL 49 DAY), (73, 22800.00, CURDATE() - INTERVAL 42 DAY), (73, 22800.00, CURDATE() - INTERVAL 35 DAY), (73, 23200.00, CURDATE() - INTERVAL 28 DAY), (73, 23100.00, CURDATE() - INTERVAL 21 DAY), (73, 23100.00, CURDATE() - INTERVAL 14 DAY), (73, 22500.00, CURDATE() - INTERVAL 7 DAY), (73, 22500.00, CURDATE() - INTERVAL 0 DAY),
(74, 30700.00, CURDATE() - INTERVAL 77 DAY), (74, 29800.00, CURDATE() - INTERVAL 70 DAY), (74, 30400.00, CURDATE() - INTERVAL 63 DAY), (74, 29500.00, CURDATE() - INTERVAL 56 DAY), (74, 30300.00, CURDATE() - INTERVAL 49 DAY), (74, 30600.00, CURDATE() - INTERVAL 42 DAY), (74, 31800.00, CURDATE() - INTERVAL 35 DAY), (74, 32500.00, CURDATE() - INTERVAL 28 DAY), (74, 33600.00, CURDATE() - INTERVAL 21 DAY), (74, 33000.00, CURDATE() - INTERVAL 14 DAY), (74, 32500.00, CURDATE() - INTERVAL 7 DAY), (74, 31000.00, CURDATE() - INTERVAL 0 DAY),
(75, 32100.00, CURDATE() - INTERVAL 77 DAY), (75, 31600.00, CURDATE() - INTERVAL 70 DAY), (75, 31900.00, CURDATE() - INTERVAL 63 DAY), (75, 32300.00, CURDATE() - INTERVAL 56 DAY), (75, 32600.00, CURDATE() - INTERVAL 49 DAY), (75, 33100.00, CURDATE() - INTERVAL 42 DAY), (75, 32600.00, CURDATE() - INTERVAL 35 DAY), (75, 33000.00, CURDATE() - INTERVAL 28 DAY), (75, 31900.00, CURDATE() - INTERVAL 21 DAY), (75, 31200.00, CURDATE() - INTERVAL 14 DAY), (75, 30500.00, CURDATE() - INTERVAL 7 DAY), (75, 30500.00, CURDATE() - INTERVAL 0 DAY);

-- Wishlists (signed-in customers) and back-in-stock alerts: the "most watched" signal on the rate board
INSERT INTO wishlists (customer_id, product_id, created_at) VALUES
(1, 55, NOW() - INTERVAL 9 DAY), (3, 55, NOW() - INTERVAL 6 DAY), (5, 55, NOW() - INTERVAL 4 DAY), (7, 55, NOW() - INTERVAL 3 DAY), (9, 55, NOW() - INTERVAL 2 DAY), (12, 55, NOW() - INTERVAL 1 DAY),
(1, 46, NOW() - INTERVAL 12 DAY), (4, 46, NOW() - INTERVAL 8 DAY), (6, 46, NOW() - INTERVAL 5 DAY), (10, 46, NOW() - INTERVAL 3 DAY), (11, 46, NOW() - INTERVAL 2 DAY),
(2, 43, NOW() - INTERVAL 10 DAY), (5, 43, NOW() - INTERVAL 7 DAY), (8, 43, NOW() - INTERVAL 6 DAY), (9, 43, NOW() - INTERVAL 4 DAY), (12, 43, NOW() - INTERVAL 2 DAY),
(3, 53, NOW() - INTERVAL 11 DAY), (6, 53, NOW() - INTERVAL 6 DAY), (7, 53, NOW() - INTERVAL 5 DAY), (11, 53, NOW() - INTERVAL 1 DAY),
(5, 57, NOW() - INTERVAL 9 DAY), (9, 57, NOW() - INTERVAL 5 DAY), (10, 57, NOW() - INTERVAL 3 DAY), (1, 57, NOW() - INTERVAL 1 DAY),
(2, 47, NOW() - INTERVAL 14 DAY), (8, 47, NOW() - INTERVAL 4 DAY), (11, 47, NOW() - INTERVAL 2 DAY),
(4, 15, NOW() - INTERVAL 13 DAY), (6, 15, NOW() - INTERVAL 8 DAY), (12, 15, NOW() - INTERVAL 3 DAY),
(3, 61, NOW() - INTERVAL 7 DAY), (7, 61, NOW() - INTERVAL 2 DAY),
(10, 58, NOW() - INTERVAL 6 DAY), (2, 58, NOW() - INTERVAL 1 DAY),
(1, 74, NOW() - INTERVAL 5 DAY), (8, 72, NOW() - INTERVAL 4 DAY), (5, 63, NOW() - INTERVAL 3 DAY), (6, 4, NOW() - INTERVAL 2 DAY);
INSERT INTO stock_alerts (product_id, email, created_at, notified_at) VALUES
(45, 'omar.farooq@example.pk', NOW() - INTERVAL 5 DAY, NULL),
(45, 'imran.butt@example.pk', NOW() - INTERVAL 2 DAY, NULL),
(71, 'hamza.s@example.pk', NOW() - INTERVAL 4 DAY, NULL),
(31, 'saad.malik@example.pk', NOW() - INTERVAL 20 DAY, NULL);

-- -----------------------------------------------------------------------------
-- Orders: promo codes, money columns, staff axes
-- total = subtotal + shipping_fee - discount_amount
-- -----------------------------------------------------------------------------
UPDATE orders SET promo_code = 'TECH10' WHERE id = 38;   -- inside the TECH10 window (AOC monitor line)
UPDATE orders SET promo_code = 'WELCOME1000' WHERE id = 29;

UPDATE orders o SET subtotal = (SELECT COALESCE(SUM(i.subtotal), 0) FROM order_items i WHERE i.order_id = o.id);
UPDATE orders o SET shipping_zone_id = COALESCE(
    (SELECT z.id FROM shipping_zones z WHERE JSON_CONTAINS(z.cities, JSON_QUOTE(o.city)) ORDER BY z.sort_order LIMIT 1),
    (SELECT z.id FROM shipping_zones z WHERE z.is_default = 1 LIMIT 1));
UPDATE orders o JOIN shipping_rates r ON r.zone_id = o.shipping_zone_id
SET o.shipping_fee = IF(r.free_over IS NOT NULL AND o.subtotal >= r.free_over, 0, r.amount);
-- TECH10: 10% of the Monitors lines; WELCOME1000: Rs 1,000 flat
UPDATE orders o SET discount_amount = ROUND(0.10 * (SELECT COALESCE(SUM(i.subtotal), 0) FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = o.id AND p.category_id = 4), 2) WHERE o.promo_code = 'TECH10';
UPDATE orders SET discount_amount = LEAST(1000, subtotal) WHERE promo_code = 'WELCOME1000';
UPDATE orders SET total = subtotal + shipping_fee - discount_amount;
UPDATE promotions p SET used_count = (SELECT COUNT(*) FROM orders o WHERE o.promo_code = p.promo_code);

UPDATE orders SET
    payment_status = IF(status = 'Delivered', 'paid', 'pending'),
    fulfillment_status = IF(status IN ('Shipped', 'Delivered'), 'fulfilled', 'unfulfilled'),
    cancelled_at = IF(status = 'Cancelled', order_date + INTERVAL (5 + MOD(id, 7)) HOUR, NULL),
    cancel_reason = IF(status = 'Cancelled', IF(payment_method = 'jazzcash', 'JazzCash payment was not received', 'Customer unreachable to confirm the order'), NULL);
UPDATE order_items i JOIN orders o ON o.id = i.order_id SET i.fulfilled_qty = IF(o.status IN ('Shipped', 'Delivered'), i.quantity, 0);

-- -----------------------------------------------------------------------------
-- Shipments (TCS / Leopards / M&P) for shipped + delivered orders
-- -----------------------------------------------------------------------------
INSERT INTO order_shipments (order_id, courier, tracking_number, shipped_at, delivered_at)
SELECT o.id,
    ELT(1 + MOD(o.id, 3), 'TCS', 'Leopards', 'M&P'),
    CASE MOD(o.id, 3)
        WHEN 0 THEN CONCAT('77', LPAD(MOD(o.id * 7919 + 4417, 9999999999), 10, '0'))
        WHEN 1 THEN CONCAT('LE', LPAD(MOD(o.id * 104729 + 271, 99999999), 8, '0'))
        ELSE CONCAT('MP', LPAD(MOD(o.id * 15485863 + 97, 999999999), 9, '0'))
    END,
    o.order_date + INTERVAL 1 DAY + INTERVAL (2 + MOD(o.id, 5)) HOUR,
    IF(o.status = 'Delivered',
        o.order_date + INTERVAL 1 DAY + INTERVAL (2 + MOD(o.id, 5)) HOUR
            + INTERVAL (CASE o.shipping_zone_id WHEN 1 THEN 1 WHEN 2 THEN 2 + MOD(o.id, 2) ELSE 3 + MOD(o.id, 3) END) DAY
            + INTERVAL (3 + MOD(o.id, 6)) HOUR,
        NULL)
FROM orders o WHERE o.status IN ('Shipped', 'Delivered') ORDER BY o.id;

-- -----------------------------------------------------------------------------
-- Timeline: placed -> confirmed -> (fulfilled -> shipped -> delivered) / cancelled, payments
-- staff actor rotates over the three accounts; cancels are owner/manager only
-- -----------------------------------------------------------------------------
INSERT INTO order_events (order_id, type, actor_type, actor_id, payload, created_at)
SELECT id, 'placed', 'customer', customer_id,
    JSON_OBJECT('total', total, 'items', (SELECT SUM(quantity) FROM order_items i WHERE i.order_id = o.id), 'payment_method', payment_method,
                'shipping_zone', (SELECT name FROM shipping_zones z WHERE z.id = o.shipping_zone_id), 'promo_code', promo_code),
    order_date
FROM orders o;

INSERT INTO order_events (order_id, type, actor_type, actor_id, payload, created_at)
SELECT id, 'confirmed', 'staff', 1 + MOD(id, 3), NULL,
    GREATEST(order_date + INTERVAL 1 MINUTE, LEAST(order_date + INTERVAL (2 + MOD(id, 4)) HOUR, NOW() - INTERVAL 20 MINUTE))
FROM orders WHERE status IN ('Processing', 'Shipped', 'Delivered');

INSERT INTO order_events (order_id, type, actor_type, actor_id, payload, created_at)
SELECT o.id, 'fulfilled', 'staff', IF(MOD(o.id, 2) = 0, 3, 2),
    -- GROUP_CONCAT instead of JSON_ARRAYAGG so the seed also runs on MariaDB 10.4 (XAMPP)
    CONCAT('{"partial":false,"items":[', (SELECT GROUP_CONCAT(JSON_OBJECT('item_id', i.id, 'name', i.product_name, 'quantity', i.quantity) SEPARATOR ',') FROM order_items i WHERE i.order_id = o.id), ']}'),
    s.shipped_at - INTERVAL 90 MINUTE
FROM orders o JOIN order_shipments s ON s.order_id = o.id;

INSERT INTO order_events (order_id, type, actor_type, actor_id, payload, created_at)
SELECT o.id, 'shipped', 'staff', IF(MOD(o.id, 2) = 0, 3, 2),
    JSON_OBJECT('shipment_id', s.id, 'courier', s.courier, 'tracking', s.tracking_number), s.shipped_at
FROM orders o JOIN order_shipments s ON s.order_id = o.id;

INSERT INTO order_events (order_id, type, actor_type, actor_id, payload, created_at)
SELECT o.id, 'delivered', 'staff', 1 + MOD(o.id + 1, 3), JSON_OBJECT('shipment_id', s.id), s.delivered_at
FROM orders o JOIN order_shipments s ON s.order_id = o.id WHERE s.delivered_at IS NOT NULL;

-- JazzCash is confirmed right after the order is confirmed; COD is collected by the courier on delivery
INSERT INTO order_events (order_id, type, actor_type, actor_id, payload, created_at)
SELECT o.id, 'payment_recorded', 'staff', 1 + MOD(o.id + 1, 3), JSON_OBJECT('amount', o.total, 'method', o.payment_method),
    IF(o.payment_method = 'jazzcash',
       (SELECT e.created_at + INTERVAL 12 MINUTE FROM order_events e WHERE e.order_id = o.id AND e.type = 'confirmed' LIMIT 1),
       s.delivered_at + INTERVAL 5 MINUTE)
FROM orders o JOIN order_shipments s ON s.order_id = o.id WHERE o.payment_status = 'paid';

INSERT INTO order_events (order_id, type, actor_type, actor_id, payload, created_at)
SELECT o.id, 'cancelled', 'staff', 1 + MOD(o.id, 2),
    JSON_OBJECT('reason', o.cancel_reason, 'restocked', (SELECT SUM(quantity) FROM order_items i WHERE i.order_id = o.id)), o.cancelled_at
FROM orders o WHERE o.status = 'Cancelled';

INSERT INTO order_notes (order_id, admin_id, body, created_at) VALUES
(37, 2, 'Customer wants all three boards together. Call before dispatch: 0300-1234567, after 6 pm.', NOW() - INTERVAL 4 DAY - INTERVAL 20 HOUR),
(39, 3, 'JazzCash transaction ID sent on WhatsApp. Verify in the merchant portal before confirming.', NOW() - INTERVAL 2 DAY + INTERVAL 1 HOUR),
(32, 3, 'TCS says the parcel is held at the Karachi hub (address incomplete). Asked customer for the flat number.', NOW() - INTERVAL 27 DAY),
(10, 1, 'Corporate buyer; sent the GST invoice by email.', NOW() - INTERVAL 200 DAY);

-- -----------------------------------------------------------------------------
-- Stock ledger: SUM(delta) per product == products.inventory (checked at the end)
-- -----------------------------------------------------------------------------
-- one 'sold' row per order line (option matched by size / colour)
INSERT INTO inventory_adjustments (product_id, variation_id, delta, reason, reference, admin_id, created_at)
SELECT i.product_id,
    (SELECT v.id FROM product_variations v WHERE v.product_id = i.product_id
        AND ((i.size IS NOT NULL AND v.variation_type = 'size' AND v.variation_value = i.size)
          OR (i.color IS NOT NULL AND v.variation_type = 'color' AND v.variation_value = i.color)) LIMIT 1),
    -i.quantity, 'sold', CONCAT('order #', o.id), NULL, o.order_date
FROM order_items i JOIN orders o ON o.id = i.order_id ORDER BY o.order_date, i.id;
-- cancelled orders put their stock back
INSERT INTO inventory_adjustments (product_id, variation_id, delta, reason, reference, admin_id, created_at)
SELECT i.product_id,
    (SELECT v.id FROM product_variations v WHERE v.product_id = i.product_id
        AND ((i.size IS NOT NULL AND v.variation_type = 'size' AND v.variation_value = i.size)
          OR (i.color IS NOT NULL AND v.variation_type = 'color' AND v.variation_value = i.color)) LIMIT 1),
    i.quantity, 'cancelled_order', CONCAT('order #', o.id), 1 + MOD(o.id, 2), o.cancelled_at
FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.status = 'Cancelled' ORDER BY o.cancelled_at, i.id;
-- recent staff adjustments
INSERT INTO inventory_adjustments (product_id, variation_id, delta, reason, reference, admin_id, created_at) VALUES
(25, NULL, 10, 'received', 'PO-2026-1031 · Galaxy Traders', 2, NOW() - INTERVAL 2 DAY - INTERVAL 3 HOUR),
(1, NULL, 6, 'received', 'PO-2026-1022 · Logitech distributor', 2, NOW() - INTERVAL 9 DAY),
(27, NULL, -1, 'damaged', 'Box crushed in storage', 3, NOW() - INTERVAL 12 DAY),
(21, NULL, -2, 'correction', 'Monthly stock count', 1, NOW() - INTERVAL 6 DAY);
-- opening stock: each option first, then whatever product-level stock remains
INSERT INTO inventory_adjustments (product_id, variation_id, delta, reason, reference, admin_id, created_at)
SELECT v.product_id, v.id, v.inventory - COALESCE((SELECT SUM(a.delta) FROM inventory_adjustments a WHERE a.variation_id = v.id), 0),
    'received', 'Opening stock', 1, p.created_at
FROM product_variations v JOIN products p ON p.id = v.product_id
WHERE v.inventory - COALESCE((SELECT SUM(a.delta) FROM inventory_adjustments a WHERE a.variation_id = v.id), 0) <> 0;
INSERT INTO inventory_adjustments (product_id, variation_id, delta, reason, reference, admin_id, created_at)
SELECT p.id, NULL, p.inventory - COALESCE((SELECT SUM(a.delta) FROM inventory_adjustments a WHERE a.product_id = p.id), 0),
    'received', 'Opening stock', 1, p.created_at
FROM products p
WHERE p.inventory - COALESCE((SELECT SUM(a.delta) FROM inventory_adjustments a WHERE a.product_id = p.id), 0) <> 0;

-- -----------------------------------------------------------------------------
-- Customer tags and notes
-- -----------------------------------------------------------------------------
INSERT INTO customer_tags (id, name) VALUES (1, 'wholesale'), (2, 'gamer'), (3, 'corporate');
INSERT INTO customer_tag_links (customer_id, tag_id) VALUES
(7, 1), (10, 1), (1, 2), (5, 2), (9, 2), (11, 2), (3, 3), (12, 3), (10, 3);
INSERT INTO customer_notes (customer_id, admin_id, body, created_at) VALUES
(7, 2, 'Runs a cyber café in Faisalabad and buys routers in batches. Offer wholesale pricing on 10+ units.', NOW() - INTERVAL 95 DAY),
(1, 3, 'Prefers delivery after 6 pm. Call before dispatch.', NOW() - INTERVAL 60 DAY),
(12, 1, 'Orders for Javed & Co. Needs a GST invoice with every order.', NOW() - INTERVAL 120 DAY),
(10, 2, 'Buys for an IT lab in Islamabad. Asked to be told when the AX55 is back in stock.', NOW() - INTERVAL 18 DAY);

-- -----------------------------------------------------------------------------
-- Activity log (last 30 days): order actions from the timeline + catalogue, settings, discounts
-- -----------------------------------------------------------------------------
INSERT INTO audit_log (admin_id, action, entity_type, entity_id, summary, `before`, `after`, ip, created_at)
SELECT e.actor_id,
    CASE e.type WHEN 'confirmed' THEN 'order.confirm' WHEN 'fulfilled' THEN 'order.fulfil' WHEN 'shipped' THEN 'order.ship'
                WHEN 'delivered' THEN 'order.deliver' WHEN 'payment_recorded' THEN 'order.payment' ELSE 'order.cancel' END,
    'order', e.order_id,
    CASE e.type WHEN 'confirmed' THEN CONCAT('Confirmed order #', e.order_id)
                WHEN 'fulfilled' THEN CONCAT('Fulfilled order #', e.order_id)
                WHEN 'shipped' THEN CONCAT('Shipped order #', e.order_id, ' with ', JSON_UNQUOTE(JSON_EXTRACT(e.payload, '$.courier')))
                WHEN 'delivered' THEN CONCAT('Marked order #', e.order_id, ' delivered')
                WHEN 'payment_recorded' THEN CONCAT('Recorded payment of Rs ', FORMAT(JSON_EXTRACT(e.payload, '$.amount'), 0), ' on order #', e.order_id)
                ELSE CONCAT('Cancelled order #', e.order_id) END,
    NULL, e.payload, '39.40.112.18', e.created_at
FROM order_events e
WHERE e.actor_type = 'staff' AND e.created_at >= NOW() - INTERVAL 30 DAY
ORDER BY e.created_at;

INSERT INTO audit_log (admin_id, action, entity_type, entity_id, summary, `before`, `after`, ip, created_at) VALUES
(1, 'staff.create', 'admin', '3', 'Added Bilal Hussain as Staff', NULL, '{"role":"staff"}', '39.40.112.18', NOW() - INTERVAL 28 DAY),
(1, 'setting.update', 'setting', 'low_stock_threshold', 'Changed low stock threshold', '{"low_stock_threshold":3}', '{"low_stock_threshold":5}', '39.40.112.18', NOW() - INTERVAL 27 DAY - INTERVAL 4 HOUR),
(2, 'product.update', 'product', '15', 'Updated TZ-0015 Samsung Odyssey G5 27" (price)', '{"price":84999}', '{"price":79999}', '111.68.97.41', NOW() - INTERVAL 26 DAY),
(2, 'product.update', 'product', '15', 'Set compare-at price on TZ-0015', '{"compare_at_price":null}', '{"compare_at_price":89999}', '111.68.97.41', NOW() - INTERVAL 26 DAY + INTERVAL 2 MINUTE),
(1, 'shipping.update', 'shipping_zone', '2', 'Changed Punjab free-shipping threshold', '{"free_over":10000}', '{"free_over":8000}', '39.40.112.18', NOW() - INTERVAL 24 DAY),
(2, 'product.update', 'product', '4', 'Set compare-at price on TZ-0004 AOC 24G2', '{"compare_at_price":null}', '{"compare_at_price":47500}', '111.68.97.41', NOW() - INTERVAL 22 DAY),
(3, 'review.approve', 'review', '15', 'Approved a 5-star review of Logitech K380', '{"status":"Pending"}', '{"status":"Approved"}', '182.180.54.9', NOW() - INTERVAL 21 DAY),
(2, 'product.update', 'product', '19', 'Updated TZ-0019 TP-Link Archer AX55 (price)', '{"price":22500}', '{"price":21500}', '111.68.97.41', NOW() - INTERVAL 19 DAY),
(1, 'product.status', 'product', '20', 'Archived TZ-0020 TP-Link Archer C6', '{"status":"active"}', '{"status":"archived"}', '39.40.112.18', NOW() - INTERVAL 17 DAY),
(2, 'discount.create', 'promotion', '4', 'Created discount FREESHIP (free shipping over Rs 3,000)', NULL, '{"kind":"free_shipping","min_order":3000}', '111.68.97.41', NOW() - INTERVAL 14 DAY),
(3, 'message.update', 'contact_message', '4', 'Marked message from Sara Qureshi as replied', '{"status":"read"}', '{"status":"replied"}', '182.180.54.9', NOW() - INTERVAL 14 DAY + INTERVAL 3 HOUR),
(3, 'inventory.adjust', 'product', '27', 'Removed 1 × TZ-0027 Redragon H510 (damaged)', '{"inventory":5}', '{"inventory":4}', '182.180.54.9', NOW() - INTERVAL 12 DAY),
(1, 'setting.update', 'setting', 'store_phone', 'Changed store phone', '{"store_phone":"042-35761200"}', '{"store_phone":"042-35761234"}', '39.40.112.18', NOW() - INTERVAL 11 DAY),
(2, 'discount.update', 'promotion', '1', 'Limited TECH10 to the Monitors category', '{"applies_to":"all"}', '{"applies_to":"category","target":"Monitors"}', '111.68.97.41', NOW() - INTERVAL 10 DAY),
(2, 'inventory.adjust', 'product', '1', 'Received 6 × TZ-0001 Logitech M331', '{"inventory":36}', '{"inventory":42}', '111.68.97.41', NOW() - INTERVAL 9 DAY),
(2, 'customer.tag', 'customer', '10', 'Tagged Hira Shah as corporate', NULL, '{"tags":["wholesale","corporate"]}', '111.68.97.41', NOW() - INTERVAL 8 DAY),
(2, 'product.update', 'product', '26', 'Updated TZ-0026 HyperX Cloud II (description)', NULL, NULL, '111.68.97.41', NOW() - INTERVAL 7 DAY),
(1, 'inventory.adjust', 'product', '21', 'Corrected TZ-0021 Tenda AC10 by −2 (stock count)', '{"inventory":37}', '{"inventory":35}', '39.40.112.18', NOW() - INTERVAL 6 DAY),
(1, 'setting.update', 'setting', 'jazzcash_enabled', 'Turned JazzCash on', '{"jazzcash_enabled":false}', '{"jazzcash_enabled":true}', '39.40.112.18', NOW() - INTERVAL 5 DAY - INTERVAL 6 HOUR),
(3, 'review.reject', 'review', '13', 'Rejected a 2-star review of Samsung T7 (shipping complaint)', '{"status":"Pending"}', '{"status":"Rejected"}', '182.180.54.9', NOW() - INTERVAL 4 DAY),
(2, 'product.update', 'product', '29', 'Set compare-at price on TZ-0029 Logitech G502 HERO', '{"compare_at_price":null}', '{"compare_at_price":15499}', '111.68.97.41', NOW() - INTERVAL 3 DAY),
(2, 'inventory.adjust', 'product', '25', 'Received 10 × TZ-0025 SanDisk Extreme 500GB', '{"inventory":50}', '{"inventory":60}', '111.68.97.41', NOW() - INTERVAL 2 DAY - INTERVAL 3 HOUR),
(3, 'order.note', 'order', '39', 'Added a note to order #39', NULL, '{"note":"JazzCash transaction ID sent on WhatsApp."}', '182.180.54.9', NOW() - INTERVAL 2 DAY + INTERVAL 1 HOUR),
(2, 'product.update', 'product', '23', 'Updated TZ-0023 Samsung T7 1TB (price, compare-at)', '{"price":29500,"compare_at_price":null}', '{"price":28500,"compare_at_price":32999}', '111.68.97.41', NOW() - INTERVAL 1 DAY - INTERVAL 5 HOUR),
(1, 'product.update', 'product', '12', 'Updated TZ-0012 TechZone Ergo Office Chair (cost)', '{"cost_price":28500}', '{"cost_price":27720}', '39.40.112.18', NOW() - INTERVAL 1 DAY),
(3, 'message.update', 'contact_message', '3', 'Marked message from Ahmed Ali as read', '{"status":"new"}', '{"status":"read"}', '182.180.54.9', NOW() - INTERVAL 7 HOUR);

-- -----------------------------------------------------------------------------
-- Seller payouts (core/marketplace.php): every closed week before last week is paid; last week and
-- this week stay Upcoming. Gross = delivered lines of the seller's products in that Monday..Sunday week.
-- -----------------------------------------------------------------------------
INSERT INTO seller_payouts (seller_id, week_start, gross, commission_pct, commission, net, paid_at, reference, paid_by, created_at)
SELECT w.seller_id, w.week_start, w.gross, s.commission_pct,
    ROUND(w.gross * s.commission_pct / 100, 2), w.gross - ROUND(w.gross * s.commission_pct / 100, 2),
    w.week_start + INTERVAL 7 DAY + INTERVAL 11 HOUR,
    CONCAT('IBFT-', DATE_FORMAT(w.week_start + INTERVAL 7 DAY, '%y%m%d'), '-', LPAD(w.seller_id, 2, '0')),
    1, w.week_start + INTERVAL 7 DAY + INTERVAL 11 HOUR
FROM (
    SELECT p.seller_id, DATE(d.delivered_at) - INTERVAL WEEKDAY(d.delivered_at) DAY AS week_start, SUM(i.subtotal) AS gross
    FROM order_items i
    JOIN products p ON p.id = i.product_id
    JOIN orders o ON o.id = i.order_id
    JOIN (SELECT order_id, MAX(delivered_at) AS delivered_at FROM order_shipments WHERE delivered_at IS NOT NULL GROUP BY order_id) d ON d.order_id = o.id
    WHERE p.seller_id > 1 AND o.cancelled_at IS NULL AND o.payment_status <> 'refunded'
    GROUP BY p.seller_id, DATE(d.delivered_at) - INTERVAL WEEKDAY(d.delivered_at) DAY
) w
JOIN sellers s ON s.id = w.seller_id
WHERE w.week_start < CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY - INTERVAL 7 DAY
ORDER BY w.week_start, w.seller_id;
