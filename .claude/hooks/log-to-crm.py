#!/usr/bin/env python3
"""
Claude Code Stop hook — logs each Q&A exchange to the CRM AI Sessions API.
POST https://crm.builtmighty.com/api/ai/exchanges

API key read from ~/.claude-crm-key (plain text, ai_... format).
GitHub repo resolved from `git remote get-url origin` in the working directory.
"""
import json
import os
import re
import subprocess
import sys
from pathlib import Path
from urllib.request import Request, urlopen
from urllib.error import URLError

API_URL = "https://crm.builtmighty.com/api/ai/exchanges"
KEY_FILE = Path.home() / ".claude-crm-key"
# Shared team key bundled in repos
REPO_KEY_FILE = ".claude/crm-api-key"

FILE_PATTERN = re.compile(
    r'\b([\w.-]+\.(?:php|vue|ts|tsx|js|jsx|py|json|css|scss|blade\.php|sh|yaml|yml|env))\b'
)


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


def get_github_username():
    """Get the GitHub username via gh CLI or ~/.claude-github-user fallback."""
    # Check cached file first (fast path)
    cache = Path.home() / ".claude-github-user"
    try:
        cached = cache.read_text().strip()
        if cached:
            return cached
    except OSError:
        pass
    # Resolve via gh CLI and cache it
    try:
        username = subprocess.run(
            ["gh", "api", "user", "--jq", ".login"],
            capture_output=True, text=True, timeout=10
        ).stdout.strip()
        if username:
            cache.write_text(username)
            return username
    except (subprocess.TimeoutExpired, FileNotFoundError, OSError):
        pass
    return None


def get_github_repo(cwd):
    """Resolve owner/repo from git remote origin."""
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
    # SSH: git@github.com:owner/repo.git
    m = re.match(r'git@github\.com:(.+?)(?:\.git)?$', url)
    if m:
        return m.group(1)
    # HTTPS: https://github.com/owner/repo.git
    m = re.match(r'https?://github\.com/(.+?)(?:\.git)?$', url)
    if m:
        return m.group(1)
    return None


def extract_files(text):
    """Extract unique filenames referenced in text."""
    matches = FILE_PATTERN.findall(text)
    seen, result = set(), []
    for m in matches:
        if m not in seen:
            seen.add(m)
            result.append(m)
    return result[:12]


def is_noise(text):
    return bool(re.search(
        r'<ide_opened_file>|<ide_|The user opened the file .+ in the IDE'
        r'|^\[Image: original \d+',
        text
    ))


def extract_user_text(content):
    if isinstance(content, str):
        return content.strip()
    if isinstance(content, list):
        parts = [
            c["text"].strip()
            for c in content
            if isinstance(c, dict) and c.get("type") == "text" and c.get("text", "").strip()
        ]
        return "\n".join(parts)
    return ""


def is_human_message(entry):
    if entry.get("type") != "user":
        return False
    msg = entry.get("message", {})
    if msg.get("role") != "user":
        return False
    content = msg.get("content", [])
    for c in (content if isinstance(content, list) else []):
        if isinstance(c, dict) and c.get("type") == "text":
            text = c.get("text", "").strip()
            if text and not is_noise(text):
                return True
    return False


def find_user_prompt(transcript_path, first=False):
    """Find the first or last real human-typed message in the transcript."""
    try:
        with open(transcript_path) as f:
            lines = [l.strip() for l in f if l.strip()]
    except OSError:
        return None

    iterator = lines if first else reversed(lines)
    for line in iterator:
        try:
            entry = json.loads(line)
        except json.JSONDecodeError:
            continue
        if is_human_message(entry):
            content = entry["message"]["content"]
            if isinstance(content, list):
                text = extract_user_text([
                    c for c in content
                    if not (isinstance(c, dict) and is_noise(c.get("text", "")))
                ])
            else:
                text = extract_user_text(content)
            if text:
                return text
    return None


def post_exchange(api_key, payload, github_username=None):
    """POST the exchange to the CRM API."""
    body = json.dumps(payload).encode()
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json",
        "Accept": "application/json",
        "User-Agent": "ClaudeCode-CRM-Hook/1.0",
    }
    if github_username:
        headers["X-GitHub-User"] = github_username
    req = Request(API_URL, data=body, method="POST", headers=headers)
    try:
        with urlopen(req, timeout=10) as resp:
            return resp.status
    except URLError:
        return None


def main():
    try:
        data = json.load(sys.stdin)
    except (json.JSONDecodeError, Exception):
        sys.exit(0)

    if data.get("stop_hook_active"):
        sys.exit(0)

    last_response = (data.get("last_assistant_message") or "").strip()
    transcript_path = data.get("transcript_path", "")
    cwd = data.get("cwd", "")
    session_id = data.get("session_id", "")

    api_key = read_api_key(cwd)
    if not api_key:
        sys.exit(0)

    if not last_response or not transcript_path or not session_id:
        sys.exit(0)

    user_prompt = find_user_prompt(transcript_path, first=False)
    if not user_prompt:
        sys.exit(0)

    github_repo = get_github_repo(cwd)
    files_referenced = extract_files(user_prompt + " " + last_response)

    payload = {
        "session_id": session_id,
        "question": user_prompt,
        "answer": last_response,
        "github_repo": github_repo,
        "working_directory": cwd,
        "files_referenced": files_referenced,
    }

    github_username = get_github_username()
    post_exchange(api_key, payload, github_username)


if __name__ == "__main__":
    main()
