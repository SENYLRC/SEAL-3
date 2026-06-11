# SEAL 3.0 — Southeastern Access to Libraries

SEAL (Southeastern Access to Libraries) is an open-source web application developed and maintained by the **Southeastern New York Library Resources Council (SENYLRC)**.  
It enables participating libraries to search, request, and manage interlibrary loan (ILL) materials across multiple systems within the Southeastern New York region.

---

## 🚀 Features

- **WordPress-based platform** with custom PHP and MySQL components  
- **Interlibrary Loan (ILL) Request System** with automated email notifications  
- **Role-based permissions** (`administrator`, `libstaff`, `libsys`)  
- **Real-time reporting and CSV exports** for system statistics  
- **Integration with ILLiad and local catalog APIs**  
- **Custom plugins** for library metadata fields and user management  
- **Security controls** including authentication and WAF compatibility  

---

## 🛠️ Technology Stack

- **Frontend:** WordPress + PHP + HTML/CSS + JavaScript  
- **Backend:** MySQL / MariaDB  
- **Server:** Apache (Ubuntu)  
- **Integrations:** ILLiad API, SENYLRC custom scripts, Cron automation  

---

## 🧩 Directory Overview

| Directory | Purpose |
|------------|----------|
| `/seal_wp_script/` | Core SEAL PHP scripts and logic |
| `/wp-content/plugins/` | Custom SENYLRC plugins (e.g. `SENYLC Custom User Fields`) |
| `/assets/` | Shared CSS, JS, and icons |
| `/admin/` | Administrative views and reports |
| `/scripts/` | CLI and cron automation scripts |

---

## ⚙️ Configuration

Before using SEAL 3.0, create a **database configuration file** at:
/var/www/seal_wp_script/seal_db.inc


For security, this file **should never be committed to GitHub**.  
Instead, include a **template** named:  seal_db.sample.inc



### 🧩 Example: `seal_db.sample.inc`

```php
<?php
// SEAL 3.0 Database Configuration (Sample)
// Copy this file to seal_db.inc and fill in your credentials

$dbhost = "localhost";
$dbuser = "seal_user";
$dbpass = "your_password_here";
$dbname = "seal_database";

// Table references (used by scripts and reporting)
$sealSTAT="STATS";    //request history for stats and handling request
$sealLIB="Library-Data";    //Library profile data
$sealILLiadMapping="ILLIAD-ADD-MAPPING";   //map of LOC to ILLad ID
?>




## 🔒 Security Notice

This repository **should not include** production credentials, `.inc` files containing database logins, or server-specific configuration paths.  
Before sharing publicly:

---

## 🧑‍💻 Maintainer
**SENYLRC Systems Department**  
📍 Southeastern New York Library Resources Council  
🌐 [https://senylrc.org](https://senylrc.org)

---

## 📜 License
Copyright © Southeastern New York Library Resources Council  
All rights reserved. Redistribution or reuse requires permission from SENYLRC.
