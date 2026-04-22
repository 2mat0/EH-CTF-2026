import socket
import os
import pty
import select

HOST = "0.0.0.0"
PORT = int(os.getenv("PORT", 8000))

def handle_client(conn):
    conn.send(b"You wake up in a locked prison cell...\n")
    conn.send(b"Something feels off. YOU NEED TO GET OUT OF HERE\n")
    conn.send(b"You should start looking around in the cell.\n\n")

    pid, fd = pty.fork()
    if pid == 0:
        os.chdir("/cell")
        os.execv("/bin/bash", ["/bin/bash"])
    else:
        while True:
            r, _, _ = select.select([conn, fd], [], [])

            if conn in r:
                data = conn.recv(1024)
                if not data:
                    break
                os.write(fd, data)

            if fd in r:
                try:
                    output = os.read(fd, 1024)
                    if output:
                        conn.send(output)
                except OSError:
                    break

def main():
    s = socket.socket()
    s.bind((HOST, PORT))
    s.listen(5)

    while True:
        conn, _ = s.accept()
        handle_client(conn)
        conn.close()

if __name__ == "__main__":
    main()
