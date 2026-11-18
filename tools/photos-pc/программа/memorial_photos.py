"""«Мемориал» — обработка фото на компьютере, без интернета и без GitHub Actions.

Те же правила, что у экрана «Мемориал → Обработка фото» на сайте (pages/photo-tools.php):
цвет (автоуровни, баланс белого, яркость, контраст, теплота, насыщенность, резкость), единый фон товаров
(открытая модель isnet-general-use, rembg), одинаковые пропорции и обрезка по предмету, логотип в правом нижнем
углу, удаление EXIF, перевод в sRGB, отчёт о плохих фото. Каждая функция включается в «настройки.ini».

Папки (рядом с файлами .bat):
  Фото\\<папка>\\...   — исходные фото; не меняются никогда.
  Готово\\<папка>\\... — обработанные фото (повторный запуск делает только новые и изменённые).
  Предпросмотр\\       — «до / после» для нескольких фото каждой папки, открывается в браузере.

Запуск: python memorial_photos.py <папка комплекта> [--preview]
"""
import configparser
import hashlib
import html
import io
import json
import os
import sys
import time
import traceback
import webbrowser

import numpy as np
from PIL import Image, ImageCms, ImageOps

VERSION = '1'
EXTS = ('.jpg', '.jpeg', '.png', '.webp')
HERE = os.path.dirname(os.path.abspath(__file__))


# ---------- Настройки ----------

def read_settings(root):
    cfg = configparser.ConfigParser(inline_comment_prefixes=(';', '#'))
    cfg.optionxform = str  # названия папок — как написаны
    with open(os.path.join(root, 'настройки.ini'), encoding='utf-8-sig') as f:
        cfg.read_file(f)

    def on(sec, key, default='0'):
        return cfg.get(sec, key, fallback=default).strip().lower() in ('1', 'да', 'yes', 'on', 'вкл')

    def num(sec, key, default):
        try:
            return int(str(cfg.get(sec, key, fallback=default)).strip().replace('%', '').replace('+', ''))
        except ValueError:
            return int(default)

    folders = {}
    if cfg.has_section('Папки'):
        for name, val in cfg.items('Папки'):
            parts = [p.strip().lower() for p in val.split(',')]
            folders[name.lower()] = {
                'kind': 'product' if 'товар' in parts else 'work',
                'bg': 'фон' in parts,
            }
    tint = cfg.get('Логотип', 'цвет', fallback='белый').strip().lower()
    return {
        'on': on('Обработка', 'включено', '1'),
        'folders': folders,
        'color': {
            'on': on('Цвет', 'включено', '1'),
            'levels': on('Цвет', 'автоуровни', '1'),
            'wb': num('Цвет', 'баланс_белого', 50),
            'bright': num('Цвет', 'яркость', 0),
            'contrast': num('Цвет', 'контраст', 5),
            'warmth': num('Цвет', 'теплота', 5),
            'sat': num('Цвет', 'насыщенность', 0),
            'sharp': num('Цвет', 'резкость', 25),
        },
        'bg': {
            'on': on('Фон', 'включено', '1'),
            'color': cfg.get('Фон', 'цвет', fallback='#fbfaf6').strip(),
        },
        'crop': {
            'on': on('Обрезка', 'включено', '1'),
            'trim': on('Обрезка', 'по_предмету', '1'),
            'product': cfg.get('Обрезка', 'товары', fallback='3:4').strip(),
            'work': cfg.get('Обрезка', 'работы', fallback='').strip(),
            'pad': num('Обрезка', 'поля', 6),
        },
        'logo': {
            'on': on('Логотип', 'включено', '1'),
            'tint': 'site' if 'сайт' in tint else 'white',
            'text': on('Логотип', 'название', '1'),
            'plate': on('Логотип', 'подложка', '0'),
            'file': cfg.get('Логотип', 'свой_файл', fallback='').strip().strip('"'),
            'size': num('Логотип', 'ширина', 22),
            'opacity': num('Логотип', 'непрозрачность', 70),
            'margin': num('Логотип', 'отступ', 3),
            'min': num('Логотип', 'не_ставить_уже', 600),
            'products': on('Логотип', 'на_товары', '1'),
            'works': on('Логотип', 'на_работы', '1'),
        },
        'clean': {
            'exif': on('Очистка', 'удалять_exif', '1'),
            'srgb': on('Очистка', 'перевод_в_srgb', '1'),
        },
        'save': {
            'format': cfg.get('Сохранение', 'формат', fallback='как было').strip().lower(),
            'quality': num('Сохранение', 'качество', 86),
            'max': num('Сохранение', 'наибольшая_сторона', 2560),
        },
        'report': on('Отчёт', 'включено', '1'),
    }


