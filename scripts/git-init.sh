#!/usr/bin/env bash
#
# Legt das Repository an und schiebt es ins GitLab.
# Aufruf:  ./scripts/git-init.sh git@gitlab.deine-domain.de:lukas/ungetrunken.git
#
set -euo pipefail

REMOTE="${1:-}"
if [[ -z "$REMOTE" ]]; then
  echo "Aufruf: $0 <git-remote-url>" >&2
  exit 1
fi

cd "$(dirname "$0")/.."

# --- Sicherheitsnetz: keine Zugangsdaten ins Repository -------
if [[ -f app/config.php ]] && ! grep -q '^app/config\.php$' .gitignore 2>/dev/null; then
  echo "ABBRUCH: app/config.php ist nicht in .gitignore." >&2
  exit 1
fi

if [[ -d .git ]]; then
  echo "Repository existiert bereits – überspringe init."
else
  git init -b main
fi

git add .

# --- Was tatsächlich committet wird, vorher zeigen -------------
echo
echo "Diese Dateien gehen ins Repository:"
git diff --cached --name-only | sed 's/^/  /'
echo
if git diff --cached --name-only | grep -qE '(^app/config\.php$|^\.env$)'; then
  echo "ABBRUCH: Eine Datei mit Zugangsdaten ist vorgemerkt." >&2
  exit 1
fi

read -r -p "Weiter? [j/N] " ok
[[ "$ok" == "j" ]] || { echo "Abgebrochen."; exit 0; }

git commit -m "ungetrunken: erste lauffähige Fassung" || echo "Nichts zu committen."

git remote get-url origin >/dev/null 2>&1 && git remote set-url origin "$REMOTE" \
                                          || git remote add origin "$REMOTE"

git push -u origin main

# --- Branch für den Mandanten-Umbau ---------------------------
if ! git rev-parse --verify feature/mandanten >/dev/null 2>&1; then
  git switch -c feature/mandanten
  if [[ -f _wip_mandanten/tenant.php ]]; then
    git mv _wip_mandanten/tenant.php app/tenant.php
    git commit -m "Mandantenkontext als Modul (Arbeitsstand)"
  fi
  git push -u origin feature/mandanten
  git switch main
fi

echo
echo "Fertig. main ist der lauffähige Stand, feature/mandanten der Umbau."
