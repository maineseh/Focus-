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

import app as appmod  # cria o banco (init_app roda no import)

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
c = cria_usuario("Caio", "caio@teste.com", "caio")

client_a = app.test_client()
client_b = app.test_client()
client_c = app.test_client()
login_como(client_a, a)
login_como(client_b, b)
login_como(client_c, c)

# ---- 1) Amizade Ana <-> Bia, depois auto-bloqueio some com a amizade -----
client_a.post("/comunidades/solicitar", data={"username": "bia"})
db = raw_db()
amizade_id = db.execute(
    "SELECT id FROM amizades WHERE solicitante_id=? AND destinatario_id=?", (a, b)
).fetchone()["id"]
db.close()
client_b.post(f"/comunidades/solicitacao/{amizade_id}/aceitar")

db = raw_db()
aceita = db.execute("SELECT status FROM amizades WHERE id=?", (amizade_id,)).fetchone()
db.close()
if aceita["status"] != "aceito":
    falha("amizade não ficou 'aceito' depois de aceitar")
ok("pedido de amizade Ana -> Bia aceito")

for _ in range(3):
    resp = client_a.post(f"/comunidades/dm/{b}/mensagem", data={"texto": "qual seu endereço mesmo?"})
    if resp.status_code != 400:
        falha(f"mensagem grave devia ser bloqueada (400), veio {resp.status_code}")

db = raw_db()
bloqueio = db.execute(
    "SELECT 1 FROM bloqueios WHERE usuario_id=? AND bloqueado_id=? AND motivo='automatico'", (b, a)
).fetchone()
amizade_ainda = db.execute(
    "SELECT 1 FROM amizades WHERE (solicitante_id=? AND destinatario_id=?) OR (solicitante_id=? AND destinatario_id=?)",
    (a, b, b, a),
).fetchone()
db.close()
if not bloqueio:
    falha("bloqueio automático não foi criado depois de 3 alertas graves")
ok("bloqueio automático criado após 3 alertas graves")
if amizade_ainda:
    falha("amizade Ana/Bia continua existindo depois do bloqueio automático (bug 2 não corrigido)")
ok("amizade removida junto com o bloqueio automático (bug 2 corrigido)")

pagina_b = client_b.get("/comunidades").get_data(as_text=True)
painel_amigos = pagina_b.split('data-painel="amigos"')[1].split('data-painel="pedidos"')[0]
if "@ana" in painel_amigos:
    falha("Ana ainda aparece na aba 'Amigos' de Bia depois do bloqueio automático")
ok("Ana não aparece mais na aba 'Amigos' de Bia (some da lista quando bloqueado)")
painel_bloqueados = pagina_b.split('data-painel="bloqueados"')[1]
if "@ana" not in painel_bloqueados:
    falha("Ana devia aparecer na aba 'Bloqueados' de Bia")
ok("Ana aparece corretamente na aba 'Bloqueados' de Bia")

# ---- 2) Amizade Ana <-> Caio pra testar grupo -----
client_a.post("/comunidades/solicitar", data={"username": "caio"})
db = raw_db()
amizade_ac = db.execute(
    "SELECT id FROM amizades WHERE solicitante_id=? AND destinatario_id=?", (a, c)
).fetchone()["id"]
db.close()
client_c.post(f"/comunidades/solicitacao/{amizade_ac}/aceitar")

resp = client_a.post("/comunidades/grupos/criar", data={"nome": "Grupo Teste", "membros": [str(c)]}, follow_redirects=False)
db = raw_db()
grupo_id = db.execute("SELECT id FROM grupos WHERE nome='Grupo Teste'").fetchone()["id"]
membro_c = db.execute("SELECT 1 FROM grupo_membros WHERE grupo_id=? AND usuario_id=?", (grupo_id, c)).fetchone()
db.close()
if not membro_c:
    falha("Caio não entrou no grupo na criação")
ok("grupo criado com Caio como membro")

# ---- 3) Caio manda 5 mensagens graves no grupo -> kick + ban -----
for _ in range(5):
    resp = client_c.post(f"/comunidades/grupo/{grupo_id}/mensagem", data={"texto": "vamos nos encontrar pessoalmente escondido"})
    if resp.status_code != 400:
        falha(f"mensagem grave em grupo devia ser bloqueada (400), veio {resp.status_code}")

db = raw_db()
ainda_membro = db.execute("SELECT 1 FROM grupo_membros WHERE grupo_id=? AND usuario_id=?", (grupo_id, c)).fetchone()
banido = db.execute("SELECT 1 FROM grupo_banidos WHERE grupo_id=? AND usuario_id=?", (grupo_id, c)).fetchone()
db.close()
if ainda_membro:
    falha("Caio continua membro do grupo depois de 5 alertas graves")
ok("Caio removido do grupo após 5 alertas graves (escopo por grupo)")
if not banido:
    falha("Caio não ficou registrado em grupo_banidos (bug 4 não corrigido)")
ok("Caio ficou banido do grupo (bug 4 corrigido)")

# readicionar amigo banido deve falhar
resp = client_a.post(f"/comunidades/grupo/{grupo_id}/membro/adicionar", data={"usuario_id": str(c)}, follow_redirects=True)
db = raw_db()
readicionado = db.execute("SELECT 1 FROM grupo_membros WHERE grupo_id=? AND usuario_id=?", (grupo_id, c)).fetchone()
db.close()
if readicionado:
    falha("admin conseguiu readicionar membro banido")
ok("readição de membro banido foi bloqueada")
if "banida" not in resp.get_data(as_text=True) and "banido" not in resp.get_data(as_text=True):
    falha("mensagem de erro de banimento não aparece na página do grupo")
