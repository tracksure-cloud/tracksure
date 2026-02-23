# Contributing to TrackSure

Thank you for your interest in contributing to TrackSure! This document provides guidelines for contributing to the project.

## Code of Conduct

Be respectful, professional, and constructive in all interactions.

## Getting Started

### 1. Fork the Repository

```bash
# Fork on GitHub, then clone your fork
git clone https://github.com/YOUR-USERNAME/tracksure.git
cd tracksure
```

### 2. Set Up Development Environment

```bash
# Install dependencies
npm install
cd admin && npm install && cd ..
composer install

# Build for development
npm run build:production
```

### 3. Create a Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/your-bug-fix
```

## Development Workflow

### Code Standards

#### PHP

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use PHP 7.4+ features
- Run PHPCS before committing:
  ```bash
  npm run phpcs
  npm run phpcs:fix  # Auto-fix issues
  ```

#### JavaScript/TypeScript

- Use TypeScript for new code
- Follow ESLint configuration
- Run linter before committing:
  ```bash
  npm run eslint
  npm run eslint:fix  # Auto-fix issues
  ```

#### CSS

- Use BEM naming convention
- Prefix classes with `ts-`
- Run Stylelint:
  ```bash
  npm run stylelint
  npm run stylelint:fix  # Auto-fix issues
  ```

### Testing

```bash
# Run all validations
npm run validate

# Build production version
npm run build:production

# Create release ZIP
npm run zip:plugin
```

### Commit Messages

Use clear, descriptive commit messages:

```
Good:
- "Add WooCommerce order tracking integration"
- "Fix session tracking in Safari ITP mode"
- "Update admin dashboard performance chart"

Bad:
- "Fix bug"
- "Update files"
- "Changes"
```

## Pull Request Process

### 1. Before Submitting

- [ ] Code follows WordPress and project coding standards
- [ ] All linters pass (PHPCS, ESLint, Stylelint)
- [ ] Production build succeeds
- [ ] No console errors or warnings
- [ ] Tested in WordPress 6.0+
- [ ] Documentation updated (if needed)

### 2. Submit Pull Request

1. Push your branch to your fork
2. Create Pull Request on GitHub
3. Fill out the PR template
4. Link related issues
5. Request review

### 3. Code Review

- Address all feedback
- Keep commits clean and logical
- Squash commits if needed

### 4. Merge

Once approved, maintainers will merge your PR.

## Development Guidelines

### File Structure

```
tracksure/
├── admin/              # React admin interface
│   ├── src/           # TypeScript source
│   └── dist/          # Compiled output
├── assets/            # Frontend assets
├── includes/          # PHP classes
│   ├── core/         # Core functionality
│   ├── admin/        # Admin classes
│   └── rest-api/     # REST API controllers
├── docs/              # Documentation
├── scripts/           # Build and deployment scripts
└── tracksure.php      # Main plugin file
```

### Adding Features

#### New REST API Endpoint

1. Create controller in `includes/rest-api/`
2. Register route in controller
3. Add TypeScript types in `admin/src/types/`
4. Update API documentation

#### New Admin Page

1. Create React component in `admin/src/pages/`
2. Add route in `admin/src/App.tsx`
3. Add menu item in `includes/admin/class-tracksure-admin-ui.php`
4. Update navigation

#### New Integration

1. Create integration class in `includes/free/integrations/`
2. Register in `includes/free/class-tracksure-free-pack.php`
3. Add tests
4. Document integration

### Security

- Always escape output: `esc_html()`, `esc_attr()`, `esc_url()`
- Sanitize input: `sanitize_text_field()`, etc.
- Use nonces for AJAX/forms
- Check capabilities: `current_user_can('manage_options')`
- Prepare SQL queries: `$wpdb->prepare()`

### Performance

- Lazy load admin assets
- Minimize database queries
- Use WordPress transients for caching
- Optimize frontend tracking script
- Test with Query Monitor

## Reporting Bugs

### Before Reporting

1. Search existing issues
2. Test with default WordPress theme
3. Disable other plugins
4. Clear all caches

### Bug Report Should Include

- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Screenshots (if applicable)
- Console errors (if applicable)

## Feature Requests

We welcome feature suggestions! Please:

1. Check if feature already exists
2. Search existing feature requests
3. Describe use case clearly
4. Explain expected behavior
5. Consider backward compatibility

## Documentation

When adding features:

- Update README.md if needed
- Add inline code comments
- Update JSDoc/PHPDoc
- Add user documentation
- Update CHANGELOG

## Questions?

- **Support Forum**: [WordPress.org](https://wordpress.org/support/plugin/tracksure/)
- **GitHub Issues**: For bugs and features only
- **Email**: support@tracksure.cloud

## License

By contributing, you agree that your contributions will be licensed under GPL v2 or later.

---

Thank you for contributing to TrackSure! 🎉
