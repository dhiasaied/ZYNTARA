"""Built-in tools. Each factory closes over the run context so tools stay tenant-scoped."""
from __future__ import annotations

import ast
import asyncio
import logging
import operator
import re
import smtplib
from datetime import datetime
from email.message import EmailMessage
from typing import Any
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

import httpx
from pydantic import BaseModel, Field

from app.config import Settings

log = logging.getLogger(__name__)

EMAIL_RE = re.compile(r"^[\w.+-]+@[\w-]+(\.[\w-]+)+$")

# ----------------------------------------------------------------------------- calculator

_OPS: dict[type, Any] = {
    ast.Add: operator.add, ast.Sub: operator.sub, ast.Mult: operator.mul, ast.Div: operator.truediv,
    ast.FloorDiv: operator.floordiv, ast.Mod: operator.mod, ast.Pow: operator.pow,
    ast.USub: operator.neg, ast.UAdd: operator.pos,
}


def _eval(node: ast.AST) -> float:
    if isinstance(node, ast.Expression):
        return _eval(node.body)
    if isinstance(node, ast.Constant) and isinstance(node.value, (int, float)):
        return node.value
    if isinstance(node, ast.BinOp) and type(node.op) in _OPS:
        left, right = _eval(node.left), _eval(node.right)
        if isinstance(node.op, ast.Pow) and abs(right) > 100:
            raise ValueError("Exponent too large")
        return _OPS[type(node.op)](left, right)
    if isinstance(node, ast.UnaryOp) and type(node.op) in _OPS:
        return _OPS[type(node.op)](_eval(node.operand))
    raise ValueError("Only numbers and + - * / // % ** ( ) are allowed")


class CalculatorInput(BaseModel):
    expression: str = Field(description="Arithmetic expression, e.g. '(1200 * 0.15) + 49.9'", max_length=200)


async def calculator(expression: str) -> str:
    try:
        result = _eval(ast.parse(expression.replace("^", "**"), mode="eval"))
    except ZeroDivisionError:
        return "Error: division by zero"
    except (SyntaxError, ValueError, TypeError) as exc:
        return f"Error: {exc}"
    return f"{expression} = {round(result, 10):g}"


# ----------------------------------------------------------------------------- datetime

class DateTimeInput(BaseModel):
    timezone: str = Field("UTC", description="IANA timezone, e.g. 'Africa/Khartoum' or 'Europe/Paris'")


async def current_datetime(timezone: str = "UTC") -> str:
    try:
        tz = ZoneInfo(timezone)
    except (ZoneInfoNotFoundError, ValueError):
        return f"Unknown timezone '{timezone}'. Use an IANA name such as 'UTC' or 'Europe/Paris'."
    now = datetime.now(tz)
    return f"{now:%A %d %B %Y, %H:%M} ({timezone}, ISO {now.isoformat(timespec='seconds')})"


# ----------------------------------------------------------------------------- web search

class WebSearchInput(BaseModel):
    query: str = Field(description="What to search for", max_length=300)


def make_web_search(settings: Settings):
    async def web_search(query: str) -> str:
        async with httpx.AsyncClient(timeout=settings.http_timeout_seconds, headers={"User-Agent": settings.http_user_agent}) as client:
            if settings.tavily_api_key:
                response = await client.post(
                    "https://api.tavily.com/search",
                    json={"api_key": settings.tavily_api_key, "query": query, "max_results": 5, "include_answer": True},
                )
                response.raise_for_status()
                data = response.json()
                lines = [f"Answer: {data['answer']}"] if data.get("answer") else []
                lines += [f"- {r['title']} ({r['url']}): {r.get('content', '')[:300]}" for r in data.get("results", [])]
                return "\n".join(lines) or "No results."

            # Keyless fallback: Wikipedia search + intro extracts.
            response = await client.get(
                "https://en.wikipedia.org/w/api.php",
                params={"action": "query", "generator": "search", "gsrsearch": query, "gsrlimit": 4, "prop": "extracts|info",
                        "exintro": 1, "explaintext": 1, "exsentences": 3, "inprop": "url", "format": "json"},
            )
            response.raise_for_status()
            pages = sorted(response.json().get("query", {}).get("pages", {}).values(), key=lambda p: p.get("index", 0))
            if not pages:
                return f"No results for '{query}'."
            return "\n".join(f"- {p['title']} ({p.get('fullurl', '')}): {p.get('extract', '').strip()[:400]}" for p in pages)

    return web_search


