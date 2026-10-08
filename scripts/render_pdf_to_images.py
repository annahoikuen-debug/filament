import fitz

doc = fitz.open("storage/app/sample_invoice.pdf")
for i, page in enumerate(doc):
    pix = page.get_pixmap(dpi=150)
    pix.save(f"storage/app/invoice_page_{i+1}.png")

doc2 = fitz.open("storage/app/sample_receipt.pdf")
for i, page in enumerate(doc2):
    pix = page.get_pixmap(dpi=150)
    pix.save(f"storage/app/receipt_page_{i+1}.png")

print("Rendered PDF pages to PNG successfully.")
