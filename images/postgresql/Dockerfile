FROM postgres:latest

# Install required extensions pg_repack
RUN set -xe; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
    "postgresql-${PG_MAJOR}-repack" \
    ; \
    rm -fr /tmp/* ; \
    rm -rf /var/lib/apt/lists/*;
