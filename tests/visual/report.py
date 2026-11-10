"""Отчёт workflow «Скриншоты»: report.md и картинки отличающихся страниц.

usage: python3 report.py <shots_dir> <out_dir> <before> <after> <run_id>
Читает <shots_dir>/report.json (JSON-отчёт Playwright второго прогона) и копирует
expected/actual/diff только для страниц, которые отличаются от эталона.
"""
import json
import os
import shutil
import sys

shots, out, before, after, run_id = sys.argv[1:6]
os.makedirs(out, exist_ok=True)


def walk(suite):
    for spec in suite.get("specs", []):
        for t in spec.get("tests", []):
            yield spec["title"], t
    for s in suite.get("suites", []):
        yield from walk(s)


rows = []
try:
    data = json.load(open(os.path.join(shots, "report.json"), encoding="utf-8"))
except (OSError, ValueError) as e:
    data = {"suites": []}
    rows.append(("—", "—", "ошибка", f"нет отчёта Playwright: {e}"))

for title, t in (x for s in data["suites"] for x in walk(s)):
    proj = t.get("projectName", "")
    res = (t.get("results") or [{}])[-1]
    status = res.get("status", "?")
    if status in ("passed", "skipped"):
        rows.append((title, proj, "совпадает" if status == "passed" else "пропущено", ""))
        continue
    msg = ((res.get("error") or {}).get("message") or "").strip().splitlines()
    msg = next((m for m in msg if m.strip()), "")
    kind = "нет эталона" if "doesn't exist" in msg or "snapshot" in msg and "missing" in msg else "отличается"
    dest = os.path.join(out, proj, title)
    copied = []
    for a in res.get("attachments", []):
        p = a.get("path") or ""
        if p.endswith(".png") and os.path.exists(p):
            os.makedirs(dest, exist_ok=True)
            name = os.path.basename(p)
            for key in ("expected", "actual", "diff"):
                if key in name:
                    name = key + ".png"
            shutil.copy(p, os.path.join(dest, name))
            copied.append(name)
    pct = ""
    for line in ((res.get("error") or {}).get("message") or "").splitlines():
        if "pixels" in line and "ratio" in line:
            pct = line.strip()
            break
    rows.append((title, proj, kind, pct or msg[:160]))

# Образцы: эталон главной и «Контактов» — чтобы было видно, что снимки не пустые
for proj in ("pc", "phone"):
    for name in ("home", "page-kontakty"):
        src = os.path.join(shots, "baseline", proj, name + ".png")
        if os.path.exists(src):
            os.makedirs(os.path.join(out, "sample", proj), exist_ok=True)
            shutil.copy(src, os.path.join(out, "sample", proj, name + ".png"))

diff = [r for r in rows if r[2] not in ("совпадает", "пропущено")]
lines = [
    f"# Скриншоты: `{before}` → `{after}`",
    "",
    f"Прогон [{run_id}](https://github.com/{os.environ.get('GITHUB_REPOSITORY', '')}/actions/runs/{run_id}). Страниц × экранов: {len(rows)}, "
    f"совпадают: {len(rows) - len(diff)}, отличаются: {len(diff)}.",
    "",
    "Картинки — в папках `<экран>/<страница>/`: expected (до), actual (после), diff (отличия красным).",
    "",
    "| Страница | Экран | Результат | Подробности |",
    "|---|---|---|---|",
]
for r in sorted(rows, key=lambda r: (r[2] == "совпадает", r[0], r[1])):
    lines.append("| " + " | ".join(x.replace("|", "\\|") for x in r) + " |")
open(os.path.join(out, "report.md"), "w", encoding="utf-8").write("\n".join(lines) + "\n")
print("\n".join(lines[:4]))
