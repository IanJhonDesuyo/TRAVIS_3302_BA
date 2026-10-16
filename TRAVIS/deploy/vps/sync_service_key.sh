#!/bin/sh
set -eu

deployment_dir=${1:-/opt/travis/current}
key_file="$deployment_dir/service_api.key.new"
env_file="$deployment_dir/.env"

test -f "$key_file"
test -f "$env_file"

key=$(tr -d '\r\n' < "$key_file")
test "${#key}" -ge 32

escaped_key=$(printf '%s' "$key" | sed 's/[&|]/\\&/g')
sed -i "s|^TRAVIS_SERVICE_API_KEY=.*|TRAVIS_SERVICE_API_KEY=$escaped_key|" "$env_file"
rm -f "$key_file"

