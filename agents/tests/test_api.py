from fastapi.testclient import TestClient

from app.config import Settings, get_settings
from app.main import app

H = {"X-Internal-Token": "secret"}


def client(tmp_path) -> TestClient:
    app.dependency_overrides = {}
    get_settings.cache_clear()
    settings = Settings(_env_file=None, storage_dir=tmp_path, internal_api_token="secret", database_url="", redis_url="",
                        openai_api_key="", anthropic_api_key="", ollama_base_url="")
    import app.main as main

    main.get_settings = lambda: settings  # lifespan reads settings through this name
    return TestClient(app)


def test_end_to_end_ingest_search_and_run(tmp_path):
    (tmp_path / "kb" / "k1").mkdir(parents=True)
    (tmp_path / "kb" / "k1" / "policy.md").write_text("# Returns\nReturns are accepted within 30 days.\n\n# Shipping\nShipping is free above 50 EUR.", encoding="utf-8")

    with client(tmp_path) as c:
        assert c.get("/health").json()["status"] == "ok"
        assert c.get("/v1/providers").status_code == 401

        providers = c.get("/v1/providers", headers=H).json()["providers"]
        assert {p["id"] for p in providers} == {"mock", "openai", "anthropic", "ollama"}
        assert next(p for p in providers if p["id"] == "openai")["available"] is False

        r = c.post("/v1/ingest", headers=H, json={"document_id": "11111111-1111-1111-1111-111111111111", "knowledge_base_id": "k1",
                                                  "path": "kb/k1/policy.md", "mime_type": "text/markdown", "filename": "policy.md"})
        assert r.status_code == 200 and r.json()["chunks"] >= 1

        r = c.post("/v1/ingest", headers=H, json={"document_id": "x", "knowledge_base_id": "k1", "path": "../../etc/passwd",
                                                  "mime_type": "text/plain", "filename": "passwd"})
        assert r.status_code == 400

        hits = c.post("/v1/search", headers=H, json={"knowledge_base_ids": ["k1"], "query": "free shipping threshold"}).json()["results"]
        assert "50 EUR" in hits[0]["content"]

        run = c.post("/v1/runs/execute", headers=H, json={
            "run_id": "r1", "kind": "agent", "input": "When is shipping free?",
            "agent": {"id": "00000000-0000-0000-0000-000000000001", "name": "Support", "system_prompt": "Support agent", "knowledge_base_ids": ["k1"]},
        })
        assert run.status_code == 200, run.text
        assert "50 EUR" in run.json()["output"]

        bad = c.post("/v1/runs/execute", headers=H, json={"run_id": "r2", "kind": "prompt", "input": "hi", "prompt": {"provider": "anthropic", "model": "claude-sonnet-5-5"}})
        assert bad.status_code == 422
        assert "not configured" in bad.json()["detail"]
