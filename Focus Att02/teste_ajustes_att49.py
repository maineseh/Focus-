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

import app as appmod

app = appmod.app
app.testing = True


def raw_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def cria_usuario(nome, email, username):
    db = raw_db()
    cur = db.execute(
        "INSERT INTO usuarios (nome, email, senha, username) VALUES (?, ?, 'x', ?)",
        (nome, email, username),
    )
    db.commit()
    uid = cur.lastrowid
    db.close()
    return uid


def login_como(client, usuario_id):
    with client.session_transaction() as sess:
        sess["usuario_id"] = usuario_id


def falha(msg):
    print(f"FALHOU: {msg}")
    sys.exit(1)


def ok(msg):
    print(f"ok: {msg}")


# ---------------------------------------------------------------------
a = cria_usuario("Ana", "ana@teste.com", "ana")
b = cria_usuario("Bia", "bia@teste.com", "bia")

client_a = app.test_client()
client_b = app.test_client()
login_como(client_a, a)
login_como(client_b, b)

client_a.post("/comunidades/solicitar", data={"username": "bia"})
db = raw_db()
amizade_id = db.execute(
    "SELECT id FROM amizades WHERE solicitante_id=? AND destinatario_id=?", (a, b)
).fetchone()["id"]
db.close()
client_b.post(f"/comunidades/solicitacao/{amizade_id}/aceitar")

# ---- 1) Botão de enviar não é mais "submit" (sem submit nativo) ----
pagina_dm = client_a.get(f"/comunidades/dm/{b}").get_data(as_text=True)
if 'type="submit" class="com-btn small"><i class="fa-solid fa-paper-plane"' in pagina_dm:
    falha("botão de enviar da DM ainda é type=submit")
if 'type="button" class="com-btn small com-btn-enviar"' not in pagina_dm:
    falha("botão de enviar da DM não virou type=button com-btn-enviar")
ok("botão de enviar da DM não é mais um submit nativo")

client_a.post("/comunidades/grupos/criar", data={"nome": "Grupo Teste"})
db = raw_db()
grupo_id = db.execute("SELECT id FROM grupos WHERE nome='Grupo Teste'").fetchone()["id"]
db.close()
pagina_grupo = client_a.get(f"/comunidades/grupo/{grupo_id}").get_data(as_text=True)
if 'type="button" class="com-btn small com-btn-enviar"' not in pagina_grupo:
    falha("botão de enviar do grupo não virou type=button com-btn-enviar")
ok("botão de enviar do grupo não é mais um submit nativo")

# ---- 2) comunidades.js trata clique + Enter em vez de só 'submit' ----
with open("static/js/comunidades.js", encoding="utf-8") as f:
    js = f.read()
if "keydown" not in js or "tentarEnviar" not in js:
    falha("comunidades.js não tem os novos handlers de clique/Enter")
ok("comunidades.js escuta clique no botão e Enter no campo diretamente")

# ---- 3) Cronômetro personalizado existe em disciplina.html ----
pagina_disc = client_a.get("/disciplina/1").get_data(as_text=True) if False else None
with open("templates/disciplina.html", encoding="utf-8") as f:
    disc_html = f.read()
if "abrirPersonalizado" not in disc_html or "confirmarPersonalizado" not in disc_html:
    falha("cronômetro personalizado não foi adicionado em disciplina.html")
ok("cronômetro personalizado (minutos livres) adicionado")

# ---- 4) Zoom no gráfico de desempenho ----
with open("templates/meu_desempenho.html", encoding="utf-8") as f:
    desemp_html = f.read()
if "chartjs-plugin-zoom" not in desemp_html or "resetarZoomDesempenho" not in desemp_html:
    falha("zoom do gráfico de desempenho não foi adicionado")
ok("zoom (scroll/pinça + resetar) adicionado no gráfico de desempenho")

# ---- 5) Editor de foto (recorte/zoom estilo Discord) plugado ----
with open("templates/perfil_editar.html", encoding="utf-8") as f:
    perfil_html = f.read()
if "image-cropper.js" not in perfil_html or "abrirRecorteImagem" not in perfil_html:
    falha("editor de recorte não está plugado na edição de perfil")
ok("editor de recorte (zoom/arraste) plugado na foto de perfil")

if "image-cropper.js" not in pagina_grupo or "abrirRecorteImagem" not in pagina_grupo:
    falha("editor de recorte não está plugado no ícone do grupo")
ok("editor de recorte (zoom/arraste) plugado no ícone do grupo")

if not os.path.exists("static/js/image-cropper.js") or not os.path.exists("static/css/image-cropper.css"):
    falha("arquivos do componente de recorte não existem")
ok("componente de recorte de imagem existe como arquivo compartilhado")

# ---- 6) Lista de "adicionar amigo" no grupo mostra foto ----
c = cria_usuario("Caio", "caio@teste.com", "caio")
client_c = app.test_client()
login_como(client_c, c)
client_a.post("/comunidades/solicitar", data={"username": "caio"})
db = raw_db()
amizade_ac = db.execute("SELECT id FROM amizades WHERE solicitante_id=? AND destinatario_id=?", (a, c)).fetchone()["id"]
db.close()
client_c.post(f"/comunidades/solicitacao/{amizade_ac}/aceitar")

pagina_grupo2 = client_a.get(f"/comunidades/grupo/{grupo_id}").get_data(as_text=True)
if 'id="listaAmigosParaAdicionar"' not in pagina_grupo2:
    falha("lista de adicionar amigo com foto não existe")
if "com-avatar" not in pagina_grupo2.split('id="listaAmigosParaAdicionar"')[1].split("</form>")[0]:
    falha("lista de adicionar amigo não mostra foto de perfil")
ok("lista de 'adicionar amigo' no grupo mostra foto de perfil de cada amigo")

# amigo que já é membro não aparece mais na lista de "adicionar"
client_a.post(f"/comunidades/grupo/{grupo_id}/membro/adicionar", data={"usuario_id": str(c)})
pagina_grupo3 = client_a.get(f"/comunidades/grupo/{grupo_id}").get_data(as_text=True)
if "Adicionar amigo" in pagina_grupo3 and "@caio" in pagina_grupo3.split("Adicionar amigo")[1].split("</form>")[0]:
    falha("Caio (já membro) ainda aparece na lista de adicionar amigo")
ok("amigo que já é membro não aparece mais na lista de adicionar")

print("\nTODOS OS TESTES PASSARAM")
