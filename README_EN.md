# LINUX DO CREDIT Points Transfer Helper

[中文说明](README.md)

A lightweight points-transfer helper for the [Linux.do](https://linux.do) forum community. It wraps the EasyPay-compatible API provided by LINUX DO CREDIT in a simple web interface, making it easier for forum users to choose a points amount, add a note, and complete a points transfer.

## Project Scope and Non-Commercial Notice

This project is intended solely for points-related use within the Linux.do forum community. It is an open-source client wrapper for the LINUX DO CREDIT API, not a commercial payment platform offered to the public.

- It only handles forum points within the LINUX DO CREDIT ecosystem; it does not process RMB or any other fiat currency.
- It does not provide deposits, withdrawals, custody, settlement, exchange, or other financial services.
- It does not issue points or change their rules. Accounts, authentication, and the actual transfer of points are handled by LINUX DO CREDIT.
- The maintainer charges no platform fee, service fee, or transaction commission and does not operate this project as a paid business.
- It is provided as a non-commercial, open-source utility whose purpose is to make forum points transfers easier to use.

Terms such as “payment” and “order” may still appear in the interface or source code because they are field names inherited from the upstream EasyPay-compatible API. Within this project, they refer only to forum points transfers and do not represent fiat payments or commercial payment collection.

## ✨ Features

- 🔢 Custom points amounts and preset amount buttons
- 💬 Optional transfer notes
- 🎨 A dark theme designed for the Linux.do Credit use case
- 📱 Fully responsive on mobile devices
- 🔒 LINUX DO CREDIT signature verification and asynchronous notifications

---

## 🚀 Choose a Deployment Method

| Method | Difficulty | Best for | Server required |
|-----|------|---------|-----------|
| **[Docker deployment](#-docker-deployment)** | ⭐⭐ Easy | Self-hosting with full functionality | ✅ Yes |
| **[PHP deployment](#-manual-php-deployment)** | ⭐⭐⭐ Moderate | Traditional web servers | ✅ Yes |

---

## 🐳 Docker Deployment

Recommended for users with a server. This option supports the full feature set, including persistent order storage.

### Step 1: Get API credentials

Create an application at [credit.linux.do](https://credit.linux.do) and record its credentials.

### Step 2: Configure the project

```bash
# Clone the project
git clone https://github.com/Razewang/LINUX_EASY_CREDIT.git
cd LINUX_EASY_CREDIT

# Create the configuration file
cp config/config.example.php config/config.php
nano config/config.php
```

Fill in the configuration:

```php
'epay' => [
    'pid' => 'your Client ID',
    'key' => 'your Client Secret',
    'notify_url' => 'https://your-domain/api/notify.php',
    'return_url' => 'https://your-domain/success.html',
],
```

### Step 3: Start the container

```bash
docker compose up -d
```

For more detail, see [DOCKER.md](DOCKER.md).

---

## 🔧 Manual PHP Deployment

Suitable for a traditional PHP environment such as Apache/Nginx with PHP.

### Quick start for testing

```bash
# Clone and configure the project
git clone https://github.com/Razewang/LINUX_EASY_CREDIT.git
cd LINUX_EASY_CREDIT
cp config/config.example.php config/config.php
nano config/config.php  # Fill in the configuration

# Start the development server
php -S 0.0.0.0:8000
```

Open: `http://your-ip:8000`

For production, Nginx with PHP-FPM is recommended. See [DEPLOYMENT.md](DEPLOYMENT.md) for details.

---

## ✅ Test the Points Transfer Flow

1. Open your website.
2. Select or enter a points amount. Start with **0.01** for testing.
3. Enter an optional transfer note.
4. Click "Next".
5. Complete authentication and the points transfer in LINUX DO CREDIT.
6. You will be redirected back to see the result.

---

## 🌐 Configuration Checklist

Before deployment, confirm that:

- [ ] You have created an application in Linux.do Credit.
- [ ] Your Client ID and Client Secret are correct.
- [ ] The notification URL follows the format: `https://your-domain/api/notify.php`
- [ ] The return URL follows the format: `https://your-domain/success.html`
- [ ] The URLs are publicly accessible and do not use localhost.

---

## ⚙️ Custom Configuration

### Docker/PHP deployment

Edit `config/config.php`:

```php
'preset_amounts' => [2, 6, 18, 66, 188],  // Preset amounts
'min_amount' => 1,      // Minimum amount
'max_amount' => 500,    // Maximum amount
'title' => 'Buy me a coffee',
'description' => 'Your support keeps the project going',
```

---

## 📝 Customizing Page Text (Web UI)

Edit the static pages or front-end scripts directly:

- Home-page text: `reward-website/index.html` (title, subtitle, section titles, button labels, placeholders, and more)
- Success/waiting page text: `reward-website/success.html`
- Front-end validation and error messages: `reward-website/assets/js/main.js`
- Theme-switcher prompt text: `reward-website/assets/js/theme.js`

Apply changes as follows:

- **Docker deployment**: run `git pull`, then `docker compose up -d --build`
- **Direct PHP deployment**: refresh the page; clear the browser cache if necessary

---

## 📁 Project Structure

```
reward-website/
├── index.html              # Points-transfer page
├── success.html            # Transfer-result page
├── api/
│   ├── create_order.php    # PHP API
│   └── ...
├── config/
│   └── config.example.php  # Configuration template
└── assets/                 # CSS/JS assets
```

---

## ❓ Frequently Asked Questions

### Q: How do I get a Client ID and Client Secret?

Visit https://credit.linux.do → Console → Marketplace Center → Create an application.

### Q: What should I do if signature verification fails?

Verify that the Client ID and Client Secret are correct and contain no extra spaces.

### Q: How do I view logs?

- **Docker**: `docker compose logs -f`
- **PHP**: `tail -f logs/*.log`

---

## 📚 More Documentation

- [DOCKER.md](DOCKER.md) - Docker deployment guide
- [DEPLOYMENT.md](DEPLOYMENT.md) - Full deployment guide
- [THEME.md](THEME.md) - UI theme customization
- [API.md](API.md) - API documentation

---

## 📧 Support

- **Linux.do Credit documentation**: https://credit.linux.do/docs
- **GitHub Issues**: https://github.com/Razewang/LINUX_EASY_CREDIT/issues

---

## 📄 License

This project is open sourced under the [MIT License](LICENSE).

---

**Enjoy using it!** 🎉
