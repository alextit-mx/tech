"""
Convierte la lista de precios (Excel: id | PRODUCTO | PRECIO | CENTRO | 24H) en el CSV que importa
`php artisan productos:importar`. Solo biblioteca estándar (sin pandas ni openpyxl).

Uso:  python3 herramientas/lista_precios.py LP_NOVIEMBRE.xlsx  [salida.csv]
Por defecto escribe importacion/<nombre>_importar.csv y muestra un resumen por categoría.
"""
import csv
import re
import sys
import zipfile
import xml.etree.ElementTree as ET
from collections import Counter
from pathlib import Path

NS = {"m": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
REL = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id"


def read_xlsx(path):
    """Filas de la primera hoja como listas de texto."""
    z = zipfile.ZipFile(path)
    shared = []
    if "xl/sharedStrings.xml" in z.namelist():
        for si in ET.fromstring(z.read("xl/sharedStrings.xml")).findall("m:si", NS):
            shared.append("".join(t.text or "" for t in si.iter("{%s}t" % NS["m"])))
    wb = ET.fromstring(z.read("xl/workbook.xml"))
    targets = {r.get("Id"): r.get("Target") for r in ET.fromstring(z.read("xl/_rels/workbook.xml.rels"))}
    sheet = wb.find("m:sheets", NS)[0]
    target = targets[sheet.get(REL)].lstrip("/")
    target = target if target.startswith("xl/") else "xl/" + target
    rows = []
    for row in ET.fromstring(z.read(target)).iter("{%s}row" % NS["m"]):
        vals = {}
        for c in row.findall("m:c", NS):
            col = 0
            for ch in re.match(r"[A-Z]+", c.get("r")).group(0):
                col = col * 26 + ord(ch) - 64
            v = c.find("m:v", NS)
            if c.get("t") == "s" and v is not None:
                vals[col - 1] = shared[int(v.text)]
            elif c.get("t") == "inlineStr":
                vals[col - 1] = "".join(x.text or "" for x in c.iter("{%s}t" % NS["m"]))
            else:
                vals[col - 1] = v.text if v is not None else ""
        if vals:
            rows.append([vals.get(i, "") for i in range(max(vals) + 1)])
    return rows


# Condición: prefijos que no son parte del nombre.
CONDITIONS = [
    (r"^(CAJA ABIERTA)\s+", "Caja abierta"),
    (r"^(REMATES?)\s+", "Remate"),
    (r"^(DESCONTINUAD[OA]|DESC)\s+", "Descontinuado"),
    (r"^(ARCH\d+)\s+", "Remate"),
]

# Categoría por tipo de producto (primera coincidencia gana). Slugs de la tienda; las nuevas las crea el importador.
RULES = [
    (r"^PC GAMER|^EQUIPO GAMER", "pcs-armadas"),
    (r"^(TARJETA DE VIDEO|TARJETA GRAFICA|TARJETA DE V\b)", "tarjetas-de-video"),
    (r"^(TARJETA DE RED|TARJETA WI-?FI|ADAPTADOR (USB )?(WI-?FI|INALAMBRICO|DE RED)|ADAPTADOR TPLINK|ROUTER|SWITCH|ACCESS POINT|REPETIDOR|EXTENSOR)", "redes"),
    (r"^(MOTHER|MB|TARJETA MADRE)\b", "tarjetas-madre"),
    (r"^PROCESADOR\b", "procesadores"),  # "CPU" en la lista es un equipo de escritorio, no un procesador.
    # SO-DIMM (laptop) no entra en una tarjeta madre de escritorio: va aparte y fuera del configurador.
    # La lista también escribe "SODDR4" o "Lap" (p. ej. "RAM Kingston Fury Impact SODDR4 Lap").
    (r"^(RAM|MEMORIA RAM)\b.*\b(SO-?DIMM|SO-?DDR\d?|LAPTOP|NOTEBOOK|LAP)\b", "memoria-laptop"),
    (r"^(RAM|MEMORIA RAM)\b", "memoria-ram"),
    # En la lista "Case …" siempre es gabinete para disco o SSD (Case ADATA EX500, Case Xcase M.2…); los de PC dicen "Gabinete".
    (r"^(MEMORIA (USB|MICRO|SD|FLASH)|USB\b|(SSD|DD|DISCO DURO) EXTERNO|ENCLOUSURE|GABINETE PARA DISCO|CASE\b)", "almacenamiento-externo-y-usb"),
    (r"^(SSD|DD|DISCO DURO|M\.2)\b", "almacenamiento"),
    (r"^FUENTE\b", "fuentes-de-poder"),
    (r"^(ENFRIAMIENTO|DISIPADOR)\b", "enfriamiento"),
    (r"^(VENTILADOR|PASTA|KIT (DE )?\d? ?VENTILADOR)", "ventiladores-y-pasta-termica"),
    (r"^(GABINETE|GABIENTE)\b", "gabinetes"),
    (r"^(MONITOR)\b", "monitores"),
    (r"^(MOUSEPAD|MOUSE PAD|ALFOMBRA)", "mousepads"),
    (r"^MOUSE\b", "mouse"),
    (r"^(KIT (DE )?TECLADO|TECLADO)", "teclados"),
    (r"^(DIADEMA|AUDIFONOS|AURICULARES)", "audifonos"),
    (r"^(SILLA|SILLON)", "sillas"),
    (r"^ESCRITORIO", "escritorios"),
    (r"^(CONTROL|VOLANTE|GAMEPAD|JOYSTICK)", "controles"),
    (r"^CONSOLA", "consolas"),
    (r"^(WEBCAM|CAPTURADORA|MICROFONO)", "webcams-y-streaming"),
    (r"^(BOCINA|BARRA DE SONIDO|BARRA)", "bocinas"),
    (r"^(TONER|TAMBOR|FUSOR|UNIDAD DE IMAGEN)", "toner"),
    (r"^(TINTA|CARTUCHO|BOTELLA)", "tintas"),
    (r"^(MULTIFUNCIONAL|IMPRESORA|MINIPRINTER|PLOTTER)", "impresoras"),
    (r"^(ESCANER|ESCANNER)", "escaneres"),
    (r"^(CINTA|BOBINA|ROTULADOR|ETIQUETA|PAPEL)", "cintas-y-etiquetas"),
    (r"^(NOBREAK|NO BREAK|REGULADOR|POWER BANK|BATERIA|CARGADOR|TIRA|SUPRESOR)", "energia-no-break-y-reguladores"),
    (r"^(CABLES?|ADAPTADOR|CONECTOR|CONVERTIDOR|DOCKING|DOKING|DIVISOR|JACK|EXTENSI[OÓ]N|CLAVIJA|HUB)", "cables-y-adaptadores"),
    (r"^(CAMARA|KIT DVR|DVR|NVR|VIDEOPORTERO|CERRADURA|SENSOR|DETECTOR|CANDADO|KIT \d+ CAMARAS)", "seguridad-y-videovigilancia"),
    (r"^LAPTOP", "laptops"),
    (r"^(TABLET|TABLETA)", "tablets"),
    (r"^(AIO|ALL IN ONE)", "all-in-one"),
    (r"^(MINI PC|MINI\b|BRIX|PC\b|CPU\b|SERVIDOR|EQUIPO)", "computadoras-de-escritorio"),
    (r"^(PROYECTOR|PANTALLA|SMART TV|TV\b|TVBOX|PRESENTADOR)", "proyectores-y-pantallas"),
    (r"^(LICENCIA|LIC\b|ANTIVIRUS|SOFTWARE)", "software-y-licencias"),
    (r"^(MOCHILA|FUNDA|MALETIN|BOLSA)", "mochilas-y-fundas"),
]
FALLBACK = "otros-accesorios"
# "KIT" y "BUNDLE" se clasifican por lo que traen.
KEYWORD_RULES = [
    (r"\bLAPTOP\b", "laptops"), (r"\b(MINI PC|NUC)\b", "computadoras-de-escritorio"), (r"\bAIO\b", "all-in-one"),
    (r"\bTECLADO\b", "teclados"), (r"\bMOUSE\b", "mouse"), (r"\bVENTILADOR", "ventiladores-y-pasta-termica"),
    (r"\b(DVR|CAMARA)", "seguridad-y-videovigilancia"), (r"\bRAM\b|\bDDR[45]\b", "memoria-ram"),
    (r"\b(DIADEMA|AUDIFONO)", "audifonos"), (r"\bBOCINA", "bocinas"), (r"\bTINTA", "tintas"),
]

BRANDS = """ACER ACTECK ADATA AMD AOC APC ASROCK ASUS BALAM BENQ BIOSTAR BROTHER CANON CORSAIR COUGAR CRUCIAL CYBERPOWER
DAHUA DELL DEEPCOOL DLINK D-LINK EPSON EVGA GAMDIAS GHIA GIGABYTE HIKVISION HP HUAWEI HYPERX HYTE INTEL KINGSTON KODAK
KOBLENZ KYOCERA LENOVO LEXAR LEXMARK LG LINKSYS LOGITECH MANHATTAN MICROSOFT MIKROTIK MSI NACEB NEXXT NINTENDO NZXT
OCELOT PATRIOT PHILIPS PNY RAZER REDRAGON RICOH SAMSUNG SANDISK SEAGATE SMARTBITT SONY STEREN TEAMGROUP TECHZONE
THERMALTAKE TOSHIBA TPLINK TP-LINK UBIQUITI VIEWSONIC VORAGO XEROX XPG XZEAL YEYIAN ZEBRA HISENSE TCL WESTERN XIAOMI
APPLE SMARTEK COMPLET ISB VICA FORZA KSTAR INTELLINET EZVIZ PERFECT LOOSAFE TOPSKY GAMEFACTOR GAME HIKSEMI KASPERSKY
ESET NORTON MCAFEE LIAN SEASONIC SUPERFLOWER ANTEC FRACTAL SPEEDLINK TRUST GETTTECH NAXA BLACKPCS JBL HAVIT
MARVO ZOTAC SAPPHIRE POWERCOLOR XFX PALIT PNY INNO3D ARCTIC NOCTUA BEQUIET SCYTHE TEROS ACERPREDATOR KLIPXTREME
KLIP EASY YEALINK POLY GRANDSTREAM CISCO ARUBA TENDA MERCUSYS CUDY QIAN WD""".split()
TWO_WORD_BRANDS = {"COOLER MASTER": "Cooler Master", "GAME FACTOR": "Game Factor", "PERFECT CHOICE": "Perfect Choice",
                   "SOLA BASIC": "Sola Basic", "WESTERN DIGITAL": "Western Digital", "LIAN LI": "Lian Li",
                   "BE QUIET": "be quiet!", "KLIP XTREME": "Klip Xtreme", "TRIPP LITE": "Tripp Lite", "SUPER FLOWER": "Super Flower"}
BRAND_CASE = {"TPLINK": "TP-Link", "TP-LINK": "TP-Link", "DLINK": "D-Link", "D-LINK": "D-Link", "WD": "Western Digital",
              "HYPERX": "HyperX", "XPG": "XPG", "AMD": "AMD", "MSI": "MSI", "HP": "HP", "LG": "LG", "AOC": "AOC", "APC": "APC",
              "ASUS": "ASUS", "PNY": "PNY", "ISB": "ISB", "TCL": "TCL", "JBL": "JBL", "XFX": "XFX", "EVGA": "EVGA",
              "NZXT": "NZXT", "ADATA": "ADATA", "HYTE": "HYTE", "ESET": "ESET", "BEQUIET": "be quiet!", "GAMEFACTOR": "Game Factor"}

KEEP_UPPER = {"SSD", "RAM", "USB", "HDMI", "RGB", "ARGB", "ATX", "MATX", "ITX", "GB", "TB", "MB", "PC", "AMD", "HP", "LED",
              "IPS", "VA", "TN", "FHD", "QHD", "UHD", "HD", "4K", "OLED", "WIFI", "NVME", "SATA", "PCIE", "DDR4", "DDR5",
              "GPU", "CPU", "AIO", "LCD", "DVR", "NVR", "POE", "VGA", "DP", "TV", "RTX", "GTX", "RX", "UPS", "ARC", "NFC",
              "AC", "AX", "LAN", "WAN", "MHZ", "HZ", "W", "VA", "ML", "XL", "XXL", "BT", "IR", "PTZ", "SD", "MSI", "LG",
              "TPLINK", "XPG", "NZXT", "ADATA", "HYTE", "ASUS", "AOC", "APC", "EVGA", "ROG", "TUF", "OEM", "PLUS", "RGB"}
LOWER = {"DE", "DEL", "PARA", "CON", "Y", "EL", "LA", "LOS", "LAS", "EN", "POR", "SIN", "A", "O", "AL", "E"}


def pretty(text):
    """Nombre legible: 'MOUSE LOGITECH G502 HERO NEGRO' -> 'Mouse Logitech G502 Hero Negro'."""
    out = []
    for i, w in enumerate(text.split()):
        if "-" in w and not any(ch.isdigit() for ch in w):
            out.append("-".join(p if p in KEEP_UPPER or len(p) <= 2 else p.capitalize() for p in w.split("-")))
        elif any(ch.isdigit() for ch in w) or w in KEEP_UPPER or len(w) <= 2 and w not in LOWER:
            out.append(w)
        elif w in LOWER and i > 0:
            out.append(w.lower())
        else:
            out.append(w.capitalize())
    return " ".join(out)


PLATFORMS = {"AMD", "INTEL", "NVIDIA"}


def brand_of(text):
    for two, canon in TWO_WORD_BRANDS.items():
        if re.search(r"\b" + two + r"\b", text):
            return canon
    found = [t for t in re.findall(r"[A-Z0-9\-]+", text) if t in BRANDS]
    # "Mother AMD A520 ASUS..." -> ASUS: la plataforma solo es marca si no hay otra.
    maker = next((t for t in found if t not in PLATFORMS), found[0] if found else "")
    return BRAND_CASE.get(maker, maker.capitalize()) if maker else ""


def warranty_of(text):
    """'... 12M de Garantia' -> (12, texto sin eso); 'sin Garantia' -> (None, texto sin eso)."""
    m = re.search(r"\s*(\d+)\s?M(?:ESES)? (?:DE )?GARANT[IÍ]A\b.*$", text)
    if m:
        return int(m.group(1)), text[: m.start()]
    return None, re.sub(r"\s*SIN GARANT[IÍ]A\b.*$", "", text)


def category_of(text):
    for pattern, slug in RULES:
        if re.search(pattern, text):
            return slug
    for pattern, slug in KEYWORD_RULES:
        if re.search(pattern, text):
            return slug
    return FALLBACK


def convert(src, dst):
    rows = read_xlsx(src)
    header = [h.strip().upper() for h in rows[0]]
    idx = {name: header.index(name) for name in ("ID", "PRODUCTO", "PRECIO", "CENTRO", "24H")}
    out, stats = [], Counter()
    for r in rows[1:]:
        cell = lambda name: (r[idx[name]] if idx[name] < len(r) else "").strip()
        raw = re.sub(r"\s+", " ", cell("PRODUCTO").upper())
        if not cell("ID") or not raw:
            continue
        condition, text = "Nuevo", raw
        for pattern, label in CONDITIONS:
            if re.match(pattern, text):
                condition, text = label, re.sub(pattern, "", text)
        months, text = warranty_of(text)
        category = category_of(text)
        centro, h24 = int(float(cell("CENTRO") or 0)), int(float(cell("24H") or 0))
        price = round(float(cell("PRECIO") or 0), 2)
        out.append({
            "sku": cell("ID"),
            "name": pretty(text)[:250],
            "warranty_months": months or "",
            "brand": brand_of(text),
            "category": category,
            "price": f"{price:.2f}",
            "stock": centro,
            "stock_24h": h24,
            # En la tienda solo lo que hay en el local o llega en 24 h, y con precio.
            "published": int(price > 0 and (centro + h24) > 0),
            "condition": condition,
        })
        stats[category] += 1
    with open(dst, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=list(out[0].keys()))
        w.writeheader()
        w.writerows(out)
    published = sum(row["published"] for row in out)
    print(f"{len(out)} productos -> {dst}  (publicados: {published}, ocultos sin existencia: {len(out) - published})")
    print("condición:", dict(Counter(row["condition"] for row in out)))
    print("sin marca detectada:", sum(1 for row in out if not row["brand"]))
    print("por categoría:", ", ".join(f"{k} {v}" for k, v in stats.most_common()))


if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    src = Path(sys.argv[1])
    dst = Path(sys.argv[2]) if len(sys.argv) > 2 else Path(__file__).resolve().parent.parent / "importacion" / f"{src.stem}_importar.csv"
    dst.parent.mkdir(parents=True, exist_ok=True)
    convert(src, dst)
