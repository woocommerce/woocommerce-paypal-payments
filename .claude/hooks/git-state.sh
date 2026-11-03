#!/usr/bin/env bash
#
# Stop (`save`) and UserPromptSubmit (`check`) hook.
#
# Detects changes made outside the agent between two turns: edited files, a
# changed index (staging), and a moved HEAD (commit, amend, branch switch).
# `save` records the state when the agent's turn ends; `check` compares it with
# the state when the user sends the next prompt and, on a difference, adds a
# note to that prompt. Each session keeps its own state file, so parallel
# sessions do not overwrite each other.

# Everything below parses JSON with jq. Without it, bail silently.
command -v jq >/dev/null 2>&1 || exit 0

# Local opt-out: hooks/config.local.json maps each "feature" to a boolean.
# A disabled feature bails immediately. No config file (the default) or a
# missing key means enabled.
feature="stale-check"
config="$(dirname "$0")/config.local.json"
if [ -f "$config" ]; then
	enabled=$(jq -r --arg k "$feature" 'if has($k) then .[$k] else true end' "$config" 2>/dev/null)
	[ "$enabled" = "false" ] && exit 0
fi

mode="$1"
input=$(cat)

session=$(printf '%s' "$input" | jq -r '.session_id // empty' 2>/dev/null)
[ -n "$session" ] || exit 0

[ -n "$CLAUDE_PROJECT_DIR" ] && cd "$CLAUDE_PROJECT_DIR" 2>/dev/null
git rev-parse --git-dir >/dev/null 2>&1 || exit 0

dir="${TMPDIR:-/tmp}/ppcp-git-state"
state="${dir}/${session}"

# `git ls-files --stage` lists every index entry with its blob hash, so its hash
# changes with any staging change. Unlike `git write-tree`, it writes nothing
# and also works during a merge conflict.
head=$(git rev-parse -q --verify HEAD 2>/dev/null)
branch=$(git symbolic-ref --short -q HEAD 2>/dev/null)
index=$(git ls-files --stage 2>/dev/null | git hash-object --stdin 2>/dev/null)

# One "<content hash> <path>" line per file that differs from HEAD (staged,
# unstaged, or untracked). A deleted file gets "-" as its hash.
files=$(
	{ git diff --name-only HEAD 2>/dev/null; git ls-files --others --exclude-standard 2>/dev/null; } \
		| sort -u \
		| while IFS= read -r path; do
			if [ -f "$path" ]; then
				printf '%s %s\n' "$(git hash-object -- "$path" 2>/dev/null)" "$path"
			else
				printf -- '- %s\n' "$path"
			fi
		done
)

save_state() {
	mkdir -p "$dir" 2>/dev/null
	printf '%s\n%s\n%s\n%s\n' "$head" "$branch" "$index" "$files" > "$state" 2>/dev/null
}

if [ "$mode" = "save" ]; then
	save_state
	exit 0
fi

[ "$mode" = "check" ] || exit 0

# First prompt of the session: nothing to compare yet.
if [ ! -f "$state" ]; then
	save_state
	exit 0
fi

{
	read -r old_head
	read -r old_branch
	read -r old_index
} < "$state"
old_files=$(tail -n +4 "$state")

notes=""

current_hash() {
	if [ -f "$1" ]; then git hash-object -- "$1" 2>/dev/null; else printf -- '-'; fi
}

# A file changed when its content differs from the saved hash, or when it was
# clean at the end of the last turn and is dirty now. A file that only left the
# list because it was committed keeps its content and is not reported.
changed=$(
	{
		printf '%s\n' "$old_files" | sed '/^$/d' | while IFS=' ' read -r hash path; do
			[ "$(current_hash "$path")" = "$hash" ] || printf '%s\n' "$path"
		done
		printf '%s\n' "$files" | sed '/^$/d' | cut -d' ' -f2- | while IFS= read -r path; do
			printf '%s\n' "$old_files" | cut -d' ' -f2- | grep -qxF -- "$path" || printf '%s\n' "$path"
		done
	} | sort -u
)
if [ -n "$changed" ]; then
	total=$(printf '%s\n' "$changed" | wc -l | tr -d ' ')
	shown=$(printf '%s\n' "$changed" | head -10 | sed 's/^/  - /')
	more=""
	[ "$total" -gt 10 ] && more=$'\n'"  - +$(( total - 10 )) more"
	notes="${notes}"$'\n'"- Files changed:"$'\n'"${shown}${more}"
fi

if [ "$branch" != "$old_branch" ]; then
	notes="${notes}"$'\n'"- Branch switched from \`${old_branch:-detached HEAD}\` to \`${branch:-detached HEAD}\`."
elif [ "$head" != "$old_head" ]; then
	if [ -n "$old_head" ] && git merge-base --is-ancestor "$old_head" "$head" 2>/dev/null; then
		count=$(git rev-list --count "${old_head}..${head}" 2>/dev/null)
		notes="${notes}"$'\n'"- ${count} new commit(s) on \`${branch}\`: ${old_head:0:7} → ${head:0:7}."
	else
		notes="${notes}"$'\n'"- HEAD was rewritten (amend, rebase, or reset): ${old_head:0:7} → ${head:0:7}."
	fi
fi

if [ "$index" != "$old_index" ]; then
	staged=$(git diff --cached --name-only 2>/dev/null)
	if [ -n "$staged" ]; then
		total=$(printf '%s\n' "$staged" | wc -l | tr -d ' ')
		shown=$(printf '%s\n' "$staged" | head -10 | sed 's/^/  - /')
		more=""
		[ "$total" -gt 10 ] && more=$'\n'"  - +$(( total - 10 )) more"
		notes="${notes}"$'\n'"- The staged changes differ. Now staged:"$'\n'"${shown}${more}"
	else
		notes="${notes}"$'\n'"- The staged changes differ. Nothing is staged now."
	fi
fi

save_state

[ -n "$notes" ] || exit 0

context="Files changed outside this session since your last turn:${notes}"

jq -n --arg c "$context" '{hookSpecificOutput: {hookEventName: "UserPromptSubmit", additionalContext: $c}}'
