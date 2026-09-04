#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
command -v docker >/dev/null 2>&1 || { echo 'Docker is required for the MySQL 8 / MariaDB 11.4 publication snapshot contract.' >&2; exit 2; }
command -v php >/dev/null 2>&1 || { echo 'PHP is required.' >&2; exit 2; }
CONTAINERS=()
cleanup(){ for container in "${CONTAINERS[@]:-}"; do docker rm -f "$container" >/dev/null 2>&1 || true; done; }
trap cleanup EXIT
run_engine(){
  local engine="$1" image="$2" client="$3" container="be-publication-snapshot-${1}-$RANDOM-$RANDOM"
  CONTAINERS+=("$container")
  if [[ "$engine" == mysql8 ]]; then
    docker run -d --name "$container" -e MYSQL_ROOT_PASSWORD=contract-root -e MYSQL_DATABASE=be_contract -p 127.0.0.1::3306 "$image" >/dev/null
  else
    docker run -d --name "$container" -e MARIADB_ROOT_PASSWORD=contract-root -e MARIADB_DATABASE=be_contract -p 127.0.0.1::3306 "$image" >/dev/null
  fi
  for attempt in {1..90}; do
    docker exec "$container" "$client" --protocol=TCP -h127.0.0.1 -uroot -pcontract-root -Nse 'SELECT 1' be_contract >/dev/null 2>&1 && break
    [[ "$attempt" -lt 90 ]] || { docker logs "$container" >&2 || true; return 1; }
    sleep 1
  done
  while read -r file; do docker exec -i "$container" "$client" --protocol=TCP -h127.0.0.1 -uroot -pcontract-root be_contract < "api/sql/$file"; done < <(python3 -c 'import json; print("\n".join(x["file"] for x in json.load(open("api/sql/000_manifest.json"))["migrations"]))')
  docker exec -i "$container" "$client" --protocol=TCP -h127.0.0.1 -uroot -pcontract-root be_contract < api/sql/013_submission_publication_snapshots.sql
  local port
  port="$(docker inspect -f '{{(index (index .NetworkSettings.Ports "3306/tcp") 0).HostPort}}' "$container")"
  PUBLICATION_SNAPSHOT_TEST_DSN="mysql:host=127.0.0.1;port=$port;dbname=be_contract;charset=utf8mb4" PUBLICATION_SNAPSHOT_TEST_USER=root PUBLICATION_SNAPSHOT_TEST_PASSWORD=contract-root php tests/publication_snapshot_mysql_contract_test.php
  docker rm -f "$container" >/dev/null
}
run_engine mysql8 mysql:8.0 mysql
run_engine mariadb114 mariadb:11.4 mariadb
echo 'Publication snapshot MySQL 8 / MariaDB 11.4 contracts: OK'
