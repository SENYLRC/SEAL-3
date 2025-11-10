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
