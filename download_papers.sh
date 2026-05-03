#!/usr/bin/env bash
# Downloads Cambridge 9702 Physics mark scheme PDFs from bestexamhelp.com
# URL pattern: https://bestexamhelp.com/exam/cambridge-international-a-level/physics-9702/{yyyy}/9702_{m|s|w}{yy}_ms_{11..14}.pdf

set -u

BASE="https://bestexamhelp.com/exam/cambridge-international-a-level/physics-9702"
OUT_DIR="${OUT_DIR:-./papers}"
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"

START_YEAR="${START_YEAR:-2010}"
END_YEAR="${END_YEAR:-2025}"
SESSIONS=(m s w)
PAPERS=(11 12 13 14)

mkdir -p "$OUT_DIR"

ok=0; missing=0; failed=0

for ((year=START_YEAR; year<=END_YEAR; year++)); do
  yy=$(printf "%02d" $((year % 100)))
  for s in "${SESSIONS[@]}"; do
    for p in "${PAPERS[@]}"; do
      file="9702_${s}${yy}_ms_${p}.pdf"
      url="${BASE}/${year}/${file}"
      dest="${OUT_DIR}/${file}"

      if [[ -s "$dest" ]]; then
        echo "[skip] $file (already exists)"
        ok=$((ok+1))
        continue
      fi

      code=$(curl -sS -A "$UA" -o "$dest" -w "%{http_code}" "$url")
      if [[ "$code" == "200" ]] && [[ -s "$dest" ]] && head -c 4 "$dest" | grep -q "%PDF"; then
        echo "[ ok ] $file"
        ok=$((ok+1))
      else
        rm -f "$dest"
        if [[ "$code" == "404" ]]; then
          echo "[404 ] $file"
          missing=$((missing+1))
        else
          echo "[fail] $file (HTTP $code)"
          failed=$((failed+1))
        fi
      fi

      sleep 0.3
    done
  done
done

echo ""
echo "Done. ok=$ok  missing=$missing  failed=$failed"
echo "Saved to: $OUT_DIR"
