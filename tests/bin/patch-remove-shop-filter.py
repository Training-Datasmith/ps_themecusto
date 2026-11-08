#!/usr/bin/env python3
from pathlib import Path

p = Path('classes/ThemeCustoRequests.php')
text = p.read_text()
old = (
    "WHERE ms.id_module = ' . (int) $moduleId . '\n"
    "                        AND ms.id_shop = ' . (int) Context::getContext()->shop->id;"
)
new = "WHERE ms.id_module = ' . (int) $moduleId;"
if old not in text:
    raise SystemExit('shop-filter pattern missing')
p.write_text(text.replace(old, new, 1))
