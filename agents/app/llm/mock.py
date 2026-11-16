"""Deterministic offline chat model.

It lets the whole platform (tools, RAG, autonomous loops, crews, streaming) run end-to-end
without any API key, and makes the graphs testable. It is clearly labelled in its answers
so nobody mistakes it for a real LLM.
"""
from __future__ import annotations

import json
import re
import uuid
from collections.abc import Iterator, Sequence
from typing import Any

from langchain_core.callbacks import CallbackManagerForLLMRun
from langchain_core.language_models.chat_models import BaseChatModel
from langchain_core.messages import (
    AIMessage,
    AIMessageChunk,
    BaseMessage,
    HumanMessage,
    SystemMessage,
    ToolMessage,
)
from langchain_core.outputs import ChatGeneration, ChatGenerationChunk, ChatResult
from langchain_core.utils.function_calling import convert_to_openai_tool

EMAIL_RE = re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+")
MATH_RE = re.compile(r"(?<![\w])[\d.\s]*\d[\d.\s]*(?:[-+*/%^][\s(]*[\d.]+[\s)]*)+")

DB_QUERIES = {
    ("order", "purchase"): "SELECT o.id, c.name AS customer, o.status, o.total, o.created_at FROM demo_orders o JOIN demo_customers c ON c.id = o.customer_id ORDER BY o.created_at DESC LIMIT 10",
    ("product", "stock", "price", "inventory"): "SELECT name, category, price, stock FROM demo_products ORDER BY name LIMIT 10",
    ("course", "lesson", "class", "enrol"): "SELECT title, level, price, seats_left FROM demo_courses ORDER BY title LIMIT 10",
    ("customer", "client", "buyer"): "SELECT name, email, country FROM demo_customers ORDER BY name LIMIT 10",
    ("revenue", "sales", "turnover"): "SELECT SUM(total) AS revenue, COUNT(*) AS orders FROM demo_orders WHERE status <> 'cancelled'",
}


