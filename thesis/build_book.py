import re
import subprocess
import sys
from pathlib import Path

import pymupdf
from bs4 import BeautifulSoup
from pypdf import PdfReader, PdfWriter

from make_print_pdf import cover_page, frame_overlay

HERE = Path(__file__).resolve().parent
BUILD = HERE / 'build'
CHROME = r'C:\Program Files\Google\Chrome\Application\chrome.exe'
GREEN = '#0c622a'

MEMBERS = [
    ('Umair', '19252071', 'Administration panel and customer account features'),
    ('Abdul Rafay', '19252064', 'Database, booking engine and integrations'),
    ('Muhammad Arqam', '19252054', 'User interface design'),
]

CSS = """
@page { size: A4; margin: 28mm 22mm 26mm 24mm; }
* { box-sizing: border-box; }
html { font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #000; }
body { margin: 0; line-height: 1.45; }
.page { break-before: page; }
p { margin: 0 0 3mm; text-align: justify; hyphens: auto; orphans: 3; widows: 3; }
a { color: inherit; text-decoration: none; }
h2, h3, h4 { font-family: 'Times New Roman', Times, serif; color: #000; break-after: avoid; }
h3 { font-size: 14pt; margin: 8mm 0 3mm; padding-bottom: 1.5mm; border-bottom: 0.6pt solid #d5d8dd; }
h4 { font-size: 12pt; margin: 6mm 0 2mm; color: #2b2e33; }
ul, ol { margin: 0 0 3mm; padding-left: 7mm; }
li { margin-bottom: 1mm; }
code { font-family: Consolas, 'Courier New', monospace; font-size: 10pt; background: #f1f3f5; padding: 0 1mm; border-radius: 1mm; }
pre { font-family: Consolas, 'Courier New', monospace; font-size: 9.5pt; line-height: 1.4; background: #f6f7f9; border: 0.6pt solid #e1e4e8; border-left: 2pt solid GREEN; padding: 3mm 4mm; margin: 0 0 4mm; white-space: pre-wrap; word-break: break-word; }
pre code { background: none; padding: 0; font-size: inherit; }
table { width: 100%; border-collapse: collapse; margin: 1mm 0 5mm; font-size: 10.5pt; line-height: 1.35; }
thead { display: table-header-group; }
tr { break-inside: avoid; }
th { background: #eef4f0; color: #15171a; text-align: left; font-weight: bold; }
th, td { border: 0.6pt solid #c9cdd3; padding: 1.1mm 2mm; vertical-align: top; hyphens: auto; }
code, a { overflow-wrap: anywhere; }
td:first-child code { white-space: nowrap; overflow-wrap: normal; }
td p, th p { margin: 0; text-align: left; }
blockquote { margin: 0 0 4mm; padding: 2mm 4mm; border-left: 2pt solid #c9cdd3; color: #3a3d42; }
hr { border: 0; border-top: 0.6pt solid #d5d8dd; margin: 6mm 0; }
.figure { break-inside: avoid; margin: 4mm 0 6mm; text-align: center; }
.figure img { max-width: 100%; max-height: 92mm; border: 0.6pt solid #d5d8dd; }
.figure img.diagram { max-height: 125mm; border: 0; }
.figure p { text-align: center; font-size: 10.5pt; color: #3a3d42; margin: 2mm 6mm 0; }
.opener { padding-top: 22mm; margin-bottom: 10mm; }
.opener .label { font-size: 11pt; letter-spacing: 4pt; text-transform: uppercase; color: GREEN; font-weight: bold; }
.opener h2 { font-size: 28pt; line-height: 1.15; margin: 3mm 0 0; padding-bottom: 6mm; border-bottom: 1.4pt solid GREEN; }
.front h2 { font-size: 22pt; margin: 6mm 0 8mm; padding-bottom: 4mm; border-bottom: 1.4pt solid GREEN; }
.title { text-align: center; display: flex; flex-direction: column; align-items: center; height: 243mm; }
.title img { width: 30mm; margin-top: 2mm; }
.title .uni { font-size: 14pt; font-weight: bold; margin-top: 4mm; }
.title .muted { color: #555a61; }
.title .kind { margin-top: 14mm; font-size: 10.5pt; letter-spacing: 3pt; text-transform: uppercase; color: GREEN; font-weight: bold; }
.title h1 { font-size: 25pt; line-height: 1.2; margin: 4mm 12mm 0; }
.title .sub { font-size: 12pt; font-style: italic; color: #3a3d42; margin-top: 3mm; }
.title .degree { margin: 9mm 14mm 0; font-size: 10.5pt; color: #3a3d42; text-align: center; }
.title .team-h { margin-top: 11mm; font-size: 10.5pt; letter-spacing: 3pt; text-transform: uppercase; color: GREEN; font-weight: bold; }
.title table { width: 150mm; margin: 4mm auto 0; font-size: 10.5pt; }
.title td, .title th { padding: 2.4mm 3mm; text-align: left; }
.title .sup { margin-top: 10mm; font-size: 11pt; }
.title .date { margin-top: auto; font-size: 11pt; color: #3a3d42; }
.toc h2 { font-size: 22pt; margin: 0 0 4mm; padding-bottom: 4mm; border-bottom: 1.4pt solid GREEN; }
.toc a { display: flex; align-items: baseline; font-size: 10pt; line-height: 1.24; }
.toc a.ch { font-weight: bold; margin-top: 1.8mm; font-size: 10.5pt; }
.toc a.sec { padding-left: 9mm; }
.toc .no { width: 9mm; flex: none; }
.toc a.ch .no { width: auto; margin-right: 2mm; }
.toc .dots { flex: 1; border-bottom: 0.8pt dotted #9aa0a8; margin: 0 2mm; transform: translateY(-1mm); }
.toc .pg { width: 9mm; text-align: right; flex: none; }
.sign td { height: 13mm; }
.abstract p { line-height: 1.4; margin-bottom: 2.5mm; }
.cert th:nth-child(1) { width: 34%; } .cert th:nth-child(2) { width: 26%; } .cert th:nth-child(3), .cert th:nth-child(4) { width: 20%; }
.title th:nth-child(1) { width: 30%; } .title th:nth-child(2) { width: 20%; white-space: nowrap; }
""".replace('GREEN', GREEN)


