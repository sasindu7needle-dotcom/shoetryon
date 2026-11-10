import { cpSync, existsSync, rmSync } from 'node:fs';

if (existsSync('node_modules/deepar')) {
    rmSync('public/vendor/deepar', { recursive: true, force: true });
    cpSync('node_modules/deepar', 'public/vendor/deepar', { recursive: true });
}
