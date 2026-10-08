import os, sys, io, sqlite3
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


def imagem_falsa(nome="foto.png"):
    conteudo = (
        b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01"
        b"\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\x0cIDATx\x9cc```\x00\x00"
        b"\x00\x04\x00\x01\xf6\x178U\x00\x00\x00\x00IEND\xaeB`\x82"
    )
    return (io.BytesIO(conteudo), nome)


def cria_usuario(nome, email, username):
    db = raw_db()
    uid = db.execute("INSERT INTO usuarios (nome, email, senha, username) VALUES (?,?,?,?)",
                      (nome, email, "x", username)).lastrowid
    db.commit()
    db.close()
    return uid


def cliente_de(uid):
    c = app.test_client()
    with c.session_transaction() as sess:
        sess["usuario_id"] = uid
    return c


def faz_amigos(u1, u2):
    db = raw_db()
    db.execute("INSERT INTO amizades (solicitante_id, destinatario_id, status) VALUES (?,?,'aceito')", (u1, u2))
    db.commit()
    db.close()


criador = cria_usuario("Criadora", "criadora@teste.com", "criadora")
admin = cria_usuario("Admin", "admin@teste.com", "admin_user")
segundo_admin = cria_usuario("SegundoAdmin", "segundoadmin@teste.com", "segundo_admin")
membro = cria_usuario("Membro", "membro@teste.com", "membro_user")
outro = cria_usuario("Outro", "outro@teste.com", "outro_user")

c_criador = cliente_de(criador)
c_admin = cliente_de(admin)
c_segundo_admin = cliente_de(segundo_admin)
c_membro = cliente_de(membro)
c_outro = cliente_de(outro)

faz_amigos(criador, admin)
faz_amigos(criador, segundo_admin)
faz_amigos(criador, membro)
faz_amigos(criador, outro)
faz_amigos(admin, outro)

resp = c_criador.post("/comunidades/grupos/criar", data={"nome": "Grupo Teste", "membros": [str(admin), str(segundo_admin), str(membro)]}, follow_redirects=True)
db = raw_db()
grupo_id = db.execute("SELECT id FROM grupos WHERE nome='Grupo Teste'").fetchone()["id"]
db.close()

# --- papel do criador ---
db = raw_db()
papel_criador = db.execute("SELECT papel FROM grupo_membros WHERE grupo_id=? AND usuario_id=?", (grupo_id, criador)).fetchone()["papel"]
db.close()
if papel_criador != "criador":
    falha(f"criador do grupo deveria ter papel 'criador', tem {papel_criador!r}")
ok("criador do grupo recebe papel 'criador' (não 'admin')")

# --- conceder admin: só criador pode ---
r = c_membro.post(f"/comunidades/grupo/{grupo_id}/membro/{outro}/conceder-admin")
if r.status_code != 403:
    falha("membro comum conseguiu conceder admin (deveria ser 403)")
ok("membro comum não pode conceder admin")

# criador promove o usuário "admin" a admin do grupo
r = c_criador.post(f"/comunidades/grupo/{grupo_id}/membro/{admin}/conceder-admin", follow_redirects=True)
db = raw_db()
papel_admin = db.execute("SELECT papel FROM grupo_membros WHERE grupo_id=? AND usuario_id=?", (grupo_id, admin)).fetchone()["papel"]
db.close()
if papel_admin != "admin":
    falha(f"criador não conseguiu conceder admin, papel ficou {papel_admin!r}")
ok("criador consegue conceder admin a um membro")

# admin comum (não-criador) não pode conceder admin a ninguém
r = c_admin.post(f"/comunidades/grupo/{grupo_id}/membro/{outro}/conceder-admin")
if r.status_code != 403:
    falha("admin comum conseguiu conceder admin (só o criador pode)")
ok("admin comum (não-criador) não pode conceder admin a outros")

# --- adicionar membro: admin comum pode ---
r = c_admin.post(f"/comunidades/grupo/{grupo_id}/membro/adicionar", data={"usuario_id": outro}, follow_redirects=True)
db = raw_db()
tem_outro = db.execute("SELECT 1 FROM grupo_membros WHERE grupo_id=? AND usuario_id=?", (grupo_id, outro)).fetchone()
db.close()
if not tem_outro:
    falha("admin comum não conseguiu adicionar membro")
ok("admin comum consegue adicionar membro")

# --- admin comum NÃO pode remover outro admin, só o criador pode ---
c_criador.post(f"/comunidades/grupo/{grupo_id}/membro/{segundo_admin}/conceder-admin")

