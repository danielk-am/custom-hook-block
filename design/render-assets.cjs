// Usage: node design/render-assets.cjs [path-to-installed-sharp]
const sharp = require(process.argv[2] || 'sharp');
const path = require('node:path');
const fs = require('node:fs');
const out = path.join(__dirname, '../.wordpress-org');
(async () => {
  for (const size of [128, 256]) await sharp(path.join(__dirname, 'icon.svg')).resize(size, size).png().toFile(path.join(out, `icon-${size}x${size}.png`));
  for (const [width, height] of [[772, 250], [1544, 500]]) await sharp(path.join(__dirname, 'banner.svg')).resize(width, height).png().toFile(path.join(out, `banner-${width}x${height}.png`));
  fs.copyFileSync(path.join(__dirname, 'icon.svg'), path.join(out, 'icon.svg'));
})();