def image_for(ref):
    hits = sorted((HERE / 'images').glob(ref.split('/', 1)[1] + '.*'))
    if not hits:
        sys.exit(f'missing image for {ref}; put it in thesis/images/')
    return 'images/' + hits[0].name


def tidy(soup):
    for img in soup.find_all('img', alt=re.compile('^Cover page')):
        img.find_parent('p').decompose()
    for fig in soup.find_all('figure'):
        p = soup.new_tag('p')
        p.append(soup.new_tag('img', src=image_for(fig['data-embed']), attrs={'class': 'diagram'}))
        fig.replace_with(p)
    for img in soup.find_all('img'):
        if img['src'].startswith('blob/'):
            img['src'] = image_for(img['src'])
            if img['src'].endswith('.png'):
                img['class'] = 'diagram'
    for p in soup.find_all('p'):
        if p.find('img') and not p.get_text(strip=True):
            box = soup.new_tag('div', attrs={'class': 'figure'})
            p.wrap(box)
            nxt = box.find_next_sibling()
            if nxt and nxt.name == 'p' and nxt.get_text(strip=True).startswith('Figure'):
                box.append(nxt.extract())


def sections(soup):
    out, cur = [], None
    for node in list(soup.body.children if soup.body else soup.children):
        if getattr(node, 'name', None) == 'h2':
            cur = [node.get_text(strip=True), node.get('id'), []]
            out.append(cur)
        elif cur is not None:
            cur[2].append(node)
    return out


