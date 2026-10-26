#!/usr/bin/env node
/**
 * blanq Motion – Build
 *
 * Kopiert GSAP und Lenis aus node_modules nach vendor/, schreibt
 * vendor/manifest.json und erhöht die Plugin-Version, wenn sich eine
 * Bibliotheksversion geändert hat.
 *
 *   npm run build            Bibliotheken kopieren, Version nur bei Änderung erhöhen
 *   npm run build -- --bump  Version in jedem Fall erhöhen
 */
import { readFileSync, writeFileSync, rmSync, mkdirSync, copyFileSync, readdirSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const nm = join(root, 'node_modules');
const vendor = join(root, 'vendor');
const pluginFile = join(root, 'blanq-motion.php');
const forceBump = process.argv.includes('--bump');

const pkgVersion = (name) => JSON.parse(readFileSync(join(nm, name, 'package.json'), 'utf8')).version;
const gsapVersion = pkgVersion('gsap');
const lenisVersion = pkgVersion('lenis');

// Abhängigkeiten zwischen GSAP-Plugins (alle brauchen zusätzlich den Core "gsap").
const extraDeps = {
  ScrollSmoother: ['ScrollTrigger'],
  CustomBounce: ['CustomEase'],
  CustomWiggle: ['CustomEase'],
  MotionPathHelper: ['MotionPathPlugin'],
};

// Alte Version merken, um zu erkennen, ob sich etwas geändert hat.
const manifestPath = join(vendor, 'manifest.json');
const previous = existsSync(manifestPath) ? JSON.parse(readFileSync(manifestPath, 'utf8')) : null;

// vendor/ neu aufbauen
rmSync(join(vendor, 'gsap'), { recursive: true, force: true });
rmSync(join(vendor, 'lenis'), { recursive: true, force: true });
mkdirSync(join(vendor, 'gsap'), { recursive: true });
mkdirSync(join(vendor, 'lenis'), { recursive: true });

const libs = {};
const gsapDist = join(nm, 'gsap', 'dist');
for (const file of readdirSync(gsapDist).sort()) {
  if (!file.endsWith('.min.js') || file === 'all.min.js') continue;
  copyFileSync(join(gsapDist, file), join(vendor, 'gsap', file));
  const name = file.replace(/\.min\.js$/, '');
  libs[name] = {
    file: `gsap/${file}`,
    deps: name === 'gsap' ? [] : ['gsap', ...(extraDeps[name] || [])],
    version: gsapVersion,
  };
}
writeFileSync(
  join(vendor, 'gsap', 'LICENSE.txt'),
  `GSAP ${gsapVersion}\nCopyright (c) 2008-${new Date().getFullYear()}, GreenSock. All rights reserved.\n` +
    `Subject to the GreenSock standard "no charge" license: https://gsap.com/standard-license\n`
);

const lenisDist = join(nm, 'lenis', 'dist');
copyFileSync(join(lenisDist, 'lenis.min.js'), join(vendor, 'lenis', 'lenis.min.js'));
copyFileSync(join(lenisDist, 'lenis.css'), join(vendor, 'lenis', 'lenis.css'));
copyFileSync(join(nm, 'lenis', 'LICENSE'), join(vendor, 'lenis', 'LICENSE.txt'));
libs.Lenis = { file: 'lenis/lenis.min.js', css: 'lenis/lenis.css', deps: [], version: lenisVersion };

const manifest = { gsap: gsapVersion, lenis: lenisVersion, libs };
writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + '\n');

// Plugin-Version erhöhen, wenn sich Bibliotheken geändert haben
const changed = !previous || previous.gsap !== gsapVersion || previous.lenis !== lenisVersion;
let php = readFileSync(pluginFile, 'utf8');
const current = php.match(/^\s*\*\s*Version:\s*([\d.]+)/m)[1];
let next = current;
if ((changed && previous) || forceBump) {
  const parts = current.split('.').map(Number);
  parts[2] = (parts[2] || 0) + 1;
  next = parts.join('.');
  php = php
    .replace(/^(\s*\*\s*Version:\s*)[\d.]+/m, `$1${next}`)
    .replace(/(define\(\s*'BQ_MOTION_VERSION',\s*')[\d.]+('\s*\))/, `$1${next}$2`);
  writeFileSync(pluginFile, php);
}

console.log(`GSAP ${gsapVersion} · Lenis ${lenisVersion} · ${Object.keys(libs).length} Dateien`);
console.log(changed && previous ? `Bibliotheken aktualisiert, Plugin-Version ${current} → ${next}` : `Plugin-Version ${next}`);