# ----------------------------------------------------------------------------- email

class EmailInput(BaseModel):
    to: str = Field(description="Recipient address(es), comma separated")
    subject: str = Field(description="Subject line", max_length=200)
    body: str = Field(description="Plain-text body", max_length=20000)


def make_send_email(settings: Settings):
    def _send(message: EmailMessage) -> None:
        with smtplib.SMTP(settings.smtp_host, settings.smtp_port, timeout=settings.http_timeout_seconds) as smtp:
            if settings.smtp_starttls:
                smtp.starttls()
            if settings.smtp_user:
                smtp.login(settings.smtp_user, settings.smtp_password)
            smtp.send_message(message)

    async def send_email(to: str, subject: str, body: str) -> str:
        if not settings.smtp_host:
            return "Email is not configured on this server (SMTP_HOST is empty). Nothing was sent."
        recipients = [r.strip() for r in to.split(",") if r.strip()]
        if not recipients or any(not EMAIL_RE.match(r) for r in recipients):
            return f"Error: invalid recipient list '{to}'."
        if len(recipients) > settings.email_max_recipients:
            return f"Error: at most {settings.email_max_recipients} recipients are allowed."
        message = EmailMessage()
        message["From"] = settings.smtp_from
        message["To"] = ", ".join(recipients)
        message["Subject"] = subject.replace("\n", " ")
        message.set_content(body)
        await asyncio.to_thread(_send, message)
        return f"Email sent to {', '.join(recipients)} with subject '{subject}'."

    return send_email


# ----------------------------------------------------------------------------- database

class DatabaseInput(BaseModel):
    sql: str = Field(description="A single read-only SELECT statement", max_length=4000)


DB_DESCRIPTION = (
    "Run ONE read-only SQL SELECT on the business database (PostgreSQL). Tables: "
    "demo_customers(id, name, email, country, created_at), "
    "demo_products(id, name, category, price, stock), "
    "demo_orders(id, customer_id, status, total, created_at), "
    "demo_order_items(order_id, product_id, quantity, unit_price), "
    "demo_courses(id, title, level, price, seats_left), "
    "demo_enrollments(course_id, customer_id, progress, enrolled_at). "
    "Returns at most 50 rows."
)
FORBIDDEN_SQL = re.compile(r"\b(insert|update|delete|drop|alter|create|grant|revoke|truncate|copy|call|do|vacuum|set|reset|lock|comment|security|pg_sleep|pg_read|lo_)\b", re.I)


def make_database_query(settings: Settings):
    async def database_query(sql: str) -> str:
        dsn = settings.business_database_url or settings.database_url
        if not dsn:
            return "The business database is not configured (BUSINESS_DATABASE_URL is empty)."
        statement = sql.strip().rstrip(";").strip()
        if ";" in statement or not re.match(r"^(select|with)\b", statement, re.I) or FORBIDDEN_SQL.search(statement):
            return "Rejected: only a single read-only SELECT statement is allowed."

        import psycopg

        try:
            async with await psycopg.AsyncConnection.connect(dsn, connect_timeout=5) as conn:
                async with conn.transaction():
                    await conn.execute("SET TRANSACTION READ ONLY")
                    await conn.execute("SET LOCAL statement_timeout = '5s'")
                    cur = await conn.execute(f"SELECT * FROM ({statement}) AS q LIMIT 50")  # noqa: S608 - guarded above + read-only tx + read-only role
                    rows = await cur.fetchall()
                    columns = [d.name for d in cur.description or []]
        except psycopg.Error as exc:
            return f"SQL error: {str(exc).splitlines()[0]}"
        if not rows:
            return "Query returned no rows."
        header = " | ".join(columns)
        lines = [" | ".join("" if v is None else str(v) for v in row) for row in rows]
        return f"{len(rows)} row(s)\n{header}\n" + "\n".join(lines)

    return database_query
