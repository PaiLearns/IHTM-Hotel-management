# IHTM Hotel Management System

Receptionist-focused hotel management system built with PHP, PDO, MySQL, and Tailwind CSS.

## Requirements

- XAMPP with Apache, MySQL, and PHP
- A browser with internet access for Tailwind CSS via CDN

## Local setup

1. Place this project in `C:\xampp\htdocs\IHTM`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Import `schema.sql` into MySQL using phpMyAdmin.
4. Confirm the local MySQL settings in `includes/db.php`.
5. Open `http://localhost/IHTM/` in your browser.

The application includes receptionist authentication, room and reservation management, check-in/check-out, and printable stay invoices. Room rates and stay dates determine the invoice estimate; payment collection and tax configuration are not included.

Change the seeded development account password and secure MySQL credentials before deploying this application beyond a local development environment.
