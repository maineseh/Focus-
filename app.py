import os
import secrets
import random
import math
import json
import urllib.request
import urllib.error
from datetime import datetime, timedelta
from functools import wraps
from urllib.parse import urlparse

from flask import Flask, render_template, request, redirect, url_for, session, flash, jsonify

from db import get_db, init_app
from werkzeug.security import generate_password_hash, check_password_hash

app = Flask(__name__)
app.secret_key = os.environ.get("FOCUS_SECRET_KEY", secrets.token_hex(32))
# Evita que o navegador guarde em cache versões antigas do CSS/JS — assim,
# toda atualização aparece na hora, sem precisar limpar o cache manualmente.
app.config["SEND_FILE_MAX_AGE_DEFAULT"] = 0
init_app(app)


# ---------------------------------------------------------------------------
# Auxiliares (equivalente às funções de conexao.php)
# ---------------------------------------------------------------------------

def verificar_auto_login():
    """Loga automaticamente o usuário se ele tiver o cookie 'lembrar_token'."""
    if "usuario_id" not in session:
        token = request.cookies.get("lembrar_token")
        if token:
            db = get_db()
            usuario = db.execute(
                "SELECT id, nome, username FROM usuarios WHERE remember_token = ?",
                (token,),
            ).fetchone()
            if usuario:
                session["usuario_id"] = usuario["id"]
                session["usuario_nome"] = usuario["nome"]
                if usuario["username"]:
                    session["usuario_username"] = usuario["username"]
                return True
    return False


def login_required(f):
    @wraps(f)
    def wrapper(*args, **kwargs):
        if "usuario_id" not in session and not verificar_auto_login():
            return redirect(url_for("login"))
        return f(*args, **kwargs)
    return wrapper


def usuario_atual():
    db = get_db()
    return db.execute(
        "SELECT * FROM usuarios WHERE id = ?", (session["usuario_id"],)
    ).fetchone()


# ---------------------------------------------------------------------------
# Rota raiz
# ---------------------------------------------------------------------------

@app.route("/")
def index():
    if "usuario_id" in session or verificar_auto_login():
        return redirect(url_for("dashboard"))
    return redirect(url_for("login"))


# ---------------------------------------------------------------------------
# Login / Cadastro / Logout
# ---------------------------------------------------------------------------

@app.route("/login", methods=["GET", "POST"])
def login():
    if "usuario_id" in session or verificar_auto_login():
        return redirect(url_for("dashboard"))

    erro = ""
    if request.method == "POST":
        login_informado = request.form.get("login", "").strip()
        senha = request.form.get("senha", "")
        lembrar = "lembrar" in request.form

        db = get_db()
        usuario = db.execute(
            "SELECT id, nome, username, senha FROM usuarios WHERE email = ? OR username = ?",
            (login_informado, login_informado),
        ).fetchone()

        if usuario and check_password_hash(usuario["senha"], senha):
            session["usuario_id"] = usuario["id"]
            session["usuario_nome"] = usuario["nome"]

            resp = None
            if not usuario["username"]:
                resp = redirect(url_for("setup_perfil"))
            else:
                session["usuario_username"] = usuario["username"]
                session["toast_msg"] = (
                    f"Que bom ter você de volta, {usuario['nome'].split(' ')[0]}! 🎉"
                )
                resp = redirect(url_for("dashboard"))

            if lembrar:
                token = secrets.token_hex(32)
                db.execute(
                    "UPDATE usuarios SET remember_token = ? WHERE id = ?",
                    (token, usuario["id"]),
                )
                db.commit()
                resp.set_cookie(
                    "lembrar_token", token, max_age=86400 * 30, path="/"
                )
            return resp
        else:
            erro = "Credenciais inválidas. Tente novamente."

    return render_template("login.html", erro=erro)


