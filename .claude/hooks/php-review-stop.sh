#!/usr/bin/env bash
#
# Stop hook.
#
# When the agent's turn settles, ask it to dispatch `php-review` for each PHP
# file it changed this session whose CONTENT changed since its last review.
# Because php-review reviews the cumulative `git diff HEAD`, one review of the
# final state beats many mid-edit reviews.
#
# Dedup is by content hash, not by existence: a file is nudged once per distinct
# state. The reviewed-hash is recorded up front (before the agent acts), so an
# unchanged file is never nudged twice and the loop terminates cleanly once edits
# stop changing content. Silent (exit 0) when there is nothing new to review.

# Local opt-out: hooks/config.local.json maps each hook filename to a boolean.
# A script set to false here bails immediately. No config file (the default) or a
# missing key means enabled.
config="$(dirname "$0")/config.local.json"
if [ -f "$config" ]; then
	self="$(basename "$0")"
	enabled=$(jq -r --arg k "$self" 'if has($k) then .[$k] else true end' "$config" 2>/dev/null)
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

reason="Before finishing this turn, review your PHP changes. Dispatch the \`php-review\` agent once for each file below (in parallel is fine), then apply any fixes it reports. Files needing review:${list}"

jq -n --arg r "$reason" '{decision: "block", reason: $r}'
