# TrackSure - First-Party Analytics & Attribution for WordPress

> **Complete analytics platform for WordPress.** Track everything, understand your visitors, grow your business—with or without ads.

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/rubait-hasan/tracksure)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)

---

## 🎯 What is TrackSure?

TrackSure is a production-grade analytics and attribution platform built specifically for WordPress. It provides:

- **First-Party Tracking** - Own your data, bypass ad blockers, 100% GDPR compliant
- **Event Deduplication** - Smart browser+server tracking prevents duplicate events
- **Ad Platform Integration** - Meta CAPI, Google Analytics 4, TikTok, Twitter, LinkedIn
- **Attribution Analytics** - Understand which channels drive conversions
- **E-commerce Tracking** - Deep WooCommerce, EDD, SureCart integration
- **Real-time Dashboard** - Beautiful React-based admin interface

---

## ✨ Key Features

### 📊 Complete Analytics

- Page views, sessions, visitors, bounce rate
- Traffic sources (UTMs, referrers, social, search, AI chatbots)
- Device, browser, OS, country analytics
- Custom events and goals
- Funnel analysis

### 🛍️ E-commerce Tracking

- View item, add to cart, begin checkout, purchase
- Product performance analytics
- Revenue attribution by source/medium
- Cart abandonment tracking
- Cross-device journey mapping

### 🎯 Ad Platform Integration

- **Meta Conversions API** - Server-side event delivery with Event Match Quality tracking
- **Google Analytics 4** - Full e-commerce and event tracking
- **TikTok Events API** - Server-side conversion tracking
- **Twitter Conversions API** - Enhanced attribution
- **LinkedIn Conversions API** - B2B tracking

### 🔒 Privacy & Compliance

- GDPR compliant (consent management)
- IP anonymization
- Opt-out mechanisms
- Data retention policies
- Cookie-less tracking option

### ⚡ Performance

- Non-blocking JavaScript (<5KB minified)
- Batched event delivery (100 events per query)
- Background processing (cron-based)
- Database optimization (indexed queries)
- Zero impact on page speed

---

## 📦 Installation

### From GitHub (Development)

1. **Clone Repository**

```bash
git clone https://github.com/YOUR-USERNAME/tracksure.git
cd tracksure
```

2. **Install Dependencies**

```bash
# Install validation tools
npm install

# Install admin build tools
cd admin
npm install
cd ..
```

3. **Build Production Assets**

```bash
npm run build:production
```

4. **Install in WordPress**

- Copy entire `tracksure` folder to `wp-content/plugins/`
- Activate in WordPress admin

### From Release ZIP

1. Download latest release from [Releases](https://github.com/YOUR-USERNAME/tracksure/releases)
2. WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose `tracksure.zip` and activate

---

## 🚀 Quick Start

### 1. Initial Setup

After activation:

1. Navigate to **TrackSure** in WordPress admin
2. Go to **Settings** → Enable tracking
3. Configure destinations (Meta, GA4, etc.)

### 2. Basic Tracking

TrackSure automatically tracks:

- ✅ Page views
- ✅ Sessions and visitors
- ✅ Traffic sources (UTMs)
- ✅ Device and location data

### 3. E-commerce Setup (WooCommerce)

Events tracked automatically:

- ✅ Product views (`view_item`)
- ✅ Add to cart (`add_to_cart`)
- ✅ Checkout initiated (`begin_checkout`)
- ✅ Purchases (`purchase`)

### 4. Meta CAPI Setup

1. Get Meta Pixel ID and Access Token from Facebook Events Manager
2. Go to **Destinations** → Enable **Meta Conversions API**
3. Enter credentials
4. Test connection
5. Save settings

---

## 🛠️ Development

### Prerequisites

- Node.js 18+
- NPM 9+
- PHP 7.4+
- WordPress 6.0+

### Development Mode

```bash
# Start webpack dev server (auto-recompile React on save)
cd admin
npm run dev
```

### Build for Production

```bash
# From plugin root
npm run build:production
```

### Code Quality

```bash
# Validate PHP, JS, CSS
npm run validate

# Auto-fix code style issues
npm run validate:fix

# Security scan
npm run security:check
```

### Testing

```bash
# Run PHPUnit tests (when configured)
npm test

# Type checking
cd admin
npm run type-check
```

---

## 📖 Documentation

- **[Build & Deployment Guide](BUILD_DEPLOY_GUIDE.md)** - How to build production ZIP and deploy
- **[Admin README](admin/README.md)** - React admin interface architecture
- **[Events Registry](registry/events.json)** - Available events and parameters
- **[API Documentation](docs/api/)** - REST API endpoints
- **[Hooks Reference](docs/hooks/)** - WordPress filters and actions

---

## 🗂️ Project Structure

```
tracksure/
├── admin/                          # React admin interface
│   ├── src/                       # TypeScript source
│   ├── dist/                      # Compiled output (gitignored)
│   ├── package.json
│   └── webpack.config.js
├── assets/                         # Frontend assets
│   ├── js/
│   │   └── tracksure-web.js      # Browser tracking SDK
│   └── css/
├── includes/                       # PHP classes
│   ├── core/                      # Core functionality
│   │   ├── class-tracksure-core.php
│   │   ├── class-tracksure-db.php
│   │   ├── class-tracksure-installer.php
│   │   └── services/              # Service classes
│   ├── integrations/              # Third-party integrations
│   ├── admin/                     # Admin functionality
│   └── api/                       # REST API endpoints
├── languages/                      # Translation files
├── registry/                       # Event/parameter schemas
│   ├── events.json
│   └── params.json
├── scripts/                        # Build utilities
│   └── create-release-zip.js
├── .gitignore
├── package.json                    # Build scripts
├── tracksure.php                   # Main plugin file
├── readme.txt                      # WordPress.org readme
└── uninstall.php                   # Cleanup on uninstall
```

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

### Coding Standards

- **PHP**: WordPress Coding Standards (PHPCS)
- **JavaScript/TypeScript**: ESLint + Prettier
- **CSS**: Stylelint
- **Commits**: Conventional Commits format

---

## 📊 System Requirements

### Minimum

- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+

### Recommended

- WordPress 6.4+
- PHP 8.2+
- MySQL 8.0+ or MariaDB 10.11+
- 512 MB PHP memory limit
- HTTPS enabled

---

## 🐛 Bug Reports

Found a bug? Please [open an issue](https://github.com/rubait-hasan/tracksure/issues) with:

- WordPress version
- PHP version
- TrackSure version
- Steps to reproduce
- Expected vs actual behavior
- Error messages (if any)

---

## 📄 License

GPL v2 or later. See [LICENSE](LICENSE) for details.

---

## 🙏 Credits

**Built with:**

- React 18 - UI framework
- Recharts - Data visualization
- WordPress REST API - Backend communication
- Lucide React - Icon system

**Inspired by:**

- Google Analytics
- Plausible Analytics
- Matomo

---

## 📞 Support

- **Documentation**: [GitHub Wiki](https://github.com/YOUR-USERNAME/tracksure/wiki)
- **Issues**: [GitHub Issues](https://github.com/YOUR-USERNAME/tracksure/issues)
- **Discussions**: [GitHub Discussions](https://github.com/YOUR-USERNAME/tracksure/discussions)

---

**Made with ❤️ for WordPress**
