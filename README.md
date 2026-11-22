SpazaSmart
SpazaSmart is a web-based inventory and sales management system designed for small retail stores and spaza shops. The system helps shop owners and staff manage products, categories, suppliers, stock levels, and reports through an easy-to-use interface.
Features
.User Login System
.Admin Dashboard
.Staff Dashboard
.Product Management
.Category Management
.Supplier Management
.Stock Management
.Stock Reports
.Responsive User Interface
.MySQL Database Integration
Project Structure
SpazaSmart/
│
├── index.php
├── login.php
├── dashboard.php
├── staff_dashboard.php
├── categories.php
├── suppliers.php
├── stock.php
├── stock_reports.php
├── connection.php
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
└── README.md
Technologies Used
.PHP
.MySQL
.HTML5
.CSS3
.JavaScript
.Bootstrap
.WAMP Server (Development)
.InfinityFree (Hosting)
Installation
Clone the Repository
git clone https://github.com/Nosi04/SpazaSmart.git
Move Project to WAMP
Copy the project folder into:
C:\wamp64\www\spazasmart
Create a Database
Start WAMP Server.
Open phpMyAdmin.
Create a database named:
spazasmart
Import your SQL database file.
Configure Database Connection
Update the database credentials in:

connection.php
Example:

<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "spazasmart";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
Running the Application
Start WAMP Server and visit:

http://localhost/SpazaSmart/
or

http://localhost/SpazaSmart/index.php
Online Deployment
The project is hosted using InfinityFree.

Live Website: https://spazasmart.infinityfree.io/SpazaSmart/ 
Future Improvements
Sales Tracking
Customer Management
Automated Reports
Barcode Scanning
Low Stock Notifications
User Role Management
Data Export to Excel/PDF
Author
Nosi

GitHub: https://github.com/Nosi04

License
This project is created for educational and learning purposes.
