import io
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
from werkzeug.security import generate_password_hash

app = appmod.app
app.testing = True


def raw_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def cria_usuario(nome, email, username, senha="senha123"):
    db = raw_db()
    cur = db.execute(
        "INSERT INTO usuarios (nome, email, senha, username) VALUES (?, ?, ?, ?)",
        (nome, email, generate_password_hash(senha), username),
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


def imagem_falsa(nome="foto.png"):
    conteudo = (
        b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01"
        b"\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\x0cIDATx\x9cc```\x00\x00"
        b"\x00\x04\x00\x01\xf6\x178U\x00\x00\x00\x00IEND\xaeB`\x82"
    )
    return (io.BytesIO(conteudo), nome)


# ---------------------------------------------------------------------
a = cria_usuario("Ana", "ana@teste.com", "ana")
b = cria_usuario("Bia", "bia@teste.com", "bia")

client_a = app.test_client()
client_b = app.test_client()
login_como(client_a, a)
login_como(client_b, b)

# ---- 1) O form de mensagem não deve mais ser sequestrado pelo hx-boost ----
pagina_dm_precisa_amizade = client_a.get(f"/comunidades/perfil/{b}")  # só garante que rotas básicas seguem de pé
client_a.post("/comunidades/solicitar", data={"username": "bia"})
db = raw_db()
amizade_id = db.execute(
    "SELECT id FROM amizades WHERE solicitante_id=? AND destinatario_id=?", (a, b)
).fetchone()["id"]
db.close()
client_b.post(f"/comunidades/solicitacao/{amizade_id}/aceitar")

pagina_dm = client_a.get(f"/comunidades/dm/{b}").get_data(as_text=True)
if 'id="comForm" hx-boost="false"' not in pagina_dm:
    falha("form do chat (DM) não tem hx-boost=false — htmx ainda pode sequestrar o envio")
ok("form de mensagem da DM protegido com hx-boost=false")

# ---- 2) Card lateral "Sobre" e aviso de segurança sumiram da DM ----
if "com-aviso-seguranca" in pagina_dm:
    falha("aviso de segurança ainda aparece na DM")
ok("aviso de segurança removido da DM")
if ">Ver perfil</a>\n            </div>\n        </div>" in pagina_dm or "Vocês são amigos no Focus" in pagina_dm:
    falha("card lateral 'Sobre' ainda aparece na DM")
ok("card lateral 'Sobre' removido da DM")

# ---- 3) Grupo: form protegido, aviso removido, card de Membros continua ----
client_a.post("/comunidades/grupos/criar", data={"nome": "Grupo Teste"})
db = raw_db()
grupo_id = db.execute("SELECT id FROM grupos WHERE nome='Grupo Teste'").fetchone()["id"]
db.close()
pagina_grupo = client_a.get(f"/comunidades/grupo/{grupo_id}").get_data(as_text=True)
if 'id="comForm" hx-boost="false"' not in pagina_grupo:
    falha("form do chat (grupo) não tem hx-boost=false")
ok("form de mensagem do grupo protegido com hx-boost=false")
if "com-aviso-seguranca" in pagina_grupo:
    falha("aviso de segurança ainda aparece no grupo")
ok("aviso de segurança removido do grupo")
if "Membros" not in pagina_grupo:
    falha("card de Membros do grupo sumiu (não devia)")
ok("card de Membros do grupo continua existindo")

# ---- 4) Ordem das abas: Amigos, Grupos, Pedidos, Bloqueados ----
pagina_com = client_a.get("/comunidades").get_data(as_text=True)
pos_amigos = pagina_com.find('data-tab="amigos"')
pos_grupos = pagina_com.find('data-tab="grupos"')
pos_pedidos = pagina_com.find('data-tab="pedidos"')
pos_bloqueados = pagina_com.find('data-tab="bloqueados"')
if not (pos_amigos < pos_grupos < pos_pedidos < pos_bloqueados):
    falha(f"ordem das abas errada: amigos={pos_amigos} grupos={pos_grupos} pedidos={pos_pedidos} bloqueados={pos_bloqueados}")
ok("ordem das abas: Amigos, Grupos, Pedidos, Bloqueados")

# ---- 5) Link sem http/www também vira clicável (regex igual server/client) ----
client_a.post(f"/comunidades/grupo/{grupo_id}/editar", data={"nome": "Grupo Teste", "descricao": "", "permitir_links": "on"})
client_a.post(f"/comunidades/grupo/{grupo_id}/mensagem", data={"texto": "da uma olhada em focusapp.com depois"})
pagina_grupo2 = client_a.get(f"/comunidades/grupo/{grupo_id}").get_data(as_text=True)
if '<a href="https://focusapp.com"' not in pagina_grupo2:
    falha("link sem http/www não ficou clicável no servidor")
ok("link sem prefixo http/www fica clicável no servidor (consistente com o cliente agora)")

# ---- 6) Busca de amigos com foto ----
resp = client_a.get("/comunidades/buscar?q=bi")
dados = resp.get_json()
if not dados["resultados"] or dados["resultados"][0]["username"] != "bia":
    falha(f"busca de amigos não encontrou Bia: {dados}")
if not dados["resultados"][0]["ja_amigo"]:
    falha("busca deveria marcar Bia como já amiga")
if "foto" not in dados["resultados"][0]:
    falha("busca não retorna foto")
ok("busca ao vivo de amigos retorna username + foto + status de amizade")

# ---- 7) Revogar vínculo agora pede senha ----
resp_id_row = raw_db()
resp_id = resp_id_row.execute(
    "INSERT INTO responsaveis (email, senha_hash) VALUES (?, ?)",
    ("mae@teste.com", generate_password_hash("senhamae")),
).lastrowid
vinculo_id = resp_id_row.execute(
    "INSERT INTO vinculos_parentais (id_responsavel, id_usuario, token, status) VALUES (?, ?, 'tok1', 'ativo')",
    (resp_id, a),
).lastrowid
resp_id_row.commit()
resp_id_row.close()

client_a.post(f"/controle_parental/{vinculo_id}/revogar", data={"senha": "senhaerrada"})
db = raw_db()
ainda_ativo = db.execute("SELECT ativo FROM vinculos_parentais WHERE id=?", (vinculo_id,)).fetchone()["ativo"]
db.close()
if not ainda_ativo:
    falha("revogar com senha errada não deveria funcionar")
ok("revogar com senha errada foi rejeitado")

client_a.post(f"/controle_parental/{vinculo_id}/revogar", data={"senha": "senha123"})
db = raw_db()
ainda_ativo2 = db.execute("SELECT ativo FROM vinculos_parentais WHERE id=?", (vinculo_id,)).fetchone()["ativo"]
db.close()
if ainda_ativo2:
    falha("revogar com senha certa deveria funcionar")
ok("revogar com a senha certa do usuário funciona (bug 'removeu senha' corrigido)")

# ---- 8) Gerar novo link pra vínculo que nasceu de pedido do responsável ----
db = raw_db()
vinculo_sem_link_visivel = db.execute(
    "INSERT INTO vinculos_parentais (id_responsavel, id_usuario, token, status) VALUES (?, ?, ?, 'ativo')",
    (resp_id, a, "token-interno-nunca-mostrado"),
).lastrowid
db.commit()
db.close()

resp = client_a.post(f"/controle_parental/{vinculo_sem_link_visivel}/gerar_link", follow_redirects=True)
db = raw_db()
token_novo = db.execute("SELECT token FROM vinculos_parentais WHERE id=?", (vinculo_sem_link_visivel,)).fetchone()["token"]
db.close()
if not token_novo or token_novo == "token-interno-nunca-mostrado":
    falha("gerar novo link não trocou o token")
ok("gerar novo link funciona mesmo pra vínculo que nasceu de pedido do responsável (token interno nunca virava link)")
if "gerar_link" not in client_a.get("/controle_parental").get_data(as_text=True):
    falha("botão 'Gerar novo link' não aparece na página")
ok("botão 'Gerar novo link' aparece ao lado de Revogar")

# ---- 9) Upload de foto de perfil (qualquer imagem da galeria) ----
resp = client_a.post(
    "/perfil/editar",
    data={
        "nome": "Ana", "username": "ana", "pronomes": "", "bio": "",
        "foto_arquivo": imagem_falsa(),
    },
    content_type="multipart/form-data",
    follow_redirects=True,
)
db = raw_db()
foto_ana = db.execute("SELECT foto_perfil FROM usuarios WHERE id=?", (a,)).fetchone()["foto_perfil"]
db.close()
if not foto_ana or "uploads/perfil/" not in foto_ana:
    falha(f"upload de foto de perfil não foi salvo, foto_perfil={foto_ana!r}")
ok("upload de foto de perfil (qualquer imagem da galeria) funciona")

# ---- 10) Upload de ícone de grupo ----
resp = client_a.post(
    f"/comunidades/grupo/{grupo_id}/editar",
    data={"nome": "Grupo Teste", "descricao": "", "foto_arquivo": imagem_falsa("icone.png")},
    content_type="multipart/form-data",
    follow_redirects=True,
)
db = raw_db()
foto_grupo = db.execute("SELECT foto FROM grupos WHERE id=?", (grupo_id,)).fetchone()["foto"]
db.close()
if not foto_grupo or "uploads/grupo/" not in foto_grupo:
    falha(f"upload de ícone de grupo não foi salvo, foto={foto_grupo!r}")
ok("upload de ícone de grupo (galeria) funciona")

print("\nTODOS OS TESTES PASSARAM")
