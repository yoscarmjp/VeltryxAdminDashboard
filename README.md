# Veltryx Admin Dashboard

A private, role-based administration panel built for the **Veltryx Network** — a Minecraft server community. It centralizes staff application management, news publishing, player report handling, and user administration behind a secure, permission-gated interface.

---

## ✨ Features

### 🏠 Dashboard Overview
- Summary statistics: total, pending, and approved applications.
- Quick view of the latest 6 applications with inline approve/reject actions.

### 📰 News Management
- Create, edit, and delete server news articles.
- Restricted to `owner` and `admin` roles.

### 👥 Staff & Media Applications
- View and manage incoming Staff and Media team applications.
- Approve or reject candidates directly from the panel.
- Form fields configurable through the **Form Settings Editor**.
- Restricted to `owner` and `manager` roles.

### 🚩 Reports System
- Receive and process player-submitted reports.
- Assign actions: `ban`, `kick`, `mute`, `blacklist`, or `none`.
- Attach evidence URLs and mark reports as attended or rejected.
- Accessible by `owner`, `admin`, and `moderator` roles.

### 🔧 User Management
- Create, edit, and delete admin panel users.
- Assign roles: `owner`, `admin`, `manager`, `moderator`.
- Exclusive to the `owner` role.

### 📋 Activity Logs
- Full audit trail of all admin actions (logins, approvals, user creation, etc.).
- Exclusive to the `owner` role.

### 🔐 Authentication & Security
- Secure session-based login with `password_verify`.
- **CSRF protection** on all POST forms.
- **Login rate limiting**: locks the account after 5 failed attempts.
- Role-based access control enforced on every page.
- `noindex, nofollow` meta tag to prevent search engine indexing.

---

## 🛠️ Tech Stack

| Layer       | Technology                                      |
|-------------|-------------------------------------------------|
| Backend     | PHP 8+ (PDO, sessions, password hashing)        |
| Database    | MySQL / MariaDB                                 |
| Frontend    | Vanilla HTML, CSS, JavaScript                   |
| Fonts       | Google Fonts — Outfit                           |
| Icons       | Font Awesome 6                                  |
| Web Server  | Apache (.htaccess URL rewriting enabled)        |

---

## 🚀 Installation

### Requirements
- PHP 
- MySQL / MariaDB
- Apache with **mod_rewrite** enabled

### Steps

1. **Clone or upload** the project to your web server's document root (or a subdirectory).

2. **Create a MySQL database** and import your schema (tables: `users`, `applications`, `reports`, `news`, `activity_logs`, etc.).

3. **Configure the database connection** in `src/assets/config.php`:
   ```php
   $host = 'localhost';
   $db   = 'your_database_name';
   $user = 'your_db_user';
   $pass = 'your_db_password';
   ```

4. **Enable Apache `mod_rewrite`** and ensure `AllowOverride All` is set for the directory so `.htaccess` rules apply (clean URLs without `.php` extensions).

5. **Create the first owner account** by inserting a user directly into the `users` table:
   ```sql
   INSERT INTO users (username, email, password, role)
   VALUES ('AdminName', 'admin@example.com', '<bcrypt_hash>', 'owner');
   ```
   > Generate the bcrypt hash with PHP: `echo password_hash('yourpassword', PASSWORD_DEFAULT);`

6. **Navigate to** `https://your-domain.com/login` and sign in.

---

## 🔑 Role Permissions

| Feature                    | Owner | Admin | Manager | Moderator |
|----------------------------|:-----:|:-----:|:-------:|:---------:|
| Dashboard overview         | ✅    | ✅    | ✅      | ✅        |
| News management            | ✅    | ✅    | ❌      | ❌        |
| Staff & Media applications | ✅    | ❌    | ✅      | ❌        |
| Form settings editor       | ✅    | ❌    | ✅      | ❌        |
| Reports system             | ✅    | ✅    | ❌      | ✅        |
| User management            | ✅    | ❌    | ❌      | ❌        |
| Activity logs              | ✅    | ❌    | ❌      | ❌        |

---

## 🔒 Security Notes

- All user-facing inputs are sanitized with `htmlspecialchars()` and `strip_tags()`.
- Database queries use **prepared statements** (PDO) to prevent SQL injection.
- CSRF tokens are verified on every state-changing POST request.
- The login system enforces a **5-attempt lockout** with a configurable cooldown.
- The panel is marked `noindex, nofollow` — it should **not** be publicly accessible via search engines.