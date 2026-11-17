// Copies only the Bootstrap Icons actually referenced in views/config into resources/icons.
// Usage: npm run icons
import { readFileSync, readdirSync, statSync, copyFileSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';

const roots = ['resources/views', 'config', 'app'];
const patterns = [/\sicon="([a-z0-9-]+)"/g, /<x-icon\s+name="([a-z0-9-]+)"/g, /'icon'\s*=>\s*'([a-z0-9-]+)'/g, /Icon::svg\('([a-z0-9-]+)'/g, /data-icon="([a-z0-9-]+)"/g];
const names = new Set();

const walk = (dir) => readdirSync(dir).forEach((f) => {
    const p = join(dir, f);
    if (statSync(p).isDirectory()) return walk(p);
    const src = readFileSync(p, 'utf8');
    patterns.forEach((re) => { for (const m of src.matchAll(re)) names.add(m[1]); });
    // Any quoted token that is a Bootstrap Icons name (icons passed via arrays / variables).
    for (const m of src.matchAll(/'([a-z][a-z0-9-]{1,40})'/g)) {
        if (/^(?:[a-z0-9]+-)*[a-z0-9]+$/.test(m[1]) && existsSync(`node_modules/bootstrap-icons/icons/${m[1]}.svg`)) names.add(m[1]);
    }
});
roots.forEach(walk);

const out = 'resources/icons';
rmSync(out, { recursive: true, force: true });
mkdirSync(out, { recursive: true });
const missing = [];
for (const n of [...names].sort()) {
    const src = `node_modules/bootstrap-icons/icons/${n}.svg`;
    if (existsSync(src)) copyFileSync(src, `${out}/${n}.svg`); else missing.push(n);
}
console.log(`Copied ${names.size - missing.length} icons to ${out}`);
if (missing.length) { console.error('Missing icons:', missing.join(', ')); process.exit(1); }
