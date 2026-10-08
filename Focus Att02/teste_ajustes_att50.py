import os
import re
import sqlite3
import sys
from datetime import date, timedelta

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


def humor_medio(html):
    m = re.search(r"<h3>\s*([\d.]+)\s*<span>/5</span>", html)
    return float(m.group(1)) if m else None


# ---------------------------------------------------------------------
a = cria_usuario("Ana", "ana@teste.com", "ana")
b = cria_usuario("Bia", "bia@teste.com", "bia")
client_a = app.test_client()
client_b = app.test_client()
login_como(client_a, a)
login_como(client_b, b)

# ---- 1) Humor médio não fica mais travado em 2 numa semana sem dados ----
html_vazio = client_a.get("/meu_desempenho").get_data(as_text=True)
humor_vazio = humor_medio(html_vazio)
if humor_vazio != 3.0:
    falha(f"humor médio numa semana sem nenhuma sessão de estudo devia ser 3.0, veio {humor_vazio}")
ok("humor médio de uma semana sem dados agora é 3.0 (neutro), não 2.0")

# painel do responsável tem que mostrar o mesmo número (mesma função reaproveitada)
db = raw_db()
resp_id = db.execute("INSERT INTO responsaveis (email, senha_hash) VALUES (?, 'x')", ("mae@teste.com",)).lastrowid
db.execute(
    "INSERT INTO vinculos_parentais (id_responsavel, id_usuario, token, status) VALUES (?, ?, 'tok', 'ativo')",
    (resp_id, a),
)
db.commit()
db.close()
client_resp = app.test_client()
with client_resp.session_transaction() as sess:
    sess["responsavel_email"] = "mae@teste.com"
pagina_resp = client_resp.get(f"/responsavel/filho/{a}").get_data(as_text=True)
if "2/5" in pagina_resp and "3/5" not in pagina_resp:
    falha("painel do responsável ainda mostra humor 2/5 com semana vazia")
ok("painel do responsável não mostra mais humor travado em 2")

# ---- 2) Um dia de estudo puxado sobe a média corretamente ----
db = raw_db()
mid = db.execute("INSERT INTO modulos (id_usuario, nome) VALUES (?, 'Mod')", (a,)).lastrowid
did = db.execute("INSERT INTO disciplinas (id_usuario, id_modulo, nome) VALUES (?, ?, 'Disc')", (a, mid)).lastrowid
hoje = date.today()
seg = hoje - timedelta(days=hoje.weekday())
db.execute(
    "INSERT INTO sessoes_estudo (id_disciplina, duracao_minutos, concluida_em) VALUES (?, ?, ?)",
    (did, 240, seg.isoformat() + " 10:00:00"),
)
db.commit()
db.close()
html_com_dado = client_a.get("/meu_desempenho").get_data(as_text=True)
humor_com_dado = humor_medio(html_com_dado)
if humor_com_dado is None or humor_com_dado <= 3.0:
    falha(f"um dia de estudo puxado devia subir a média acima de 3.0, veio {humor_com_dado}")
ok(f"um dia de estudo puxado sobe a média corretamente (ficou {humor_com_dado})")

# ---- 3) Modal de criar grupo NÃO seleciona amigos (removido a pedido do
#         usuário — ficava quebrado/apertado; adicionar amigo agora é só
#         depois, na tela do próprio grupo, que já tem essa lista funcionando) ----
client_a.post("/comunidades/solicitar", data={"username": "bia"})
db = raw_db()
amizade_id = db.execute(
    "SELECT id FROM amizades WHERE solicitante_id=? AND destinatario_id=?", (a, b)
).fetchone()["id"]
db.close()
client_b.post(f"/comunidades/solicitacao/{amizade_id}/aceitar")

pagina_com = client_a.get("/comunidades").get_data(as_text=True)
modal_novo_grupo = pagina_com.split('id="modalNovoGrupo"')[1].split("</form>")[0]
if "com-avatar" in modal_novo_grupo or 'name="membros"' in modal_novo_grupo:
    falha("modal de criar grupo ainda mostra a lista de amigos (deveria ter sido removida)")
ok("modal de criar grupo não mostra mais a lista de amigos (removida a pedido do usuário)")

# ---- 4) Ícone de grupo: sem presets de gato, só upload (com hidden pra preservar o atual) ----
client_a.post("/comunidades/grupos/criar", data={"nome": "Grupo Teste"})
db = raw_db()
grupo_id = db.execute("SELECT id FROM grupos WHERE nome='Grupo Teste'").fetchone()["id"]
db.close()
pagina_grupo = client_a.get(f"/comunidades/grupo/{grupo_id}").get_data(as_text=True)
modal_config = pagina_grupo.split('id="modalConfigGrupo"')[1]
if 'type="radio" name="foto"' in modal_config:
    falha("ícone de grupo ainda tem os radios de preset (gatos)")
ok("ícone de grupo não tem mais os presets de gato")
if 'type="hidden" name="foto"' not in modal_config:
    falha("falta o hidden que preserva o ícone atual quando não se envia arquivo novo")
ok("hidden que preserva o ícone atual está presente")

# o valor do hidden tem que refletir o ícone atual de verdade (não só existir)
db = raw_db()
db.execute("UPDATE grupos SET foto = 'uploads/grupo/atual123.png' WHERE id = ?", (grupo_id,))
db.commit()
db.close()
pagina_grupo2 = client_a.get(f"/comunidades/grupo/{grupo_id}").get_data(as_text=True)
modal_config2 = pagina_grupo2.split('id="modalConfigGrupo"')[1]
if 'name="foto" value="uploads/grupo/atual123.png"' not in modal_config2:
    falha("o hidden não reflete o ícone atual do grupo no HTML renderizado")
ok("o hidden reflete corretamente o ícone atual do grupo no HTML renderizado")

# salvar sem trocar o ícone não deve apagar um ícone já definido
db = raw_db()
db.execute("UPDATE grupos SET foto = 'uploads/grupo/existente.png' WHERE id = ?", (grupo_id,))
db.commit()
db.close()
client_a.post(f"/comunidades/grupo/{grupo_id}/editar", data={"nome": "Grupo Teste", "descricao": "", "foto": "uploads/grupo/existente.png"})
db = raw_db()
foto_depois = db.execute("SELECT foto FROM grupos WHERE id=?", (grupo_id,)).fetchone()["foto"]
db.close()
if foto_depois != "uploads/grupo/existente.png":
    falha(f"editar o grupo sem trocar o ícone apagou o ícone existente (ficou {foto_depois!r})")
ok("editar o grupo sem escolher novo arquivo preserva o ícone já definido")

print("\nTODOS OS TESTES PASSARAM")
