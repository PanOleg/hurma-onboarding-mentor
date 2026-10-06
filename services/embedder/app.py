import io
from typing import Literal

import fitz
from docx import Document as DocxDocument
from fastapi import FastAPI, File, HTTPException, UploadFile
from fastembed import TextEmbedding
from fastembed.common.model_description import ModelSource, PoolingType
from pydantic import BaseModel, Field

MODEL_NAME = "intfloat/multilingual-e5-small"
DIM = 384
MAX_TEXTS = 64
MAX_TEXT_CHARS = 8000
MAX_FILE_BYTES = 20 * 1024 * 1024
PDF_MIME = "application/pdf"
DOCX_MIME = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"

TextEmbedding.add_custom_model(
    model=MODEL_NAME,
    pooling=PoolingType.MEAN,
    normalization=True,
    sources=ModelSource(hf=MODEL_NAME),
    dim=DIM,
    model_file="onnx/model.onnx",
)
_model = TextEmbedding(model_name=MODEL_NAME)

app = FastAPI(title="embedder", version="1.0")


class EmbedRequest(BaseModel):
    texts: list[str] = Field(min_length=1, max_length=MAX_TEXTS)
    kind: Literal["query", "passage"]


class EmbedResponse(BaseModel):
    vectors: list[list[float]]
    model: str
    dim: int


@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME, "dim": DIM}


@app.post("/embed", response_model=EmbedResponse)
def embed(req: EmbedRequest):
    for t in req.texts:
        if len(t) > MAX_TEXT_CHARS:
            raise HTTPException(status_code=422, detail=f"text longer than {MAX_TEXT_CHARS} chars")
    prefixed = [f"{req.kind}: {t}" for t in req.texts]
    vectors = [v.tolist() for v in _model.embed(prefixed)]
    return EmbedResponse(vectors=vectors, model=MODEL_NAME, dim=DIM)


@app.post("/extract-text")
async def extract_text(file: UploadFile = File(...)):
    data = await file.read()
    if len(data) > MAX_FILE_BYTES:
        raise HTTPException(status_code=413, detail="file too large")
    name = (file.filename or "").lower()
    if file.content_type == PDF_MIME or name.endswith(".pdf"):
        return _extract_pdf(data)
    if file.content_type == DOCX_MIME or name.endswith(".docx"):
        return _extract_docx(data)
    raise HTTPException(status_code=422, detail="unsupported file type, expected pdf or docx")


def _extract_pdf(data: bytes):
    try:
        doc = fitz.open(stream=data, filetype="pdf")
    except Exception as exc:  # noqa: BLE001
        raise HTTPException(status_code=422, detail=f"cannot open pdf: {exc}") from exc
    pages = [{"page": i + 1, "text": page.get_text("text").strip()} for i, page in enumerate(doc)]
    title = (doc.metadata or {}).get("title") or None
    return {"pages": pages, "meta": {"pages_count": len(pages), "title": title}}


def _extract_docx(data: bytes):
    try:
        d = DocxDocument(io.BytesIO(data))
    except Exception as exc:  # noqa: BLE001
        raise HTTPException(status_code=422, detail=f"cannot open docx: {exc}") from exc
    paragraphs = [p.text.strip() for p in d.paragraphs if p.text.strip()]
    text = "\n\n".join(paragraphs)
    return {"pages": [{"page": 1, "text": text}], "meta": {"pages_count": 1, "title": d.core_properties.title or None}}
