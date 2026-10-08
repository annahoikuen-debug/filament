import fitz
import sys

pdf_path = sys.argv[1] if len(sys.argv) > 1 else "storage/app/sample_invoice.pdf"
doc = fitz.open(pdf_path)
text = ""
for page in doc:
    text += page.get_text()
print("Extracted text length:", len(text))
# Check for Japanese characters
japanese_chars = [c for c in text if '\u3000' <= c <= '\u303f' or '\u3040' <= c <= '\u309f' or '\u30a0' <= c <= '\u30ff' or '\u4e00' <= c <= '\u9fff' or '\uff00' <= c <= '\uff9f']
if japanese_chars:
    print("Japanese characters found:", ''.join(set(japanese_chars))[:20])
else:
    print("No Japanese characters found")
# Look for specific words
if '請求書' in text:
    print("請求書 found")
else:
    print("請求書 NOT found")
if 'ご請求金額' in text:
    print("ご請求金額 found")
else:
    print("ご請求金額 NOT found")
if '佐藤' in text:
    print("佐藤 found")
else:
    print("佐藤 NOT found")
# Show first 200 chars
print("First 200 chars:", repr(text[:200]))