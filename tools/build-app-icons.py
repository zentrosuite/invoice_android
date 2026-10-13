#!/usr/bin/env python3
"""
Build the app icon and launch image from the Zentro logo master.

    python3 tools/build-app-icons.py "/home/lws/Downloads/Zentro Brandings/Logo Draft.png"

The master is a horizontal lockup (mark left, "Zentro" right) on white, and raster only — there
is no vector source. So nothing is redrawn: the mark and the wordmark are found by their pixels,
cut out exactly, and stacked (mark on top, wordmark beneath) as the app asked for.

Writes public/icon.png (1024×1024, what NativePHP turns into the Android launcher icons) and
public/splash.png + public/splash-dark.png (the static launch screen).
"""
import sys
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
master = Image.open(sys.argv[1]).convert('RGBA')
W, H = master.size

# "Ink" = clearly darker than the white background.
gray = master.convert('L')
ink = gray.point(lambda v: 255 if v < 160 else 0)

def bbox_columns(x0, x1):
    return ink.crop((x0, 0, x1, H)).getbbox()

# Columns holding any ink, then the widest empty run between them: the gap that separates the
# mark from the wordmark.
cols = [ink.crop((x, 0, x + 1, H)).getbbox() is not None for x in range(W)]
runs, start = [], None
for x, filled in enumerate(cols + [False]):
    if filled and start is None:
        start = x
    if not filled and start is not None:
        runs.append((start, x)); start = None
gaps = [(runs[i + 1][0] - runs[i][1], runs[i][1], runs[i + 1][0]) for i in range(len(runs) - 1)]
_, gap_left, gap_right = max(gaps)

def cut(x0, x1):
    b = bbox_columns(x0, x1)
    box = (x0 + b[0], b[1], x0 + b[2], b[3])
    piece = master.crop(box)
    # White → transparent, keeping the anti-aliased edge as partial alpha (un-matted to navy).
    px = piece.load()
    for y in range(piece.height):
        for x in range(piece.width):
            r, g, bl, a = px[x, y]
            lum = (r + g + bl) / 3
            alpha = max(0, min(255, int((255 - lum) * 255 / (255 - 20))))
            px[x, y] = (17, 22, 51, min(a, alpha))  # brand navy #111633
    return piece

mark = cut(0, gap_left + 1)
word = cut(gap_right - 1, W)
print('mark', mark.size, 'wordmark', word.size)

def stacked(width_px, word_ratio=0.95, gap_ratio=0.16, color=None):
    """Mark above wordmark; the wordmark scaled to `word_ratio` of the mark's width."""
    m = mark.copy()
    w = word.resize((int(m.width * word_ratio), int(word.height * m.width * word_ratio / word.width)), Image.LANCZOS)
    gap = int(m.height * gap_ratio)
    canvas = Image.new('RGBA', (max(m.width, w.width), m.height + gap + w.height), (0, 0, 0, 0))
    canvas.alpha_composite(m, ((canvas.width - m.width) // 2, 0))
    canvas.alpha_composite(w, ((canvas.width - w.width) // 2, m.height + gap))
    if color:
        r, g, b = color
        alpha = canvas.getchannel('A')
        canvas = Image.new('RGBA', canvas.size, (r, g, b, 255))
        canvas.putalpha(alpha)
    scale = width_px / canvas.width
    return canvas.resize((width_px, int(canvas.height * scale)), Image.LANCZOS)

def place(size, art, bg):
    out = Image.new('RGBA', size, bg)
    out.alpha_composite(art, ((size[0] - art.width) // 2, (size[1] - art.height) // 2))
    return out

# Launcher icon: Android masks it (circle/squircle) and keeps only the middle ~66% safe, so the
# lockup sits inside that zone on white.
icon = place((1024, 1024), stacked(560), (255, 255, 255, 255))
icon.convert('RGB').save(ROOT / 'public' / 'icon.png')

# Launch screen: light and dark (white lockup on navy) variants.
splash = place((1242, 2688), stacked(620), (255, 255, 255, 255))
splash.convert('RGB').save(ROOT / 'public' / 'splash.png')
splash_dark = place((1242, 2688), stacked(620, color=(255, 255, 255)), (17, 22, 51, 255))
splash_dark.convert('RGB').save(ROOT / 'public' / 'splash-dark.png')
print('wrote public/icon.png, public/splash.png, public/splash-dark.png')
