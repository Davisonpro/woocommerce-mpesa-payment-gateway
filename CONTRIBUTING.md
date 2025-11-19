# Contributing to M-Pesa Payment Gateway for WooCommerce

Thank you for your interest in contributing to this M-Pesa Payment Gateway! 🎉

## Getting Started

1. **Fork the repository**
   ```bash
   git clone https://github.com/Davisonpro/mpesa-payment-gateway.git
   cd mpesa-payment-gateway
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Create a branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Guidelines

### Code Standards

We follow strict coding standards:

- **WordPress Coding Standards**
- **PSR-12** Extended Coding Style
- **PSR-4** Autoloading
- **SOLID** Principles
- **Type Safety** (PHP 7.4+)

### Running Code Quality Checks

```bash
# Code sniffer
composer phpcs

# Static analysis
composer phpstan

# Auto-fix formatting
composer format
```

### Commit Messages

Follow conventional commits:

```
feat: add new payment method
fix: resolve callback timeout issue
docs: update README with new examples
refactor: improve service container
test: add unit tests for API service
```

## What to Contribute

### 🐛 Bug Reports

- Check if the issue already exists
- Provide detailed steps to reproduce
- Include WordPress, WooCommerce, and PHP versions
- Attach relevant error logs

### ✨ Feature Requests

- Describe the feature clearly
- Explain the use case
- Consider backward compatibility
- Discuss with maintainers first for large features

### 💻 Code Contributions

#### Areas We'd Love Help With

- Unit tests and integration tests
- Additional payment methods
- Performance optimizations
- Accessibility improvements
- Translation to other languages
- Documentation improvements

### 📝 Documentation

- Fix typos and grammar
- Add examples and tutorials
- Improve code comments
- Write integration guides

## Pull Request Process

1. **Update your fork**
   ```bash
   git remote add upstream https://github.com/Davisonpro/mpesa-payment-gateway.git
   git fetch upstream
   git merge upstream/main
   ```

2. **Make your changes**
   - Write clean, documented code
   - Follow existing patterns
   - Add PHPDoc comments
   - Include type hints

3. **Test your changes**
   - Test in sandbox environment
   - Test both classic and Blocks checkout
   - Verify no console errors
   - Check mobile responsiveness

4. **Run quality checks**
   ```bash
   composer phpcs
   composer phpstan
   ```

5. **Submit PR**
   - Clear title and description
   - Link related issues
   - Include screenshots if UI changes
   - Update documentation if needed

## Code Review Process

- Maintainers will review within 7 days
- Address feedback promptly
- Be open to suggestions
- Maintain professional communication

## Testing

### Manual Testing

1. Install the plugin in a test environment
2. Configure with sandbox credentials
3. Test payment flows:
   - STK Push success
   - STK Push failure
   - C2B payments
   - Callbacks
   - Reversals
4. Test both checkouts:
   - Classic checkout
   - Blocks checkout
5. Test on mobile devices

### Automated Testing (Future)

We're working on setting up:
- PHPUnit for unit tests
- Integration tests
- E2E tests with Playwright

## Architecture Overview

### Key Components

```
includes/
├── Core/
│   ├── ServiceContainer.php  # DI container
│   └── Config.php            # Configuration
├── Services/
│   ├── MpesaApiService.php  # API wrapper
│   └── LoggerService.php     # Logging
├── Gateway/
│   ├── MpesaGateway.php     # Payment gateway
│   └── CallbackHandler.php   # Webhooks
├── Blocks/
│   └── BlocksIntegration.php # WC Blocks
└── Admin/
    └── AdminInterface.php     # Admin UI
```

### Design Patterns Used

- **Singleton**: Plugin and ServiceContainer
- **Dependency Injection**: Via ServiceContainer
- **Factory**: Service instantiation
- **Strategy**: Payment processing
- **Observer**: WordPress hooks

## Coding Best Practices

### PHP

```php
declare(strict_types=1);

namespace WooMpesa\Services;

/**
 * Service description
 * 
 * @package WooMpesa\Services
 */
final class ExampleService
{
    /**
     * Property description
     *
     * @var string
     */
    private string $property;

    /**
     * Constructor
     *
     * @param string $property Property value
     */
    public function __construct(string $property)
    {
        $this->property = $property;
    }

    /**
     * Method description
     *
     * @param string $param Parameter description
     * @return bool Result description
     */
    public function doSomething(string $param): bool
    {
        // Implementation
        return true;
    }
}
```

### JavaScript

```javascript
/**
 * Function description
 * 
 * @param {string} phone - Phone number
 * @returns {boolean} - Is valid
 */
const validatePhone = (phone) => {
    // Use modern ES6+
    const cleaned = phone.replace(/\D/g, '');
    return /^254[17]\d{8}$/.test(cleaned);
};
```

## Security

### Reporting Security Issues

**DO NOT** open public issues for security vulnerabilities.

Instead, email: davis@davisonpro.dev

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

We'll respond within 48 hours.

## Community

### Code of Conduct

- Be respectful and inclusive
- Welcome newcomers
- Accept constructive criticism
- Focus on what's best for the project
- Show empathy

### Getting Help

- **GitHub Discussions**: Ask questions
- **Issues**: Report bugs
- **Email**: davis@davisonpro.dev

## Recognition

Contributors will be:
- Listed in CONTRIBUTORS.md
- Mentioned in release notes
- Credited in documentation

## License

By contributing, you agree that your contributions will be licensed under the GPL-3.0-or-later license.

---

Thank you for making this plugin better! 🚀

**Davison Pro**  
https://davisonpro.dev

