# Build Instructions

This document describes how to build a distributable package of the M-Pesa Payment Gateway plugin.

## Prerequisites

- Node.js 14+ and npm 6+
- Composer
- Git

## Building a Release Package

### 1. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### 2. Build the Package

```bash
npm run build
```

This will:
1. Clean previous build artifacts
2. Install production PHP dependencies (without dev dependencies)
3. Create a zip file in the `dist/` directory
4. Restore development dependencies

### 3. Output

The build process creates:
- `dist/woocommerce-mpesa-payment-gateway-2.0.0.zip` - Distributable plugin package

## What's Included in the Package

The zip file contains:

```
woocommerce-mpesa-payment-gateway/
├── assets/
│   ├── css/
│   ├── images/
│   └── js/
├── includes/
│   ├── Admin/
│   ├── Blocks/
│   ├── Core/
│   ├── Gateway/
│   ├── Hooks/
│   ├── Services/
│   └── certificates/
├── languages/
├── templates/
├── vendor/ (production only)
├── woocommerce-mpesa-payment-gateway.php
├── composer.json
├── LICENSE
├── README.md
├── CHANGELOG.md
└── CONTRIBUTING.md
```

## What's Excluded

The following are excluded from the package:
- Development dependencies (`vendor/bin/`, test files)
- Node modules
- Build scripts
- Git files
- IDE configuration
- Test files

## Manual Build Steps

If you prefer to build manually:

```bash
# 1. Clean old builds
rm -rf dist/ mpesa-payment-gateway.zip

# 2. Install production dependencies
composer install --no-dev --optimize-autoloader --prefer-dist

# 3. Create package
npm run package

# 4. Restore dev dependencies
composer install
```

## Testing the Package

After building:

```bash
# 1. Extract the zip
unzip dist/woocommerce-mpesa-payment-gateway-2.0.0.zip -d test-install/

# 2. Check the contents
ls -la test-install/woocommerce-mpesa-payment-gateway/

# 3. Verify file sizes
du -sh test-install/woocommerce-mpesa-payment-gateway/
```

## Troubleshooting

### "archiver not found"

```bash
npm install
```

### "composer not found"

Install Composer from https://getcomposer.org/

### Permission Denied

```bash
chmod +x scripts/package.js
```

### Zip file too large

Check if node_modules or development files were accidentally included.

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Build Release

on:
  push:
    tags:
      - 'v*'

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'
          tools: composer
      
      - name: Install dependencies
        run: |
          npm install
          composer install
      
      - name: Build package
        run: npm run build
      
      - name: Upload artifact
        uses: actions/upload-artifact@v3
        with:
          name: mpesa-payment-gateway-release
          path: dist/*.zip
```

## Version Management

Update version in:
1. `woocommerce-mpesa-payment-gateway.php` (plugin header)
2. `package.json` (version field)
3. `scripts/package.js` (version constant)
4. `composer.json` (version field)
5. `README.md` (badges and installation)

## Release Checklist

- [ ] Update version numbers
- [ ] Update CHANGELOG.md
- [ ] Run tests (when implemented)
- [ ] Build package
- [ ] Test package installation
- [ ] Create git tag
- [ ] Upload to WordPress.org (if applicable)
- [ ] Create GitHub release
- [ ] Update documentation

---

**Questions?** Open an issue at https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway/issues