r = c_admin.post(f"/comunidades/grupo/{grupo_id}/membro/{segundo_admin}/remover")
if r.status_code != 403:
    falha("admin comum conseguiu remover outro admin (deveria ser 403)")
ok("admin comum não pode remover outro admin")

r = c_criador.post(f"/comunidades/grupo/{grupo_id}/membro/{segundo_admin}/remover", follow_redirects=True)
db = raw_db()
ainda_tem = db.execute("SELECT 1 FROM grupo_membros WHERE grupo_id=? AND usuario_id=?", (grupo_id, segundo_admin)).fetchone()
db.close()
if ainda_tem:
    falha("criador não conseguiu remover um admin")
ok("criador consegue remover um admin")

# --- ninguém remove o criador ---
r = c_admin.post(f"/comunidades/grupo/{grupo_id}/membro/{criador}/remover")
if r.status_code != 403:
    falha("conseguiram remover o criador do grupo (nunca deveria ser possível)")
ok("ninguém consegue remover o criador do grupo")

# --- excluir grupo: só criador ---
r = c_admin.post(f"/comunidades/grupo/{grupo_id}/excluir")
if r.status_code != 403:
    falha("admin comum conseguiu excluir o grupo (deveria ser só o criador)")
ok("admin comum não pode excluir o grupo")

# --- castigo (mute) ---
r = c_admin.post(f"/comunidades/grupo/{grupo_id}/membro/{outro}/castigo", data={"minutos": "30"}, follow_redirects=True)
db = raw_db()
castigo = db.execute("SELECT expira_em FROM grupo_castigos WHERE grupo_id=? AND usuario_id=?", (grupo_id, outro)).fetchone()
db.close()
if not castigo:
    falha("admin não conseguiu colocar um membro de castigo")
ok("admin consegue colocar um membro comum de castigo")

r = c_outro.post(f"/comunidades/grupo/{grupo_id}/mensagem", data={"texto": "oi"})
corpo = r.get_json()
if corpo.get("ok"):
    falha("membro de castigo conseguiu mandar mensagem")
ok("membro de castigo não consegue mandar mensagem")

r = c_criador.post(f"/comunidades/grupo/{grupo_id}/membro/{outro}/perdoar", follow_redirects=True)
db = raw_db()
castigo = db.execute("SELECT 1 FROM grupo_castigos WHERE grupo_id=? AND usuario_id=?", (grupo_id, outro)).fetchone()
db.close()
if castigo:
    falha("perdoar não removeu o castigo")
ok("perdoar remove o castigo e o membro volta a poder falar")

r = c_outro.post(f"/comunidades/grupo/{grupo_id}/mensagem", data={"texto": "voltei"})
corpo = r.get_json()
if not corpo.get("ok"):
    falha("membro perdoado ainda não conseguiu mandar mensagem")
ok("membro perdoado consegue mandar mensagem de novo")

# --- bloquear chat ---
r = c_admin.post(f"/comunidades/grupo/{grupo_id}/bloquear-chat", follow_redirects=True)
r2 = c_outro.post(f"/comunidades/grupo/{grupo_id}/mensagem", data={"texto": "alguém aí?"})
corpo2 = r2.get_json()
if corpo2.get("ok"):
    falha("mensagem de membro comum passou com o chat bloqueado")
ok("chat bloqueado impede membro comum de mandar mensagem")

r3 = c_admin.post(f"/comunidades/grupo/{grupo_id}/mensagem", data={"texto": "aviso importante"})
corpo3 = r3.get_json()
if not corpo3.get("ok"):
    falha("admin não conseguiu mandar mensagem com o chat bloqueado (admin deveria poder)")
ok("admin consegue mandar mensagem mesmo com o chat bloqueado")

# --- excluir mensagem ---
msg_id = corpo3["mensagem"]["id"]
r4 = c_admin.post(f"/comunidades/grupo/{grupo_id}/mensagem/{msg_id}/excluir")
db = raw_db()
existe_msg = db.execute("SELECT 1 FROM mensagens WHERE id=?", (msg_id,)).fetchone()
db.close()
if existe_msg:
    falha("admin não conseguiu excluir a mensagem")
ok("admin consegue excluir mensagem")

r5 = c_outro.post(f"/comunidades/grupo/{grupo_id}/mensagem/1/excluir")
if r5.status_code != 403:
    falha("membro comum conseguiu excluir mensagem")
ok("membro comum não pode excluir mensagem")

print("\nTODOS OS TESTES PASSARAM")
