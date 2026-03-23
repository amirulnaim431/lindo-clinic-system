import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(rootDir, '..');
const source = resolve(projectRoot, 'public/build/.vite/manifest.json');
const target = resolve(projectRoot, 'public/build/manifest.json');

if (!existsSync(source)) {
    throw new Error(`Vite manifest not found at ${source}`);
}

mkdirSync(dirname(target), { recursive: true });
copyFileSync(source, target);
