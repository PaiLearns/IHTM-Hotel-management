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

## Keeping your data

Application files and hotel data are stored separately. Rooms, guests, and reservations are stored in the MySQL `hotel_db` database, not in the downloaded project files. Replacing or downloading the project files again on the same computer will not remove that database. `schema.sql` creates any missing tables and adds the starter account and rooms only when they are absent; it does not drop tables or replace existing records.

Before reinstalling XAMPP, moving to another computer, or deleting/recreating `hotel_db`, make a database backup in phpMyAdmin:

1. Select `hotel_db`, choose **Export**, select the **SQL** format, and download the export. Keep this backup somewhere private and separate from the project files; it contains guest and reservation information.
2. On the destination MySQL server, create or select an empty `hotel_db` database, then use phpMyAdmin **Import** to load the backup. The export includes both the schema and data.
3. Do not import the backup over an already populated database unless you know how its SQL statements will interact with the existing records.

Use `schema.sql` for a fresh installation or to add missing schema objects. It is not a backup of your live data. For additional safety, export a backup before applying schema changes.

Room transfers are available for confirmed and checked-in reservations. Transfers are recorded with the staff member and reason, and the destination room's current rate applies to the full stay. A transferred-from room is marked dirty when the guest is checked in; checking out marks the room maintenance. Staff can mark it available from Room Management after servicing and inspection.

The application includes receptionist authentication, room and reservation management, check-in/check-out, and printable stay invoices. Room rates and stay dates determine the invoice estimate; payment collection and tax configuration are not included.

Change the seeded development account password and secure MySQL credentials before deploying this application beyond a local development environment.
