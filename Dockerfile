FROM ghcr.io/nfra-project/kickstart-flavor-php:8.1
ENV DEV_CONTAINER_NAME="rudl-cert-issuer"
ENV DEV_MODE="0"

ADD / /opt
RUN ["bash", "-c",  "chown -R user /opt"]
RUN ["/kickstart/run/entrypoint.sh", "build"]

ENTRYPOINT ["/kickstart/run/entrypoint.sh", "standalone"]