@app.route("/cadastro", methods=["GET", "POST"])
def cadastro():
    if "usuario_id" in session or verificar_auto_login():
        return redirect(url_for("dashboard"))

    erro = ""
    nome = email = ""
    if request.method == "POST":
        nome = request.form.get("nome", "").strip()
        email = request.form.get("email", "").strip()
        senha = request.form.get("senha", "")
        confirma_senha = request.form.get("confirma_senha", "")
        lembrar = "lembrar" in request.form
        mostrar_nome_real = 1 if "mostrar_nome_real" in request.form else 0

        if senha != confirma_senha:
            erro = "As senhas não coincidem!"
        else:
            db = get_db()
            existe = db.execute(
                "SELECT id FROM usuarios WHERE email = ?", (email,)
            ).fetchone()
            if existe:
                erro = "Este e-mail já está cadastrado."
            else:
                senha_hash = generate_password_hash(senha)
                cur = db.execute(
                    "INSERT INTO usuarios (nome, email, senha, mostrar_nome_real) VALUES (?, ?, ?, ?)",
                    (nome, email, senha_hash, mostrar_nome_real),
                )
                db.commit()
                usuario_id = cur.lastrowid

                session.clear()
                session["usuario_id"] = usuario_id
                session["usuario_nome"] = nome
                session["toast_msg"] = "Conta criada com sucesso! Vamos escolher seu Avatar? 🚀"

                resp = redirect(url_for("setup_perfil"))
                if lembrar:
                    token = secrets.token_hex(32)
                    db.execute(
                        "UPDATE usuarios SET remember_token = ? WHERE id = ?",
                        (token, usuario_id),
                    )
                    db.commit()
                    resp.set_cookie(
                        "lembrar_token", token, max_age=86400 * 30, path="/"
                    )
                return resp

    return render_template("cadastro.html", erro=erro, nome=nome, email=email)


@app.route("/logout")
def logout():
    session.clear()
    resp = redirect(url_for("login"))
    resp.set_cookie("lembrar_token", "", expires=0, path="/")
    return resp


@app.route("/esqueceu_senha", methods=["GET", "POST"])
def esqueceu_senha():
    erro = ""
    link_simulado = ""
    if request.method == "POST":
        email = request.form.get("email", "").strip()
        db = get_db()
        usuario = db.execute(
            "SELECT id, nome FROM usuarios WHERE email = ?", (email,)
        ).fetchone()

        if usuario:
            token = secrets.token_hex(32)
            expiracao = (datetime.now() + timedelta(minutes=30)).strftime("%Y-%m-%d %H:%M:%S")
            db.execute(
                "UPDATE usuarios SET codigo_recuperacao = ?, codigo_expiracao = ? WHERE email = ?",
                (token, expiracao, email),
            )
            db.commit()
            link_simulado = url_for("nova_senha", token=token)
        else:
            erro = "Se este e-mail estiver cadastrado, as instruções serão enviadas em instantes."

    return render_template("esqueceu_senha.html", erro=erro, link_simulado=link_simulado)


@app.route("/nova_senha", methods=["GET", "POST"])
def nova_senha():
    token_url = request.args.get("token", "")
    if not token_url:
        return redirect(url_for("login"))

    erro = ""
    db = get_db()
    usuario = db.execute(
        "SELECT id FROM usuarios WHERE codigo_recuperacao = ? AND codigo_expiracao > ?",
        (token_url, datetime.now().strftime("%Y-%m-%d %H:%M:%S")),
    ).fetchone()
    token_valido = usuario is not None

    if request.method == "POST" and token_valido:
        senha = request.form.get("senha", "")
        confirma_senha = request.form.get("confirma_senha", "")
        if senha != confirma_senha:
            erro = "As senhas não coincidem!"
        else:
            senha_hash = generate_password_hash(senha)
            db.execute(
                "UPDATE usuarios SET senha = ?, codigo_recuperacao = NULL, codigo_expiracao = NULL WHERE id = ?",
                (senha_hash, usuario["id"]),
            )
            db.commit()
            return redirect(url_for("login"))
    elif not token_valido:
        erro = "Link de recuperação inválido ou expirado. Por favor, solicite um novo."

    return render_template(
        "nova_senha.html", erro=erro, token_valido=token_valido, token_url=token_url
    )


# ---------------------------------------------------------------------------
# Perfil
# ---------------------------------------------------------------------------

