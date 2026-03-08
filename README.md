
# WP Redirect Gateway & Ads Monetization

A powerful WordPress plugin for managing redirect links, ad gateway flows, countdown timers, anti-bot protection, and detailed click logging.

---

## 🚀 Overview

**WP Redirect Gateway & Ads Monetization** is designed for WordPress site owners who want full control over link redirection, traffic flow, and gateway monetization.

The plugin allows you to create smart redirect systems with:

- Custom slugs
- Ad gateway steps
- Countdown timers
- Password-protected links
- Anti‑bot logic
- Detailed visitor logging
- CSV / JSON import‑export tools
- Multilingual support
- Automatic backup scheduling

Perfect for download sites, affiliate landing pages, monetization gateways, resource hubs, or traffic routing systems.

---

## ✨ Features

### 🔗 Smart Redirect Links
Create redirect links with:

- Link name
- Original URL
- Custom slug
- Ad step count
- Wait time
- Password protection
- Gateway shortcode ID

### 💰 Gateway Ads Monetization
Add ad gateway steps before users can access the final destination link.

### ⏱ Countdown Timer
Force visitors to wait before continuing to the destination link.

### 📊 Detailed Click Logs
Track every redirect with:

- IP Address
- User Agent
- Referrer
- Sub ID
- URL Parameters
- Click status
- Timestamp

### 📁 CSV Export
Export:

- All redirect links
- All redirect logs

Great for analytics and reporting.

### 📦 JSON Settings Backup
Easily export and import plugin settings.

### 🌐 Multilingual Ready
Supports WordPress translations via:

Text Domain: `wp-redirect-gateway`  
Languages folder: `/languages`

### 🔐 Security First
Includes multiple security protections:

- Direct file access protection
- WordPress nonce verification
- Permission checks
- Safe redirects
- Data sanitization

---

## 📦 Plugin Information

| Field | Value |
|------|------|
| Plugin Name | WP Redirect Gateway & Ads Monetization |
| Version | 1.0.0 |
| Author | Anh Quan Bui |
| Text Domain | wp-redirect-gateway |

---

## ⚙️ Installation

### 1️⃣ Upload Plugin

Upload the plugin folder to:

/wp-content/plugins/wp-redirect-gateway/

or install the ZIP via WordPress admin.

### 2️⃣ Activate Plugin

Go to:

**WordPress Admin → Plugins → Activate**

### 3️⃣ Automatic Setup

On activation the plugin automatically creates database tables:

- `{prefix}rg_links`
- `{prefix}rg_logs`

It also initializes default options.

---

## 🗄 Database Structure

### Redirect Links Table

`{prefix}rg_links`

Fields:

- id
- name
- original_url
- slug
- ad_count
- wait_time
- password
- shortcode_id
- created_at

### Logs Table

`{prefix}rg_logs`

Fields:

- id
- link_id
- ip_address
- user_agent
- referrer
- sub_id
- url_params
- status
- clicked_at

---

## 📤 Import / Export

### Export Settings

Download all plugin settings as JSON.

### Import Settings

Restore settings from JSON backup.

### Export Links CSV

Export all redirect links.

### Export Logs CSV

Export click logs with optional monthly filtering.

---

## 🧩 Plugin Modules

The plugin loads these modules:

admin/class-admin-menu.php  
admin/backup-manager.php  
public/class-gateway-logic.php  
public/class-shortcode-gateway.php  
public/class-shortcode-inline.php  
public/class-frontend-ajax.php

---

## ⏰ Cron Job

The plugin schedules a daily event:

`wprg_daily_auto_backup_event`

This cron event is automatically removed when the plugin is deactivated.

---

## 🎯 Use Cases

Ideal for:

- Download gateway sites
- Affiliate redirects
- Traffic monetization pages
- Resource hubs
- Content lockers
- Link tracking systems

---

## 📋 Requirements

- WordPress 5.8+
- PHP 7.4+ recommended
- MySQL / MariaDB compatible

---

## 🛣 Roadmap

Possible future improvements:

- Advanced analytics dashboard
- Geo click tracking
- Bot detection improvements
- REST API support
- Link expiration rules
- Conversion tracking
- Enhanced shortcode options

---

## 📜 Changelog

### 1.0.0
Initial release

- Redirect link management
- Gateway monetization system
- Countdown timer
- Password protection
- Click logging
- CSV export tools
- JSON settings backup
- Daily cron backup
- Translation support

---

## 👨‍💻 Author

**Anh Quan Bui**

---

## ❤️ Support

If you find this plugin useful, consider supporting its development by contributing ideas, improvements, or reporting issues through the repository.
