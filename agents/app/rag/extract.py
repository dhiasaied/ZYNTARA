"""Text extraction and chunking for knowledge-base documents."""
from __future__ import annotations

import csv
import io
import json
import re
from pathlib import Path


class ExtractionError(ValueError):
    pass


def extract_text(path: Path, mime_type: str) -> str:
    if not path.is_file():
        raise ExtractionError(f"File not found: {path.name}")

    if mime_type == "application/pdf":
        from pypdf import PdfReader

        try:
            reader = PdfReader(str(path))
            pages = [page.extract_text() or "" for page in reader.pages]
        except Exception as exc:  # noqa: BLE001 - pypdf raises many types
            raise ExtractionError(f"Unreadable PDF: {exc}") from exc
        text = "\n\n".join(pages)
        if not text.strip():
            raise ExtractionError("The PDF contains no extractable text (scanned images are not supported).")
        return text

    raw = path.read_bytes().decode("utf-8", errors="replace")
    if mime_type == "text/html":
        from bs4 import BeautifulSoup

        soup = BeautifulSoup(raw, "html.parser")
        for tag in soup(["script", "style", "noscript"]):
            tag.decompose()
        return soup.get_text("\n")
    if mime_type == "text/csv":
        rows = list(csv.reader(io.StringIO(raw)))
        if not rows:
            return ""
        header, body = rows[0], rows[1:]
        return "\n".join("; ".join(f"{h}: {v}" for h, v in zip(header, row)) for row in body)
    if mime_type == "application/json":
        try:
            return json.dumps(json.loads(raw), ensure_ascii=False, indent=1)
        except json.JSONDecodeError as exc:
            raise ExtractionError(f"Invalid JSON: {exc}") from exc
    return raw


def chunk_text(text: str, size: int = 800, overlap: int = 120) -> list[str]:
    """Paragraph-aware splitter: packs paragraphs up to `size` chars, hard-splits long ones."""
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n{3,}", "\n\n", text).strip()
    if not text:
        return []

    pieces: list[str] = []
    for paragraph in re.split(r"\n\s*\n", text):
        paragraph = paragraph.strip()
        if not paragraph:
            continue
        while len(paragraph) > size:
            cut = paragraph.rfind(" ", 0, size)
            cut = cut if cut > size // 2 else size
            pieces.append(paragraph[:cut].strip())
            paragraph = paragraph[max(cut - overlap, 1):].strip()
        pieces.append(paragraph)

    chunks: list[str] = []
    current = ""
    for piece in pieces:
        if current and len(current) + len(piece) + 2 > size:
            chunks.append(current)
            tail = current[-overlap:] if overlap else ""
            current = (tail + "\n\n" + piece).strip() if tail and len(tail) + len(piece) < size else piece
        else:
            current = f"{current}\n\n{piece}".strip() if current else piece
    if current:
        chunks.append(current)
    return chunks