@app.route("/setup_perfil", methods=["GET", "POST"])
@login_required
def setup_perfil():
    db = get_db()
    usuario_id = session["usuario_id"]
    erro = ""
    user = db.execute(
        "SELECT nome, username, foto_perfil FROM usuarios WHERE id = ?", (usuario_id,)
    ).fetchone()

    if request.method == "POST":
        username = request.form.get("username", "").strip()
        foto = request.form.get("foto_perfil", "")

        existe = db.execute(
            "SELECT id FROM usuarios WHERE username = ? AND id != ?",
            (username, usuario_id),
        ).fetchone()

        if existe:
            erro = "Esse nome de utilizador já está em uso."
        else:
            db.execute(
                "UPDATE usuarios SET username = ?, foto_perfil = ? WHERE id = ?",
                (username, foto, usuario_id),
            )
            db.commit()
            session["usuario_username"] = username
            return redirect(url_for("dashboard"))

    return render_template("setup_perfil.html", erro=erro, user=user)


@app.route("/perfil")
@login_required
def perfil():
    db = get_db()
    id_usuario = session["usuario_id"]
    user = db.execute(
        """SELECT nome, username, foto_perfil, email, bio, criado_em,
                  pronomes, mostrar_nome_real, status_disponibilidade
           FROM usuarios WHERE id = ?""",
        (id_usuario,),
    ).fetchone()
    return render_template("perfil.html", user=user)


@app.route("/perfil/status", methods=["POST"])
@login_required
def perfil_status():
    status = request.form.get("status", "online")
    if status not in ("online", "ausente", "ocupado"):
        status = "online"
    db = get_db()
    db.execute(
        "UPDATE usuarios SET status_disponibilidade = ? WHERE id = ?",
        (status, session["usuario_id"]),
    )
    db.commit()
    return redirect(url_for("perfil"))


@app.route("/perfil/editar", methods=["GET", "POST"])
@login_required
def perfil_editar():
    db = get_db()
    id_usuario = session["usuario_id"]
    erro = session.pop("toast_erro", "")
    sucesso = session.pop("toast_msg", "")
    user = db.execute(
        """SELECT nome, username, foto_perfil, email, bio,
                  pronomes, mostrar_nome_real
           FROM usuarios WHERE id = ?""",
        (id_usuario,),
    ).fetchone()

    if request.method == "POST":
        nome = request.form.get("nome", "").strip()
        username = request.form.get("username", "").strip()
        pronomes = request.form.get("pronomes", "").strip()[:50]
        foto = request.form.get("foto_perfil", "")
        bio = request.form.get("bio", "").strip()[:280]
        mostrar_nome_real = 1 if "mostrar_nome_real" in request.form else 0

        existe = db.execute(
            "SELECT id FROM usuarios WHERE username = ? AND id != ?",
            (username, id_usuario),
        ).fetchone()

        if existe:
            erro = "Este nome de utilizador já está ocupado."
        else:
            db.execute(
                """UPDATE usuarios SET nome = ?, username = ?,
                   pronomes = ?, foto_perfil = ?, bio = ?, mostrar_nome_real = ?
                   WHERE id = ?""",
                (nome, username, pronomes, foto, bio, mostrar_nome_real, id_usuario),
            )
            db.commit()
            session["usuario_nome"] = nome
            session["usuario_username"] = username
            return redirect(url_for("perfil"))
        user = db.execute(
            """SELECT nome, username, foto_perfil, email, bio,
                      pronomes, mostrar_nome_real
               FROM usuarios WHERE id = ?""",
            (id_usuario,),
        ).fetchone()

    return render_template("perfil_editar.html", erro=erro, sucesso=sucesso, user=user)


@app.route("/perfil/alterar-email", methods=["POST"])
@login_required
def alterar_email():
    db = get_db()
    id_usuario = session["usuario_id"]
    novo_email = request.form.get("novo_email", "").strip()
    senha_atual = request.form.get("senha_atual", "")

    usuario = db.execute("SELECT senha FROM usuarios WHERE id = ?", (id_usuario,)).fetchone()

    if not check_password_hash(usuario["senha"], senha_atual):
        session["toast_erro"] = "Senha atual incorreta. O e-mail não foi alterado."
        return redirect(url_for("configuracoes"))

    existe = db.execute(
        "SELECT id FROM usuarios WHERE email = ? AND id != ?", (novo_email, id_usuario)
    ).fetchone()
    if existe:
        session["toast_erro"] = "Este e-mail já está em uso por outra conta."
        return redirect(url_for("configuracoes"))

    db.execute("UPDATE usuarios SET email = ? WHERE id = ?", (novo_email, id_usuario))
    db.commit()
    session["toast_msg"] = "E-mail atualizado com sucesso!"
    return redirect(url_for("configuracoes"))


