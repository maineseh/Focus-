import os
import secrets
import random
import math
from datetime import datetime, timedelta
from functools import wraps

from flask import Flask, render_template, request, redirect, url_for, session, flash

from db import get_db, init_app
from werkzeug.security import generate_password_hash, check_password_hash

app = Flask(__name__)
app.secret_key = os.environ.get("FOCUS_SECRET_KEY", secrets.token_hex(32))
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
                    "INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)",
                    (nome, email, senha_hash),
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


@app.route("/perfil", methods=["GET", "POST"])
@login_required
def perfil():
    db = get_db()
    id_usuario = session["usuario_id"]
    erro = ""
    user = db.execute(
        "SELECT nome, username, foto_perfil FROM usuarios WHERE id = ?", (id_usuario,)
    ).fetchone()

    if request.method == "POST":
        nome = request.form.get("nome", "").strip()
        username = request.form.get("username", "").strip()
        foto = request.form.get("foto_perfil", "")

        existe = db.execute(
            "SELECT id FROM usuarios WHERE username = ? AND id != ?",
            (username, id_usuario),
        ).fetchone()

        if existe:
            erro = "Este nome de utilizador já está ocupado."
        else:
            db.execute(
                "UPDATE usuarios SET nome = ?, username = ?, foto_perfil = ? WHERE id = ?",
                (nome, username, foto, id_usuario),
            )
            db.commit()
            session["usuario_nome"] = nome
            session["usuario_username"] = username
            return redirect(url_for("dashboard"))
        user = db.execute(
            "SELECT nome, username, foto_perfil FROM usuarios WHERE id = ?", (id_usuario,)
        ).fetchone()

    return render_template("perfil.html", erro=erro, user=user)


@app.route("/configuracoes")
@login_required
def configuracoes():
    db = get_db()
    u = db.execute(
        "SELECT nome, username, foto_perfil FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()
    username = u["username"] or "usuário"
    foto_perfil = u["foto_perfil"] or "img/ex1.png"
    return render_template("configuracoes.html", username=username, foto_perfil=foto_perfil)


# ---------------------------------------------------------------------------
# Páginas principais
# ---------------------------------------------------------------------------

@app.route("/dashboard")
@login_required
def dashboard():
    db = get_db()
    u = db.execute(
        "SELECT foto_perfil, username FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()
    username_exibir = u["username"] or "Usuário"
    foto_perfil = u["foto_perfil"] or "img/ex1.png"
    return render_template(
        "dashboard.html", username_exibir=username_exibir, foto_perfil=foto_perfil
    )


@app.route("/agenda")
@login_required
def agenda():
    db = get_db()
    u = db.execute(
        "SELECT username, foto_perfil FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()
    username_exibir = u["username"] or "usuário"
    foto = u["foto_perfil"] or "img/ex1.png"
    return render_template("agenda.html", username_exibir=username_exibir, foto=foto)


@app.route("/meus_estudos")
@login_required
def meus_estudos():
    db = get_db()
    u = db.execute(
        "SELECT username, foto_perfil FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()
    username_exibir = u["username"] or "usuário"
    foto_perfil = u["foto_perfil"] or "img/ex1.png"
    return render_template(
        "meus_estudos.html", username_exibir=username_exibir, foto_perfil=foto_perfil
    )


@app.route("/meu_desempenho")
@login_required
def meu_desempenho():
    db = get_db()
    u = db.execute(
        "SELECT foto_perfil, username FROM usuarios WHERE id = ?",
        (session["usuario_id"],),
    ).fetchone()
    username_limpo = u["username"] or "usuário"
    foto_perfil = u["foto_perfil"] or "img/ex1.png"

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
    )


if __name__ == "__main__":
    app.run(debug=True)