def _estimate_tokens(text: str) -> int:
    return max(1, len(text) // 4)


def _text(message: BaseMessage) -> str:
    content = message.content
    if isinstance(content, str):
        return content
    return " ".join(part.get("text", "") for part in content if isinstance(part, dict))


class MockChatModel(BaseChatModel):
    """A rule-based stand-in that speaks the same protocol as real chat models."""

    model_name: str = "zyntara-mock-1"
    bound_tools: list[dict[str, Any]] = []

    @property
    def _llm_type(self) -> str:
        return "zyntara-mock"

    def bind_tools(self, tools: Sequence[Any], **kwargs: Any) -> "MockChatModel":  # type: ignore[override]
        return self.model_copy(update={"bound_tools": [convert_to_openai_tool(t) for t in tools]})

    # ------------------------------------------------------------------ protocol
    def _generate(
        self,
        messages: list[BaseMessage],
        stop: list[str] | None = None,
        run_manager: CallbackManagerForLLMRun | None = None,
        **kwargs: Any,
    ) -> ChatResult:
        return ChatResult(generations=[ChatGeneration(message=self._respond(messages))])

    def _stream(
        self,
        messages: list[BaseMessage],
        stop: list[str] | None = None,
        run_manager: CallbackManagerForLLMRun | None = None,
        **kwargs: Any,
    ) -> Iterator[ChatGenerationChunk]:
        message = self._respond(messages)
        if message.tool_calls:
            yield ChatGenerationChunk(
                message=AIMessageChunk(
                    content="",
                    tool_call_chunks=[
                        {"name": c["name"], "args": json.dumps(c["args"]), "id": c["id"], "index": i, "type": "tool_call_chunk"}
                        for i, c in enumerate(message.tool_calls)
                    ],
                    usage_metadata=message.usage_metadata,
                )
            )
            return
        words = re.split(r"(\s+)", str(message.content))
        for i in range(0, len(words), 6):
            yield ChatGenerationChunk(message=AIMessageChunk(content="".join(words[i : i + 6])))
        yield ChatGenerationChunk(message=AIMessageChunk(content="", usage_metadata=message.usage_metadata))

    # ------------------------------------------------------------------ behaviour
    def _respond(self, messages: list[BaseMessage]) -> AIMessage:
        system = "\n".join(_text(m) for m in messages if isinstance(m, SystemMessage))
        prompt_tokens = sum(_estimate_tokens(_text(m)) for m in messages)
        last = messages[-1] if messages else HumanMessage(content="")
        last_text = _text(last)

        if "SUPERVISOR_DECISION" in system or "SUPERVISOR_DECISION" in last_text:
            content = self._supervise(system + "\n" + last_text)
        elif "Return a numbered plan" in last_text:
            content = self._plan(last_text)
        elif "Reply with APPROVED" in last_text:
            content = "APPROVED — the answer addresses the request."
        elif isinstance(last, ToolMessage):
            content = self._answer_from_tools(messages)
        else:
            tool_calls = self._choose_tools(self._user_request(messages), messages) if self.bound_tools else []
            if tool_calls:
                return AIMessage(
                    content="",
                    tool_calls=tool_calls,
                    usage_metadata={"input_tokens": prompt_tokens, "output_tokens": 20, "total_tokens": prompt_tokens + 20},
                )
            content = self._answer(last_text, system)

        out = _estimate_tokens(content)
        return AIMessage(content=content, usage_metadata={"input_tokens": prompt_tokens, "output_tokens": out, "total_tokens": prompt_tokens + out})

    @staticmethod
    def _user_request(messages: list[BaseMessage]) -> str:
        """The user's actual request, not graph scaffolding (plans, crew briefs, reviewer feedback)."""
        humans = [_text(m) for m in messages if isinstance(m, HumanMessage)]
        for text in reversed(humans):
            if text.startswith(("Plan to follow:", "Reviewer feedback:")):
                continue
            match = re.search(r"^Original request: (.+)$", text, flags=re.M)
            return match.group(1).strip() if match else text
        return humans[-1] if humans else ""

    def _tool_names(self) -> set[str]:
        return {t["function"]["name"] for t in self.bound_tools}

    def _choose_tools(self, query: str, messages: list[BaseMessage]) -> list[dict[str, Any]]:
        # Only act once per user turn: if tools already ran after the last human message, answer.
        for message in reversed(messages):
            if isinstance(message, ToolMessage):
                return []
            if isinstance(message, HumanMessage):
                break
        names = self._tool_names()
        q = query.lower()
        calls: list[tuple[str, dict[str, Any]]] = []

        if "knowledge_search" in names:
            calls.append(("knowledge_search", {"query": query[:500]}))
        if "recall_memory" in names and ("remember" in q and "?" in q or "what do you know about me" in q):
            calls.append(("recall_memory", {"query": query[:300]}))
        elif "save_memory" in names and q.startswith(("remember that", "note that", "please remember")):
            calls.append(("save_memory", {"fact": query.split(" that ", 1)[-1][:500]}))
        math = MATH_RE.search(query)
        if "calculator" in names and math and re.search(r"[-+*/%^]", math.group(0)):
            calls.append(("calculator", {"expression": math.group(0).strip()}))
        email = EMAIL_RE.search(query)
        if "send_email" in names and email and any(w in q for w in ("send", "email", "mail")):
            calls.append(("send_email", {"to": email.group(0), "subject": "Message from your ZYNTARA agent", "body": query}))
        if "database_query" in names:
            for keywords, sql in DB_QUERIES.items():
                if any(k in q for k in keywords):
                    calls.append(("database_query", {"sql": sql}))
                    break
        if "current_datetime" in names and any(w in q for w in ("time", "date", "today", "day", "hour")):
            calls.append(("current_datetime", {"timezone": "UTC"}))
        if "web_search" in names and not any(c[0] == "knowledge_search" for c in calls) and any(
            w in q for w in ("search", "latest", "news", "who", "what", "when", "find", "research", "brief", "look up")
        ):
            calls.append(("web_search", {"query": re.sub(r"\s+", " ", query)[:200]}))

        return [{"name": n, "args": a, "id": f"call_{uuid.uuid4().hex[:12]}", "type": "tool_call"} for n, a in calls[:3]]

    @staticmethod
    def _answer_from_tools(messages: list[BaseMessage]) -> str:
        results: list[ToolMessage] = []
        for message in reversed(messages):
            if isinstance(message, ToolMessage):
                results.append(message)
            elif results:
                break
        lines = ["Here is what I found using my tools:"]
        for result in reversed(results):
            body = _text(result).strip()
            lines.append(f"\n**{result.name}** →\n{body[:700]}{'…' if len(body) > 700 else ''}")
        lines.append("\n_(Answer composed by the offline ZYNTARA mock model — connect OpenAI, Anthropic or Ollama for real reasoning.)_")
        return "\n".join(lines)

    @staticmethod
    def _answer(question: str, system: str) -> str:
        context = ""
        if "<context>" in system:
            context = system.split("<context>", 1)[1].split("</context>", 1)[0].strip()
        if context:
            words = {w for w in re.findall(r"\w{4,}", question.lower())}
            sentences = re.split(r"(?<=[.!?])\s+|\n+", context)
            ranked = sorted(sentences, key=lambda s: -len(words & set(re.findall(r"\w{4,}", s.lower()))))
            best = [s.strip() for s in ranked[:3] if s.strip() and not s.startswith("[")]
            if best:
                return "According to your documents: " + " ".join(best) + "\n\n_(offline mock model)_"
            return "I could not find this in your documents, so I won't guess. _(offline mock model)_"
        persona = system.strip().split("\n", 1)[0][:160] if system.strip() else "a helpful assistant"
        return (
            f"You asked: “{question.strip()[:300]}”.\n\n"
            f"I'm running as: {persona}\n\n"
            "This reply comes from the built-in offline mock model, which demonstrates the full pipeline "
            "(prompt → app → tools → RAG → agent → crew) without an API key. Select OpenAI, Anthropic or "
            "Ollama in the agent settings to get real answers."
        )

    @staticmethod
    def _plan(task: str) -> str:
        goal = task.split("Task:", 1)[-1].strip().split("\n", 1)[0][:200]
        return (
            f"1. Understand the goal: {goal}\n"
            "2. Gather the facts needed with the available tools.\n"
            "3. Verify the results and compute anything numeric.\n"
            "4. Write a concise final answer with sources."
        )

    @staticmethod
    def _supervise(prompt: str) -> str:
        roles = re.findall(r"^- ROLE: (.+?) \|", prompt, flags=re.M)
        acted_line = re.search(r"^ACTED: (.*)$", prompt, flags=re.M)
        acted = {a.strip() for a in acted_line.group(1).split(",")} if acted_line and acted_line.group(1).strip() else set()
        for role in roles:
            if role not in acted:
                return json.dumps({"next": role, "instruction": f"As the {role}, contribute your part towards the goal."})
        return json.dumps({"next": "FINISH", "instruction": "All members contributed."})
