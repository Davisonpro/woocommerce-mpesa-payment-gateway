#!/usr/bin/env node

/**
 * Build Script
 * 
 * Creates a distributable zip file of the plugin
 * 
 * @package WooMpesa
 */

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

// Configuration
const pluginSlug = 'mpesa-payment-gateway';
const version = '2.0.0';
const outputDir = path.resolve(__dirname, '../dist');
const outputFile = `${pluginSlug}-${version}.zip`;

// Files and directories to include
const includePatterns = [
    'assets/**/*',
    'includes/**/*',
    'languages/**/*',
    'templates/**/*',
    'vendor/**/*',
    'mpesa-payment-gateway.php',
    'uninstall.php',
    'readme.txt',
    'composer.json',
    'LICENSE',
    'README.md',
    'CONTRIBUTING.md',
];

// Files and directories to exclude
const excludePatterns = [
    '**/.DS_Store',
    '**/.git*',
    '**/node_modules/**',
    '**/tests/**',
    '**/test/**',
    '**/*.md.backup',
    'vendor/bin/**',
    'vendor/composer/installers/**',
    'vendor/phpstan/**',
    'vendor/squizlabs/**',
    'vendor/phpunit/**',
    'vendor/doctrine/**',
    'vendor/myclabs/**',
    'vendor/nikic/**',
    'vendor/phar-io/**',
    'vendor/sebastian/**',
    'vendor/theseer/**',
    '**/*.phar',
    '**/*.phar.asc',
    '**/phpstan',
    '**/phpcs',
    '**/phpcbf',
    '**/phpunit',
];

console.log('🚀 Building plugin package...\n');

// Create output directory
if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
    console.log('✓ Created dist directory');
}

// Create zip file
const output = fs.createWriteStream(path.join(outputDir, outputFile));
const archive = archiver('zip', {
    zlib: { level: 9 } // Maximum compression
});

// Handle errors
output.on('close', function() {
    const sizeMB = (archive.pointer() / 1024 / 1024).toFixed(2);
    console.log(`\n✓ Package created successfully!`);
    console.log(`  File: dist/${outputFile}`);
    console.log(`  Size: ${sizeMB} MB`);
    console.log(`  Files: ${archive.pointer()} bytes\n`);
});

archive.on('error', function(err) {
    console.error('✗ Error creating package:', err);
    process.exit(1);
});

archive.on('warning', function(err) {
    if (err.code === 'ENOENT') {
        console.warn('⚠ Warning:', err);
    } else {
        throw err;
    }
});

// Pipe archive to file
archive.pipe(output);

// Add files
const rootDir = path.resolve(__dirname, '..');

console.log('📦 Adding files to package...\n');

includePatterns.forEach(pattern => {
    const isDirectory = pattern.endsWith('/**/*');
    const cleanPattern = pattern.replace('/**/*', '');
    const sourcePath = path.join(rootDir, cleanPattern);
    
    if (!fs.existsSync(sourcePath)) {
        console.log(`  ⊘ Skipping ${cleanPattern} (not found)`);
        return;
    }
    
    if (isDirectory) {
        console.log(`  ✓ Adding ${cleanPattern}/`);
        archive.directory(sourcePath, path.join(pluginSlug, cleanPattern), (entry) => {
            // Exclude patterns
            for (const exclude of excludePatterns) {
                if (exclude.includes('**')) {
                    const pattern = exclude.replace('**/', '').replace('/**', '');
                    if (entry.name.includes(pattern)) {
                        return false;
                    }
                }
            }
            return entry;
        });
    } else {
        console.log(`  ✓ Adding ${cleanPattern}`);
        archive.file(sourcePath, { name: path.join(pluginSlug, cleanPattern) });
    }
});

// Finalize the archive
archive.finalize();