@app.route("/perfil/alterar-senha", methods=["POST"])
@login_required
def alterar_senha():
    db = get_db()
    id_usuario = session["usuario_id"]
    senha_atual = request.form.get("senha_atual", "")
    nova_senha = request.form.get("nova_senha", "")
    confirma_senha = request.form.get("confirma_senha", "")

    usuario = db.execute("SELECT senha FROM usuarios WHERE id = ?", (id_usuario,)).fetchone()

    if not check_password_hash(usuario["senha"], senha_atual):
        session["toast_erro"] = "Senha atual incorreta. A senha não foi alterada."
    elif nova_senha != confirma_senha:
        session["toast_erro"] = "As novas senhas não coincidem."
    elif len(nova_senha) < 6:
        session["toast_erro"] = "A nova senha precisa ter pelo menos 6 caracteres."
    else:
        db.execute(
            "UPDATE usuarios SET senha = ? WHERE id = ?",
            (generate_password_hash(nova_senha), id_usuario),
        )
        db.commit()
        session["toast_msg"] = "Senha atualizada com sucesso!"

    return redirect(url_for("configuracoes"))


@app.route("/configuracoes")
@login_required
def configuracoes():
    db = get_db()
    u = db.execute(
        "SELECT nome, username, foto_perfil, email FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()
    username = u["username"] or "usuário"
    foto_perfil = u["foto_perfil"] or "img/ex1.png"
    erro = session.pop("toast_erro", "")
    sucesso = session.pop("toast_msg", "")

    # Lembra de qual página o usuário veio, pra poder voltar pro mesmo lugar
    # (em vez de sempre cair no Dashboard). Só aceita links do próprio site.
    origem = request.referrer
    if origem and "/configuracoes" not in origem:
        parsed = urlparse(origem)
        if not parsed.netloc or parsed.netloc == request.host:
            session["config_origem"] = parsed.path or url_for("dashboard")
    origem_final = session.get("config_origem") or url_for("dashboard")

    return render_template(
        "configuracoes.html",
        username=username,
        foto_perfil=foto_perfil,
        email=u["email"],
        erro=erro,
        sucesso=sucesso,
        origem=origem_final,
    )



# ---------------------------------------------------------------------------
# Páginas principais
# ---------------------------------------------------------------------------

@app.route("/dashboard")
@login_required
def dashboard():
    db = get_db()
    u = db.execute(
        "SELECT foto_perfil, username, status_disponibilidade FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()
    username_exibir = u["username"] or "Usuário"
    foto_perfil = u["foto_perfil"] or "img/ex1.png"
    status = u["status_disponibilidade"] or "online"
    return render_template(
        "dashboard.html", username_exibir=username_exibir, foto_perfil=foto_perfil, status=status
    )


@app.route("/agenda")
@login_required
def agenda():
    db = get_db()
    u = db.execute(
        "SELECT username, foto_perfil, status_disponibilidade FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()
    username_exibir = u["username"] or "usuário"
    foto = u["foto_perfil"] or "img/ex1.png"
    status = u["status_disponibilidade"] or "online"
    return render_template("agenda.html", username_exibir=username_exibir, foto=foto, status=status)


GEMINI_API_KEY = os.environ.get("GEMINI_API_KEY", "")
GEMINI_MODEL = "gemini-3.5-flash"


def gerar_microtarefas_ia(energia, contexto_disciplinas):
    """Pede pra IA do Gemini (gratuito) gerar microtarefas personalizadas.
    Devolve None se não tiver chave configurada ou se algo der errado —
    nesse caso o front-end usa as sugestões fixas de sempre, sem quebrar nada."""
    if not GEMINI_API_KEY:
        return None

    resumo_disciplinas = "\n".join(
        f"- {d['nome']} ({d['progresso']}% concluído, dificuldade {d['dificuldade']}/5)"
        for d in contexto_disciplinas
    ) or "Nenhuma disciplina cadastrada ainda."

    prompt = f"""Você é o assistente de estudos do app Focus. Gere 4 microtarefas curtas e práticas
para ajudar o estudante agora, considerando o nível de energia dele (1=baixo a 5=alto) e o que ele
está estudando. Misture dicas de bem-estar (água, pausa, alongamento) com sugestões de estudo
ligadas às disciplinas reais dele quando fizer sentido.

Energia atual: {energia}/5

Disciplinas do estudante:
{resumo_disciplinas}

Responda com um array JSON no formato:
[{{"icone": "fa-glass-water", "titulo": "Título curto", "texto": "Descrição breve e prática"}}]

Os ícones devem ser nomes válidos do Font Awesome 6 (ex: fa-glass-water, fa-mug-hot, fa-rotate,
fa-bolt, fa-book, fa-brain, fa-stopwatch)."""

    payload = json.dumps({
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {"response_mime_type": "application/json"},
    }).encode("utf-8")

    url = f"https://generativelanguage.googleapis.com/v1beta/models/{GEMINI_MODEL}:generateContent"
    req = urllib.request.Request(
        url,
        data=payload,
        headers={
            "Content-Type": "application/json",
            "x-goog-api-key": GEMINI_API_KEY,
        },
        method="POST",
    )

    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            resultado = json.loads(resp.read().decode("utf-8"))
        texto = resultado["candidates"][0]["content"]["parts"][0]["text"].strip()
        texto = texto.replace("```json", "").replace("```", "").strip()
        microtarefas = json.loads(texto)
        if isinstance(microtarefas, list) and len(microtarefas) > 0:
            return microtarefas[:4]
    except (urllib.error.URLError, KeyError, IndexError, ValueError, json.JSONDecodeError):
        return None
    return None


@app.route("/api/microtarefas")
@login_required
def api_microtarefas():
    try:
        energia = int(request.args.get("energia", "3"))
    except ValueError:
        energia = 3

    db = get_db()
    disciplinas = db.execute(
        """SELECT d.nome, d.dificuldade,
                  COUNT(t.id) AS total, SUM(CASE WHEN t.concluida THEN 1 ELSE 0 END) AS feitas
           FROM disciplinas d
           JOIN modulos m ON m.id = d.id_modulo
           LEFT JOIN tarefas t ON t.id_disciplina = d.id
           WHERE m.id_usuario = ?
           GROUP BY d.id""",
        (session["usuario_id"],),
    ).fetchall()

    contexto = []
    for d in disciplinas:
        total = d["total"] or 0
        feitas = d["feitas"] or 0
        progresso = round((feitas / total * 100)) if total > 0 else 0
        contexto.append({"nome": d["nome"], "dificuldade": d["dificuldade"], "progresso": progresso})

    microtarefas = gerar_microtarefas_ia(energia, contexto)
    if microtarefas is None:
        return jsonify({"ok": False, "usando_ia": False})

    return jsonify({"ok": True, "usando_ia": True, "microtarefas": microtarefas})


@app.route("/meus_estudos")
@login_required
def meus_estudos():
    db = get_db()
    id_usuario = session["usuario_id"]
    u = db.execute(
        "SELECT username, foto_perfil, status_disponibilidade FROM usuarios WHERE id = ?",
        (id_usuario,),
    ).fetchone()
    username_exibir = u["username"] or "usuário"
    foto_perfil = u["foto_perfil"] or "img/ex1.png"
    status = u["status_disponibilidade"] or "online"

    modulos = db.execute(
        """SELECT m.id, m.nome, COUNT(d.id) AS total_disciplinas
           FROM modulos m
           LEFT JOIN disciplinas d ON d.id_modulo = m.id
           WHERE m.id_usuario = ?
           GROUP BY m.id ORDER BY m.id DESC""",
        (id_usuario,),
    ).fetchall()

    LIMITE_MODULOS = 12
    return render_template(
        "meus_estudos.html",
        username_exibir=username_exibir,
        foto_perfil=foto_perfil,
        status=status,
        modulos=modulos,
        limite_modulos=LIMITE_MODULOS,
        pode_adicionar=len(modulos) < LIMITE_MODULOS,
    )


@app.route("/meus_estudos/modulo", methods=["POST"])
@login_required
def criar_modulo():
    db = get_db()
    id_usuario = session["usuario_id"]
    nome = request.form.get("nome", "").strip()
    if not nome:
        return redirect(url_for("meus_estudos"))

    total = db.execute(
        "SELECT COUNT(*) AS c FROM modulos WHERE id_usuario = ?", (id_usuario,)
    ).fetchone()["c"]

    if total >= 12:
        session["toast_erro"] = "Você atingiu o limite de 12 módulos."
        return redirect(url_for("meus_estudos"))

    db.execute(
        "INSERT INTO modulos (id_usuario, nome) VALUES (?, ?)", (id_usuario, nome)
    )
    db.commit()
    return redirect(url_for("meus_estudos"))


@app.route("/meus_estudos/modulo/<int:modulo_id>/excluir", methods=["POST"])
@login_required
def excluir_modulo(modulo_id):
    db = get_db()
    db.execute(
        "DELETE FROM modulos WHERE id = ? AND id_usuario = ?",
        (modulo_id, session["usuario_id"]),
    )
    db.commit()
    return redirect(url_for("meus_estudos"))


def _get_modulo_do_usuario(db, modulo_id):
    return db.execute(
        "SELECT * FROM modulos WHERE id = ? AND id_usuario = ?",
        (modulo_id, session["usuario_id"]),
    ).fetchone()


@app.route("/meus_estudos/modulo/<int:modulo_id>")
@login_required
def ver_modulo(modulo_id):
    db = get_db()
    modulo = _get_modulo_do_usuario(db, modulo_id)
    if not modulo:
        return redirect(url_for("meus_estudos"))

    u = db.execute(
        "SELECT username, foto_perfil, status_disponibilidade FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()

    disciplinas = db.execute(
        """SELECT d.id, d.nome, d.dificuldade, d.minutos_estudados,
                  COUNT(t.id) AS total_tarefas,
                  SUM(CASE WHEN t.concluida THEN 1 ELSE 0 END) AS tarefas_feitas
           FROM disciplinas d
           LEFT JOIN tarefas t ON t.id_disciplina = d.id
           WHERE d.id_modulo = ?
           GROUP BY d.id ORDER BY d.id DESC""",
        (modulo_id,),
    ).fetchall()

    return render_template(
        "modulo.html",
        modulo=modulo,
        disciplinas=disciplinas,
        username_exibir=u["username"] or "usuário",
        foto_perfil=u["foto_perfil"] or "img/ex1.png",
        status=u["status_disponibilidade"] or "online",
    )


@app.route("/meus_estudos/modulo/<int:modulo_id>/disciplina", methods=["POST"])
@login_required
def criar_disciplina(modulo_id):
    db = get_db()
    modulo = _get_modulo_do_usuario(db, modulo_id)
    if not modulo:
        return redirect(url_for("meus_estudos"))

    nome = request.form.get("nome", "").strip()
    dificuldade = request.form.get("dificuldade", "1")
    if nome:
        db.execute(
            "INSERT INTO disciplinas (id_usuario, id_modulo, nome, dificuldade) VALUES (?, ?, ?, ?)",
            (session["usuario_id"], modulo_id, nome, dificuldade),
        )
        db.commit()
    return redirect(url_for("ver_modulo", modulo_id=modulo_id))


@app.route("/meus_estudos/disciplina/<int:disciplina_id>/excluir", methods=["POST"])
@login_required
def excluir_disciplina(disciplina_id):
    db = get_db()
    disciplina = db.execute(
        "SELECT id_modulo FROM disciplinas WHERE id = ? AND id_usuario = ?",
        (disciplina_id, session["usuario_id"]),
    ).fetchone()
    if disciplina:
        db.execute("DELETE FROM disciplinas WHERE id = ?", (disciplina_id,))
        db.commit()
        return redirect(url_for("ver_modulo", modulo_id=disciplina["id_modulo"]))
    return redirect(url_for("meus_estudos"))


def _get_disciplina_do_usuario(db, disciplina_id):
    return db.execute(
        "SELECT * FROM disciplinas WHERE id = ? AND id_usuario = ?",
        (disciplina_id, session["usuario_id"]),
    ).fetchone()


@app.route("/meus_estudos/disciplina/<int:disciplina_id>")
@login_required
def ver_disciplina(disciplina_id):
    db = get_db()
    disciplina = _get_disciplina_do_usuario(db, disciplina_id)
    if not disciplina:
        return redirect(url_for("meus_estudos"))

    u = db.execute(
        "SELECT username, foto_perfil, status_disponibilidade FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()

    tarefas = db.execute(
        "SELECT * FROM tarefas WHERE id_disciplina = ? ORDER BY concluida ASC, id DESC",
        (disciplina_id,),
    ).fetchall()

    return render_template(
        "disciplina.html",
        disciplina=disciplina,
        tarefas=tarefas,
        username_exibir=u["username"] or "usuário",
        foto_perfil=u["foto_perfil"] or "img/ex1.png",
        status=u["status_disponibilidade"] or "online",
    )


@app.route("/meus_estudos/disciplina/<int:disciplina_id>/tarefa", methods=["POST"])
@login_required
def criar_tarefa(disciplina_id):
    db = get_db()
    disciplina = _get_disciplina_do_usuario(db, disciplina_id)
    if not disciplina:
        return redirect(url_for("meus_estudos"))

    nome = request.form.get("nome", "").strip()
    if nome:
        db.execute(
            "INSERT INTO tarefas (id_disciplina, nome) VALUES (?, ?)", (disciplina_id, nome)
        )
        db.commit()
    return redirect(url_for("ver_disciplina", disciplina_id=disciplina_id))


@app.route("/tarefa/<int:tarefa_id>/concluir", methods=["POST"])
@login_required
def concluir_tarefa(tarefa_id):
    db = get_db()
    tarefa = db.execute(
        """SELECT t.id, t.concluida FROM tarefas t
           JOIN disciplinas d ON d.id = t.id_disciplina
           WHERE t.id = ? AND d.id_usuario = ?""",
        (tarefa_id, session["usuario_id"]),
    ).fetchone()
    if not tarefa:
        return {"ok": False}, 404

    novo_valor = 0 if tarefa["concluida"] else 1
    db.execute("UPDATE tarefas SET concluida = ? WHERE id = ?", (novo_valor, tarefa_id))
    db.commit()
    return {"ok": True, "concluida": bool(novo_valor)}


@app.route("/tarefa/<int:tarefa_id>/excluir", methods=["POST"])
@login_required
def excluir_tarefa(tarefa_id):
    db = get_db()
    tarefa = db.execute(
        """SELECT t.id_disciplina FROM tarefas t
           JOIN disciplinas d ON d.id = t.id_disciplina
           WHERE t.id = ? AND d.id_usuario = ?""",
        (tarefa_id, session["usuario_id"]),
    ).fetchone()
    if tarefa:
        db.execute("DELETE FROM tarefas WHERE id = ?", (tarefa_id,))
        db.commit()
        return redirect(url_for("ver_disciplina", disciplina_id=tarefa["id_disciplina"]))
    return redirect(url_for("meus_estudos"))


@app.route("/meus_estudos/disciplina/<int:disciplina_id>/anotacoes", methods=["POST"])
@login_required
def salvar_anotacoes(disciplina_id):
    db = get_db()
    disciplina = _get_disciplina_do_usuario(db, disciplina_id)
    if disciplina:
        texto = request.form.get("anotacoes", "")
        db.execute(
            "UPDATE disciplinas SET anotacoes = ? WHERE id = ?", (texto, disciplina_id)
        )
        db.commit()
    return redirect(url_for("ver_disciplina", disciplina_id=disciplina_id))


@app.route("/meus_estudos/disciplina/<int:disciplina_id>/desenho", methods=["POST"])
@login_required
def salvar_desenho(disciplina_id):
    db = get_db()
    disciplina = _get_disciplina_do_usuario(db, disciplina_id)
    if not disciplina:
        return {"ok": False}, 404
    imagem = request.form.get("desenho", "")
    db.execute("UPDATE disciplinas SET desenho = ? WHERE id = ?", (imagem, disciplina_id))
    db.commit()
    return {"ok": True}


@app.route("/meus_estudos/disciplina/<int:disciplina_id>/sessao", methods=["POST"])
@login_required
def registrar_sessao(disciplina_id):
    db = get_db()
    disciplina = _get_disciplina_do_usuario(db, disciplina_id)
    if not disciplina:
        return {"ok": False}, 404

    minutos = int(request.form.get("minutos", 0))
    if minutos > 0:
        db.execute(
            "INSERT INTO sessoes_estudo (id_disciplina, duracao_minutos) VALUES (?, ?)",
            (disciplina_id, minutos),
        )
        db.execute(
            "UPDATE disciplinas SET minutos_estudados = minutos_estudados + ? WHERE id = ?",
            (minutos, disciplina_id),
        )
        db.commit()
    return {"ok": True}


@app.route("/meu_desempenho")
@login_required
def meu_desempenho():
    db = get_db()
    u = db.execute(
        "SELECT foto_perfil, username, status_disponibilidade FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()
    username_limpo = u["username"] or "usuário"
    foto_perfil = u["foto_perfil"] or "img/ex1.png"
    status = u["status_disponibilidade"] or "online"

    # ---- Geração de dados simulados de desempenho (igual à versão PHP) ----
    dias_semana = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"]
    horas_estudo = []
    humor_status = []

    energia = random.randint(55, 75) / 10
    fadiga = random.randint(15, 30) / 10
    estresse = random.randint(10, 25) / 10
    concentracao = random.randint(60, 80) / 10

    for i in range(7):
        ciclo = math.sin((i / 6) * math.pi * 1.1)
        ruido = random.randint(-12, 12) / 10

        evento = 0
        roll = random.randint(1, 100)
        if roll <= 6:
            evento = random.randint(8, 15) / 10
        elif roll >= 92:
            evento = random.randint(-15, -8) / 10
        elif 70 <= roll <= 75:
            evento = random.randint(-5, 5) / 10

        horas = (
            energia * 0.45
            + ciclo * 0.9
            + evento
            - fadiga * 0.35
            + concentracao * 0.08
        )
        horas += ruido
        horas = max(0.8, min(4.0, horas))

        if horas < 2:
            fadiga += 0.25
            estresse += 0.2
            concentracao -= 0.1
        elif horas > 3.2:
            fadiga -= 0.15
            concentracao += 0.1
        else:
            fadiga += 0.05

        fadiga = max(1, min(4.5, fadiga))
        estresse = max(0.5, min(4, estresse))
        concentracao = max(5, min(9, concentracao))

        humor = random.randint(1, 5)
        humor += random.randint(-1, 1)
        if horas > 3.2:
            humor += random.randint(0, 1)
        if horas < 1.5:
            humor -= random.randint(0, 2)
        if fadiga > 3:
            humor -= random.randint(0, 1)
        if estresse > 3:
            humor -= random.randint(0, 1)
        if concentracao > 7.5:
            humor += random.randint(0, 1)
        humor = max(1, min(5, humor))

        horas_estudo.append(round(horas, 1))
        humor_status.append(humor)

    total_horas = sum(horas_estudo)
    media_humor = round(sum(humor_status) / 7, 1)

    if media_humor >= 4 and total_horas >= 14:
        nivel = "alto"
    elif media_humor >= 2.8:
        nivel = "médio"
    else:
        nivel = "baixo"

    sincronia = 55 + (media_humor * 3) + (total_horas * 2) - (fadiga * 2) - (estresse * 1.5)
    sincronia = max(35, min(80, round(sincronia)))

    return render_template(
        "meu_desempenho.html",
        username_limpo=username_limpo,
        foto_perfil=foto_perfil,
        dias_semana=dias_semana,
        horas_estudo=horas_estudo,
        humor_status=humor_status,
        total_horas=total_horas,
        media_humor=media_humor,
        nivel=nivel,
        sincronia=sincronia,
        pct_extra=random.randint(70, 98),
        status=status,
    )


if __name__ == "__main__":
    app.run(debug=True, host="0.0.0.0")
