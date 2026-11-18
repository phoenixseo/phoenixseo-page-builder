#!/usr/bin/env bash
# Keep the wp-env stack for this plugin bound to localhost/NetBird instead of
# 0.0.0.0, and pointed at the repository as it exists today.
#
# Why: `wp-env start` regenerates wp-env/<project>/docker-compose.yml from
# .wp-env.json on every run. The generated file binds WordPress and MySQL to
# 0.0.0.0 (public internet on this VPS) and records the plugin path that was
# current when wp-env last ran. Both are wrong here, and both silently come
# back. Run this after every `wp-env start`, then recreate:
#
#   test-scf/harden-wp-env.sh                 # fix the compose file
#   test-scf/harden-wp-env.sh --recreate      # fix, then recreate the stack
#
# Verification after recreate (all three must hold):
#   curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8899/          # 200
#   curl -s -o /dev/null -w '%{http_code}\n' http://100.67.225.184:8899/     # 200
#   curl -s -o /dev/null -w '%{http_code}\n' http://37.120.166.123:8899/     # 000
#
# The script is idempotent: a compose file that is already correct is left
# untouched and reported as such.

set -euo pipefail

NETBIRD_IP="${NETBIRD_IP:-100.67.225.184}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE="${COMPOSE:-}"
RECREATE=0

for arg in "$@"; do
	case "$arg" in
		--recreate) RECREATE=1 ;;
		--help|-h) sed -n '2,26p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
		*) echo "Unbekannte Option: $arg" >&2; exit 2 ;;
	esac
done

if [ -z "$COMPOSE" ]; then
	shopt -s nullglob
	candidates=(/home/hermes/wp-env/wp-env-*/docker-compose.yml)
	shopt -u nullglob
	if [ "${#candidates[@]}" -ne 1 ]; then
		echo "Erwarte genau einen wp-env-Stack unter /home/hermes/wp-env/, gefunden: ${#candidates[@]}" >&2
		printf '  %s\n' "${candidates[@]}" >&2
		echo "Setze COMPOSE=/pfad/zu/docker-compose.yml" >&2
		exit 1
	fi
	COMPOSE="${candidates[0]}"
fi

[ -f "$COMPOSE" ] || { echo "Nicht gefunden: $COMPOSE" >&2; exit 1; }

before_hash="$(sha256sum "$COMPOSE" | cut -d' ' -f1)"

python3 - "$COMPOSE" "$REPO_DIR" "$NETBIRD_IP" <<'PY'
import re, sys

path, repo, netbird = sys.argv[1], sys.argv[2], sys.argv[3]
src = open(path, encoding="utf-8").read()
out = src
changes = []

# --- 1. Port bindings: 0.0.0.0 -> 127.0.0.1 (+ NetBird for the web UI) -----
# Containers reach each other over the compose network, so nothing inside the
# stack needs a published port. Only the browser does: localhost and NetBird.
out = out.replace("- '${WP_ENV_MYSQL_PORT:-}:3306'",
                  "- '127.0.0.1:${WP_ENV_MYSQL_PORT:-}:3306'")
out = out.replace("- '${WP_ENV_PHPMYADMIN_PORT:-}:80'",
                  "- '127.0.0.1:${WP_ENV_PHPMYADMIN_PORT:-}:80'")

wp_old = "- '${WP_ENV_PORT:-8899}:80'"
wp_new = ("- '127.0.0.1:${WP_ENV_PORT:-8899}:80'\n"
          "      - '%s:${WP_ENV_PORT:-8899}:80'" % netbird)
if wp_old in out:
    out = out.replace(wp_old, wp_new)

if out != src:
    changes.append("Portbindungen auf 127.0.0.1%s" % (" + " + netbird if wp_new in out else ""))

# --- 2. Plugin mount: point at this checkout -------------------------------
mount_re = re.compile(r"^\s*(/[^\s:]+)/test-scf:/var/www/html/wp-content/plugins/test-scf\s*$",
                      re.MULTILINE)
m = mount_re.search(out)
if not m:
    print("WARNUNG: keine test-scf-Bind-Mount-Zeile in %s gefunden" % path, file=sys.stderr)
elif m.group(1) != repo:
    out = out[:m.start(1)] + repo + out[m.end(1):]
    changes.append("Plugin-Mount %s -> %s" % (m.group(1), repo))

if out == src:
    print("bereits korrekt, nichts zu tun")
else:
    open(path, "w", encoding="utf-8").write(out)
    print("angepasst: " + "; ".join(changes))
PY

after_hash="$(sha256sum "$COMPOSE" | cut -d' ' -f1)"

# --- 3. Optional: recreate so the new bindings take effect -----------------
if [ "$RECREATE" -eq 1 ]; then
	if [ "$before_hash" = "$after_hash" ]; then
		echo "keine Änderung an $COMPOSE — recreate übersprungen"
		echo "(Portbindungen ändern sich nur durch Neuanlegen: docker compose up -d --force-recreate)"
	else
		project="$(basename "$(dirname "$COMPOSE")")"
		echo "lege Stack $project neu an ..."
		(cd "$(dirname "$COMPOSE")" && docker compose -p "$project" up -d --no-build mysql wordpress cli)
	fi
fi

# --- 4. Ist-Zustand melden -------------------------------------------------
echo "--- offene Bindungen (0.0.0.0 = öffentlich):"
docker ps --format '{{.Names}}\t{{.Ports}}' | grep -E "0\.0\.0\.0" || echo "  keine"
echo "--- wp-env-Stack:"
docker ps --format '{{.Names}}\t{{.Ports}}' | grep wp-env || echo "  läuft nicht"
