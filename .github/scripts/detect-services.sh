#!/usr/bin/env bash
#
# Maps a list of changed files onto the services declared in services.json.
#
# Reads  : newline-separated file paths on stdin (paths relative to the repo root)
# Writes : one JSON object on stdout
#            { "shared": bool, "services": [names...], "hits": [{service,file,rule}...] }
# Usage  : git diff --name-only A B | detect-services.sh [path/to/services.json]
#
# A file under any `global_paths` entry marks every service as affected, because shared
# infrastructure can break any of them.
#
# This is the single implementation used by BOTH the GitHub composite action and the local
# pre-push hook, so CI and your machine can never disagree about what "affected" means.
set -euo pipefail

CONFIG="${1:-.github/services.json}"

if [ ! -f "$CONFIG" ]; then
  echo "detect-services: configuration file not found: $CONFIG" >&2
  exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
  echo "detect-services: 'jq' is required but was not found in PATH." >&2
  exit 1
fi

if ! jq -e '.services | type == "array" and length > 0' "$CONFIG" >/dev/null; then
  echo "detect-services: $CONFIG must contain a non-empty \"services\" array." >&2
  exit 1
fi

# `tr -d '\r'` guards against CRLF creeping in from a Windows checkout or a Windows jq build:
# a trailing carriage return would silently stop every path from matching its rule.
tr -d '\r' | jq -R -s -c --slurpfile cfg "$CONFIG" '
  $cfg[0] as $c
  | (split("\n") | map(select(length > 0))) as $files
  | ($c.global_paths // []) as $globals
  | [ $files[] as $f | $globals[] as $g
      | select($f | startswith($g)) | {file: $f, rule: $g} ] as $globalHits
  | [ $c.services[] as $s | $files[] as $f | $s.paths[] as $p
      | select($f | startswith($p)) | {service: $s.name, file: $f, rule: $p} ] as $svcHits
  | if ($globalHits | length) > 0 then
      { shared: true, hits: $globalHits, services: [$c.services[].name] }
    else
      { shared: false, hits: $svcHits, services: ($svcHits | map(.service) | unique) }
    end
'
