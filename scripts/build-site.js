const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const target = process.argv[2]; // 'aw', 'litm', or 'litmv4'

if (target !== 'aw' && target !== 'litm' && target !== 'litmv4') {
    console.error('Usage: node scripts/build-site.js [aw|litm|litmv4]');
    process.exit(1);
}

const targetName = target === 'aw' ? 'AutoWealth' : target === 'litm' ? 'LitmFin' : 'LitmFinV4';
const outDestName = `out_${target}`;
const outDestPath = path.join(__dirname, `../${outDestName}`);
const outSrcPath = path.join(__dirname, '../out');

// 1. Run the switcher script to set the correct config and environment files
console.log(`[Build] Step 1: Switching environment to ${targetName}...`);
try {
    execSync(`node "${path.join(__dirname, 'switch-site.js')}" ${target}`, { stdio: 'inherit' });
} catch (err) {
    console.error('[Build] Failed to switch environment:', err);
    process.exit(1);
}

// 2. Run the Next.js production build
console.log(`[Build] Step 2: Running production build for ${targetName}...`);
try {
    // Next.js automatically runs in production mode when executing 'next build'
    execSync('npx next build', { stdio: 'inherit' });
    console.log('[Build] Next.js build completed successfully.');
} catch (err) {
    console.error('[Build] Next.js build execution failed:', err);
    process.exit(1);
}

// 3. Move/Rename the default output folder 'out' to the target directory
console.log(`[Build] Step 3: Moving output to ${outDestName}...`);
try {
    // Clean up existing destination if it exists
    if (fs.existsSync(outDestPath)) {
        console.log(`- Cleaning up existing ${outDestName} directory...`);
        fs.rmSync(outDestPath, { recursive: true, force: true });
    }

    if (!fs.existsSync(outSrcPath)) {
        console.error(`- Error: Output directory 'out' was not found at ${outSrcPath}`);
        process.exit(1);
    }

    // Rename/move out folder
    try {
        fs.renameSync(outSrcPath, outDestPath);
    } catch (renameErr) {
        console.log(`- Rename failed (${renameErr.code || renameErr.message}), trying copy and delete fallback...`);
        fs.cpSync(outSrcPath, outDestPath, { recursive: true });
        try {
            fs.rmSync(outSrcPath, { recursive: true, force: true });
        } catch (rmErr) {
            console.warn(`- Warning: Could not clean up temporary 'out' directory: ${rmErr.message}`);
        }
    }
    console.log(`[Build] Successfully built and exported to: ${outDestName}/`);
} catch (err) {
    console.error(`[Build] Failed to move build output to ${outDestName}:`, err);
    process.exit(1);
}
