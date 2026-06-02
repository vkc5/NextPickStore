# NextPickStore 🛒

A full-featured PHP-based E-Commerce Platform developed as part of the Database Programming 2 course.

## 📌 Project Overview

NextPickStore is a web-based e-commerce platform that enables buyers to browse and purchase products, sellers to manage products and orders, and administrators to manage the entire system through dashboards, reports, and management tools.

## 👥 Team Members

* Mohammed Alhalal – 202200670
* Noora Thabet – 202202341
* Maryam Sarhan – 202200553
* Zahraa Humaidan – 202303553
* Noof Abdulla – 202204310

## 🛠 Technologies Used

* PHP
* MySQL
* NetBeans IDE
* XAMPP
* AJAX
* PHPMailer
* HTML5
* CSS3
* JavaScript

## ✨ Main Features

### Authentication & Security

* User Registration
* Email Verification
* User Login
* Forgot Password
* Password Reset via Email
* Session Management
* Role-Based Authorization

### Admin Features

* Dashboard Analytics
* User Management
* Create / Edit / Activate / Deactivate Users
* Product Management
* Product Visibility Control
* Comment Moderation
* Profile Management
* System Reports
* Excel Report Export

### Seller Features

* Seller Dashboard
* Product Management
* Inventory Management
* Product Search & Filtering
* Order Management
* Customer Management
* Reports & Analytics
* Profile Management

### Buyer Features

* Browse Products
* Product Search
* Category Filtering
* Shopping Cart
* Checkout System
* Order History
* Product Reviews & Ratings
* Profile Management

### Advanced Features

* AJAX Live Search
* PHPMailer Email System
* MySQL Stored Procedures
* Trigger-Based Admin Logging
* Dynamic Reporting System

## 📂 Project Structure

```text
Source Files/
│
├── ajax/
├── auth/
├── includes/
├── public/
├── roles/
│   ├── admin/
│   ├── seller/
│   └── buyer/
├── uploads/
├── vendor/
└── All_in_one.sql
```

## 🔑 User Roles

### Admin

* Manage Users
* Manage Products
* Manage Comments
* Generate Reports
* Export Reports

### Seller

* Manage Products
* Manage Orders
* View Customer Data
* View Analytics

### Buyer

* Browse Products
* Add Products to Cart
* Place Orders
* Submit Reviews and Ratings

## 🗄 Database

The system uses MySQL and includes the following major entities:

* Users
* Roles
* Products
* Categories
* Product Images
* Orders
* Order Items
* Comments
* Ratings
* Reports
* Admin Logs

## 📊 Reporting System

The platform provides:

* Orders Report
* Products by Seller Report
* Popular Products Report
* Report History
* Report Details
* Excel Export

Reports are generated using MySQL Stored Procedures and stored in the database for future access.

## 📧 Email System

PHPMailer is integrated to support:

* Email Verification
* Password Reset Verification Codes
* Secure Email Notifications

## 🚀 Installation

1. Clone the repository

```bash
git clone <repository-url>
```

2. Import the database

```sql
All_in_one.sql
```

3. Start XAMPP

* Apache
* MySQL

4. Configure database credentials inside:

```php
includes/config.php
```

5. Install dependencies

```bash
composer install
```

6. Open the project in your browser

```text
http://localhost/NextPickStore
```

## 🧪 Testing Accounts

| Role   | Email                                                 | Password  |
| ------ | ----------------------------------------------------- | --------- |
| Admin  | [admin@nextpick.com](mailto:admin@nextpick.com)       | admin123  |
| Seller | [ahmed@nextpick.com](mailto:ahmed@nextpick.com)       | seller123 |
| Buyer  | [mohammed@nextpick.com](mailto:mohammed@nextpick.com) | buyer123  |

## 🔒 Security Features

* Password Hashing
* Session Protection
* Role-Based Access Control
* Input Validation
* Email Verification
* Secure Authentication

## 📈 Future Improvements

* Online Payment Gateway
* Wishlist System
* Product Recommendations
* Mobile Responsive Enhancements
* Real-Time Notifications
* Multi-Vendor Analytics

## 📄 License

This project was developed for educational purposes as part of the Database Programming 2 course at Bahrain Polytechnic.
