"""
Icons: stroke → filled outlines (Inkscape output) → SVG Icon API format of WordPress 7.1, and a visual check.

Usage: python3 tools/icons/check.py <src-dir> <inkscape-dir> <out-dir>

  src-dir       — icons as the site draws them (export-sources.php): stroke 1.5, round caps/joins;
  inkscape-dir  — the same icons after Inkscape “Object to Path” + “Stroke to Path”;
  out-dir       — result:
                    icons/<name>.svg   — only <svg viewBox> + <path fill d fill-rule>, i.e. what the
                                         WordPress 7.1 sanitizer (WP_Icons_Registry::sanitize_icon_content)
                                         keeps unchanged;
                    compare/<name>.png — before | after | overlay (before red, after blue, both = dark),
                                         at 24 px (as on the site) and 192 px;
                    report.md          — pixel difference per icon and the verdict.

The check passes when, for every icon, the sanitized result equals the normalized output (nothing is
stripped) and the rendered “after” differs from “before” by at most MAX_MEAN mean / MAX_PIXEL share of
pixels (anti-aliasing noise) at both sizes.
"""

import io
import json
import os
import re
import subprocess
import sys
import xml.etree.ElementTree as ET

from PIL import Image, ImageChops

SVG = '{http://www.w3.org/2000/svg}'
SIZES = (24, 48, 192)
MAX_MEAN = 0.01  # mean |alpha difference| / 255
MAX_PIXEL = 0.01  # share of pixels with |difference| > 25 % of full alpha

# WordPress 7.1 WP_Icons_Registry::sanitize_icon_content(): allowed tags and attributes.
ALLOWED = {
    'svg': {'class', 'xmlns', 'width', 'height', 'viewbox', 'aria-hidden', 'role', 'focusable'},
    'path': {'fill', 'fill-rule', 'd', 'transform'},
    'polygon': {'fill', 'fill-rule', 'points', 'transform', 'focusable'},
}


def style_dict(el):
    out = {}
    for part in (el.get('style') or '').split(';'):
        if ':' in part:
            k, v = part.split(':', 1)
            out[k.strip()] = v.strip()
    return out


def normalize(inkscape_svg):
    """Inkscape plain SVG → <svg viewBox="0 0 24 24"><path fill="currentColor" d=".." [fill-rule]/>…</svg>."""
    root = ET.fromstring(inkscape_svg)
    paths = []
    problems = []

    def walk(el, inherited_transform):
        tag = el.tag.replace(SVG, '')
        transform = el.get('transform')
        if transform:
            problems.append('transform on <%s>: %s' % (tag, transform))
        if tag in ('defs', 'metadata', 'namedview') or tag.startswith('{'):
            return
        if tag == 'path':
            st = style_dict(el)
            fill = el.get('fill') or st.get('fill', '#000000')
            stroke = el.get('stroke') or st.get('stroke', 'none')
            if stroke not in ('none', None) and st.get('stroke-width', '0') not in ('0', '0px'):
                problems.append('stroke left on a path: %s' % stroke)
            if fill == 'none':
                return  # empty leftover of the original stroke path
            rule = el.get('fill-rule') or st.get('fill-rule')
            paths.append((el.get('d'), rule))
        elif tag in ('circle', 'rect', 'line', 'polyline', 'ellipse', 'polygon'):
            problems.append('<%s> was not converted to a path' % tag)
        for child in el:
            walk(child, transform or inherited_transform)

    walk(root, None)
    out = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">']
    for d, rule in paths:
        d = re.sub(r'\s+', ' ', d.strip())
        out.append('<path fill="currentColor"%s d="%s"/>' % (' fill-rule="%s"' % rule if rule and rule != 'nonzero' else '', d))
    out.append('</svg>')
    return '\n'.join(out) + '\n', problems


def sanitize(svg):
    """What wp_kses with the WordPress 7.1 icon allowlist leaves: unknown tags dropped, unknown attributes removed."""
    root = ET.fromstring(svg)
    stripped = []

    def walk(el):
        tag = el.tag.replace(SVG, '')
        if tag not in ALLOWED:
            stripped.append('<%s>' % tag)
            return None
        for attr in list(el.attrib):
            if attr.lower() not in ALLOWED[tag]:
                stripped.append('%s@%s' % (tag, attr))
        for child in el:
            walk(child)

    walk(root)
    return stripped


def sanitize_apply(svg):
    """The SVG as WordPress 7.1 stores it: disallowed elements and attributes removed (as wp_kses does)."""
    root = ET.fromstring(svg)

    def clean(el):
        for child in list(el):
            if child.tag.replace(SVG, '') not in ALLOWED:
                el.remove(child)
            else:
                clean(child)
        tag = el.tag.replace(SVG, '')
        for attr in list(el.attrib):
            if attr.lower() not in ALLOWED.get(tag, set()):
                del el.attrib[attr]

    clean(root)
    ET.register_namespace('', SVG[1:-1])
    return ET.tostring(root, encoding='unicode')