def hex_rgb(value):
    v = value.lstrip('#')
    if len(v) != 6:
        v = 'fbfaf6'
    return tuple(int(v[i:i + 2], 16) for i in (0, 2, 4))


def recipe(s, folder):
    """Что делать с фото этой папки — как memorial_plugin_photo_recipe()."""
    f = s['folders'].get(folder.lower(), s['folders'].get('остальное', {'kind': 'work', 'bg': False}))
    kind = f['kind']
    r = {'kind': kind}
    if s['color']['on']:
        r['color'] = s['color']
    if kind == 'product' and s['bg']['on'] and f['bg']:
        r['bg'] = hex_rgb(s['bg']['color'])
    if s['crop']['on']:
        ratio = s['crop']['product'] if kind == 'product' else s['crop']['work']
        trim = kind == 'product' and s['crop']['trim']
        if ratio or trim:
            r['crop'] = {'ratio': ratio, 'trim': trim, 'pad': s['crop']['pad']}
    lg = s['logo']
    if lg['on'] and (lg['products'] if kind == 'product' else lg['works']):
        r['logo'] = lg
    if s['clean']['srgb']:
        r['srgb'] = True
    if s['clean']['exif']:
        r['exif'] = True
    return r


# ---------- Обработка (те же формулы, что на сайте) ----------

def sample(im):
    """Копия не больше 200 px — по ней считается статистика."""
    w, h = im.size
    k = min(1, 200 / max(w, h))
    return im.resize((max(1, round(w * k)), max(1, round(h * k))), Image.BILINEAR)


def luts(smp, c):
    a = np.asarray(smp.convert('RGBA'))
    px = a[a[:, :, 3] > 54][:, :3].astype(np.int64)  # прозрачное вокруг товара не считается
    n = max(1, len(px))
    lo, hi = [0, 0, 0], [255, 255, 255]
    if c.get('levels'):
        for ch in range(3):
            hist = np.bincount(px[:, ch], minlength=256) if len(px) else np.zeros(256)
            cs = np.cumsum(hist)
            lo[ch] = int(np.argmax(cs > n * 0.005))
            cs2 = np.cumsum(hist[::-1])
            hi[ch] = 255 - int(np.argmax(cs2 > n * 0.005))
            if hi[ch] - lo[ch] < 32:  # почти один тон (ровный фон) — не растягивать
                lo[ch], hi[ch] = 0, 255
    mean = []
    for ch in range(3):
        m = ((px[:, ch] - lo[ch]) * 255 / (hi[ch] - lo[ch])).sum() / n if len(px) else 1
        mean.append(max(1, m))
    grey = sum(mean) / 3
    wb = c.get('wb', 0) / 100
    b = c.get('bright', 0) * 1.28
    k = 1 + c.get('contrast', 0) / 100
    warm = c.get('warmth', 0) * 0.8
    i = np.arange(256, dtype=np.float64)
    out = []
    for ch in range(3):
        gain = 1 + (grey / mean[ch] - 1) * wb
        v = (i - lo[ch]) * 255 / (hi[ch] - lo[ch])
        v = v * gain + b
        v = (v - 128) * k + 128
        v += warm if ch == 0 else (-warm if ch == 2 else 0)
        out.append(np.clip(np.floor(v + 0.5), 0, 255).astype(np.uint8))
    return out


