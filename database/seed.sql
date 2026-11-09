-- =============================================================================
-- TechZone demo seed data (run AFTER database/schema.sql)
-- Staff logins:    admin@techzone.pk / Admin@1234          (owner)
--                  sana.manager@techzone.pk / Manager@1234 (manager)
--                  bilal.staff@techzone.pk / Staff@1234    (staff)
-- Customer logins: any seeded customer email / Customer@123 (e.g. ali.raza@example.pk)
-- Dates are relative to NOW() so dashboard charts always cover the last 12 months.
-- Re-runnable: wipes the demo tables first.
-- =============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
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
('store_address', '"Shop 14, Hafeez Centre, Gulberg III, Lahore"', 1),
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
('store_hours', '"Mon–Sat 11:00–21:00 · Sunday closed"', 1),
('store_notice', '"Cash on delivery across Pakistan. Free delivery on orders over Rs 5,000."', 1),
('cod_limit', '0', 1),
('jazzcash_merchant_id', '""', 1);

-- Shipping zones (a city in no zone ships at the default zone's rate)
INSERT INTO shipping_zones (id, name, cities, is_default, sort_order) VALUES
(1, 'Lahore', '["Lahore"]', 0, 1),
(2, 'Punjab', '["Rawalpindi","Islamabad","Faisalabad","Multan","Gujranwala","Sialkot","Sargodha","Bahawalpur","Sheikhupura","Sahiwal","Gujrat","Jhelum","Kasur","Okara","Rahim Yar Khan"]', 0, 2),
(3, 'Nationwide', '["Karachi","Peshawar","Quetta","Hyderabad","Sukkur","Abbottabad","Mardan","Gilgit","Muzaffarabad"]', 1, 3);
INSERT INTO shipping_rates (zone_id, name, amount, free_over, eta) VALUES
(1, 'Standard', 250, 5000, '1–2 days'),
(2, 'Standard', 350, 8000, '2–4 days'),
(3, 'Standard', 500, 10000, '3–6 days');

-- Categories
INSERT INTO categories (id, name, description, image, created_at) VALUES
(1, 'Computer Accessories', 'Mice, keyboards, webcams and everyday desk essentials.', 'Computer Accessories.png', NOW() - INTERVAL 380 DAY),
(2, 'Computer Hardware', 'Motherboards, processors, graphics cards, RAM and PSUs.', 'Computer Hardware.png', NOW() - INTERVAL 380 DAY),
(3, 'Furniture', 'Gaming chairs and ergonomic desks built for long sessions.', 'Furntiure.png', NOW() - INTERVAL 380 DAY),
(4, 'Monitors', 'Office, gaming and creator displays from 24" to 34" ultrawide.', 'Monitors.png', NOW() - INTERVAL 380 DAY),
(5, 'Networking', 'Wi-Fi 6 routers, mesh systems, switches and adapters.', 'network.png', NOW() - INTERVAL 380 DAY),
(6, 'Storage Devices', 'Portable SSDs, NVMe drives, hard disks and flash storage.', 'Storage Devices.png', NOW() - INTERVAL 380 DAY),
(7, 'Audio & Headsets', 'Gaming headsets, studio headphones and speakers.', '695e07b640c78_headphones.png', NOW() - INTERVAL 380 DAY),
(8, 'Gaming Peripherals', 'High-DPI mice, mechanical keyboards and pro gaming gear.', '695d7e54382e4_mouse.png', NOW() - INTERVAL 380 DAY);

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
(1, 1, 'Logitech M331 Silent Plus Wireless Mouse', 'Silent clicks, 24-month battery life and a contoured right-hand shape.', 4499, 'uploads/695d7e54382e4_mouse.png', 42, 10, 1, NOW() - INTERVAL 370 DAY),
(2, 1, 'Logitech K380 Multi-Device Bluetooth Keyboard', 'Compact keyboard that pairs with three devices and switches instantly.', 9999, 'uploads/695e02d88682a_keyboard.png', 25, 8, 1, NOW() - INTERVAL 361 DAY),
(3, 5, 'Xiaomi Mi Router 4A Gigabit Edition', 'AC1200 dual-band with gigabit ports and Mi Wi-Fi app control.', 6999, 'uploads/69bf29110ebb4_695e08dbaed9c_Screenshot_2026-01-07_121813.png', 3, 21, 1, NOW() - INTERVAL 352 DAY),
(4, 4, 'AOC 24G2 24" 144Hz IPS Gaming Monitor', '1080p IPS panel, 144Hz, 1ms MPRT and FreeSync Premium on a height-adjustable stand.', 42500, 'uploads/695e07fd24255_monitor.png', 14, 22, 1, NOW() - INTERVAL 343 DAY),
(5, 2, 'MSI PRO B760M-E DDR4 Motherboard', 'Micro-ATX LGA1700 board for 12th/13th/14th gen Intel with M.2 and 2.5G LAN.', 32500, 'uploads/695e097ba5fe3_Screenshot_2026-01-07_122012.png', 9, 24, 1, NOW() - INTERVAL 334 DAY),
(6, 2, 'Gigabyte B760M DS3H DDR4 Motherboard', 'Micro-ATX LGA1700 board with four DIMM slots, PCIe 4.0 M.2 and 2.5GbE.', 36500, 'uploads/695e097ba5fe3_Screenshot_2026-01-07_122012.png', 11, 42, 1, NOW() - INTERVAL 325 DAY),
(7, 2, 'ASUS PRIME H610M-K D4 Motherboard', 'Entry-level LGA1700 micro-ATX with M.2, HDMI/VGA and ASUS 5X Protection.', 23500, 'uploads/695e097ba5fe3_Screenshot_2026-01-07_122012.png', 30, 25, 1, NOW() - INTERVAL 316 DAY),
(8, 2, 'ASRock B650M-HDV/M.2 Motherboard', 'AM5 micro-ATX for Ryzen 7000/9000, PCIe 4.0 x16, two M.2 slots, DDR5.', 44500, 'uploads/695e097ba5fe3_Screenshot_2026-01-07_122012.png', 4, 38, 1, NOW() - INTERVAL 307 DAY),
(9, 2, 'MSI MAG B650 Tomahawk WiFi Motherboard', 'ATX AM5 board with Wi-Fi 6E, 2.5GbE, three M.2 slots and 14+2+1 power stages.', 69500, 'uploads/695e097ba5fe3_Screenshot_2026-01-07_122012.png', 16, 20, 1, NOW() - INTERVAL 298 DAY),
(10, 3, 'Arozzi Verona Pro V2 Gaming Chair', 'Wide seat, adjustable lumbar and neck pillows, 4D armrests.', 68000, 'uploads/695e0869cb62e_Screenshot_2026-01-07_020804.png', 7, 12, 1, NOW() - INTERVAL 289 DAY),
(11, 3, 'FlexiSpot E7 Electric Standing Desk', 'Dual-motor height adjustable frame with memory presets.', 145000, 'uploads/695e082da3b60_desk.png', 2, 16, 1, NOW() - INTERVAL 280 DAY),
(12, 3, 'TechZone Ergo Office Chair', 'Breathable mesh back, synchro-tilt mechanism and adjustable headrest.', 38500, 'uploads/695e0869cb62e_Screenshot_2026-01-07_020804.png', 12, 31, 1, NOW() - INTERVAL 271 DAY),
(13, 3, 'Compact Writing Desk 120cm', 'Engineered wood top with steel frame and cable grommet.', 24999, 'uploads/695e082da3b60_desk.png', 8, 19, 0, NOW() - INTERVAL 262 DAY),
(14, 4, 'Dell S2421H 24" IPS Monitor', 'Full HD IPS, 75Hz, built-in speakers and AMD FreeSync.', 42000, 'uploads/695e07fd24255_monitor.png', 13, 14, 1, NOW() - INTERVAL 253 DAY),
(15, 4, 'Samsung Odyssey G5 27" 144Hz Curved', 'QHD 1000R curved VA panel with 1ms response time.', 79999, 'uploads/695e07fd24255_monitor.png', 6, 41, 1, NOW() - INTERVAL 244 DAY),
(16, 4, 'LG 27UP850 27" 4K USB-C Monitor', 'UHD IPS, DCI-P3 95%, HDR400 and 90W USB-C power delivery.', 132000, 'uploads/695e07fd24255_monitor.png', 5, 7, 1, NOW() - INTERVAL 235 DAY),
(17, 4, 'LG UltraWide 34WP65C 34" Curved', '21:9 QHD ultrawide, 160Hz, HDR10 for productivity and gaming.', 115000, 'uploads/695e07fd24255_monitor.png', 3, 21, 1, NOW() - INTERVAL 226 DAY),
(18, 5, 'ASUS RT-AC86U Dual-Band Gaming Router', 'AC2900 with adaptive QoS, AiProtection and three-level game acceleration.', 34999, 'uploads/695e08dbaed9c_Screenshot_2026-01-07_121813.png', 10, 32, 1, NOW() - INTERVAL 217 DAY),
(19, 5, 'TP-Link Archer AX55 Wi-Fi 6 Router', 'AX3000 dual-band Wi-Fi 6 with OneMesh support.', 21500, 'uploads/695e08dbaed9c_Screenshot_2026-01-07_121813.png', 18, 23, 1, NOW() - INTERVAL 208 DAY),
(20, 5, 'TP-Link Archer C6 AC1200 Router', 'Dual-band AC1200 with four gigabit ports, MU-MIMO and beamforming.', 9500, 'uploads/69bf29110ebb4_695e08dbaed9c_Screenshot_2026-01-07_121813.png', 5, 37, 1, NOW() - INTERVAL 199 DAY),
(21, 5, 'Tenda AC10 AC1200 Dual-Band Router', 'Gigabit WAN/LAN, four 6dBi antennas and app-based setup.', 7500, 'uploads/69bf29110ebb4_695e08dbaed9c_Screenshot_2026-01-07_121813.png', 35, 23, 1, NOW() - INTERVAL 190 DAY),
(22, 6, 'Transcend ESD380C 1TB Portable SSD', 'USB 3.2 Gen 2x2 up to 2000 MB/s with military-grade shock resistance.', 28500, 'uploads/695e09df646a2_Screenshot_2026-01-07_122229.png', 15, 34, 1, NOW() - INTERVAL 181 DAY),
(23, 6, 'Samsung T7 1TB Portable SSD', 'USB 3.2 Gen 2, up to 1,050 MB/s read, shock-resistant metal body.', 28500, 'uploads/695e09df646a2_Screenshot_2026-01-07_122229.png', 1, 30, 1, NOW() - INTERVAL 172 DAY),
(24, 6, 'WD My Passport 2TB Portable Hard Drive', 'Slim USB 3.0 drive with hardware encryption and WD Backup software.', 19500, 'uploads/695e09df646a2_Screenshot_2026-01-07_122229.png', 22, 37, 1, NOW() - INTERVAL 163 DAY),
(25, 6, 'SanDisk Extreme 500GB Portable SSD', 'IP65 water and dust resistance, up to 1,050 MB/s, carabiner loop.', 21500, 'uploads/695e09df646a2_Screenshot_2026-01-07_122229.png', 60, 14, 1, NOW() - INTERVAL 154 DAY),
(26, 7, 'HyperX Cloud II Gaming Headset', 'Virtual 7.1 surround, memory-foam ear cushions and a detachable noise-cancelling mic.', 22999, 'uploads/695e07b640c78_headphones.png', 19, 34, 1, NOW() - INTERVAL 145 DAY),
(27, 7, 'Redragon H510 Zeus Wired Gaming Headset', '53mm drivers, 7.1 surround over USB, detachable mic and braided cable.', 11500, 'uploads/695e07b640c78_headphones.png', 4, 39, 1, NOW() - INTERVAL 136 DAY),
(28, 7, 'Logitech G331 Stereo Gaming Headset', '50mm drivers, flip-to-mute mic and a lightweight frame for long sessions.', 12500, 'uploads/695e07b640c78_headphones.png', 9, 36, 1, NOW() - INTERVAL 127 DAY),
(29, 8, 'Logitech G502 HERO Gaming Mouse', '25K DPI HERO sensor, 11 programmable buttons and adjustable weights.', 12999, 'uploads/695d7e54382e4_mouse.png', 27, 14, 1, NOW() - INTERVAL 118 DAY),
(30, 8, 'Redragon K552 Kumara Mechanical Keyboard', 'TKL layout, Outemu Blue switches and red LED backlight.', 8999, 'uploads/695e02d88682a_keyboard.png', 33, 43, 1, NOW() - INTERVAL 109 DAY),
(31, 8, 'Razer BlackWidow V4 Pro Keyboard', 'Green mechanical switches, command dial and Chroma RGB underglow.', 68500, 'uploads/695e02d88682a_keyboard.png', 0, 22, 1, NOW() - INTERVAL 100 DAY),
(32, 2, 'ASUS TUF Gaming B760M-Plus WiFi D4', 'Military-grade components, Wi-Fi 6, PCIe 5.0 x16 and Aura Sync lighting.', 52500, 'uploads/695e097ba5fe3_Screenshot_2026-01-07_122012.png', 2, 13, 1, NOW() - INTERVAL 91 DAY);

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
(40, 10, 'Hira Shah', 'hira.shah@example.pk', '0346-4433221', 'Islamabad', 77000, 'Processing', 'cash_on_delivery', NULL, NOW() - INTERVAL 150 MINUTE);

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
(76, 40, 12, 'TechZone Ergo Office Chair', 38500, 2, 77000, NULL, NULL, NOW() - INTERVAL 150 MINUTE);

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
