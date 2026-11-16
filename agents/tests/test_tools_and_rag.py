import pytest

from app.rag.embeddings import HashEmbedder
from app.rag.extract import chunk_text
from app.rag.store import InMemoryVectorStore
from app.tools import builtin


async def test_calculator_is_safe_and_exact():
    assert await builtin.calculator("(1200 * 0.15) + 49.9") == "(1200 * 0.15) + 49.9 = 229.9"
    assert (await builtin.calculator("__import__('os')")).startswith("Error")
    assert await builtin.calculator("1/0") == "Error: division by zero"


async def test_datetime_rejects_unknown_timezone():
    assert "UTC" in await builtin.current_datetime("UTC")
    assert "Unknown timezone" in await builtin.current_datetime("Mars/Olympus")


@pytest.mark.parametrize("sql", ["DELETE FROM demo_orders", "SELECT 1; DROP TABLE users", "select pg_sleep(10)", "update x set a=1"])
async def test_database_tool_rejects_writes(settings, sql):
    settings.business_database_url = "postgresql://nobody@127.0.0.1:1/none"
    result = await builtin.make_database_query(settings)(sql)
    assert result.startswith("Rejected")


async def test_email_without_smtp_does_not_send(settings):
    result = await builtin.make_send_email(settings)("a@b.co", "hi", "body")
    assert "not configured" in result


def test_chunking_respects_size_and_overlap():
    text = "\n\n".join(f"Paragraph {i}. " + "word " * 60 for i in range(12))
    chunks = chunk_text(text, size=400, overlap=50)
    assert len(chunks) > 3
    assert all(len(c) <= 460 for c in chunks)
    assert chunk_text("   ") == []


async def test_hash_embeddings_rank_relevant_chunk_first():
    embedder = HashEmbedder()
    store = InMemoryVectorStore()
    chunks = [
        "Products can be returned within 30 days of delivery for a refund.",
        "Express delivery costs 9.90 EUR and arrives next business day.",
        "Electronics carry a 2-year warranty.",
    ]
    await store.upsert_document("kb1", "doc1", "policies.md", chunks, await embedder.embed(chunks), embedder.model_name)
    [query] = await embedder.embed(["How many days do I have to return a product?"])
    results = await store.search(["kb1"], query, embedder.model_name, 2)
    assert results[0]["content"].startswith("Products can be returned")
    assert await store.search(["other-kb"], query, embedder.model_name, 2) == []
    assert await store.delete(document_id="doc1") == 3
