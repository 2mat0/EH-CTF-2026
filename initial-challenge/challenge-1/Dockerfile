FROM ubuntu:20.04

RUN apt-get update && apt-get install -y gcc python3

RUN useradd -m player

WORKDIR /cell
COPY cell/ /cell/

COPY unlock.c /tmp/unlock.c
RUN gcc /tmp/unlock.c -o /opt/unlock

RUN chown root:root /opt/unlock && chmod 4755 /opt/unlock

RUN chown -R player:player /cell

RUN mkdir -p /var/prison && \
    echo "PrisonCTF{welcome_to_our_prison}" > /var/prison/exit.txt && \
    chown root:root /var/prison/exit.txt && \
    chmod 711 /var/prison/ && \
    chmod 400 /var/prison/exit.txt

COPY relay.py /relay.py

EXPOSE 8000

USER player
WORKDIR /cell

CMD ["python3", "/relay.py"]