def split_h3(nodes):
    parts, cur = {}, None
    for n in nodes:
        if getattr(n, 'name', None) == 'h3':
            cur = n.get_text(strip=True)
            parts[cur] = []
        elif cur:
            parts[cur].append(n)
    return parts


def html_of(nodes):
    return ''.join(str(n) for n in nodes)


def title_page(title, logo):
    rows = ''.join(f'<tr><td>{n}</td><td>{r}</td><td>{role}</td></tr>' for n, r, role in MEMBERS)
    return f'''<section class="title">
<img src="{logo}" alt="">
<div class="uni">Federal Urdu University of Arts, Science &amp; Technology</div>
<div class="muted">Gulshan-e-Iqbal Campus, Karachi<br>Department of Computer Science</div>
<div class="kind">Final Year Project Report</div>
<h1>{title}</h1>
<div class="sub">Project Thesis and Technical Report</div>
<div class="degree">Submitted in partial fulfilment of the requirements for the degree of<br><b>Bachelor of Science in Computer Science</b></div>
<div class="team-h">Project Members</div>
<table><thead><tr><th>Name</th><th>Roll number</th><th>Contribution</th></tr></thead><tbody>{rows}</tbody></table>
<div class="sup"><b>Supervisor</b><br>Muhammad Sadiq<br><span class="muted">Head of Department, Department of Computer Science</span></div>
<div class="date">October 2026</div>
</section>'''


def toc_html(entries, pages):
    rows = []
    for i, (kind, no, text, anchor) in enumerate(entries):
        pg = pages.get(anchor, '000')
        num = f'<span class="no">{no}</span>' if no or kind == 'sec' else ''
        rows.append(f'<a class="{kind}" href="#{anchor}">{num}<span>{text}</span><span class="dots"></span><span class="pg">{pg}</span></a>')
    return '<section class="page toc"><h2>Table of Contents</h2>' + ''.join(rows) + '</section>'


def build(export, pages):
    soup = BeautifulSoup(Path(export).read_text(encoding='utf-8'), 'html.parser')
    tidy(soup)
    secs = sections(soup)
    title = soup.find('h1').get_text(strip=True)
    front, body, entries = [], [], []
    for name, sid, nodes in secs:
        if name == 'Title Page':
            for h3, part in split_h3(nodes).items():
                if h3 == 'Table of Contents':
                    continue
                anchor = re.sub(r'[^a-z]+', '-', h3.lower()).strip('-')
                cls = {'Declaration': ' sign', 'Certificate of Approval': ' sign cert'}.get(h3, '')
                front.append(f'<section class="page front{cls}"><h2 id="{anchor}">{h3}</h2>{html_of(part)}</section>')
                entries.append(('ch', '', h3, anchor))
        elif name == 'Abstract':
            front.append(f'<section class="page front abstract"><h2 id="{sid}">Abstract</h2>{html_of(nodes)}</section>')
            entries.append(('ch', '', 'Abstract', sid))
        else:
            m = re.match(r'(Chapter \d+|Appendix [A-Z]):\s*(.*)', name)
            label, heading = (m.group(1), m.group(2)) if m else ('', name)
            opener = f'<div class="label">{label}</div>' if label else ''
            body.append(f'<section class="page"><div class="opener">{opener}<h2 id="{sid}">{heading}</h2></div>{html_of(nodes)}</section>')
            entries.append(('ch', label + (':' if label else ''), heading, sid))
            if name.startswith('Chapter'):
                for h3 in (n for n in nodes if getattr(n, 'name', None) == 'h3'):
                    t = h3.get_text(strip=True)
                    sm = re.match(r'(\d+\.\d+)\s+(.*)', t)
                    no, text = (sm.group(1), sm.group(2)) if sm else ('', t)
                    entries.append(('sec', no, text, h3['id']))
    doc = (f'<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{title}</title><style>{CSS}</style></head><body>'
           + title_page(title, '../logo_green.png') + toc_html(entries, pages) + ''.join(front) + ''.join(body) + '</body></html>')
    out = BUILD / 'book.html'
    out.write_text(doc, encoding='utf-8')
    return out, entries


