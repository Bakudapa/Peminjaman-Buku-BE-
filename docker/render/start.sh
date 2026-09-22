#!/bin/sh
set -e

envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

exec supervisord -c /etc/supervisord.conf