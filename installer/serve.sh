#!/usr/bin/env bash
# Quick dev server (not for production). Usage: bash installer/serve.sh [port]
HERE="$(cd "$(dirname "$0")" && pwd)"
CODE="$(dirname "$HERE")/sapiqo"
PORT="${1:-8000}"
echo "Sapiqo dev server on http://localhost:$PORT  (Ctrl+C to stop)"
exec php -d max_execution_time=0 -S "localhost:$PORT" -t "$CODE/public" "$CODE/public/router.php"
