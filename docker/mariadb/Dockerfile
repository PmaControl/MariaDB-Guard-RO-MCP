FROM mariadb:11.8

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl tar; \
    curl -fsSL "https://downloads.mysql.com/docs/sakila-db.tar.gz" -o /tmp/sakila-db.tar.gz; \
    tar -xzf /tmp/sakila-db.tar.gz -C /tmp; \
    cp /tmp/sakila-db/sakila-schema.sql /docker-entrypoint-initdb.d/01-sakila-schema.sql; \
    cp /tmp/sakila-db/sakila-data.sql /docker-entrypoint-initdb.d/02-sakila-data.sql; \
    rm -rf /var/lib/apt/lists/* /tmp/sakila-db /tmp/sakila-db.tar.gz