def color(im, c):
    """Цветокоррекция: таблицы, насыщенность, резкость. Возвращает новую картинку того же режима."""
    mode = im.mode
    a = np.asarray(im.convert('RGBA')).copy()
    lr, lg, lb = luts(sample(im), c)
    rgb = np.stack([lr[a[:, :, 0]], lg[a[:, :, 1]], lb[a[:, :, 2]]], axis=2).astype(np.float64)
    k = 1 + c.get('sat', 0) / 100
    if k != 1:
        lum = (0.299 * rgb[:, :, 0] + 0.587 * rgb[:, :, 1] + 0.114 * rgb[:, :, 2])[:, :, None]
        rgb = np.clip(np.floor(lum + (rgb - lum) * k + 0.5), 0, 255)
    s = c.get('sharp', 0) / 100 * 0.5
    if s > 0:
        p = np.pad(rgb, ((1, 1), (1, 1), (0, 0)), mode='edge')
        rgb = (1 + 4 * s) * p[1:-1, 1:-1] - s * (p[:-2, 1:-1] + p[2:, 1:-1] + p[1:-1, :-2] + p[1:-1, 2:])
        rgb = np.clip(np.floor(rgb + 0.5), 0, 255)
    a[:, :, :3] = rgb.astype(np.uint8)
    out = Image.fromarray(a, 'RGBA')
    return out if mode == 'RGBA' else out.convert('RGB')