def render(svg_text, size, color='#000000'):
    """Rasterize with rsvg-convert; returns the alpha channel."""
    svg_text = svg_text.replace('currentColor', color)
    png = subprocess.run(
        ['rsvg-convert', '-w', str(size), '-h', str(size), '-f', 'png'],
        input=svg_text.encode(), capture_output=True, check=True
    ).stdout
    return Image.open(io.BytesIO(png)).convert('RGBA').split()[3]


def overlay(before, after):
    """Before in red, after in blue; where both — dark purple; on white."""
    w, h = before.size
    white = Image.new('RGB', (w, h), 'white')
    red = Image.new('RGB', (w, h), (220, 40, 40))
    blue = Image.new('RGB', (w, h), (40, 70, 220))
    both = Image.new('RGB', (w, h), (60, 20, 80))
    img = Image.composite(red, white, before)
    img = Image.composite(blue, img, after)
    common = ImageChops.multiply(before, after)
    return Image.composite(both, img, common)


def main(src_dir, ink_dir, out_dir):
    os.makedirs(os.path.join(out_dir, 'icons'), exist_ok=True)
    os.makedirs(os.path.join(out_dir, 'compare'), exist_ok=True)
    rows = []
    ok_all = True
    for name in sorted(f[:-4] for f in os.listdir(src_dir) if f.endswith('.svg')):
        src = open(os.path.join(src_dir, name + '.svg')).read()
        ink_path = os.path.join(ink_dir, name + '.svg')
        if not os.path.exists(ink_path):
            rows.append((name, '', 'no Inkscape output', '', '', False))
            ok_all = False
            continue
        icon, problems = normalize(open(ink_path).read())
        stripped = sanitize(icon)
        open(os.path.join(out_dir, 'icons', name + '.svg'), 'w').write(icon)
        raw = render(sanitize_apply(src), 24)
        raw_mean = sum(i * n for i, n in enumerate(ImageChops.difference(render(src, 24), raw).histogram())) / (255.0 * 24 * 24)
        diffs = []
        tiles = []
        for size in SIZES:
            a = render(src, size)
            b = render(icon, size)
            d = ImageChops.difference(a, b)
            hist = d.histogram()
            mean = sum(i * n for i, n in enumerate(hist)) / (255.0 * size * size)
            share = sum(hist[64:]) / float(size * size)
            diffs.append((size, mean, share))
            if size in (24, 192):
                scale = 8 if size == 24 else 1
                big = lambda im: im.resize((size * scale, size * scale), Image.NEAREST)
                tiles.append([big(Image.merge('RGB', [ImageChops.invert(a)] * 3)),
                              big(Image.merge('RGB', [ImageChops.invert(b)] * 3)),
                              big(overlay(a, b))])
        sheet = Image.new('RGB', (192 * 3 + 40, 192 * 2 + 30), 'white')
        for r, row in enumerate(tiles):
            for c, tile in enumerate(row):
                sheet.paste(tile, (c * 202, r * 202))
        sheet.save(os.path.join(out_dir, 'compare', name + '.png'))
        ok = not problems and not stripped and all(m <= MAX_MEAN and s <= MAX_PIXEL for _, m, s in diffs)
        ok_all = ok_all and ok
        rows.append((
            name,
            '%.1f %%' % (raw_mean * 100),
            '; '.join(problems + ['stripped ' + x for x in stripped]) or 'passes the 7.1 sanitizer unchanged',
            ' / '.join('%dpx %.3f%%' % (sz, m * 100) for sz, m, _ in diffs),
            ' / '.join('%dpx %.3f%%' % (sz, s * 100) for sz, _, s in diffs),
            ok,
        ))

    with open(os.path.join(out_dir, 'report.md'), 'w') as f:
        f.write('# Icons: stroke → filled outlines → SVG Icon API (WordPress 7.1)\n\n')
        f.write('Before = the site icon (stroke 1.5, round caps/joins). After = Inkscape “Stroke to Path”, '
                'normalized to `<path fill d>`. Overlay: red — only before, blue — only after, dark — both.\n\n')
        f.write('Thresholds: mean difference ≤ %.1f %%, pixels differing by > 25 %% ≤ %.1f %%.\n\n' % (MAX_MEAN * 100, MAX_PIXEL * 100))
        f.write('“As is” = the original stroke icon passed through the 7.1 sanitizer without conversion '
                '(stroke attributes and <circle>/<rect> removed), mean difference at 24 px.\n\n')
        f.write('| Icon | As is | Converted: registry | Mean difference | Pixels > 25 % | Result |\n|---|---|---|---|---|---|\n')
        for name, raw, reg, mean, share, ok in rows:
            f.write('| %s | %s | %s | %s | %s | %s |\n' % (name, raw, reg, mean, share, 'identical' if ok else '**differs**'))
        f.write('\nVerdict: %s\n' % ('all icons pass' if ok_all else 'NOT all icons pass'))
    json.dump({'ok': ok_all, 'icons': [r[0] for r in rows if r[5]]}, open(os.path.join(out_dir, 'result.json'), 'w'))
    print(open(os.path.join(out_dir, 'report.md')).read())
    return 0 if ok_all else 1


if __name__ == '__main__':
    sys.exit(main(*sys.argv[1:4]))
