# 🏨 Harar Ras Hotel — Hotel Management System

A full-featured hotel management web application built with **PHP**, **MySQL**, **Bootstrap 5**, and **Chapa Payment Gateway**. Designed for Harar Ras Hotel, Ethiopia.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [User Roles](#user-roles)
- [Installation](#installation)
- [Environment Configuration](#environment-configuration)
- [Payment Integration](#payment-integration)
- [Multi-Language Support](#multi-language-support)
- [Screenshots](#screenshots)
- [License](#license)

---

## 🌟 Overview

Harar Ras Hotel Management System is a complete web-based solution that handles:

- Room bookings with online payment via **Chapa**
- Food ordering, Spa & Wellness, and Laundry service bookings
- Receptionist check-in/check-out workflow
- Manager reports, refund management, and staff oversight
- Super Admin system control
- Multi-language support (English, Amharic, Afan Oromo)
- Real-time staff notifications for new paid bookings
- Email confirmations via PHPMailer (Gmail SMTP)

---

## ✨ Features

### Customer
- Register / Login (with Google OAuth on registration)
- Browse rooms, services, food menu
- Book rooms, order food, book spa & laundry services
- Pay online via **Chapa** (Telebirr, CBE, Awash, Amole)
- View booking history and confirmation receipts
- Print booking details
- Switch language (English / Amharic / Afan Oromo)
- Receive email confirmation after payment

### Receptionist
- Today's check-ins dashboard (Room, Food, Spa, Laundry — separated)
- Customer check-in / check-out processing
- Manage rooms and services
- Real-time notification bell for new paid bookings
- Generate bills
- Payment verification dashboard

### Manager
- Overview dashboard with statistics
- Manage bookings (view, cancel, delete)
- Approve bills
- Customer feedback management
- Refund management with printable receipt
- Room and staff management
- Reports

### Admin
- Full user management (create, edit, delete)
- Manage rooms, services, bookings
- View all data
- Payment verification
- Settings

### Super Admin
- All admin capabilities
- System-level settings (separate from admin settings)
- User role management across the system

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x |
| Database | MySQL (via MySQLi) |
| Frontend | HTML5, CSS3, Bootstrap 5.3, JavaScript |
| Payment | Chapa API (Sandbox & Production) |
| Email | PHPMailer (Gmail SMTP) |
| Icons | Font Awesome 6.5 |
| Auth | Session-based + Google OAuth (registration) |
| Server | Apache (XAMPP) |

---

## 📁 Project Structure

```
harar-ras-hotel/
├── api/                        # AJAX API endpoints
│   ├── chapa/                  # Chapa payment (initiate, callback, verify)
│   ├── cancel_booking.php
│   ├── check_room_availability.php
│   ├── notifications.php
│   ├── staff_notifications.php
│   ├── switch_language.php
│   └── verify_payment.php
│
├── assets/
│   ├── css/                    # style.css, print.css
│   ├── images/                 # Hotel, room, food images
│   └── js/                    # main.js
│
├── config/
│   └── database.php            # DB connection
│
├── dashboard/                  # Staff dashboards
│   ├── admin.php
│   ├── manager.php
│   ├── receptionist.php
│   ├── super-admin.php
│   ├── customer-checkin.php
│   ├── receptionist-checkin.php
│   ├── receptionist-checkout.php
│   ├── manager-refund.php
│   ├── payment-verification.php
│   └── ...
│
├── database/
│   └── setup.sql               # Full database schema
│
├── includes/
│   ├── auth.php                # Authentication & role guards
│   ├── config.php              # App config, auto DB setup
│   ├── functions.php           # Helper functions
│   ├── language.php            # Multi-language system
│   ├── Mailer.php              # PHPMailer wrapper
│   ├── navbar.php              # Global navigation
│   ├── footer.php
│   ├── RoomLockManager.php
│   └── services/               # EmailService, NotificationService
│
├── languages/
│   ├── en.php                  # English translations
│   ├── am.php                  # Amharic translations
│   └── om.php                  # Afan Oromo translations
│
├── uploads/
│   └── payment_screenshots/    # Customer payment screenshots
│
├── booking.php                 # Room booking form
├── booking-confirmation.php    # Post-booking confirmation
├── chapa-return.php            # Chapa payment return handler
├── food-booking.php
├── spa-booking.php
├── laundry-booking.php
├── payment-upload.php          # Payment page (Chapa + screenshot)
├── my-bookings.php
├── index.php
├── login.php
├── register.php
├── profile.php
├── notifications.php
├── .env                        # Environment variables (not committed)
└── .env.example                # Environment template
```

---

## 👥 User Roles

| Role | Access Level |
|---|---|
| `customer` | Book services, view bookings, pay online |
| `receptionist` | Check-in/out, manage rooms & services |
| `manager` | Reports, refunds, staff, bookings overview |
| `admin` | Full hotel management |
| `super_admin` | System-level control, all admin features |

---

## ⚙️ Installation

### Prerequisites
- XAMPP (PHP 8.x + MySQL + Apache)
- Composer (optional — PHPMailer included locally)
- Git

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/AbdulmalikNure/ras-hotel-project.git
cd ras-hotel-project

# 2. Copy environment file
cp .env.example .env

# 3. Edit .env with your settings (DB, email, Chapa keys)

# 4. Import the database
# Open phpMyAdmin → Create database: harar_ras_hotel
# Import: database/setup.sql

# 5. Start Apache & MySQL in XAMPP

# 6. Open in browser
http://localhost/ras-hotel-project/
```

> **Note:** The system auto-creates required tables on first load via `includes/config.php`.

---

## 🔧 Environment Configuration

Copy `.env.example` to `.env` and fill in:

```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=harar_ras_hotel

# Site
SITE_URL=http://localhost/ras-hotel-project

# Chapa Payment (Sandbox)
CHAPA_PUBLIC_KEY=CHAPUBK_TEST-...
CHAPA_SECRET_KEY=CHASECK_TEST-...
CHAPA_BASE_URL=https://api.chapa.co/v1
CHAPA_CALLBACK_URL=http://localhost/ras-hotel-project/api/chapa/callback.php
CHAPA_RETURN_URL=http://localhost/ras-hotel-project/chapa-return.php

# Email (Gmail SMTP)
EMAIL_ENABLED=true
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USERNAME=your-email@gmail.com
EMAIL_PASSWORD=your-app-password
EMAIL_FROM_ADDRESS=your-email@gmail.com
EMAIL_FROM_NAME="Harar Ras Hotel"

# Google OAuth (for registration)
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=http://localhost/ras-hotel-project/oauth-callback.php
```

---

## 💳 Payment Integration

### Chapa (Primary)
- Supports: Telebirr, CBE, Awash, Amole and more
- Flow: Book → Pay with Chapa → Chapa redirects to `chapa-return.php` → Verify → Confirm booking → Send email
- Sandbox mode enabled by default

### Manual Screenshot Upload
- Customers can also upload a bank transfer screenshot
- Staff verify via Payment Verification dashboard

---

## 🌍 Multi-Language Support

The system supports 3 languages switchable from the user profile menu:

| Code | Language |
|---|---|
| `en` | English (default) |
| `am` | አማርኛ — Amharic |
| `om` | Afaan Oromoo — Afan Oromo |

- Language preference is saved per user in the database
- Switching language reloads the page with full translation applied
- Translation files: `languages/en.php`, `languages/am.php`, `languages/om.php`

---

## 🔐 Security Features

- Session-based authentication with role guards
- `require_auth_role()` / `require_auth_roles()` on all protected pages
- Prepared statements (MySQLi) throughout — SQL injection prevention
- XSS prevention via `htmlspecialchars()`
- Cache-control headers to prevent back-button access after logout
- Super Admin settings isolated from Admin settings

---

## 📧 Email Notifications

Sent automatically after successful Chapa payment:

- **Room Booking** — booking reference, room, check-in/out dates
- **Food Order** — items ordered, reservation date/time, guests
- **Spa & Wellness** — service name, date, time
- **Laundry Service** — service name, collection date/time

Powered by **PHPMailer** with Gmail SMTP (App Password required).

---

## 🔔 Staff Notifications

Receptionists receive real-time bell notifications when a customer completes a Chapa payment. Each notification shows:
- Booking type (Room / Food / Spa / Laundry)
- Customer name and email
- Service details and amount
- Time ago

Notifications disappear after the receptionist reads them.

---

## 📄 License

This project is developed for **Harar Ras Hotel**, Harar, Ethiopia.

© 2026 Harar Ras Hotel. All rights reserved.

---

## 👨‍💻 Developer

**Abdulmalik Nure**
- GitHub: [@AbdulmalikNure](https://github.com/AbdulmalikNure)
- Email: abdulmaliknure9026@gmail.com
