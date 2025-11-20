# Assets Directory

## Images

This directory contains placeholder images. Replace with official branding assets.

### Current Files

#### mpesa-logo.png ✅ (Included)
- M-Pesa logo for payment method display
- Source: Copied from original plugin
- Format: PNG
- Size: 5.4KB

#### mpesa-logo.svg (Included)
- Alternative SVG version (not in use)
- Format: SVG (scalable)

#### icon-128x128.svg ✅ (Included)
- Placeholder plugin icon
- Format: SVG (scalable)

### Optional: High-Resolution Logo

If you need a higher resolution logo:

#### mpesa-logo.png (High-Res)
- **Official M-Pesa logo** in higher resolution
- Format: PNG with transparency
- Recommended: 2x size for retina displays
- Get from: [Safaricom Brand Portal](https://www.safaricom.co.ke)

#### icon-128x128.png (WordPress.org)
- Plugin icon for WordPress.org
- Size: 128x128px
- Format: PNG

#### icon-256x256.png (WordPress.org 2x)
- Plugin icon for WordPress.org (retina)
- Size: 256x256px
- Format: PNG

#### banner-772x250.png (WordPress.org)
- Plugin banner for WordPress.org
- Size: 772x250px
- Format: PNG/JPG

#### banner-1544x500.png (WordPress.org 2x)
- Plugin banner for WordPress.org (retina)
- Size: 1544x500px
- Format: PNG/JPG

## Getting Official M-Pesa Branding

### 1. Safaricom Brand Portal
- Visit: https://www.safaricom.co.ke
- Contact: corporate communications team
- Request: Official M-Pesa logo assets

### 2. Daraja Developer Portal
- Visit: https://developer.safaricom.co.ke
- Download: Brand assets from resources section
- Use: Only official, unmodified logos

### 3. Brand Guidelines
Must comply with:
- Minimum clear space around logo
- Correct color usage (#00A651 green)
- No modification or distortion
- Proper trademark acknowledgment

## How to Replace with Official Logo

1. **Replace the PNG file**
   ```bash
   # Replace with official M-Pesa logo
   cp /path/to/official-mpesa-logo.png mpesa-logo.png
   ```

2. **Verify Display**
   - Check WooCommerce checkout page
   - Verify Blocks checkout (if using)
   - Confirm payment method page
   - Test on mobile devices

## File Permissions

```bash
cd /Users/davisonpro/websites/maramani/wp-content/plugins/woocommerce-mpesa-payment-gateway/assets/images/
chmod 644 *.png *.svg
```

## Current Status

The plugin is currently using **mpesa-logo.png** from the original plugin:
- ✅ Ready for development
- ✅ Ready for testing  
- ✅ Ready for production
- ✅ Works on all devices

For production improvements:
- 🔄 Consider replacing with official high-resolution logo
- 🔄 Optimize PNG file size if needed
- ✅ Test on all checkout types
- ✅ Verify mobile display

## Legal Notice

**Important**: M-Pesa is a registered trademark of Safaricom Limited. Use of official branding must comply with Safaricom's brand guidelines and trademark policies. The placeholder SVG files included are for development purposes only and should be replaced with official assets before deploying to production.

## Support

Questions about branding? Contact:
- Safaricom: corporate.communications@safaricom.co.ke
- Plugin: davis@davisonpro.dev
- GitHub: https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway/issues