def render(html, pdf):
    subprocess.run([CHROME, '--headless=new', '--disable-gpu', '--no-pdf-header-footer', '--generate-pdf-document-outline',
                    '--allow-file-access-from-files', f'--print-to-pdf={pdf}', html.as_uri()],
                   check=True, capture_output=True, timeout=300)


def anchor_pages(pdf, entries):
    doc = pymupdf.open(pdf)
    wanted = {a for *_, a in entries}
    found = []
    for page in doc:
        links = [l['page'] for l in page.get_links() if l.get('kind') in (pymupdf.LINK_GOTO, pymupdf.LINK_NAMED)]
        if not links and found:
            break
        found += links
    if len(found) != len(entries):
        sys.exit(f'{len(found)} contents links for {len(entries)} entries')
    targets = dict(zip([a for *_, a in entries], found))
    missing = wanted - set(targets)
    if missing:
        sys.exit(f'no page found for {sorted(missing)}')
    return targets, doc.page_count


def roman(n):
    out = ''
    for v, s in ((10, 'x'), (9, 'ix'), (5, 'v'), (4, 'iv'), (1, 'i')):
        while n >= v:
            out, n = out + s, n - v
    return out


def labels(targets, entries, count):
    first = targets[next(a for k, no, t, a in entries if no == 'Chapter 1:')]
    return [roman(p + 1) if p < first else str(p - first + 1) for p in range(count)]


def main(export, dst):
    BUILD.mkdir(exist_ok=True)
    (BUILD / 'images').mkdir(exist_ok=True)
    for f in (HERE / 'images').iterdir():
        target = BUILD / 'images' / f.name
        if not target.exists():
            target.write_bytes(f.read_bytes())
    html, entries = build(export, {})
    render(html, BUILD / 'pass1.pdf')
    targets, count = anchor_pages(BUILD / 'pass1.pdf', entries)
    names = labels(targets, entries, count)
    html, entries = build(export, {a: names[p] for a, p in targets.items()})
    render(html, BUILD / 'pass2.pdf')
    again, count2 = anchor_pages(BUILD / 'pass2.pdf', entries)
    if again != targets or count2 != count:
        sys.exit('page numbers moved between passes; run again')
    reader = PdfReader(BUILD / 'pass2.pdf')
    writer = PdfWriter()
    first = reader.pages[0]
    writer.add_page(cover_page(float(first.mediabox.width), float(first.mediabox.height)))
    for i, page in enumerate(reader.pages):
        if i > 0:
            page.merge_page(frame_overlay(float(page.mediabox.width), float(page.mediabox.height), names[i]))
        writer.add_page(page)
    parent = None
    for kind, no, text, anchor in entries:
        title = f'{no} {text}'.strip()
        if kind == 'ch':
            parent = writer.add_outline_item(title, targets[anchor] + 1)
        else:
            writer.add_outline_item(title, targets[anchor] + 1, parent=parent)
    writer.write(BUILD / 'stamped.pdf')
    with pymupdf.open(BUILD / 'stamped.pdf') as doc:
        first = names.index('1') + 1
        doc.set_page_labels([{'startpage': 0, 'prefix': 'Cover', 'style': '', 'firstpagenum': 1},
                             {'startpage': 1, 'prefix': '', 'style': 'r', 'firstpagenum': 1},
                             {'startpage': first, 'prefix': '', 'style': 'D', 'firstpagenum': 1}])
        doc.save(dst, garbage=4, deflate=True)
    print(f'Wrote {dst}: {len(writer.pages)} pages; Chapter 1 starts on PDF page {targets[next(a for k, no, t, a in entries if no == "Chapter 1:")] + 2}')


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit('usage: python build_book.py <doc export.html> <book.pdf>')
    main(sys.argv[1], sys.argv[2])
