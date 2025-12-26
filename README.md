# 🛍️ Pikkit - E-Commerce Platform

A modern, user-friendly e-commerce platform where users can buy and sell products.

## ✨ Features

- 🔐 Secure user authentication (Manual & Google OAuth)
- 🛒 Browse products without login
- 💳 Buy products with one click
- 📦 Add items to pocket (shopping cart)
- 🏪 Sell your own products
- 🔍 Search functionality
- 📱 Responsive design
- 📧 Email OTP for password reset
- 👤 User roles (Buyers & Sellers)

## 🛠️ Technologies Used

- **Backend:** PHP 7.4+
- **Database:** MySQL
- **Frontend:** HTML5, CSS3, JavaScript
- **Authentication:** Google OAuth 2.0, PHPMailer
- **Libraries:** PHPMailer for email

## 📦 Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (optional)

### Setup Steps

1. **Clone the repository**
```bash
   git clone https://github.com/YOUR_USERNAME/pikkit.git
   cd pikkit
```

2. **Create database**
```sql
   CREATE DATABASE pikkit;
```

3. **Import database schema**
   - Open phpMyAdmin
   - Select `pikkit` database
   - Import the SQL file or run the schema from setup guide

4. **Configure database connection**
   - Copy `dbconnect.php.example` to `dbconnect.php`
   - Update with your database credentials

5. **Create uploads folder**
```bash
   mkdir -p uploads/products
   chmod 777 uploads/products
```

6. **Configure Google OAuth** (Optional)
   - Get credentials from Google Cloud Console
   - Update client ID in `login.php` and `registration.php`

7. **Configure Email** (Optional)
   - Update SMTP credentials in `login.php`

8. **Access the application**
