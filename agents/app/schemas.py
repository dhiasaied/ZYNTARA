"""Wire contract with the Symfony backend (see backend/src/Service/RunSpecBuilder.php)."""
from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field

Provider = Literal["mock", "openai", "anthropic", "ollama"]


class AgentSpec(BaseModel):
    id: str
    name: str
    vertical: str = "generic"
    system_prompt: str
    provider: Provider = "mock"
    model: str = "zyntara-mock-1"
    temperature: float = Field(0.3, ge=0, le=2)
    tools: list[str] = []
    knowledge_base_ids: list[str] = []
    autonomous: bool = False
    max_iterations: int = Field(6, ge=1, le=25)
    memory_enabled: bool = False


class CrewMemberSpec(BaseModel):
    role: str
    agent: AgentSpec


class SupervisorSpec(BaseModel):
    provider: Provider = "mock"
    model: str = "zyntara-mock-1"


class CrewSpec(BaseModel):
    id: str
    name: str
    goal: str
    strategy: Literal["supervisor", "sequential"] = "supervisor"
    max_rounds: int = Field(6, ge=1, le=20)
    supervisor: SupervisorSpec = SupervisorSpec()
    members: list[CrewMemberSpec] = Field(min_length=1)


class PromptSpec(BaseModel):
    provider: Provider = "mock"
    model: str = "zyntara-mock-1"
    temperature: float = Field(0.7, ge=0, le=2)


class Callback(BaseModel):
    url: str
    token: str


class HistoryMessage(BaseModel):
    role: Literal["user", "assistant"]
    content: str


class RunSpec(BaseModel):
    run_id: str
    owner_id: str | None = None
    kind: Literal["prompt", "agent", "crew"]
    input: str = Field(min_length=1, max_length=20000)
    history: list[HistoryMessage] = []
    prompt: PromptSpec | None = None
    agent: AgentSpec | None = None
    crew: CrewSpec | None = None
    callback: Callback | None = None


class Usage(BaseModel):
    input_tokens: int = 0
    output_tokens: int = 0


class RunResult(BaseModel):
    output: str
    usage: Usage
    tools_used: list[str] = []
    cancelled: bool = False


class IngestRequest(BaseModel):
    document_id: str
    knowledge_base_id: str
    path: str
    mime_type: str
    filename: str


class SearchRequest(BaseModel):
    knowledge_base_ids: list[str] = Field(min_length=1)
    query: str = Field(min_length=1, max_length=2000)
    k: int = Field(5, ge=1, le=20)


class DeleteVectorsRequest(BaseModel):
    document_id: str | None = None
    knowledge_base_id: str | None = None
