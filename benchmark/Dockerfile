ARG SHOPWARE_FRANKENPHP_IMAGE=ghcr.io/shopware/docker-base:8.4.28-frankenphp@sha256:3676a352c995ed07216d2b1dcae980e552955b7420d222db4bb191fa42dde9db
FROM ${SHOPWARE_FRANKENPHP_IMAGE}

USER root
RUN install-php-extensions pdo_pgsql
USER www-data
