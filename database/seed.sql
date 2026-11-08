-- =============================================================================
-- TechZone demo seed data (run AFTER database/schema.sql)
-- Admin login:     admin@techzone.pk / Admin@1234
-- Customer logins: any seeded customer email / Customer@123 (e.g. ali.raza@example.pk)
-- Dates are relative to NOW() so dashboard charts always cover the last 12 months.
-- Re-runnable: wipes the demo tables first.
-- =============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
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

-- Admin
INSERT INTO admins (id, name, username, email, phone, password, created_at) VALUES
(1, 'TechZone Admin', 'admin', 'admin@techzone.pk', '0300-0000000', '$2y$10$i6i0oi7IQni0dozr8Z6P7es5Js2bjbV3w9nOG.iqp6aXHbhHfpiu6', NOW() - INTERVAL 400 DAY);

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
(40, 10, 'Hira Shah', 'hira.shah@example.pk', '0346-4433221', 'Islamabad', 77000, 'Processing', 'cash_on_delivery', NULL, DATE_FORMAT(NOW() - INTERVAL 0 DAY, '%Y-%m-%d 11:46:00'));

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
(76, 40, 12, 'TechZone Ergo Office Chair', 38500, 2, 77000, NULL, NULL, NOW() - INTERVAL 0 DAY);

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

-- Promotions (one expired)
INSERT INTO promotions (title, description, image_url, discount_percentage, discount_amount, promo_code, start_date, end_date, is_active, display_order, link_url, created_at) VALUES
('Monitor Mega Sale', 'Save 10% on every monitor and accessory this month.', 'uploads/695e07fd24255_monitor.png', 10.00, 0.00, 'TECH10', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 30 DAY, 1, 1, '/MarketingSite/shop/products.php?category=4', NOW() - INTERVAL 10 DAY),
('Welcome Offer', 'Rs 1,000 off your first order over Rs 10,000.', 'uploads/695e07b640c78_headphones.png', 0.00, 1000.00, 'WELCOME1000', CURDATE() - INTERVAL 60 DAY, CURDATE() + INTERVAL 120 DAY, 1, 2, '/MarketingSite/shop/products.php', NOW() - INTERVAL 60 DAY),
('Summer Gaming Fest', '15% off gaming peripherals (ended).', 'uploads/695d7e54382e4_mouse.png', 15.00, 0.00, 'SUMMER15', CURDATE() - INTERVAL 150 DAY, CURDATE() - INTERVAL 90 DAY, 1, 3, '/MarketingSite/shop/products.php?category=8', NOW() - INTERVAL 150 DAY);

-- Contact messages
INSERT INTO contact_messages (name, email, subject, message, status, created_at) VALUES
('Kamran Akhtar', 'kamran.akhtar@example.pk', 'Bulk order for office', 'We need 15 ergonomic chairs and 15 standing desks for our new office in Lahore. Can you share a corporate quote?', 'new', NOW() - INTERVAL 1 DAY),
('Nadia Rehman', 'nadia.r@example.pk', 'Warranty claim', 'My HyperX headset left earcup stopped working after 5 months. How do I claim the warranty?', 'new', NOW() - INTERVAL 3 DAY),
('Ahmed Ali', 'ahmed.ali@example.pk', 'Delivery to Quetta?', 'Do you deliver to Quetta, and what are the shipping charges for a monitor?', 'read', NOW() - INTERVAL 9 DAY),
('Sara Qureshi', 'sara.q@example.pk', 'JazzCash payment failed', 'Amount was deducted from my JazzCash wallet but the order shows pending. Please check order #38.', 'replied', NOW() - INTERVAL 15 DAY),
('Imran Butt', 'imran.butt@example.pk', 'Feedback', 'Great service and quick delivery. Please add more AMD processors to the catalogue!', 'archived', NOW() - INTERVAL 40 DAY);

-- Keep order totals consistent with their line items.
UPDATE orders o SET total = (SELECT COALESCE(SUM(subtotal),0) FROM order_items i WHERE i.order_id = o.id);