def border(smp):
    """Цвет фона фото — медиана краёв маленькой копии."""
    a = np.asarray(smp.convert('RGB'))
    edge = np.concatenate([a[0], a[-1], a[:, 0], a[:, -1]])
    return tuple(int(np.sort(edge[:, ch])[len(edge) // 2]) for ch in range(3))


def frame(im, crop, bg, fit):
    """Рамка: [ширина, высота, x, y, w, h источника, x, y на холсте] или None — как memorial_plugin_photo_frame()."""
    w, h = im.size
    bx, by, bw, bh = 0, 0, w, h
    if crop.get('trim'):
        s = np.asarray(sample(im).convert('RGBA')).astype(np.int64)
        sh, sw = s.shape[:2]
        d = np.max(np.abs(s[:, :, :3] - np.array(bg)), axis=2)
        mask = (s[:, :, 3] > 54) & (d > 28)
        ys, xs = np.nonzero(mask)
        if len(xs):
            x0, x1, y0, y1 = xs.min(), xs.max(), ys.min(), ys.max()
            if (x1 - x0 + 1) * (y1 - y0 + 1) >= 0.05 * sw * sh:  # предмет хотя бы 5 % фото
                k = w / sw
                bx, by = int(np.floor(x0 * k)), int(np.floor(y0 * k))
                bw = int(min(w - bx, np.ceil((x1 - x0 + 1) * k)))
                bh = int(min(h - by, np.ceil((y1 - y0 + 1) * k)))
                p = round(max(bw, bh) * crop['pad'] / 100)
                bx, by, bw, bh = bx - p, by - p, bw + 2 * p, bh + 2 * p
    ratio = crop.get('ratio') or ''
    if ':' in ratio:
        a, b = ratio.split(':')
        r = int(a) / int(b)
        if not crop.get('trim'):
            if bw / bh > r:
                nw = round(bh * r)
                bx += (bw - nw) // 2
                bw = nw
            else:
                nh = round(bw / r)
                by += (bh - nh) // 2
                bh = nh
        elif bw / bh > r:
            nh = round(bw / r)
            by -= (nh - bh) // 2
            bh = nh
        else:
            nw = round(bh * r)
            bx -= (nw - bw) // 2
            bw = nw
    if fit and (bx < 0 or by < 0 or bx + bw > w or by + bh > h):
        # свой фон (стена, пол): рамка остаётся внутри фото, цветных полей нет
        if bw > w or bh > h:
            k = min(w / bw, h / bh)
            nw, nh = int(min(w, np.floor(bw * k))), int(min(h, np.floor(bh * k)))
            bx, by = int(np.floor(bx + (bw - nw) / 2)), int(np.floor(by + (bh - nh) / 2))
            bw, bh = nw, nh
        bx = max(0, min(bx, w - bw))
        by = max(0, min(by, h - bh))
    if bx == 0 and by == 0 and bw == w and bh == h:
        return None
    sx, sy = max(0, bx), max(0, by)
    ex, ey = min(w, bx + bw), min(h, by + bh)
    return bw, bh, sx, sy, ex - sx, ey - sy, sx - bx, sy - by


_logo_cache = {}


def logo_image(lg, root):
    own = lg['file']
    path = own if own and os.path.isabs(own) else os.path.join(root, own) if own else os.path.join(HERE, f"logo-{lg['tint']}-{1 if lg['text'] else 0}{'-p' if lg['plate'] else ''}.png")
    if path not in _logo_cache:
        _logo_cache[path] = Image.open(path).convert('RGBA')
    return _logo_cache[path]


def put_logo(im, lg, root):
    src = logo_image(lg, root)
    w, h = im.size
    lw = max(1, round(w * lg['size'] / 100))
    lh = max(1, round(src.height * lw / src.width))
    m = round(w * lg['margin'] / 100)
    one = src.resize((lw, lh), Image.LANCZOS)
    alpha = one.getchannel('A').point(lambda v: round(v * max(0, min(100, lg['opacity'])) / 100))
    one.putalpha(alpha)
    base = im.convert('RGBA')
    base.alpha_composite(one, (w - lw - m, h - lh - m))
    return base if im.mode == 'RGBA' else base.convert('RGB')


SRGB = ImageCms.createProfile('sRGB')


def to_srgb(im):
    icc = im.info.get('icc_profile')
    alpha = 'A' in im.getbands()
    mode = 'RGBA' if alpha else 'RGB'
    if im.mode == 'CMYK':
        if icc:
            return ImageCms.profileToProfile(im, ImageCms.ImageCmsProfile(io.BytesIO(icc)), SRGB, outputMode='RGB')
        return im.convert('RGB')
    if not icc:
        return im.convert(mode)
    prof = ImageCms.ImageCmsProfile(io.BytesIO(icc))
    if 'srgb' in (ImageCms.getProfileDescription(prof) or '').lower():
        return im.convert(mode)
    return ImageCms.profileToProfile(im.convert(mode), prof, SRGB, outputMode=mode)


_session = None


def cutout(im):
    """Предмет без фона (RGBA) — открытая модель isnet-general-use."""
    global _session
    os.environ.setdefault('U2NET_HOME', os.path.join(HERE, 'модель'))
    from rembg import new_session, remove
    if _session is None:
        print('  загрузка модели фона (первый раз скачивается ~180 МБ)…', flush=True)
        _session = new_session('isnet-general-use')
    return remove(im.convert('RGB'), session=_session, post_process_mask=True)


def process(path, r, s, root):
    """Одно фото: возвращает (картинка, icc или None, exif или None)."""
    src = Image.open(path)
    icc = src.info.get('icc_profile')
    exif = src.getexif()
    im = ImageOps.exif_transpose(src)
    if r.get('srgb'):
        im = to_srgb(im)
        icc = None
    else:
        im = im.convert('RGBA' if 'A' in im.getbands() else 'RGB')
    has_bg = 'bg' in r
    if has_bg:
        im = cutout(im)
        bg = r['bg']
    else:
        bg = border(sample(im))
    if has_bg and 'color' in r:
        im = color(im, r['color'])  # с новым фоном — сначала цвет предмета
    f = None
    if 'crop' in r:
        f = frame(im, r['crop'], bg, not has_bg)
        if f:
            canvas = Image.new('RGBA', (f[0], f[1]), bg + (255,))
            part = im.convert('RGBA').crop((f[2], f[3], f[2] + f[4], f[3] + f[5]))
            canvas.alpha_composite(part, (f[6], f[7]))
            im = canvas if im.mode == 'RGBA' and not has_bg else canvas.convert('RGB')
    if has_bg and not f:
        canvas = Image.new('RGBA', im.size, bg + (255,))
        canvas.alpha_composite(im.convert('RGBA'))
        im = canvas.convert('RGB')
    if 'color' in r and not has_bg:
        im = color(im, r['color'])
    big = s['save']['max']
    if big and max(im.size) > big:
        im.thumbnail((big, big), Image.LANCZOS)
    if 'logo' in r and im.width >= r['logo']['min']:
        im = put_logo(im, r['logo'], root)
    if r.get('exif') or not exif:
        exif = None
    else:
        exif[0x0112] = 1  # фото уже повёрнуто
    return im, icc, exif


def out_name(rel, im, s, has_bg):
    base, ext = os.path.splitext(rel)
    fmt = s['save']['format']
    if 'jpg' in fmt or 'jpeg' in fmt:
        ext = '.jpg'
    elif 'webp' in fmt:
        ext = '.webp'
    elif ext.lower() == '.png' and has_bg:
        ext = '.jpg'  # PNG с новым фоном — прозрачности больше нет
    return base + ext


def save(im, path, icc, exif, quality):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    ext = os.path.splitext(path)[1].lower()
    kw = {}
    if icc:
        kw['icc_profile'] = icc
    if exif:
        kw['exif'] = exif
    if ext in ('.jpg', '.jpeg'):
        if im.mode == 'RGBA':
            flat = Image.new('RGB', im.size, (255, 255, 255))
            flat.paste(im, mask=im.getchannel('A'))
            im = flat
        im.save(path, 'JPEG', quality=quality, optimize=True, progressive=True, **kw)
    elif ext == '.webp':
        im.save(path, 'WEBP', quality=quality, method=5, **kw)
    else:
        im.save(path, 'PNG', optimize=True, **kw)


# ---------- Отчёт о плохих фото (как на сайте) ----------

def measure(im):
    smp = sample(im.convert('RGB'))
    a = np.asarray(smp).astype(np.float64)
    g = 0.299 * a[:, :, 0] + 0.587 * a[:, :, 1] + 0.114 * a[:, :, 2]
    lap = g[:-2, 1:-1] + g[2:, 1:-1] + g[1:-1, :-2] + g[1:-1, 2:] - 4 * g[1:-1, 1:-1]
    d = np.asarray(smp.resize((9, 8), Image.BILINEAR)).astype(np.int64)[:, :, 2]
    bits = ''.join('1' if d[y, x] > d[y, x + 1] else '0' for y in range(8) for x in range(8))
    return float(g.mean()), float(lap.var()) if lap.size else 0.0, bits


def report(files, root):
    rows = {}
    hashes = []
    for rel, path in files:
        try:
            im = ImageOps.exif_transpose(Image.open(path))
        except Exception:  # noqa: BLE001
            rows[rel] = ['не открывается']
            continue
        p = []
        if im.width < 1000:
            p.append(f'маленькое ({im.width} px в ширину, нужно от 1000)')
        luma, sharp, bits = measure(im)
        if luma < 70:
            p.append('тёмное')
        elif luma > 225:
            p.append('пересвеченное')
        if sharp < 40:
            p.append('размытое')
        for other, ob in hashes:
            if sum(x != y for x, y in zip(bits, ob)) <= 4:
                p.append('повтор: ' + other)
                rows.setdefault(other, []).append('повтор: ' + rel)
                break
        hashes.append((rel, bits))
        if p:
            rows.setdefault(rel, []).extend(p)
    return rows


# ---------- Предпросмотр ----------

PAGE = """<!doctype html><html lang="ru"><meta charset="utf-8"><title>Мемориал — предпросмотр</title>
<style>
body{{font:16px/1.5 system-ui,Segoe UI,Arial,sans-serif;background:#f4f2ec;color:#2a2c28;margin:0;padding:24px}}
h1{{font-weight:600;font-size:24px;margin:0 0 4px}} .note{{color:#55574f;margin:0 0 20px}}
.grid{{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:20px}}
figure{{margin:0;background:#fff;border:1px solid #e2ded2;border-radius:8px;padding:12px}}
figcaption{{font-size:15px;color:#55574f;margin-top:8px}}
.cmp{{position:relative;display:grid;place-items:center;background:#ece9e0;border-radius:6px;overflow:hidden;aspect-ratio:3/4}}
.cmp img{{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}}
.cmp .before{{clip-path:inset(0 calc(100% - var(--x,50%)) 0 0)}}
.cmp .line{{position:absolute;top:0;bottom:0;left:var(--x,50%);width:2px;background:#fff;box-shadow:0 0 3px #0008}}
.lbl{{position:absolute;top:8px;padding:2px 8px;border-radius:4px;background:#000a;color:#fff;font-size:14px}}
input[type=range]{{width:100%;margin-top:8px}}
.bad{{background:#fff;border:1px solid #e2ded2;border-radius:8px;padding:12px 16px;margin-top:24px}}
</style>
<h1>Предпросмотр</h1>
<p class="note">Двигайте ползунок: слева — как было, справа — после обработки. Настройки — в файле «настройки.ini»;
поменяйте и запустите «1 Предпросмотр» ещё раз. Исходные фото не меняются.</p>
<div class="grid">{items}</div>{bad}
<script>
document.querySelectorAll('figure').forEach(function(f){{var c=f.querySelector('.cmp'),r=f.querySelector('input');
r.addEventListener('input',function(){{c.style.setProperty('--x',r.value+'%')}})}});
</script></html>"""


def preview_item(name, before, after, note):
    return (f'<figure><div class="cmp"><img src="{before}" class="before" alt=""><img src="{after}" alt="" '
            f'style="clip-path:inset(0 0 0 var(--x,50%))"><span class="line"></span>'
            f'<span class="lbl" style="left:8px">До</span><span class="lbl" style="right:8px">После</span></div>'
            f'<input type="range" min="0" max="100" value="50" aria-label="Сравнить">'
            f'<figcaption>{html.escape(name)} — {html.escape(note)}</figcaption></figure>')


# ---------- Запуск ----------

def collect(src_dir):
    files = []
    for base, dirs, names in os.walk(src_dir):
        dirs.sort()
        for n in sorted(names):
            if n.lower().endswith(EXTS):
                full = os.path.join(base, n)
                files.append((os.path.relpath(full, src_dir), full))
    return files


def folder_of(rel):
    parts = rel.replace('\\', '/').split('/')
    return parts[0] if len(parts) > 1 else ''


def describe(r):
    out = []
    if 'bg' in r:
        out.append('новый фон')
    if 'color' in r:
        out.append('цвет')
    if 'crop' in r:
        out.append('рамка ' + (r['crop']['ratio'] or 'по предмету'))
    if 'logo' in r:
        out.append('логотип')
    return ', '.join(out) or 'без изменений'


def main():
    root = os.path.abspath(sys.argv[1]) if len(sys.argv) > 1 else os.path.dirname(HERE)
    preview = '--preview' in sys.argv
    s = read_settings(root)
    src_dir = os.path.join(root, 'Фото')
    out_dir = os.path.join(root, 'Готово')
    os.makedirs(src_dir, exist_ok=True)
    if not s['on']:
        print('Обработка выключена: в «настройки.ini» → [Обработка] включено = 0.')
        return 0
    files = collect(src_dir)
    if not files:
        print('В папке «Фото» нет фото. Положите их в подпапки (Венки, Гробы, Кресты, Оградки, Памятники, Работы…).')
        return 0

    if preview:
        pv = os.path.join(root, 'Предпросмотр')
        os.makedirs(pv, exist_ok=True)
        for old in os.listdir(pv):
            os.remove(os.path.join(pv, old))
        picked, seen = [], {}
        for rel, path in files:
            fo = folder_of(rel)
            if seen.get(fo, 0) < 3:
                seen[fo] = seen.get(fo, 0) + 1
                picked.append((rel, path))
        items = []
        for i, (rel, path) in enumerate(picked, 1):
            print(f'[{i}/{len(picked)}] {rel}', flush=True)
            r = recipe(s, folder_of(rel))
            try:
                before = ImageOps.exif_transpose(Image.open(path)).convert('RGB')
                after, _, _ = process(path, r, s, root)
                for im, name in ((before, f'{i}-before.jpg'), (after.convert('RGB'), f'{i}-after.jpg')):
                    im = im.copy()
                    im.thumbnail((1200, 1200))
                    im.save(os.path.join(pv, name), quality=88)
                items.append(preview_item(rel, f'{i}-before.jpg', f'{i}-after.jpg', describe(r)))
            except Exception as e:  # noqa: BLE001
                items.append(f'<figure><figcaption>{html.escape(rel)} — ошибка: {html.escape(str(e))}</figcaption></figure>')
        bad = ''
        if s['report']:
            rows = report(files, root)
            if rows:
                bad = ('<div class="bad"><h2>Плохие фото</h2><ul>' + ''.join(
                    f'<li>{html.escape(k)}: {html.escape(", ".join(v))}</li>' for k, v in rows.items()) + '</ul></div>')
        page = os.path.join(pv, 'предпросмотр.html')
        with open(page, 'w', encoding='utf-8') as f:
            f.write(PAGE.format(items=''.join(items), bad=bad))
        print('Готово: открываю предпросмотр в браузере.')
        webbrowser.open('file:///' + page.replace('\\', '/'))
        return 0

    state_file = os.path.join(out_dir, '.состояние.json')
    try:
        state = json.load(open(state_file, encoding='utf-8'))
    except Exception:  # noqa: BLE001
        state = {}
    done = skipped = 0
    errors = []
    start = time.time()
    for i, (rel, path) in enumerate(files, 1):
        r = recipe(s, folder_of(rel))
        st = os.stat(path)
        logo_mark = ''
        if 'logo' in r:
            try:
                lp = logo_image(r['logo'], root)
                logo_mark = hashlib.md5(lp.tobytes()).hexdigest()[:8]
            except Exception:  # noqa: BLE001
                logo_mark = 'нет'
        key = hashlib.md5(json.dumps([VERSION, st.st_size, int(st.st_mtime), r, s['save'], logo_mark],
                                     ensure_ascii=False, sort_keys=True, default=str).encode()).hexdigest()
        prev = state.get(rel)
        if prev and prev.get('key') == key and os.path.exists(os.path.join(out_dir, prev.get('out', ''))):
            skipped += 1
            continue
        left = (time.time() - start) / max(1, done) * (len(files) - i) if done else 0
        print(f'[{i}/{len(files)}] {rel}' + (f'  (осталось ~{int(left // 60)} мин)' if left > 60 else ''), flush=True)
        try:
            im, icc, exif = process(path, r, s, root)
            name = out_name(rel, im, s, 'bg' in r)
            save(im, os.path.join(out_dir, name), icc, exif, s['save']['quality'])
            if prev and prev.get('out') and prev['out'] != name:
                old = os.path.join(out_dir, prev['out'])
                if os.path.exists(old):
                    os.remove(old)
            state[rel] = {'key': key, 'out': name}
            done += 1
        except Exception as e:  # noqa: BLE001
            errors.append(f'{rel}: {e}')
            traceback.print_exc()
        if done % 10 == 0:
            json.dump(state, open(state_file, 'w', encoding='utf-8'), ensure_ascii=False)
    os.makedirs(out_dir, exist_ok=True)
    json.dump(state, open(state_file, 'w', encoding='utf-8'), ensure_ascii=False)
    lines = [f'Обработано: {done}, без изменений: {skipped}, ошибок: {len(errors)}.']
    if errors:
        lines += ['', 'Ошибки:'] + errors
    if s['report']:
        rows = report(files, root)
        lines += ['', 'Плохие фото:' if rows else 'Плохих фото нет.']
        lines += [f'  {k}: {", ".join(v)}' for k, v in rows.items()]
    with open(os.path.join(out_dir, 'отчёт.txt'), 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines) + '\n')
    print('\n'.join(lines[:3]))
    print('Обработанные фото — в папке «Готово», подробности — «Готово\\отчёт.txt».')
    return 0


if __name__ == '__main__':
    sys.exit(main())
