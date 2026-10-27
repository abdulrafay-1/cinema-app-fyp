import io
import sys
from pathlib import Path

from pypdf import PdfReader, PdfWriter
from reportlab.lib.units import mm
from reportlab.pdfgen import canvas

HERE = Path(__file__).resolve().parent
PROJECT = 'CineMax Karachi: Online Movie Ticket Booking System'
UNIVERSITY = 'Federal Urdu University of Arts, Science & Technology'
CAMPUS = 'Gulshan-e-Iqbal Campus, Karachi'
INK = (0.17, 0.17, 0.19)
MUTED = (0.42, 0.44, 0.52)
RULE = (0.82, 0.83, 0.87)


def cover_page(width, height):
    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=(width, height))
    c.drawImage(str(HERE / 'cover.png'), 0, 0, width=width, height=height)
    c.showPage()
    c.save()
    buf.seek(0)
    return PdfReader(buf).pages[0]


def frame_overlay(width, height, number):
    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=(width, height))
    side = 14 * mm
    top = height - 6 * mm

    c.drawImage(str(HERE / 'logo_green.png'), width - side - 9 * mm, top - 9 * mm, width=9 * mm, height=9 * mm, mask='auto')
    c.setFillColorRGB(*INK)
    c.setFont('Times-Bold', 8.5)
    c.drawRightString(width - side - 11 * mm, top - 4 * mm, UNIVERSITY)
    c.setFillColorRGB(*MUTED)
    c.setFont('Times-Roman', 7.5)
    c.drawRightString(width - side - 11 * mm, top - 8 * mm, CAMPUS)
    c.setStrokeColorRGB(*RULE)
    c.setLineWidth(0.6)
    c.line(side, top - 11 * mm, width - side, top - 11 * mm)

    base = 8 * mm
    c.line(side, base + 9 * mm, width - side, base + 9 * mm)
    c.drawImage(str(HERE / 'logo_grey.png'), side, base, width=7 * mm, height=7 * mm, mask='auto')
    c.setFillColorRGB(*INK)
    c.setFont('Times-Bold', 9)
    c.drawString(side + 9.5 * mm, base + 2.5 * mm, PROJECT)
    c.setFillColorRGB(*MUTED)
    c.setFont('Times-Roman', 8.5)
    c.drawRightString(width - side, base + 2.5 * mm, f'Page {number}')
    c.showPage()
    c.save()
    buf.seek(0)
    return PdfReader(buf).pages[0]


def main(src, dst):
    reader = PdfReader(src)
    writer = PdfWriter()
    first = reader.pages[0]
    width, height = float(first.mediabox.width), float(first.mediabox.height)
    if 'Title Page' in (first.extract_text() or ''):
        print('Warning: page 1 of the export already contains the Title Page heading; check the cover is the first thing in the doc.')
    writer.add_page(cover_page(width, height))
    for number, page in enumerate(reader.pages[1:], start=1):
        w, h = float(page.mediabox.width), float(page.mediabox.height)
        page.merge_page(frame_overlay(w, h, number))
        writer.add_page(page)
    with open(dst, 'wb') as out:
        writer.write(out)
    print(f'Wrote {dst}: {len(writer.pages)} pages ({width / mm:.0f} x {height / mm:.0f} mm)')


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit('usage: python make_print_pdf.py <exported.pdf> <print.pdf>')
    main(sys.argv[1], sys.argv[2])
