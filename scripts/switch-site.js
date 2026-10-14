const fs = require('fs');
const path = require('path');

const target = process.argv[2]; // 'aw', 'litm', or 'litmv4'

if (target !== 'aw' && target !== 'litm' && target !== 'litmv4') {
    console.error('Usage: node scripts/switch-site.js [aw|litm|litmv4]');
    process.exit(1);
}

const targetName = target === 'aw' ? 'AutoWealth' : target === 'litm' ? 'LitmFin' : 'LitmFinV4';
console.log(`Switching configuration to ${targetName}...`);

// Paths
const paths = {
    configSrc: path.join(__dirname, `../src/config/${target}_siteConfig.ts`),
    configDest: path.join(__dirname, '../src/config/siteConfig.ts'),
    envLocalSrc: path.join(__dirname, `./templates/.env.local.${target}`),
    envLocalDest: path.join(__dirname, '../.env.local'),
    envProdSrc: path.join(__dirname, `./templates/.env.production.local.${target}`),
    envProdDest: path.join(__dirname, '../.env.production.local')
};

// Helper function to safely copy a file
function copyFile(src, dest, description) {
    try {
        if (!fs.existsSync(src)) {
            console.error(`Error: Source file does not exist: ${src}`);
            process.exit(1);
        }
        fs.copyFileSync(src, dest);
        console.log(`- Successfully updated ${description}`);
    } catch (err) {
        console.error(`Error: Failed to copy ${description}:`, err);
        process.exit(1);
    }
}

// Perform copies
copyFile(paths.configSrc, paths.configDest, 'siteConfig.ts');
copyFile(paths.envLocalSrc, paths.envLocalDest, '.env.local');
copyFile(paths.envProdSrc, paths.envProdDest, '.env.production.local');

console.log(`Successfully switched to ${targetName}!`);
