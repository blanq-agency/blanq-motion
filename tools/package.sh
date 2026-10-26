#!/bin/sh
# Baut dist/blanq-motion.zip – genau das, was auf die WordPress-Seiten kommt.
set -e
cd "$(dirname "$0")/.."
rm -rf dist
mkdir -p dist/blanq-motion
cp -r blanq-motion.php includes vendor lib README.md dist/blanq-motion/
cd dist
zip -qr blanq-motion.zip blanq-motion
rm -rf blanq-motion
echo "dist/blanq-motion.zip erstellt"
