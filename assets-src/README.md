# Asset sources

Editable SVG sources for the WordPress.org listing artwork. Neither this
directory nor `.wordpress-org/` ships in the plugin zip — both are excluded by
`.distignore`.

- `icon.svg` — plugin icon. Also a deliverable: WordPress.org accepts an SVG
  icon, but [requires PNG fallbacks](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
  for older browsers and Facebook, so both are generated.
- `banner.svg` — plugin page banner. Source only; WordPress.org banners must be
  PNG or JPG.

## Regenerating

Requires `rsvg-convert` (`brew install librsvg`) and ImageMagick.

```sh
cd assets-src
rsvg-convert -w  772 -h 250 banner.svg -o ../.wordpress-org/banner-772x250.png
rsvg-convert -w 1544 -h 500 banner.svg -o ../.wordpress-org/banner-1544x500.png
rsvg-convert -w  128 -h 128 icon.svg   -o ../.wordpress-org/icon-128x128.png
rsvg-convert -w  256 -h 256 icon.svg   -o ../.wordpress-org/icon-256x256.png
cp icon.svg ../.wordpress-org/icon.svg
for f in ../.wordpress-org/*.png; do magick "$f" -strip "$f"; done
```

## Design notes

The icon is a carrot alone. An earlier draft set a farm-stand awning above it,
which reads well in the abstract but not on this canvas: the awning and the
carrot greens want the same band, so at 128px they collided and at the 40px
size WordPress.org uses in search rows they merged into a single white blob.
One mark that reads beats two that fight.

The carrot is not an arbitrary pick — `dashicons-carrot` is already the plugin's
admin menu icon and its block category icon, so the listing matches what a user
sees after installing.

Three greens rather than a leafy cluster, and two ridge marks rather than four:
both were tuned at 40px, where anything denser fills in.

The banner reuses the icon's carrot through a `<use>` reference rather than a
copy, so the two cannot drift apart. The status chips are the plugin's own
availability vocabulary in the colours the availability board block uses.

Text in `banner.svg` is live text, not outlines, so it depends on the rendering
machine having Helvetica or Arial. If you regenerate on a machine without
either, check the output before committing: a fallback face will change the
line widths, and the title is sized to just fit within 772px.

## Screenshots

`screenshot-1..5.png` are **not** generated here — they are captures of the
running plugin, and `readme.txt` already carries their five captions in order.
`bin/validate-config.php` warns until the files exist and errors if the numbering
and the captions ever disagree.

## Where these go

Into the top-level `assets/` directory of the WordPress.org **SVN** repo — a
sibling of `trunk/` and `tags/`, not inside either. Artwork placed under
`trunk/assets/` ships to users and still does not appear on the listing page.
