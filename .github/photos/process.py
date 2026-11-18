"""Подготовка фото для «Мемориал → Обработка фото» (запускает .github/workflows/photos.yml).

То, чего не умеет хостинг (там только GD):
  - фон: вокруг товара всё прозрачное (открытая модель isnet-general-use, rembg — MIT / Apache-2.0);
  - профиль цвета: P3, Adobe RGB, CMYK → sRGB (Pillow ImageCms, LittleCMS).
Результат — PNG в uploads/memorial-photos/stage/<id>-<ключ>.png; дальше сайт сам делает обрезку, цвет и логотип.
Фото уже в sRGB и без замены фона — только отметка «проверено» (agent.php?do=mark), файл не нужен.
Образцы для предпросмотра — uploads/memorial-photos/preview/<id>.png.

Аргументы: путь к ответу agent.php?do=list (JSON), папка для готовых файлов, режим (process | preview).
Итог — out/result.json: какие файлы положить и какие фото отметить.
"""
import io
import json
import os
import sys

from PIL import Image, ImageCms, ImageOps

src_list, out_dir, mode = sys.argv[1], sys.argv[2], sys.argv[3]
data = json.load(open(src_list, encoding='utf-8'))
os.makedirs(out_dir, exist_ok=True)
SRGB = ImageCms.createProfile('sRGB')
session = None


def cutout(im):
    """Предмет без фона (RGBA)."""
    global session
    from rembg import new_session, remove
    if session is None:
        session = new_session('isnet-general-use')
    return remove(im, session=session, post_process_mask=True)


def to_srgb(im):
    """Перевод в sRGB по встроенному профилю. Возвращает (картинка, был_ли_перевод)."""
    icc = im.info.get('icc_profile')
    if im.mode == 'CMYK':
        im = im.convert('RGB') if not icc else ImageCms.profileToProfile(im, ImageCms.ImageCmsProfile(io.BytesIO(icc)), SRGB, outputMode='RGB')
        return im, True
    if not icc:
        return im.convert('RGBA' if 'A' in im.getbands() else 'RGB'), False
    prof = ImageCms.ImageCmsProfile(io.BytesIO(icc))
    name = (ImageCms.getProfileDescription(prof) or '').lower()
    if 'srgb' in name:
        return im.convert('RGBA' if 'A' in im.getbands() else 'RGB'), False
    mode_out = 'RGBA' if 'A' in im.getbands() else 'RGB'
    return ImageCms.profileToProfile(im.convert(mode_out), prof, SRGB, outputMode=mode_out), True


result = {'files': [], 'ok': [], 'errors': []}
todo = data['preview'] if mode == 'preview' else data['items']
for it in todo:
    path = os.path.join('in', it['file'])
    try:
        im = ImageOps.exif_transpose(Image.open(path))
        im, changed = to_srgb(im)
        need_bg = mode == 'preview' or it.get('bg')
        if not need_bg and not changed:
            result['ok'].append({'id': it['id'], 'key': it['key']})
            continue
        if need_bg:
            im = cutout(im.convert('RGB'))
        name = f"{it['id']}.png" if mode == 'preview' else f"{it['id']}-{it['key']}.png"
        # Не больше 2560 px по длинной стороне, как у WordPress.
        im.thumbnail((2560, 2560))
        im.save(os.path.join(out_dir, name), optimize=True)
        result['files'].append(name)
    except Exception as e:  # noqa: BLE001
        result['errors'].append({'id': it.get('id'), 'error': str(e)})

json.dump(result, open('out/result.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(json.dumps({k: (len(v) if isinstance(v, list) else v) for k, v in result.items()}), result['errors'][:5])
