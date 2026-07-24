#!/usr/bin/env bash
#
# PostToolUse (Edit|Write) recorder.
#
# Records which PHP files the agent touches this session, so the Stop hook can
# review their FINAL state once the turn settles. Injects nothing and never
# blocks: it just drops a marker and exits 0. Scope guard: only files touched
# here get reviewed later, so pre-existing dirty files the session never edited
# are left alone.

# Everything below parses JSON with jq. Without it, bail silently.
command -v jq >/dev/null 2>&1 || exit 0

# Local opt-out: hooks/config.local.json maps each "feature" to a boolean.
# A disabled feature bails immediately. No config file (the default) or a
# missing key means enabled.
feature="php-review"
config="$(dirname "$0")/config.local.json"
if [ -f "$config" ]; then
	enabled=$(jq -r --arg k "$feature" 'if has($k) then .[$k] else true end' "$config" 2>/dev/null)
	[ "$enabled" = "false" ] && exit 0
fi

input=$(cat)

file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null)
[ -n "$file" ] || exit 0
case "$file" in
	*.php) ;;
	*) exit 0 ;;
esac

# Test files are out of scope for cleanliness review.
case "$file" in
	*/tests/*|*/test/*) exit 0 ;;
esac

session=$(printf '%s' "$input" | jq -r '.session_id // "nosession"' 2>/dev/null)
[ -n "$session" ] || session="nosession"

if command -v md5 >/dev/null 2>&1; then
	path_hash=$(printf '%s' "$file" | md5 -q)
else
	path_hash=$(printf '%s' "$file" | md5sum | cut -d' ' -f1)
fi

dir="${TMPDIR:-/tmp}/ppcp-php-review/${session}"
mkdir -p "$dir" 2>/dev/null
printf '%s' "$file" > "${dir}/touched-${path_hash}" 2>/dev/null

exit 0
