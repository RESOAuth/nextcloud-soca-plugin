#!/bin/bash
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/.." && pwd)
ENGINE=${CONTAINER_ENGINE:-podman}
NAME=${SOCA_DEMO_NAME:-soca-nextcloud-demo}
PORT=${SOCA_DEMO_PORT:-8091}
SECRETS=${SOCA_DEMO_SECRETS:-$(mktemp -d "${TMPDIR:-/tmp}/soca-nextcloud-XXXXXX")}
HOST=cloud.soca.localhost
python3 "$ROOT/demo/init.py" nextcloud "$SECRETS" "https://$HOST:$PORT"
"$ENGINE" build --network host -f "$ROOT/demo/Dockerfile" -t "$NAME:local" "$ROOT"
MOUNTS=()
for file in config.json client.pem state.key admin-password server.crt server.key; do
  MOUNTS+=(-v "$SECRETS/$file:/run/soca/$file:ro")
done
"$ENGINE" run -d --name "$NAME" -p "127.0.0.1:$PORT:80"   -e SOCA_CONFIG_FILE=/run/soca/config.json -e SQLITE_DATABASE=nextcloud -e NEXTCLOUD_ADMIN_USER=localadmin -e NEXTCLOUD_ADMIN_PASSWORD_FILE=/run/soca/admin-password  "${MOUNTS[@]}" "$NAME:local"
for attempt in {1..60}; do
  if "$ENGINE" exec "$NAME" test -f /run/soca/app-ready && "$ENGINE" exec -u www-data "$NAME" php occ status --output=json 2>/dev/null | python3 -c 'import json,sys;sys.exit(not json.load(sys.stdin).get("installed"))' 2>/dev/null; then break; fi
  sleep 1
done
"$ENGINE" exec -u www-data "$NAME" php occ config:system:set trusted_domains 1 --value="$HOST:$PORT"
"$ENGINE" exec -u www-data "$NAME" php occ app:enable soca
printf 'Open https://%s:%s. Local account: localadmin. Password file: %s/admin-password
' "$HOST" "$PORT" "$SECRETS"
printf 'Trust the generated development certificate explicitly. CA/certificate: %s/server.crt
' "$SECRETS"
printf 'SSO starts disabled. See demo/README.md for candidate checks, recovery and cleanup.
'