ok("mensagem de erro do banimento aparece na página")

# desbanir e readicionar deve funcionar
client_a.post(f"/comunidades/grupo/{grupo_id}/banido/{c}/desbanir")
db = raw_db()
ainda_banido = db.execute("SELECT 1 FROM grupo_banidos WHERE grupo_id=? AND usuario_id=?", (grupo_id, c)).fetchone()
db.close()
if ainda_banido:
    falha("desbanir não removeu o registro de grupo_banidos")
ok("desbanir removeu o registro de banimento")

client_a.post(f"/comunidades/grupo/{grupo_id}/membro/adicionar", data={"usuario_id": str(c)})
db = raw_db()
readicionado2 = db.execute("SELECT 1 FROM grupo_membros WHERE grupo_id=? AND usuario_id=?", (grupo_id, c)).fetchone()
db.close()
if not readicionado2:
    falha("depois de desbanir, não deu pra readicionar Caio")
ok("depois de desbanir, Caio pôde ser readicionado")

# ---- 4) Limite de tamanho de mensagem no servidor -----
texto_gigante = "a" * 5000
resp = client_a.post(f"/comunidades/dm/{c}/mensagem", data={"texto": texto_gigante})
# Ana e Caio não são amigos (nunca pediram), então isso devia dar 403 --
# troquei pra usar Ana/Bia que JÁ foram bloqueados automaticamente; teste
# de tamanho tem que ser numa DM válida. Vamos usar um 4º par de amigos.
d = cria_usuario("Duda", "duda@teste.com", "duda")
client_d = app.test_client()
login_como(client_d, d)
client_a.post("/comunidades/solicitar", data={"username": "duda"})
db = raw_db()
amizade_ad = db.execute("SELECT id FROM amizades WHERE solicitante_id=? AND destinatario_id=?", (a, d)).fetchone()["id"]
db.close()
client_d.post(f"/comunidades/solicitacao/{amizade_ad}/aceitar")

resp = client_a.post(f"/comunidades/dm/{d}/mensagem", data={"texto": texto_gigante})
if resp.status_code != 200:
    falha(f"mensagem grande devia ser aceita (só cortada), veio {resp.status_code}: {resp.get_data(as_text=True)}")
db = raw_db()
tam = db.execute(
    "SELECT LENGTH(conteudo) AS t FROM mensagens WHERE remetente_id=? AND destinatario_id=? ORDER BY id DESC LIMIT 1",
    (a, d),
).fetchone()["t"]
db.close()
if tam != 1000:
    falha(f"mensagem devia ter sido cortada em 1000 caracteres, ficou com {tam}")
ok("mensagem de texto gigante foi cortada em 1000 caracteres no servidor (bug 7 corrigido)")

# ---- 5) Linkificação no carregamento inicial (grupo com links liberados) -----
client_a.post(f"/comunidades/grupo/{grupo_id}/editar", data={"nome": "Grupo Teste", "descricao": "", "permitir_links": "on"})
client_a.post(f"/comunidades/grupo/{grupo_id}/mensagem", data={"texto": "olha esse site https://exemplo.com/pagina legal"})
pagina_grupo = client_a.get(f"/comunidades/grupo/{grupo_id}").get_data(as_text=True)
if '<a href="https://exemplo.com/pagina"' not in pagina_grupo:
    falha("link não ficou clicável na renderização inicial do Jinja (bug 5 não corrigido)")
ok("link enviado aparece clicável já no carregamento inicial da página (bug 5 corrigido)")

# ---- 6) Perfil de usuário bloqueado não pode ser visto (bug 8) -----
resp = client_a.get(f"/comunidades/perfil/{b}")
if resp.status_code != 403:
    falha(f"Ana devia estar impedida de ver o perfil de Bia (bloqueio automático), veio {resp.status_code}")
ok("perfil de contato bloqueado retorna 403 (bug 8 corrigido)")

# ---- 7) Alertas de segurança + marcar como revisado (bug 6) -----
db = raw_db()
resp_id = db.execute("INSERT INTO responsaveis (email, senha_hash) VALUES (?, 'x')", ("mae@teste.com",)).lastrowid
db.execute(
    "INSERT INTO vinculos_parentais (id_responsavel, id_usuario, token, status) VALUES (?, ?, 'tok123', 'ativo')",
    (resp_id, b),
)
db.commit()
alerta_id = db.execute(
    "SELECT id FROM alertas_seguranca WHERE id_usuario=? AND autor_id=? ORDER BY id DESC LIMIT 1", (b, a)
).fetchone()["id"]
db.close()

client_resp = app.test_client()
with client_resp.session_transaction() as sess:
    sess["responsavel_email"] = "mae@teste.com"

pagina_resp = client_resp.get(f"/responsavel/filho/{b}").get_data(as_text=True)
if f"/responsavel/filho/{b}/alerta/{alerta_id}/revisar" not in pagina_resp:
    falha("botão de marcar como revisado não aparece no painel do responsável")
ok("botão de marcar alerta como revisado aparece no painel")

client_resp.post(f"/responsavel/filho/{b}/alerta/{alerta_id}/revisar")
db = raw_db()
revisado = db.execute("SELECT revisado FROM alertas_seguranca WHERE id=?", (alerta_id,)).fetchone()["revisado"]
db.close()
if not revisado:
    falha("alerta não foi marcado como revisado depois do POST (bug 6 não corrigido)")
ok("alerta marcado como revisado com sucesso (bug 6 corrigido)")

print("\nTODOS OS TESTES PASSARAM")
