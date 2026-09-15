#!/usr/bin/env bash
#
# Stop hook.
#
# When the agent's turn settles, ask it to dispatch code cleanliness check for each PHP
# file it changed this session whose CONTENT changed since its last review.
# Because the agent reviews the cumulative `git diff HEAD`, one review of the
# final state beats many mid-edit reviews.
#
# Dedup is by content hash, not by existence: a file is nudged once per distinct
# state. The reviewed-hash is recorded up front (before the agent acts), so an
# unchanged file is never nudged twice and the loop terminates cleanly once edits
# stop changing content. Silent (exit 0) when there is nothing new to review.

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

session=$(printf '%s' "$input" | jq -r '.session_id // "nosession"' 2>/dev/null)
[ -n "$session" ] || session="nosession"

dir="${TMPDIR:-/tmp}/ppcp-php-review/${session}"
[ -d "$dir" ] || exit 0

content_hash() {
	if command -v md5 >/dev/null 2>&1; then
		md5 -q "$1"
	else
		md5sum "$1" | cut -d' ' -f1
	fi
}

list=""
for marker in "$dir"/touched-*; do
	[ -e "$marker" ] || continue
	# The glob also matches the ".reviewed" sidecars we write below; skip them.
	case "$marker" in *.reviewed) continue ;; esac
	file=$(cat "$marker" 2>/dev/null)
	[ -n "$file" ] && [ -f "$file" ] || continue

	cur=$(content_hash "$file")
	seen="${marker}.reviewed"
	if [ -f "$seen" ] && [ "$(cat "$seen" 2>/dev/null)" = "$cur" ]; then
		continue
	fi
	printf '%s' "$cur" > "$seen" 2>/dev/null
	list="${list}"$'\n'"- ${file}"
done

[ -n "$list" ] || exit 0

reason="Before finishing this turn, review your PHP changes. Dispatch the \`review-php-code-cleanliness\` agent once for each file below (in parallel is fine), each with the prompt \`review CHANGES in <file>\`, then apply any fixes it reports. Files needing review:${list}"

jq -n --arg r "$reason" '{decision: "block", reason: $r}'
