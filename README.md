# La Maison — Fashion Store E-Commerce

Full-stack web application สำหรับร้านขายเสื้อผ้าออนไลน์ พัฒนาด้วย PHP + MySQL ดูแลครบวงจรตั้งแต่ Frontend, Backend, Database, จนถึง Admin Dashboard

**Live Demo:** https://lamaison.infinityfreeapp.com

> ระบบเปิดให้สมัครสมาชิกใหม่ได้ทันที — สั่งซื้อทดลองใช้ที่อยู่ปลอม ไม่มีการตัดเงินจริง

---

## Features

### ฝั่งลูกค้า (Customer)
- รายการสินค้า พร้อมระบบค้นหา + กรอง (หมวดหมู่, ช่วงราคา, sort)
- หน้ารายละเอียดสินค้าพร้อม variant (สี/ไซส์)
- ตะกร้าสินค้า + ระบบ Checkout
- สมัครสมาชิก / เข้าสู่ระบบ / ลืมรหัสผ่าน
- ที่อยู่จัดส่งแบบเลือก จังหวัด/อำเภอ/ตำบล (ฐานข้อมูลไทยครบ)
- อัพโหลดสลิปโอนเงิน + ติดตามสถานะคำสั่งซื้อ
- ประวัติการสั่งซื้อ + พิมพ์ใบกำกับ

### ฝั่งผู้ดูแล (Admin)
- Dashboard สรุปยอดขาย พร้อม chart รายวัน/เดือน
- จัดการสินค้า (CRUD + อัพโหลดรูป + variant)
- จัดการหมวดหมู่
- จัดการคำสั่งซื้อ (อัพเดตสถานะ, ยกเลิก, batch update)
- ดูสลิปลูกค้า + ยืนยันชำระเงิน
- API endpoint สำหรับ sales analytics (`sales_data_api.php`)

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Frontend** | HTML5, CSS3, JavaScript (Vanilla, no framework) |
| **Backend** | PHP 8.x (Procedural, Prepared Statements) |
| **Database** | MySQL 10.x (mysqli + utf8mb4) |
| **Server** | Apache (with `.htaccess`) |
| **Dev Environment** | XAMPP, VS Code, Git |
| **Production Host** | InfinityFree (free PHP/MySQL hosting) |

---

## Security Practices

- ใช้ **Prepared Statements (mysqli `bind_param`)** ในทุก query → กัน SQL injection
- ใช้ `password_hash()` + `password_verify()` สำหรับรหัสผ่าน
- ใช้ `htmlspecialchars()` ทุก output ป้องกัน XSS
- `.htaccess` block ไฟล์สำคัญ (`config.php`, `.env`, `.git/`, `*.sql`)
- Reset password token แบบสุ่ม 64-byte hex มีวันหมดอายุ 1 ชั่วโมง
- Security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
- Production hide error details จากผู้ใช้ → log แทน

---

## Database Schema

6 ตารางหลัก:

```
users          ─── customers + admin (role enum)
categories     ─── หมวดหมู่สินค้า
products       ─── สินค้า (name, price, image_url, status, stock_status)
product_variants ─── ตัวเลือก (size/color) + stock ราย variant
orders         ─── คำสั่งซื้อ (address, status, payment_slip)
order_items    ─── line items (foreign key → orders, products)
```

ใช้ **Foreign Key Constraints** ระหว่าง `order_items` ↔ `orders` และ `order_items` ↔ `products`

---

## Local Development

### Prerequisites
- XAMPP 8.x (PHP 8 + MariaDB)
- Git

### Setup
```bash
# 1. Clone
git clone https://github.com/YOUR_USERNAME/la_maison.git C:/xampp/htdocs/la_maison

# 2. สร้าง database
# เปิด http://localhost/phpmyadmin → Create database "la_maison_db" (utf8mb4_general_ci)
# Import ไฟล์ SQL ที่ส่งให้แยก (la_maison_db.sql)

# 3. กำหนด config (default ใช้ root + no password ของ XAMPP)
# ดูใน config.php → ปรับตามต้องการ

# 4. เปิดเว็บ
# http://localhost/la_maison/
```

### Default Admin Account
ระบบมี admin user แล้วใน database — login ด้วย username/password ที่ตั้งใน setup

---

## Project Structure

```
la_maison/
├── index.php              # หน้าแรก (รายการสินค้า + filter)
├── config.php             # DB connection
├── header.php / footer.php # Layout components
│
├── ── User Auth ──
│   ├── login.php / register.php / logout.php
│   ├── forgot_password.php / reset_password.php
│   └── profile.php
│
├── ── Shopping Flow ──
│   ├── product_detail.php
│   ├── cart.php / cart_handler.php
│   ├── checkout.php / checkout_process.php
│   └── payment_upload.php / payment_process.php
│
├── ── Order Management ──
│   ├── order_history.php / order_detail.php
│   ├── order_success.php / cancel_order.php
│   └── print_invoice.php
│
├── ── Admin ──
│   ├── dashboard.php (sales charts)
│   ├── manage_products.php (CRUD)
│   ├── add_product.php / edit_product.php / delete_product.php
│   ├── manage_categories.php
│   ├── all_orders.php / update_order_status.php
│   ├── update_all_orders.php / update_filtered_orders.php
│   ├── sales_summary.php / sales_data_api.php
│   └── product_status_handler.php
│
├── img/                   # Product images
├── uploads/slips/         # Payment slips (ลูกค้า upload)
├── data/                  # Province/district JSON
├── style.css
└── .htaccess
```

---

## Deployment

โปรเจกต์นี้ deploy บน **InfinityFree** (PHP/MySQL hosting ฟรี) — ดูคู่มือ deployment แบบละเอียดที่:
- `E:\DataAi\la_maison_deploy\DEPLOY_GUIDE.md` (ผู้พัฒนา)

ขั้นตอนย่อ:
1. สร้าง MySQL database บน InfinityFree
2. แก้ `config.php` ใส่ credentials production
3. Upload ผ่าน FTP (FileZilla) หรือ Zip + File Manager
4. Import SQL dump ผ่าน phpMyAdmin
5. เปิด HTTPS ผ่าน Free SSL Certificate

---

## Author

**ปิยะนนท์ แซ่ว่าง** (Piyanon Seawang)  
บัณฑิตใหม่ บริหารธุรกิจ — เทคโนโลยีสารสนเทศทางธุรกิจ  
มหาวิทยาลัยเทคโนโลยีราชมงคลรัตนโกสินทร์

โปรเจกต์นี้พัฒนาเป็นผลงานหลักสำหรับ Portfolio — ครอบคลุมทักษะ Full-Stack Web Development + ความเข้าใจ Business flow ของ E-Commerce
