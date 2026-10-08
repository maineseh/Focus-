import os
import sqlite3
import sys

os.environ["FOCUS_SECRET_KEY"] = "teste"
sys.path.insert(0, ".")

DB_PATH = os.path.join("instance", "focus.db")
for ext in ("", "-wal", "-shm"):
    p = DB_PATH + ext
    if os.path.exists(p):
        os.remove(p)

import io

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


def imagem_falsa(nome="foto.png"):
    conteudo = (
        b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01"
        b"\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\x0cIDATx\x9cc```\x00\x00"
        b"\x00\x04\x00\x01\xf6\x178U\x00\x00\x00\x00IEND\xaeB`\x82"
    )
    return (io.BytesIO(conteudo), nome)


db = raw_db()
uid = db.execute("INSERT INTO usuarios (nome, email, senha) VALUES ('Ana','ana@teste.com','x')").lastrowid
db.commit()
db.close()

client = app.test_client()
with client.session_transaction() as sess:
    sess["usuario_id"] = uid

# a pedido do usuário, a tela de completar perfil (cadastro) voltou a
# oferecer só os 3 avatares prontos — sem a opção de escolher qualquer
# foto da galeria (essa opção continua existindo em /perfil/editar).
pagina = client.get("/setup_perfil").get_data(as_text=True)
if "fotoArquivoInput" in pagina:
    falha("setup_perfil.html ainda tem a opção de galeria (deveria ter sido removida)")
ok("tela de completar perfil (setup_perfil) não oferece mais upload de galeria, só os 3 avatares")

if 'required' in pagina.split('name="foto_perfil"')[1].split(">")[0]:
    falha("radio de avatar ainda é required")
ok("radio de avatar não é required (mantém compatibilidade com o backend)")

resp = client.post(
    "/setup_perfil",
    data={"username": "ana_nova", "foto_arquivo": imagem_falsa()},
    content_type="multipart/form-data",
    follow_redirects=True,
)
db = raw_db()
foto = db.execute("SELECT foto_perfil FROM usuarios WHERE id=?", (uid,)).fetchone()["foto_perfil"]
db.close()
if not foto or "uploads/perfil/" not in foto:
    falha(f"upload de foto no setup_perfil não foi salvo, foto_perfil={foto!r}")
ok("backend do setup_perfil ainda aceita foto_arquivo (upload fica só via /perfil/editar na UI)")

print("\nTODOS OS TESTES PASSARAM")
