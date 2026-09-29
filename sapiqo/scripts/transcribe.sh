#!/usr/bin/env bash
# Generate English captions (SRT + VTT) for each course video using OpenAI
# Whisper. Captions are auto-generated and should be reviewed.
#
# Usage:
#   scripts/transcribe.sh                # all videos, turbo model
#   WHISPER_MODEL=medium.en scripts/transcribe.sh
#   scripts/transcribe.sh 741221819.mp4  # a single file
#
# Requires the whisper venv (openai-whisper). Override with WHISPER_BIN.
set -euo pipefail

WHISPER_BIN="${WHISPER_BIN:-whisper}"
MODEL="${WHISPER_MODEL:-turbo}"
SLUG="${SLUG:-chromebook-educator}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="$ROOT/$SLUG/media/videos"

if [ ! -x "$WHISPER_BIN" ]; then
  echo "whisper not found at $WHISPER_BIN (set WHISPER_BIN)" >&2
  exit 1
fi

if [ "$#" -gt 0 ]; then
  FILES=("$DIR/$1")
else
  FILES=("$DIR"/*.mp4)
fi

for f in "${FILES[@]}"; do
  base="$(basename "$f" .mp4)"
  # Skip files already captioned (unless FORCE=1) so re-runs only do new videos.
  if [ "${FORCE:-0}" != "1" ] && [ -f "$DIR/$base.srt" ] && [ -f "$DIR/$base.vtt" ]; then
    echo "--- skip $base (already captioned)"
    continue
  fi
  echo ">>> Transcribing $base ($MODEL)"
  "$WHISPER_BIN" "$f" \
    --model "$MODEL" \
    --language English \
    --task transcribe \
    --output_format all \
    --output_dir "$DIR" \
    --word_timestamps True \
    --max_line_width 42 \
    --max_line_count 2 \
    --verbose False
done

# Keep only SRT (deliverable) and VTT (used for <track> captions).
find "$DIR" -maxdepth 1 -type f \( -name '*.txt' -o -name '*.tsv' -o -name '*.json' \) -delete

echo "Done. SRT + VTT written to $DIR"
