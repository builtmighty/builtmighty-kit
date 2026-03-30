#!/usr/bin/env python3
"""
Claude Code UserPromptSubmit hook — searches CRM AI Sessions for past context.
GET https://crm.builtmighty.com/api/ai/search

Only fires when the user's prompt looks like a recall query.
Outputs a context block to stdout that Claude sees before responding.
"""
import json
import os
import re
import subprocess
import sys
from pathlib import Path
from urllib.parse import urlencode, quote
from urllib.request import Request, urlopen
from urllib.error import URLError

API_URL = "https://crm.builtmighty.com/api/ai/search"
KEY_FILE = Path.home() / ".claude-crm-key"
REPO_KEY_FILE = ".claude/crm-api-key"

RECALL_PATTERNS = [
    r'\b(last time|previously|before|earlier|remember|recall|we discussed|we decided|we talked|you mentioned)\b',
    r'\b(what did we|what was|check (your )?notes?|look (it )?up|any notes?)\b',
    r'\b(past (conversation|session|time|work)|prior (conversation|work|session))\b',
    r'\b(history|background|context|what happened)\b',
    r'\b(when did we|how did we|why did we)\b',
    r'\b(search.*(session|history)|our history)\b',
]

RECALL_RE = re.compile('|'.join(RECALL_PATTERNS), re.IGNORECASE)

STOPWORDS = {
    'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
    'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
    'should', 'may', 'might', 'can', 'shall', 'to', 'of', 'in', 'on',
    'at', 'by', 'for', 'with', 'from', 'up', 'about', 'into', 'through',
    'after', 'we', 'i', 'you', 'it', 'that', 'this', 'what', 'when',
    'how', 'why', 'any', 'last', 'time', 'previously', 'before', 'earlier',
    'remember', 'recall', 'discussed', 'decided', 'talked', 'mentioned',
    'notes', 'check', 'look', 'past', 'prior', 'conversation', 'session',
    'history', 'background', 'context', 'happened', 'did', 'and', 'or',
    'but', 'so', 'if', 'then', 'my', 'your', 'our', 'search',
}


def read_api_key(cwd):
    """Read API key: env var first, then personal file, then repo file."""
    env_key = os.environ.get("CLAUDE_CRM_KEY", "").strip()
    if env_key:
        return env_key
    try:
        return KEY_FILE.read_text().strip()
    except OSError:
        pass
    if cwd:
        try:
            return (Path(cwd) / REPO_KEY_FILE).read_text().strip()
        except OSError:
            pass
    return None


def get_github_repo(cwd):
    if not cwd:
        return None
    try:
        url = subprocess.run(
            ["git", "remote", "get-url", "origin"],
            capture_output=True, text=True, timeout=5, cwd=cwd
        ).stdout.strip()
    except (subprocess.TimeoutExpired, FileNotFoundError, OSError):
        return None
    if not url:
        return None
    m = re.match(r'git@github\.com:(.+?)(?:\.git)?$', url)
    if m:
        return m.group(1)
    m = re.match(r'https?://github\.com/(.+?)(?:\.git)?$', url)
    if m:
        return m.group(1)
    return None


def extract_terms(text):
    words = re.findall(r'\b[a-zA-Z][a-zA-Z0-9_-]{2,}\b', text)
    terms = [w for w in words if w.lower() not in STOPWORDS]
    seen, result = set(), []
    for t in terms:
        if t.lower() not in seen:
            seen.add(t.lower())
            result.append(t)
    return result[:8]


def search_crm(api_key, github_repo, query):
    params = urlencode({"github_repo": github_repo, "q": query, "limit": 8})
    url = f"{API_URL}?{params}"
    req = Request(url, method="GET", headers={
        "Authorization": f"Bearer {api_key}",
        "Accept": "application/json",
        "User-Agent": "ClaudeCode-CRM-Hook/1.0",
    })
    try:
        with urlopen(req, timeout=10) as resp:
            return json.loads(resp.read().decode())
    except (URLError, json.JSONDecodeError):
        return None


def main():
    try:
        data = json.load(sys.stdin)
    except Exception:
        sys.exit(0)

    # Extract the user's current message
    prompt = ""
    for msg in reversed(data.get("messages", [])):
        if msg.get("role") == "user":
            content = msg.get("content", "")
            if isinstance(content, list):
                parts = [c.get("text", "") for c in content if isinstance(c, dict) and c.get("type") == "text"]
                prompt = "\n".join(parts).strip()
            elif isinstance(content, str):
                prompt = content.strip()
            if prompt:
                break

    if not prompt:
        sys.exit(0)

    if not RECALL_RE.search(prompt):
        sys.exit(0)

    cwd = data.get("cwd", "")

    api_key = read_api_key(cwd)
    if not api_key:
        sys.exit(0)
    github_repo = get_github_repo(cwd)
    if not github_repo:
        sys.exit(0)

    terms = extract_terms(prompt)
    if not terms:
        sys.exit(0)

    query = " ".join(terms[:5])
    results = search_crm(api_key, github_repo, query)

    if not results:
        sys.exit(0)

    lines = [
        "<crm_session_context>",
        f"CRM AI Session search results for: {query}\n",
    ]
    for r in results:
        title = r.get("session_title", "Untitled")
        user = r.get("user_name", "Unknown")
        q = r.get("question", "")[:200]
        a = r.get("answer", "")[:300]
        lines.append(f"**Session: {title}** ({user})")
        lines.append(f"Q: {q}")
        lines.append(f"A: {a}")
        lines.append("")
    lines.append("</crm_session_context>")

    print("\n".join(lines))
    sys.exit(0)


if __name__ == "__main__":
    main()
