import os
import re
import sqlite3
import sys

os.environ["FOCUS_SECRET_KEY"] = "teste"
sys.path.insert(0, ".")

DB_PATH = os.path.join("instance", "focus.db")
for ext in ("", "-wal", "-shm"):
    p = DB_PATH + ext
    if os.path.exists(p):
        os.remove(p)

import app as appmod

app = appmod.app
app.testing = True


def raw_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def falha(msg):
    print(f"FALHOU: {msg}")
    sys.exit(1)


def ok(msg):
    print(f"ok: {msg}")


def marcador(html):
    m = re.search(r'<i id="focus-onde-parei"([^>]*)>', html)
    if not m:
        return None
    attrs = dict(re.findall(r'data-(\w+)="([^"]*)"', m.group(1)))
    return attrs


db = raw_db()
uid = db.execute("INSERT INTO usuarios (nome, email, senha) VALUES ('Ana','ana@teste.com','x')").lastrowid
amigo = db.execute("INSERT INTO usuarios (nome, email, senha) VALUES ('Bia','bia@teste.com','x')").lastrowid
db.commit()
db.close()

client = app.test_client()
with client.session_transaction() as sess:
    sess["usuario_id"] = uid

# --- dados: módulo, disciplina, grupo e amizade (DM) ---
client.post("/meus_estudos/modulo", data={"nome": "Vestibular"})
db = raw_db()
mod_id = db.execute("SELECT id FROM modulos WHERE id_usuario=?", (uid,)).fetchone()["id"]
db.close()
client.post(f"/meus_estudos/modulo/{mod_id}/disciplina", data={"nome": "Biologia", "dificuldade": "1"})
db = raw_db()
disc_id = db.execute("SELECT id FROM disciplinas WHERE id_usuario=?", (uid,)).fetchone()["id"]
db.execute(
    "INSERT INTO amizades (solicitante_id, destinatario_id, status) VALUES (?, ?, 'aceito')",
    (uid, amigo),
)
db.commit()
db.close()
resp = client.post("/comunidades/grupos/criar", data={"nome": "Grupo X"})
grupo_id = int(resp.headers["Location"].rstrip("/").split("/")[-1])

# --- páginas que lembram onde a pessoa parou: marcador com a URL do servidor ---
casos = [
    (f"/meus_estudos/modulo/{mod_id}", "estudos", f"/meus_estudos/modulo/{mod_id}"),
    (f"/meus_estudos/disciplina/{disc_id}", "estudos", f"/meus_estudos/disciplina/{disc_id}"),
    (f"/comunidades/dm/{amigo}", "comunidades", f"/comunidades/dm/{amigo}"),
    (f"/comunidades/grupo/{grupo_id}", "comunidades", f"/comunidades/grupo/{grupo_id}"),
]
for url, secao, esperado in casos:
    html = client.get(url).get_data(as_text=True)
    m = marcador(html)
    if m is None:
        falha(f"{url} não renderizou o marcador focus-onde-parei")
    if m.get("secao") != secao or m.get("url") != esperado:
        falha(f"{url}: marcador errado {m!r}, esperado secao={secao!r} url={esperado!r}")
    if "window.location.pathname+window.location.search" in html:
        falha(f"{url} ainda grava a parada via window.location (atrasado no htmx)")
    ok(f"{url} -> marcador {secao} com a própria URL vinda do servidor")

# query string é preservada (ex: origem da DM) e sem '?' sobrando quando não há query
html = client.get(f"/comunidades/dm/{amigo}?origem=/comunidades").get_data(as_text=True)
m = marcador(html)
if m.get("url") != f"/comunidades/dm/{amigo}?origem=/comunidades":
    falha(f"query string não preservada no marcador: {m!r}")
ok("marcador preserva a query string quando existe")

# --- páginas de lista zeram a seção ---
for url, secao in (("/meus_estudos", "estudos"), ("/comunidades", "comunidades")):
    m = marcador(client.get(url).get_data(as_text=True))
    if m is None or m.get("secao") != secao or m.get("url") != "":
        falha(f"{url} deveria ter marcador vazio pra zerar {secao}: {m!r}")
    ok(f"{url} tem marcador vazio (zera o onde parei de {secao})")

# --- login limpa a memória ---
html = app.test_client().get("/login").get_data(as_text=True)
if "removeItem('focus_ultimo_estudos')" not in html or "removeItem('focus_ultimo_comunidades')" not in html:
    falha("login.html não limpa as chaves focus_ultimo_*")
ok("login limpa o onde parei (não vaza de uma conta pra outra)")

# --- spa.js ---
js = open("static/js/spa.js", encoding="utf-8").read()
for trecho in ("focus-onde-parei", "htmx:historyRestore", "focusAtualizarOndeParei"):
    if trecho not in js:
        falha(f"spa.js sem {trecho!r}")
ok("spa.js lê o marcador, cobre histórico e limpa parada inválida")

print("\nTODOS OS TESTES PASSARAM")
