#!/usr/bin/env bash
# Transcode the AI Essentials Vibe-Coding MKV masters (H.264 video + PCM audio,
# very large) into web-friendly MP4 named by their Vimeo id, using the GPU
# (NVENC). Video is re-encoded to a sane bitrate for the web; audio -> AAC.
set -euo pipefail

SRC="${SRC:-$HOME/Downloads/AI Essentials}"
DST="$(cd "$(dirname "$0")/.." && pwd)/ai-essentials/media/videos"
mkdir -p "$DST"

# vibe master -> Vimeo id
declare -A MAP=(
  [vibe111_v1]=1189905452 [vibe112_v1]=1189902701 [vibe113_v1]=1189906641
  [vibe211_v1]=1190167661 [vibe212_v1]=1190177256 [vibe213_v1]=1190190600
  [vibe311_v1]=1190202254 [vibe312_v1]=1190207571 [vibe313_v1]=1190213455
)

for name in "${!MAP[@]}"; do
  in="$SRC/$name.mkv"
  out="$DST/${MAP[$name]}.mp4"
  if [ ! -f "$in" ]; then echo "MISSING $in"; continue; fi
  echo ">>> $name -> ${MAP[$name]}.mp4"
  ffmpeg -y -loglevel error -stats -i "$in" \
    -c:v h264_nvenc -preset p5 -rc vbr -cq 28 -b:v 3M -maxrate 5M -bufsize 10M \
    -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart "$out"
done
echo "Done. Transcoded $(ls "$DST"/*.mp4 2>/dev/null | wc -l) file(s) to $DST"
