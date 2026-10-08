import os
import re
import secrets
import random
import json
import urllib.request
import urllib.error
from datetime import datetime, timedelta
from functools import wraps
from urllib.parse import urlparse

from flask import Flask, render_template, request, redirect, url_for, session, flash, jsonify, abort
from markupsafe import Markup, escape as markup_escape

from db import get_db, init_app
from werkzeug.security import generate_password_hash, check_password_hash
from werkzeug.utils import secure_filename

app = Flask(__name__)
app.secret_key = os.environ.get("FOCUS_SECRET_KEY", secrets.token_hex(32))
# Evita que o navegador guarde em cache versões antigas do CSS/JS — assim,
# toda atualização aparece na hora, sem precisar limpar o cache manualmente.
app.config["SEND_FILE_MAX_AGE_DEFAULT"] = 0
# Sessão do responsável (Controle Parental) dura bastante tempo, já que o
# acesso é só por link mágico (sem senha) — ele não precisa colar o link
# de novo toda vez que fechar o navegador.
app.permanent_session_lifetime = timedelta(days=180)
# Limite de tamanho de upload (imagens do chat de Comunidades, fotos de
# perfil e ícones de grupo) — 5 MB.
app.config["MAX_CONTENT_LENGTH"] = 5 * 1024 * 1024
init_app(app)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_DIR_CHAT = os.path.join(BASE_DIR, "static", "uploads", "chat")
UPLOAD_DIR_PERFIL = os.path.join(BASE_DIR, "static", "uploads", "perfil")
UPLOAD_DIR_GRUPO = os.path.join(BASE_DIR, "static", "uploads", "grupo")
os.makedirs(UPLOAD_DIR_CHAT, exist_ok=True)
os.makedirs(UPLOAD_DIR_PERFIL, exist_ok=True)
os.makedirs(UPLOAD_DIR_GRUPO, exist_ok=True)
EXTENSOES_IMAGEM_PERMITIDAS = {"png", "jpg", "jpeg", "gif", "webp"}


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


def responsavel_required(f):
    """Equivalente ao login_required, mas pro lado do responsável — a
    'sessão' dele é só o e-mail confirmado por ter acessado um link mágico
    válido (nenhuma senha envolvida)."""
    @wraps(f)
    def wrapper(*args, **kwargs):
        if "responsavel_email" not in session:
            return redirect(url_for("responsavel_login"))
        return f(*args, **kwargs)
    return wrapper


def responsavel_atual():
    db = get_db()
    return db.execute(
        "SELECT * FROM responsaveis WHERE email = ?", (session["responsavel_email"],)
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
        arquivo_foto = request.files.get("foto_arquivo")
        erro_upload = ""
        if arquivo_foto and arquivo_foto.filename:
            salva = _salvar_imagem_generica(arquivo_foto, UPLOAD_DIR_PERFIL, "uploads/perfil")
            if salva:
                foto = salva
            else:
                erro_upload = "Não deu pra usar essa imagem (formato não aceito ou reprovada na checagem)."

        existe = db.execute(
            "SELECT id FROM usuarios WHERE username = ? AND id != ?",
            (username, usuario_id),
        ).fetchone()

        if existe:
            erro = "Esse nome de utilizador já está em uso."
        elif erro_upload:
            erro = erro_upload
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


@app.route("/perfil/foto", methods=["POST"])
@login_required
def perfil_editar_foto():
    """Salva a foto de perfil na hora, assim que a pessoa confirma o
    recorte — em vez de só guardar o arquivo no <input> esperando o
    formulário inteiro ser enviado depois. Isso evita que a escolha se
    perca se a pessoa mudar outra coisa no formulário, o navegador
    recarregar por qualquer motivo, ou ela sair da página antes de
    clicar em "Salvar Alterações"."""
    db = get_db()
    id_usuario = session["usuario_id"]
    arquivo_foto = request.files.get("foto_arquivo")
    if not arquivo_foto or not arquivo_foto.filename:
        return jsonify(ok=False, erro="Nenhuma imagem recebida."), 400
    salva = _salvar_imagem_generica(arquivo_foto, UPLOAD_DIR_PERFIL, "uploads/perfil")
    if not salva:
        return jsonify(ok=False, erro="Não deu pra usar essa imagem (formato não aceito ou reprovada na checagem)."), 400
    db.execute("UPDATE usuarios SET foto_perfil = ? WHERE id = ?", (salva, id_usuario))
    db.commit()
    return jsonify(ok=True, foto=url_for("static", filename=salva))


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
        # Se nenhum dos 3 avatares prontos foi marcado (porque a pessoa está
        # usando uma foto da galeria, que não tem radio próprio — ela já foi
        # salva na hora pelo /perfil/foto), mantém a foto atual em vez de
        # apagar com string vazia.
        foto = request.form.get("foto_perfil", "").strip() or user["foto_perfil"]
        arquivo_foto = request.files.get("foto_arquivo")
        erro_upload = ""
        if arquivo_foto and arquivo_foto.filename:
            salva = _salvar_imagem_generica(arquivo_foto, UPLOAD_DIR_PERFIL, "uploads/perfil")
            if salva:
                foto = salva
            else:
                erro_upload = "Não deu pra usar essa imagem (formato não aceito ou reprovada na checagem)."
        bio = request.form.get("bio", "").strip()[:280]
        mostrar_nome_real = 1 if "mostrar_nome_real" in request.form else 0

        existe = db.execute(
            "SELECT id FROM usuarios WHERE username = ? AND id != ?",
            (username, id_usuario),
        ).fetchone()

        if existe:
            erro = "Este nome de utilizador já está ocupado."
        elif erro_upload:
            erro = erro_upload
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


@app.route("/api/atividade/ping", methods=["POST"])
@login_required
def api_atividade_ping():
    """Recebe um 'batimento' do navegador (enviado a cada ~1 minuto enquanto
    a aba está visível) e soma no total de minutos do dia — é isso que vira
    'tempo total no site' no painel do responsável."""
    db = get_db()
    id_usuario = session["usuario_id"]
    hoje = datetime.utcnow().date().isoformat()
    db.execute(
        """INSERT INTO atividade_site (id_usuario, data, minutos) VALUES (?, ?, 1)
           ON CONFLICT(id_usuario, data) DO UPDATE SET minutos = minutos + 1""",
        (id_usuario, hoje),
    )
    db.execute(
        "UPDATE usuarios SET ultimo_ping = ? WHERE id = ?",
        (datetime.utcnow().isoformat(), id_usuario),
    )
    db.commit()
    return jsonify(ok=True)


LIMITE_ONLINE_MINUTOS = 3


def _esta_online(ultimo_ping):
    """Um usuário é considerado 'online de verdade' se mandou um heartbeat
    de atividade (api_atividade_ping) nos últimos minutos. Sem isso, mesmo
    quem marcou status 'online' manualmente aparece 'offline' pros amigos."""
    if not ultimo_ping:
        return False
    try:
        dt = datetime.fromisoformat(ultimo_ping)
    except ValueError:
        return False
    return (datetime.utcnow() - dt) <= timedelta(minutes=LIMITE_ONLINE_MINUTOS)


def _status_exibicao(status_disponibilidade, ultimo_ping):
    """Combina o status manual (ausente/ocupado) com a presença real
    (heartbeat) pra decidir o que mostrar em Comunidades: se não há
    atividade recente, é sempre 'offline', não importa o status manual."""
    if not _esta_online(ultimo_ping):
        return "offline"
    return status_disponibilidade or "online"


# ---------------------------------------------------------------------------
# Controle Parental — lado do filho (gerar/revogar acesso do responsável)
# ---------------------------------------------------------------------------

@app.route("/controle_parental")
@login_required
def controle_parental():
    db = get_db()
    id_usuario = session["usuario_id"]
    u = db.execute(
        "SELECT foto_perfil, username, status_disponibilidade FROM usuarios WHERE id = ?",
        (id_usuario,),
    ).fetchone()

    vinculos = db.execute(
        """SELECT v.id, v.criado_em, r.email
           FROM vinculos_parentais v
           JOIN responsaveis r ON r.id = v.id_responsavel
           WHERE v.id_usuario = ? AND v.ativo = 1 AND v.status = 'ativo'
           ORDER BY v.criado_em DESC""",
        (id_usuario,),
    ).fetchall()

    solicitacoes = db.execute(
        """SELECT v.id, v.criado_em, r.email
           FROM vinculos_parentais v
           JOIN responsaveis r ON r.id = v.id_responsavel
           WHERE v.id_usuario = ? AND v.ativo = 1 AND v.status = 'pendente'
           ORDER BY v.criado_em DESC""",
        (id_usuario,),
    ).fetchall()

    link_gerado = session.pop("cp_link_gerado", None)
    erro = session.pop("cp_erro", None)

    # Lembra de qual página o usuário veio, pra poder voltar pro mesmo lugar
    # ao clicar no X (mesmo padrão usado em Configurações).
    origem = request.referrer
    if origem and "/controle_parental" not in origem:
        parsed = urlparse(origem)
        if not parsed.netloc or parsed.netloc == request.host:
            session["cp_origem"] = parsed.path or url_for("dashboard")
    origem_final = session.get("cp_origem") or url_for("dashboard")

    return render_template(
        "controle_parental.html",
        username_limpo=u["username"] or "usuário",
        foto_perfil=u["foto_perfil"] or "img/ex1.png",
        status=u["status_disponibilidade"] or "online",
        vinculos=vinculos,
        solicitacoes=solicitacoes,
        link_gerado=link_gerado,
        erro=erro,
        origem=origem_final,
    )


@app.route("/controle_parental/adicionar", methods=["POST"])
@login_required
def controle_parental_adicionar():
    email = request.form.get("email", "").strip().lower()
    db = get_db()
    id_usuario = session["usuario_id"]

    if not email or "@" not in email or "." not in email.split("@")[-1]:
        session["cp_erro"] = "Digite um e-mail válido pro responsável."
        return redirect(url_for("controle_parental"))

    ja_vinculado = db.execute(
        """SELECT 1 FROM vinculos_parentais v
           JOIN responsaveis r ON r.id = v.id_responsavel
           WHERE v.id_usuario = ? AND r.email = ? AND v.ativo = 1""",
        (id_usuario, email),
    ).fetchone()
    if ja_vinculado:
        session["cp_erro"] = "Esse responsável já tem acesso (ou já tem uma solicitação pendente) à sua conta."
        return redirect(url_for("controle_parental"))

    responsavel = db.execute("SELECT id FROM responsaveis WHERE email = ?", (email,)).fetchone()
    if responsavel:
        id_responsavel = responsavel["id"]
    else:
        cur = db.execute("INSERT INTO responsaveis (email) VALUES (?)", (email,))
        id_responsavel = cur.lastrowid

    token = secrets.token_urlsafe(24)
    db.execute(
        "INSERT INTO vinculos_parentais (id_responsavel, id_usuario, token, status) VALUES (?, ?, ?, 'ativo')",
        (id_responsavel, id_usuario, token),
    )
    db.commit()

    session["cp_link_gerado"] = url_for("responsavel_acesso", token=token, _external=True)
    return redirect(url_for("controle_parental"))


@app.route("/controle_parental/<int:vinculo_id>/revogar", methods=["POST"])
@login_required
def controle_parental_revogar(vinculo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    senha = request.form.get("senha", "")
    usuario = db.execute("SELECT senha FROM usuarios WHERE id = ?", (id_usuario,)).fetchone()
    if not senha or not usuario or not check_password_hash(usuario["senha"], senha):
        session["cp_erro"] = "Senha incorreta — o acesso do responsável não foi revogado."
        return redirect(url_for("controle_parental"))
    db.execute(
        "UPDATE vinculos_parentais SET ativo = 0 WHERE id = ? AND id_usuario = ?",
        (vinculo_id, id_usuario),
    )
    db.commit()
    return redirect(url_for("controle_parental"))


@app.route("/controle_parental/<int:vinculo_id>/gerar_link", methods=["POST"])
@login_required
def controle_parental_gerar_link(vinculo_id):
    """Gera (ou renova) o link mágico de acesso de um vínculo já ativo —
    inclusive os que nasceram do responsável pedindo acesso pelo e-mail
    dele (nesse fluxo nenhum token era gerado antes, então não existia
    link nenhum pra reenviar)."""
    db = get_db()
    id_usuario = session["usuario_id"]
    vinculo = db.execute(
        "SELECT id FROM vinculos_parentais WHERE id = ? AND id_usuario = ? AND ativo = 1",
        (vinculo_id, id_usuario),
    ).fetchone()
    if not vinculo:
        abort(404)
    token = secrets.token_urlsafe(24)
    db.execute("UPDATE vinculos_parentais SET token = ? WHERE id = ?", (token, vinculo_id))
    db.commit()
    session["cp_link_gerado"] = url_for("responsavel_acesso", token=token, _external=True)
    return redirect(url_for("controle_parental"))


@app.route("/controle_parental/solicitacao/<int:vinculo_id>/aprovar", methods=["POST"])
@login_required
def controle_parental_aprovar(vinculo_id):
    db = get_db()
    db.execute(
        "UPDATE vinculos_parentais SET status = 'ativo' WHERE id = ? AND id_usuario = ? AND status = 'pendente'",
        (vinculo_id, session["usuario_id"]),
    )
    db.commit()
    return redirect(url_for("controle_parental"))


@app.route("/controle_parental/solicitacao/<int:vinculo_id>/recusar", methods=["POST"])
@login_required
def controle_parental_recusar(vinculo_id):
    db = get_db()
    db.execute(
        "UPDATE vinculos_parentais SET ativo = 0 WHERE id = ? AND id_usuario = ? AND status = 'pendente'",
        (vinculo_id, session["usuario_id"]),
    )
    db.commit()
    return redirect(url_for("controle_parental"))


@app.route("/controle_parental/tarefa/<int:tarefa_id>/concluir", methods=["POST"])
@login_required
def controle_parental_tarefa_concluir(tarefa_id):
    db = get_db()
    # Concluir aqui é definitivo (não alterna): uma vez feita, a tarefa some
    # da lista do filho e passa a aparecer como concluída pro responsável.
    db.execute(
        "UPDATE tarefas_responsavel SET concluida = 1 WHERE id = ? AND id_usuario = ?",
        (tarefa_id, session["usuario_id"]),
    )
    db.commit()
    return redirect(url_for("meus_estudos"))


# ---------------------------------------------------------------------------
# Controle Parental — lado do responsável (login com senha própria)
# ---------------------------------------------------------------------------

@app.route("/responsavel/acesso/<token>")
def responsavel_acesso(token):
    db = get_db()
    vinculo = db.execute(
        """SELECT v.id, r.id AS id_responsavel, r.email, r.senha_hash
           FROM vinculos_parentais v
           JOIN responsaveis r ON r.id = v.id_responsavel
           WHERE v.token = ? AND v.ativo = 1""",
        (token,),
    ).fetchone()

    if not vinculo:
        return render_template("responsavel_login.html", invalido=True), 404

    # Primeiro acesso: precisa criar uma senha antes de ver qualquer coisa.
    if not vinculo["senha_hash"]:
        return render_template(
            "responsavel_definir_senha.html", token=token, email=vinculo["email"], erro=None
        )

    return render_template(
        "responsavel_entrar.html", token=token, email=vinculo["email"], erro=None
    )


@app.route("/responsavel/acesso/<token>/senha", methods=["POST"])
def responsavel_definir_senha(token):
    db = get_db()
    vinculo = db.execute(
        """SELECT r.id AS id_responsavel, r.email, r.senha_hash
           FROM vinculos_parentais v
           JOIN responsaveis r ON r.id = v.id_responsavel
           WHERE v.token = ? AND v.ativo = 1""",
        (token,),
    ).fetchone()
    if not vinculo:
        return render_template("responsavel_login.html", invalido=True), 404
    if vinculo["senha_hash"]:
        return redirect(url_for("responsavel_acesso", token=token))

    senha = request.form.get("senha", "")
    confirmar = request.form.get("confirmar_senha", "")

    erro = None
    if len(senha) < 6:
        erro = "A senha precisa ter pelo menos 6 caracteres."
    elif senha != confirmar:
        erro = "As senhas não coincidem."

    if erro:
        return render_template(
            "responsavel_definir_senha.html", token=token, email=vinculo["email"], erro=erro
        )

    db.execute(
        "UPDATE responsaveis SET senha_hash = ? WHERE id = ?",
        (generate_password_hash(senha), vinculo["id_responsavel"]),
    )
    db.commit()

    session.permanent = True
    session["responsavel_email"] = vinculo["email"]
    return redirect(url_for("responsavel_painel"))


@app.route("/responsavel/acesso/<token>/entrar", methods=["POST"])
def responsavel_entrar_token(token):
    db = get_db()
    vinculo = db.execute(
        """SELECT r.email, r.senha_hash
           FROM vinculos_parentais v
           JOIN responsaveis r ON r.id = v.id_responsavel
           WHERE v.token = ? AND v.ativo = 1""",
        (token,),
    ).fetchone()
    if not vinculo:
        return render_template("responsavel_login.html", invalido=True), 404

    senha = request.form.get("senha", "")
    if not vinculo["senha_hash"] or not check_password_hash(vinculo["senha_hash"], senha):
        return render_template(
            "responsavel_entrar.html", token=token, email=vinculo["email"], erro="Senha incorreta."
        )

    session.permanent = True
    session["responsavel_email"] = vinculo["email"]
    return redirect(url_for("responsavel_painel"))


@app.route("/responsavel", methods=["GET", "POST"])
def responsavel_login():
    if "responsavel_email" in session:
        return redirect(url_for("responsavel_painel"))

    erro = None
    if request.method == "POST":
        db = get_db()
        email = request.form.get("email", "").strip().lower()
        senha = request.form.get("senha", "")
        resp = db.execute("SELECT * FROM responsaveis WHERE email = ?", (email,)).fetchone()
        if resp and resp["senha_hash"] and check_password_hash(resp["senha_hash"], senha):
            session.permanent = True
            session["responsavel_email"] = resp["email"]
            return redirect(url_for("responsavel_painel"))
        erro = "E-mail ou senha incorretos."

    return render_template("responsavel_login.html", invalido=False, erro=erro)


@app.route("/responsavel/sair")
def responsavel_sair():
    session.pop("responsavel_email", None)
    return redirect(url_for("responsavel_login"))


@app.route("/responsavel/painel")
@responsavel_required
def responsavel_painel():
    db = get_db()
    resp = responsavel_atual()
    if not resp:
        session.pop("responsavel_email", None)
        return redirect(url_for("responsavel_login"))

    filhos = db.execute(
        """SELECT u.id, u.nome, u.username, u.foto_perfil, u.status_disponibilidade
           FROM vinculos_parentais v
           JOIN usuarios u ON u.id = v.id_usuario
           WHERE v.id_responsavel = ? AND v.ativo = 1 AND v.status = 'ativo'
           ORDER BY u.nome""",
        (resp["id"],),
    ).fetchall()

    pendentes = db.execute(
        """SELECT u.username, u.nome, v.criado_em
           FROM vinculos_parentais v
           JOIN usuarios u ON u.id = v.id_usuario
           WHERE v.id_responsavel = ? AND v.ativo = 1 AND v.status = 'pendente'
           ORDER BY v.criado_em DESC""",
        (resp["id"],),
    ).fetchall()

    erro = session.pop("rp_erro", None)
    sucesso = session.pop("rp_sucesso", None)

    return render_template(
        "responsavel_painel.html", resp=resp, filhos=filhos, pendentes=pendentes, erro=erro, sucesso=sucesso
    )


@app.route("/responsavel/solicitar_vinculo", methods=["POST"])
@responsavel_required
def responsavel_solicitar_vinculo():
    db = get_db()
    resp = responsavel_atual()
    email_filho = request.form.get("email", "").strip().lower()

    if not email_filho or "@" not in email_filho:
        session["rp_erro"] = "Digite um e-mail válido."
        return redirect(url_for("responsavel_painel"))

    filho = db.execute("SELECT id, username, nome FROM usuarios WHERE email = ?", (email_filho,)).fetchone()
    if not filho:
        session["rp_erro"] = "Não encontramos nenhuma conta do Focus com esse e-mail."
        return redirect(url_for("responsavel_painel"))

    existente = db.execute(
        "SELECT status FROM vinculos_parentais WHERE id_responsavel = ? AND id_usuario = ? AND ativo = 1",
        (resp["id"], filho["id"]),
    ).fetchone()
    if existente:
        if existente["status"] == "pendente":
            session["rp_erro"] = "Você já enviou uma solicitação pra essa conta — aguarde a aprovação."
        else:
            session["rp_erro"] = "Você já tem acesso a essa conta."
        return redirect(url_for("responsavel_painel"))

    token = secrets.token_urlsafe(24)
    db.execute(
        "INSERT INTO vinculos_parentais (id_responsavel, id_usuario, token, status) VALUES (?, ?, ?, 'pendente')",
        (resp["id"], filho["id"], token),
    )
    db.commit()

    nome_exibir = filho["username"] or filho["nome"]
    session["rp_sucesso"] = f"Solicitação enviada! Assim que @{nome_exibir} aprovar em Controle Parental, ele aparece aqui."
    return redirect(url_for("responsavel_painel"))


def _vinculo_ativo(db, id_responsavel, id_usuario):
    return db.execute(
        "SELECT 1 FROM vinculos_parentais WHERE id_responsavel = ? AND id_usuario = ? AND ativo = 1 AND status = 'ativo'",
        (id_responsavel, id_usuario),
    ).fetchone()


def _estimar_humor_semana(horas_estudo, total_horas):
    """Estima o humor (1 a 5) de cada dia da semana a partir da consistência
    real de horas estudadas nesse dia frente à média da semana. Mesma lógica
    usada em 'Meu Desempenho', reaproveitada aqui pra que o Painel do
    Responsável mostre o mesmo número que o próprio usuário vê.

    Dia sem nenhuma hora estudada não tem informação nenhuma sobre o humor
    de verdade — por isso conta como neutro (3), não como "ruim" (2). Antes
    isso usava 2 como padrão, e como a maioria dos dias de quem não estuda
    todo santo dia cai nesse caso, a média da semana ficava sempre grudada
    perto de 2, faça o que fizer nos dias em que a pessoa realmente estudou."""
    media_horas_dia = (total_horas / 7) if total_horas else 0
    humor_status = []
    for h in horas_estudo:
        if h <= 0:
            humor_status.append(3)
        else:
            humor_status.append(max(1, min(5, round(3 + (h - media_horas_dia)))))
    media_humor = round(sum(humor_status) / 7, 1) if humor_status else 3
    return humor_status, media_humor


@app.route("/responsavel/filho/<int:id_usuario>")
@responsavel_required
def responsavel_filho(id_usuario):
    db = get_db()
    resp = responsavel_atual()
    if not resp or not _vinculo_ativo(db, resp["id"], id_usuario):
        abort(403)

    filho = db.execute("SELECT * FROM usuarios WHERE id = ?", (id_usuario,)).fetchone()
    if not filho:
        abort(404)

    # ---- Mesma lógica de "Meu Desempenho": semana fixa (Segunda a Domingo) ----
    NOMES_DIA = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"]
    hoje = datetime.utcnow().date()
    inicio_semana = hoje - timedelta(days=hoje.weekday())
    fim_semana = inicio_semana + timedelta(days=6)

    linhas_dias = db.execute(
        """SELECT date(s.concluida_em) AS dia, SUM(s.duracao_minutos) AS minutos
           FROM sessoes_estudo s
           JOIN disciplinas d ON d.id = s.id_disciplina
           JOIN modulos m ON m.id = d.id_modulo
           WHERE m.id_usuario = ? AND date(s.concluida_em) BETWEEN ? AND ?
           GROUP BY date(s.concluida_em)""",
        (id_usuario, inicio_semana.isoformat(), fim_semana.isoformat()),
    ).fetchall()
    minutos_por_dia = {r["dia"]: (r["minutos"] or 0) for r in linhas_dias}

    dias_semana, horas_estudo = [], []
    for i in range(7):
        d = inicio_semana + timedelta(days=i)
        dias_semana.append(NOMES_DIA[d.weekday()])
        horas_estudo.append(round(minutos_por_dia.get(d.isoformat(), 0) / 60, 1))
    total_horas_estudo = round(sum(horas_estudo), 1)

    # ---- Humor médio da semana (mesma estimativa usada em "Meu Desempenho") ----
    _, media_humor = _estimar_humor_semana(horas_estudo, total_horas_estudo)

    # ---- Tempo total no site (atividade_site), semana fixa + geral ----
    # O heartbeat de "tempo no site" só soma enquanto a aba fica em primeiro
    # plano (visibilitychange). Uma sessão de estudo (Pomodoro) continua
    # contando minutos mesmo se a aba ficar em segundo plano, então o tempo
    # estudado pode ficar maior que o heartbeat registrou naquele dia. Pra
    # o tempo estudado sempre contar como tempo no site (nunca o contrário),
    # usamos o maior valor entre os dois, dia a dia.
    linhas_ativ = db.execute(
        "SELECT data, minutos FROM atividade_site WHERE id_usuario = ? AND data BETWEEN ? AND ?",
        (id_usuario, inicio_semana.isoformat(), fim_semana.isoformat()),
    ).fetchall()
    minutos_site_por_dia = {r["data"]: (r["minutos"] or 0) for r in linhas_ativ}
    minutos_site_semana = [
        max(
            minutos_site_por_dia.get((inicio_semana + timedelta(days=i)).isoformat(), 0),
            minutos_por_dia.get((inicio_semana + timedelta(days=i)).isoformat(), 0),
        )
        for i in range(7)
    ]
    horas_site_semana = [round(m / 60, 1) for m in minutos_site_semana]

    total_minutos_site_geral = db.execute(
        "SELECT COALESCE(SUM(minutos), 0) AS t FROM atividade_site WHERE id_usuario = ?",
        (id_usuario,),
    ).fetchone()["t"]
    total_minutos_estudo_geral = db.execute(
        "SELECT COALESCE(SUM(minutos_estudados), 0) AS t FROM disciplinas WHERE id_usuario = ?",
        (id_usuario,),
    ).fetchone()["t"]
    total_horas_site_geral = round(max(total_minutos_site_geral, total_minutos_estudo_geral) / 60, 1)
    total_horas_site_semana = round(sum(horas_site_semana), 1)

    # ---- Progresso por módulo (barra de progresso já usada em módulo.html) ----
    modulos = db.execute(
        """SELECT m.id, m.nome,
                  COUNT(t.id) AS total_tarefas,
                  SUM(CASE WHEN t.concluida THEN 1 ELSE 0 END) AS tarefas_feitas
           FROM modulos m
           LEFT JOIN disciplinas d ON d.id_modulo = m.id
           LEFT JOIN tarefas t ON t.id_disciplina = d.id
           WHERE m.id_usuario = ?
           GROUP BY m.id
           ORDER BY m.ordem, m.id""",
        (id_usuario,),
    ).fetchall()
    modulos_progresso = []
    for m in modulos:
        total = m["total_tarefas"] or 0
        feitas = m["tarefas_feitas"] or 0
        pct = round((feitas / total) * 100) if total else 0
        modulos_progresso.append({"nome": m["nome"], "pct": pct, "total": total, "feitas": feitas})

    tarefas = db.execute(
        """SELECT id, descricao, concluida, data_alvo
           FROM tarefas_responsavel
           WHERE id_usuario = ? AND id_responsavel = ?
           ORDER BY data_alvo DESC, criado_em DESC LIMIT 30""",
        (id_usuario, resp["id"]),
    ).fetchall()

    # ---- Alertas de segurança do Comunidades (Controle Parental) ----
    alertas_rows = db.execute(
        """SELECT a.id, a.motivo, a.contexto, a.criado_em, a.revisado, u.username AS autor_username
           FROM alertas_seguranca a
           LEFT JOIN usuarios u ON u.id = a.autor_id AND u.id != a.id_usuario
           WHERE a.id_usuario = ?
           ORDER BY a.criado_em DESC LIMIT 20""",
        (id_usuario,),
    ).fetchall()
    alertas = [
        {
            "id": a["id"],
            "texto": MOTIVOS_LEGIVEIS_RESPONSAVEL.get(a["motivo"], "Conteúdo sinalizado pelo filtro de segurança."),
            "contexto": "mensagem privada" if a["contexto"] == "dm" else ("grupo" if a["contexto"] == "grupo" else "sistema"),
            "autor_username": a["autor_username"],
            "criado_em": a["criado_em"],
            "revisado": bool(a["revisado"]),
        }
        for a in alertas_rows
    ]

    return render_template(
        "responsavel_filho.html",
        resp=resp,
        filho=filho,
        dias_semana=dias_semana,
        horas_estudo=horas_estudo,
        horas_site_semana=horas_site_semana,
        total_horas_estudo=total_horas_estudo,
        total_horas_site_semana=total_horas_site_semana,
        total_horas_site_geral=total_horas_site_geral,
        media_humor=media_humor,
        modulos_progresso=modulos_progresso,
        tarefas=tarefas,
        alertas=alertas,
    )


@app.route("/responsavel/filho/<int:id_usuario>/alerta/<int:alerta_id>/revisar", methods=["POST"])
@responsavel_required
def responsavel_revisar_alerta(id_usuario, alerta_id):
    db = get_db()
    resp = responsavel_atual()
    if not resp or not _vinculo_ativo(db, resp["id"], id_usuario):
        abort(403)
    db.execute(
        "UPDATE alertas_seguranca SET revisado = 1 WHERE id = ? AND id_usuario = ?",
        (alerta_id, id_usuario),
    )
    db.commit()
    return redirect(url_for("responsavel_filho", id_usuario=id_usuario))


# ---- Monitoramento de conversas (Controle Parental) ------------------------
# Só existe pra quem já tem um vínculo ativo com o filho — ou seja, é o
# próprio recurso de "controle parental ativado" monitorando as conversas
# que a Comunidades promete. O filho consegue ver, na tela dele, que esse
# vínculo existe (é ele quem aprova o link mágico), então não é um
# monitoramento escondido.

@app.route("/responsavel/filho/<int:id_usuario>/conversas")
@responsavel_required
def responsavel_conversas(id_usuario):
    db = get_db()
    resp = responsavel_atual()
    if not resp or not _vinculo_ativo(db, resp["id"], id_usuario):
        abort(403)

    filho = db.execute("SELECT * FROM usuarios WHERE id = ?", (id_usuario,)).fetchone()
    if not filho:
        abort(404)

    conversas_dm = db.execute(
        """SELECT u.id, u.username, u.foto_perfil,
                  (SELECT COUNT(*) FROM mensagens m2
                   WHERE (m2.remetente_id = ? AND m2.destinatario_id = u.id)
                      OR (m2.remetente_id = u.id AND m2.destinatario_id = ?)) AS total_mensagens,
                  (SELECT MAX(criado_em) FROM mensagens m3
                   WHERE (m3.remetente_id = ? AND m3.destinatario_id = u.id)
                      OR (m3.remetente_id = u.id AND m3.destinatario_id = ?)) AS ultima_mensagem_em
           FROM amizades a
           JOIN usuarios u ON u.id = (CASE WHEN a.solicitante_id = ? THEN a.destinatario_id ELSE a.solicitante_id END)
           WHERE (a.solicitante_id = ? OR a.destinatario_id = ?) AND a.status = 'aceito'
           ORDER BY ultima_mensagem_em DESC NULLS LAST""",
        (id_usuario, id_usuario, id_usuario, id_usuario, id_usuario, id_usuario, id_usuario),
    ).fetchall()

    grupos = db.execute(
        """SELECT g.id, g.nome, g.foto,
                  (SELECT COUNT(*) FROM grupo_membros gm2 WHERE gm2.grupo_id = g.id) AS total_membros
           FROM grupo_membros gm JOIN grupos g ON g.id = gm.grupo_id
           WHERE gm.usuario_id = ?
           ORDER BY g.nome""",
        (id_usuario,),
    ).fetchall()

    return render_template(
        "responsavel_conversas.html",
        resp=resp,
        filho=filho,
        conversas_dm=conversas_dm,
        grupos=grupos,
    )


@app.route("/responsavel/filho/<int:id_usuario>/conversas/dm/<int:amigo_id>")
@responsavel_required
def responsavel_conversa_dm(id_usuario, amigo_id):
    db = get_db()
    resp = responsavel_atual()
    if not resp or not _vinculo_ativo(db, resp["id"], id_usuario):
        abort(403)

    filho = db.execute("SELECT id, username, foto_perfil FROM usuarios WHERE id = ?", (id_usuario,)).fetchone()
    amigo = db.execute("SELECT id, username, foto_perfil FROM usuarios WHERE id = ?", (amigo_id,)).fetchone()
    if not filho or not amigo:
        abort(404)

    mensagens_rows = db.execute(
        """SELECT * FROM mensagens
           WHERE (remetente_id = ? AND destinatario_id = ?) OR (remetente_id = ? AND destinatario_id = ?)
           ORDER BY id ASC LIMIT 500""",
        (id_usuario, amigo_id, amigo_id, id_usuario),
    ).fetchall()
    mensagens = [_mensagem_para_json(db, m) for m in mensagens_rows]

    return render_template(
        "responsavel_conversa.html",
        resp=resp,
        filho=filho,
        titulo=f"@{amigo['username']}",
        icone_url=amigo["foto_perfil"] or "img/ex1.png",
        mensagens=mensagens,
        voltar_url=url_for("responsavel_conversas", id_usuario=id_usuario),
    )


@app.route("/responsavel/filho/<int:id_usuario>/conversas/grupo/<int:grupo_id>")
@responsavel_required
def responsavel_conversa_grupo(id_usuario, grupo_id):
    db = get_db()
    resp = responsavel_atual()
    if not resp or not _vinculo_ativo(db, resp["id"], id_usuario):
        abort(403)

    filho = db.execute("SELECT id, username FROM usuarios WHERE id = ?", (id_usuario,)).fetchone()
    grupo = db.execute("SELECT * FROM grupos WHERE id = ?", (grupo_id,)).fetchone()
    if not filho or not grupo or not _membro_grupo(db, grupo_id, id_usuario):
        abort(404)

    mensagens_rows = db.execute(
        "SELECT * FROM mensagens WHERE grupo_id = ? ORDER BY id ASC LIMIT 500", (grupo_id,)
    ).fetchall()
    mensagens = [_mensagem_para_json(db, m) for m in mensagens_rows]

    return render_template(
        "responsavel_conversa.html",
        resp=resp,
        filho=filho,
        titulo=grupo["nome"],
        icone_url=grupo["foto"] or "img/ex1.png",
        mensagens=mensagens,
        voltar_url=url_for("responsavel_conversas", id_usuario=id_usuario),
    )


@app.route("/responsavel/filho/<int:id_usuario>/tarefa", methods=["POST"])
@responsavel_required
def responsavel_criar_tarefa(id_usuario):
    db = get_db()
    resp = responsavel_atual()
    if not resp or not _vinculo_ativo(db, resp["id"], id_usuario):
        abort(403)

    descricao = request.form.get("descricao", "").strip()
    if descricao:
        hoje = datetime.utcnow().date().isoformat()
        db.execute(
            "INSERT INTO tarefas_responsavel (id_usuario, id_responsavel, descricao, data_alvo) VALUES (?, ?, ?, ?)",
            (id_usuario, resp["id"], descricao, hoje),
        )
        db.commit()
    return redirect(url_for("responsavel_filho", id_usuario=id_usuario))


@app.route("/responsavel/tarefa/<int:tarefa_id>/excluir", methods=["POST"])
@responsavel_required
def responsavel_excluir_tarefa(tarefa_id):
    db = get_db()
    resp = responsavel_atual()
    db.execute(
        "DELETE FROM tarefas_responsavel WHERE id = ? AND id_responsavel = ?",
        (tarefa_id, resp["id"]),
    )
    db.commit()
    id_usuario = request.form.get("id_usuario", type=int)
    if id_usuario:
        return redirect(url_for("responsavel_filho", id_usuario=id_usuario))
    return redirect(url_for("responsavel_painel"))




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


# ---------------------------------------------------------------------------
# Metas diárias (uma por humor, renovadas a cada 6 horas) — agenda_metas
# ---------------------------------------------------------------------------

BIBLIOTECA_METAS = {
    1: [
        {"t": "Foco de Manutenção", "d": "A meta é organizar seus tópicos de estudo e limpar sua mesa de trabalho. Evite novos conteúdos hoje."},
        {"t": "Rastreio de Dúvidas", "d": "Liste 5 termos técnicos que você tem dificuldade e apenas pesquise o significado de um deles."},
        {"t": "Input Suave", "d": "Assista a um vídeo curto (máx 5min) sobre produtividade suave ou bem-estar."},
    ],
    2: [
        {"t": "Início de Fluxo", "d": "Assista a uma videoaula de 15 minutos e anote os 3 pontos mais importantes do conteúdo."},
        {"t": "Resumo em Flashcard", "d": "Crie 5 flashcards sobre o último módulo que você estudou e revise-os uma vez."},
        {"t": "Micro-Prática", "d": "Resolva 3 exercícios de nível fácil da sua disciplina principal para manter o engajamento."},
    ],
    3: [
        {"t": "Ciclo Pomodoro", "d": "Realize uma sessão de 45 minutos de estudo focado (Deep Work) sem nenhuma distração."},
        {"t": "Mapa Mental Base", "d": "Crie um mapa mental conectando o tema central da sua matéria a pelo menos 5 ramificações."},
        {"t": "Bateria de Fixação", "d": "Resolva 10 questões de nível médio e analise detalhadamente o porquê de cada acerto ou erro."},
    ],
    4: [
        {"t": "Técnica Feynman", "d": "Grave um áudio de 5 minutos explicando a matéria atual como se desse aula para um iniciante."},
        {"t": "Simulado de Pressão", "d": "Resolva 20 questões cronometrando exatamente 2 minutos para cada uma das perguntas."},
        {"t": "Desafio Prático", "d": "Crie um problema prático da vida real que envolva a teoria que você está estudando no momento."},
    ],
    5: [
        {"t": "Sprint de Produção", "d": "Avançar dois módulos inteiros e criar um guia de estudos rápido para quem vai começar."},
        {"t": "Projeto de Domínio", "d": "Desenvolva uma aplicação real (código, plano, texto ou cálculo) do conhecimento da semana."},
        {"t": "Maratona Analítica", "d": "Resolva 40 questões variadas e identifique qual o seu principal 'ponto cego' no desempenho."},
    ],
}

NOMES_HUMOR = {1: "Crítica", 2: "Baixa", 3: "Estável", 4: "Alta", 5: "Máxima"}


def _inicio_periodo_atual():
    """Início do bloco de 6h em que estamos agora (00h, 06h, 12h ou 18h).
    Usa UTC de propósito: o SQLite grava CURRENT_TIMESTAMP em UTC, então
    comparar com a hora local (datetime.now()) causava um bug real — perto
    da virada de bloco, metas do bloco anterior "vazavam" pro bloco novo
    (ou o contrário) por causa da diferença de fuso do servidor, fazendo o
    checklist não resetar e continuar mostrando metas já marcadas."""
    agora = datetime.utcnow()
    bloco = (agora.hour // 6) * 6
    return agora.replace(hour=bloco, minute=0, second=0, microsecond=0)


def _linha_para_json(row):
    return {
        "id": row["id"],
        "titulo": row["titulo"] or "",
        "descricao": row["descricao"],
        "esforco": row["esforco"],
        "humor_nome": NOMES_HUMOR.get(row["esforco"], "Estável"),
        "concluida": bool(row["concluida"]),
        "data_criacao": row["data_criacao"],
        "concluida_em": row["concluida_em"],
        "usando_ia": bool(row["gerado_por_ia"]) if row["gerado_por_ia"] is not None else False,
    }


def _contexto_disciplinas_usuario(db, id_usuario):
    """Resumo (nome, dificuldade, % concluído) das disciplinas reais do
    usuário, usado tanto pelas microtarefas quanto pelas metas com IA."""
    disciplinas = db.execute(
        """SELECT d.nome, d.dificuldade,
                  COUNT(t.id) AS total, SUM(CASE WHEN t.concluida THEN 1 ELSE 0 END) AS feitas
           FROM disciplinas d
           JOIN modulos m ON m.id = d.id_modulo
           LEFT JOIN tarefas t ON t.id_disciplina = d.id
           WHERE m.id_usuario = ?
           GROUP BY d.id""",
        (id_usuario,),
    ).fetchall()

    contexto = []
    for d in disciplinas:
        total = d["total"] or 0
        feitas = d["feitas"] or 0
        progresso = round((feitas / total * 100)) if total > 0 else 0
        contexto.append({"nome": d["nome"], "dificuldade": d["dificuldade"], "progresso": progresso})
    return contexto


@app.route("/api/metas_pendentes")
@login_required
def api_metas_pendentes():
    """Só CONSULTA as metas do bloco de 6h atual, sem gerar nada novo (ao
    contrário de /api/metas_atuais) — usada pelo alerta de metas do
    Dashboard, que não deve disparar geração (nem custo de IA) só de passar
    por lá; se o bloco ainda não tem metas geradas, simplesmente não há
    nada pendente pra alertar ainda."""
    db = get_db()
    inicio = _inicio_periodo_atual()
    linhas = db.execute(
        """SELECT concluida FROM agenda_metas WHERE usuario_id = ? AND data_criacao >= ?""",
        (session["usuario_id"], inicio.strftime("%Y-%m-%d %H:%M:%S")),
    ).fetchall()
    total = len(linhas)
    concluidas = sum(1 for l in linhas if l["concluida"])
    return jsonify({
        "bloco": inicio.isoformat(),
        "total": total,
        "concluidas": concluidas,
        "pendentes": total - concluidas,
    })


@app.route("/api/metas_atuais")
@login_required
def api_metas_atuais():
    """Devolve as metas diárias do bloco de 6h atual (formato checklist).
    Se o bloco ainda não tiver metas, gera novas — com IA (Gemini) quando
    configurada e com base nas disciplinas reais do usuário, caindo pra
    biblioteca fixa quando a IA não está disponível ou falha."""
    try:
        energia = int(request.args.get("energia", "3"))
    except ValueError:
        energia = 3
    energia = max(1, min(5, energia))

    db = get_db()
    inicio = _inicio_periodo_atual()
    linhas = db.execute(
        """SELECT * FROM agenda_metas WHERE usuario_id = ? AND data_criacao >= ?
           ORDER BY id ASC""",
        (session["usuario_id"], inicio.strftime("%Y-%m-%d %H:%M:%S")),
    ).fetchall()

    if not linhas:
        contexto = _contexto_disciplinas_usuario(db, session["usuario_id"])
        metas_ia = gerar_metas_ia(energia, contexto)
        usando_ia = metas_ia is not None

        if usando_ia:
            escolhidas = [{"t": m.get("titulo", ""), "d": m.get("descricao", "")} for m in metas_ia]
        else:
            escolhidas = random.sample(BIBLIOTECA_METAS[energia], k=len(BIBLIOTECA_METAS[energia]))

        for escolhida in escolhidas:
            db.execute(
                """INSERT INTO agenda_metas
                   (usuario_id, titulo, descricao, esforco, dia_semana, concluida, gerado_por_ia)
                   VALUES (?, ?, ?, ?, ?, 0, ?)""",
                (session["usuario_id"], escolhida["t"], escolhida["d"], energia,
                 datetime.now().weekday(), int(usando_ia)),
            )
        db.commit()
        linhas = db.execute(
            """SELECT * FROM agenda_metas WHERE usuario_id = ? AND data_criacao >= ?
               ORDER BY id ASC""",
            (session["usuario_id"], inicio.strftime("%Y-%m-%d %H:%M:%S")),
        ).fetchall()

    return jsonify({
        "metas": [_linha_para_json(r) for r in linhas],
        "proxima_atualizacao": (inicio + timedelta(hours=6)).strftime("%Y-%m-%dT%H:%M:%S"),
    })


@app.route("/api/meta/<int:meta_id>/concluir", methods=["POST"])
@login_required
def api_meta_concluir(meta_id):
    """Marca/desmarca uma meta do checklist diário (toggle, igual às tarefas)."""
    db = get_db()
    meta = db.execute(
        "SELECT id, concluida FROM agenda_metas WHERE id = ? AND usuario_id = ?",
        (meta_id, session["usuario_id"]),
    ).fetchone()
    if meta is None:
        return jsonify({"ok": False}), 404

    novo_valor = 0 if meta["concluida"] else 1
    if novo_valor:
        db.execute(
            "UPDATE agenda_metas SET concluida = 1, concluida_em = CURRENT_TIMESTAMP WHERE id = ?",
            (meta_id,),
        )
    else:
        db.execute(
            "UPDATE agenda_metas SET concluida = 0, concluida_em = NULL WHERE id = ?",
            (meta_id,),
        )
    db.commit()
    return jsonify({"ok": True, "concluida": bool(novo_valor)})


@app.route("/api/metas_historico")
@login_required
def api_metas_historico():
    db = get_db()
    # UTC pelo mesmo motivo de _inicio_periodo_atual: data_criacao/concluida_em
    # vêm do CURRENT_TIMESTAMP do SQLite, que é sempre UTC.
    limite = (datetime.utcnow() - timedelta(days=30)).strftime("%Y-%m-%d %H:%M:%S")
    linhas = db.execute(
        """SELECT * FROM agenda_metas
           WHERE usuario_id = ? AND concluida = 1 AND data_criacao >= ?
           ORDER BY concluida_em DESC""",
        (session["usuario_id"], limite),
    ).fetchall()
    return jsonify({"metas": [_linha_para_json(r) for r in linhas]})


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


def gerar_metas_ia(energia, contexto_disciplinas):
    """Pede pra IA do Gemini gerar 3 metas de estudo personalizadas pro bloco
    de 6h atual, considerando energia e as disciplinas reais do estudante.
    Devolve None se não tiver chave ou se algo der errado — nesse caso quem
    chamou cai de volta pra biblioteca fixa de sempre."""
    if not GEMINI_API_KEY:
        return None

    resumo_disciplinas = "\n".join(
        f"- {d['nome']} ({d['progresso']}% concluído, dificuldade {d['dificuldade']}/5)"
        for d in contexto_disciplinas
    ) or "Nenhuma disciplina cadastrada ainda."

    prompt = f"""Você é o assistente de estudos do app Focus. Gere exatamente 3 metas de estudo
para o estudante cumprir nas próximas 6 horas, considerando o nível de energia dele (1=crítica a
5=máxima) e as disciplinas reais que ele está estudando. As metas devem ter dificuldade compatível
com o nível de energia (energia baixa = metas leves de organização/revisão, energia alta = metas
mais ambiciosas de produção/prática), e usar as disciplinas reais quando fizer sentido.

Energia atual: {energia}/5

Disciplinas do estudante:
{resumo_disciplinas}

Responda com um array JSON de exatamente 3 objetos no formato:
[{{"titulo": "Título curto (até 4 palavras)", "descricao": "Instrução prática de 1-2 frases"}}]"""

    payload = json.dumps({
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {"response_mime_type": "application/json"},
    }).encode("utf-8")

    url = f"https://generativelanguage.googleapis.com/v1beta/models/{GEMINI_MODEL}:generateContent"
    req = urllib.request.Request(
        url,
        data=payload,
        headers={"Content-Type": "application/json", "x-goog-api-key": GEMINI_API_KEY},
        method="POST",
    )

    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            resultado = json.loads(resp.read().decode("utf-8"))
        texto = resultado["candidates"][0]["content"]["parts"][0]["text"].strip()
        texto = texto.replace("```json", "").replace("```", "").strip()
        metas = json.loads(texto)
        if isinstance(metas, list) and len(metas) > 0:
            return metas[:3]
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
    contexto = _contexto_disciplinas_usuario(db, session["usuario_id"])

    microtarefas = gerar_microtarefas_ia(energia, contexto)
    if microtarefas is None:
        return jsonify({"ok": False, "usando_ia": False})

    return jsonify({"ok": True, "usando_ia": True, "microtarefas": microtarefas})


def gerar_sugestao_estudo_ia(contexto_disciplinas):
    """Pede pra IA escolher UMA disciplina pra focar hoje, com um motivo curto.
    Devolve None se não tiver chave ou se algo der errado."""
    if not GEMINI_API_KEY or not contexto_disciplinas:
        return None

    lista = "\n".join(
        f"- id {d['id']}: {d['nome']} (progresso {d['progresso']}%, dificuldade {d['dificuldade']}/5, "
        f"{d['minutos_estudados']} minutos estudados no total)"
        for d in contexto_disciplinas
    )

    prompt = f"""Você é o assistente de estudos do app Focus. Olhando a lista de disciplinas do
estudante abaixo, escolha APENAS UMA pra ele focar hoje — dê preferência a disciplinas com progresso
baixo, dificuldade alta, ou que estudou pouco recentemente. Explique o motivo em uma frase curta e
motivadora.

Disciplinas:
{lista}

Responda com um único objeto JSON no formato:
{{"id": 3, "motivo": "frase curta explicando por que estudar essa disciplina hoje"}}"""

    payload = json.dumps({
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {"response_mime_type": "application/json"},
    }).encode("utf-8")

    url = f"https://generativelanguage.googleapis.com/v1beta/models/{GEMINI_MODEL}:generateContent"
    req = urllib.request.Request(
        url,
        data=payload,
        headers={"Content-Type": "application/json", "x-goog-api-key": GEMINI_API_KEY},
        method="POST",
    )

    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            resultado = json.loads(resp.read().decode("utf-8"))
        texto = resultado["candidates"][0]["content"]["parts"][0]["text"].strip()
        texto = texto.replace("```json", "").replace("```", "").strip()
        escolha = json.loads(texto)
        if isinstance(escolha, dict) and "id" in escolha and "motivo" in escolha:
            return escolha
    except (urllib.error.URLError, KeyError, IndexError, ValueError, json.JSONDecodeError):
        return None
    return None


@app.route("/api/sugestao_estudo")
@login_required
def api_sugestao_estudo():
    db = get_db()
    disciplinas = db.execute(
        """SELECT d.id, d.nome, d.dificuldade, d.minutos_estudados, d.id_modulo,
                  COUNT(t.id) AS total, SUM(CASE WHEN t.concluida THEN 1 ELSE 0 END) AS feitas
           FROM disciplinas d
           JOIN modulos m ON m.id = d.id_modulo
           LEFT JOIN tarefas t ON t.id_disciplina = d.id
           WHERE m.id_usuario = ?
           GROUP BY d.id""",
        (session["usuario_id"],),
    ).fetchall()

    if not disciplinas:
        return jsonify({"ok": False})

    contexto = []
    for d in disciplinas:
        total = d["total"] or 0
        feitas = d["feitas"] or 0
        progresso = round((feitas / total * 100)) if total > 0 else 0
        contexto.append({
            "id": d["id"], "id_modulo": d["id_modulo"], "nome": d["nome"],
            "dificuldade": d["dificuldade"], "progresso": progresso,
            "minutos_estudados": d["minutos_estudados"] or 0,
        })

    escolha_ia = gerar_sugestao_estudo_ia(contexto)
    if escolha_ia:
        alvo = next((c for c in contexto if c["id"] == escolha_ia["id"]), None)
        if alvo:
            return jsonify({
                "ok": True, "usando_ia": True,
                "disciplina_id": alvo["id"], "modulo_id": alvo["id_modulo"],
                "nome": alvo["nome"], "motivo": escolha_ia["motivo"],
            })

    # Sem IA (ou falhou): escolhe por regra — menor progresso, desempate por maior dificuldade
    escolhida = sorted(contexto, key=lambda c: (c["progresso"], -c["dificuldade"]))[0]
    if escolhida["progresso"] < 100:
        motivo = f"Essa disciplina ainda está com {escolhida['progresso']}% das tarefas concluídas — um bom lugar pra focar hoje."
    else:
        motivo = "Você já concluiu tudo por aqui — que tal revisar pra manter o conteúdo fresco?"

    return jsonify({
        "ok": True, "usando_ia": False,
        "disciplina_id": escolhida["id"], "modulo_id": escolhida["id_modulo"],
        "nome": escolhida["nome"], "motivo": motivo,
    })


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
           GROUP BY m.id ORDER BY m.ordem ASC""",
        (id_usuario,),
    ).fetchall()

    # Tarefas definidas pelo responsável (Controle Parental) — só aparecem
    # (e a aba inteira só existe) pra quem tem pelo menos um vínculo ativo.
    tem_controle_parental = db.execute(
        "SELECT 1 FROM vinculos_parentais WHERE id_usuario = ? AND ativo = 1 AND status = 'ativo'",
        (id_usuario,),
    ).fetchone() is not None

    tarefas_responsavel = []
    if tem_controle_parental:
        tarefas_responsavel = db.execute(
            """SELECT t.id, t.descricao, r.email AS email_responsavel
               FROM tarefas_responsavel t
               JOIN responsaveis r ON r.id = t.id_responsavel
               WHERE t.id_usuario = ? AND t.concluida = 0
               ORDER BY t.criado_em ASC""",
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
        tem_controle_parental=tem_controle_parental,
        tarefas_responsavel=tarefas_responsavel,
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


@app.route("/meus_estudos/modulo/<int:modulo_id>/editar", methods=["POST"])
@login_required
def editar_modulo(modulo_id):
    db = get_db()
    nome = request.form.get("nome", "").strip()
    if nome:
        db.execute(
            "UPDATE modulos SET nome = ? WHERE id = ? AND id_usuario = ?",
            (nome, modulo_id, session["usuario_id"]),
        )
        db.commit()
    return redirect(url_for("meus_estudos"))


@app.route("/meus_estudos/modulos/reordenar", methods=["POST"])
@login_required
def reordenar_modulos():
    dados = request.get_json(silent=True) or {}
    ids = dados.get("ids", [])
    db = get_db()
    for posicao, modulo_id in enumerate(ids):
        db.execute(
            "UPDATE modulos SET ordem = ? WHERE id = ? AND id_usuario = ?",
            (posicao, modulo_id, session["usuario_id"]),
        )
    db.commit()
    return jsonify({"ok": True})


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
           GROUP BY d.id ORDER BY d.ordem ASC""",
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


@app.route("/meus_estudos/disciplina/<int:disciplina_id>/editar", methods=["POST"])
@login_required
def editar_disciplina(disciplina_id):
    db = get_db()
    disciplina = db.execute(
        "SELECT id_modulo FROM disciplinas WHERE id = ? AND id_usuario = ?",
        (disciplina_id, session["usuario_id"]),
    ).fetchone()
    if not disciplina:
        return redirect(url_for("meus_estudos"))

    nome = request.form.get("nome", "").strip()
    dificuldade = request.form.get("dificuldade", "1")
    if nome:
        db.execute(
            "UPDATE disciplinas SET nome = ?, dificuldade = ? WHERE id = ?",
            (nome, dificuldade, disciplina_id),
        )
        db.commit()
    return redirect(url_for("ver_modulo", modulo_id=disciplina["id_modulo"]))


@app.route("/meus_estudos/modulo/<int:modulo_id>/disciplinas/reordenar", methods=["POST"])
@login_required
def reordenar_disciplinas(modulo_id):
    dados = request.get_json(silent=True) or {}
    ids = dados.get("ids", [])
    db = get_db()
    for posicao, disciplina_id in enumerate(ids):
        db.execute(
            "UPDATE disciplinas SET ordem = ? WHERE id = ? AND id_usuario = ? AND id_modulo = ?",
            (posicao, disciplina_id, session["usuario_id"], modulo_id),
        )
    db.commit()
    return jsonify({"ok": True})


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


def _analise_estatica_desempenho(nivel, user):
    """Análise padrão (sem IA) — a mesma redação de sempre, usada quando a
    IA não está configurada, falha, ou ainda não há dados reais suficientes
    na semana pra analisar. Igual em espírito às metas/microtarefas fixas."""
    if nivel == "alto":
        return (f"Mapeamento neural concluído, {user}. Sistema indica alta performance contínua. "
                 "Eficiência cognitiva acima do padrão esperado. Sincronização otimizada.")
    elif nivel == "médio":
        return (f"Mapeamento neural concluído, {user}. Performance estável detectada. "
                 "Há margem real para expansão de produtividade e foco.")
    return (f"Mapeamento neural concluído, {user}. Baixa consistência operacional identificada. "
             "Recomenda-se reajuste de rotina e gestão de energia mental.")


def gerar_analise_desempenho_ia(user, dias_semana, horas_estudo, humor_status,
                                 total_horas, media_humor, pct_extra, distrib_modulos):
    """Pede pra IA (Gemini) analisar de verdade a semana real de estudos do
    usuário (horas por dia, humor estimado, sincronia e distribuição por
    módulo) e escrever uma análise curta. Devolve None se não tiver chave
    configurada ou se algo der errado — nesse caso quem chamou cai pra
    análise estática de sempre, igual às metas/microtarefas sem IA."""
    if not GEMINI_API_KEY:
        return None

    resumo_dias = "\n".join(
        f"- {d}: {h}h estudadas, humor estimado {m}/5"
        for d, h, m in zip(dias_semana, horas_estudo, humor_status)
    )
    resumo_modulos = "\n".join(
        f"- {nome}: {pct}% do tempo de estudo" for nome, pct in distrib_modulos
    ) or "Nenhum módulo com tempo de estudo registrado ainda."

    prompt = f"""Você é o assistente de desempenho do app Focus, com um tom de HUD/sistema neural
(ex.: "Mapeamento neural concluído", "Sincronização otimizada"). Analise os dados REAIS da semana de
estudos do usuário {user} abaixo e escreva uma análise curta (3 a 4 frases, em português), apontando
um padrão real (dia mais forte ou mais fraco, consistência ao longo da semana, módulo que consumiu
mais tempo) e terminando com uma recomendação prática. Não invente números que não estejam nos dados.

Estudo total na semana: {total_horas}h
Humor médio estimado: {media_humor}/5
Sincronia (aderência à meta semanal): {pct_extra}%

Resumo por dia:
{resumo_dias}

Distribuição do tempo de estudo por módulo:
{resumo_modulos}

Responda em texto puro (sem JSON, sem markdown, sem aspas), só o parágrafo da análise."""

    payload = json.dumps({"contents": [{"parts": [{"text": prompt}]}]}).encode("utf-8")
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{GEMINI_MODEL}:generateContent"
    req = urllib.request.Request(
        url,
        data=payload,
        headers={"Content-Type": "application/json", "x-goog-api-key": GEMINI_API_KEY},
        method="POST",
    )

    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            resultado = json.loads(resp.read().decode("utf-8"))
        texto = resultado["candidates"][0]["content"]["parts"][0]["text"].strip()
        texto = texto.replace("```", "").strip()
        if texto:
            return texto
    except (urllib.error.URLError, KeyError, IndexError, ValueError, json.JSONDecodeError):
        return None
    return None


@app.route("/meu_desempenho")
@login_required
def meu_desempenho():
    db = get_db()
    id_usuario = session["usuario_id"]
    u = db.execute(
        "SELECT foto_perfil, username, status_disponibilidade FROM usuarios WHERE id = ?",
        (id_usuario,),
    ).fetchone()
    username_limpo = u["username"] or "usuário"
    foto_perfil = u["foto_perfil"] or "img/ex1.png"
    status = u["status_disponibilidade"] or "online"

    # ---- Horas de estudo REAIS da semana atual (tabela sessoes_estudo) ----
    # Semana fixa (Segunda a Domingo), reiniciando toda segunda — não é uma
    # janela deslizante dos últimos 7 dias.
    NOMES_DIA = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"]
    hoje = datetime.utcnow().date()
    inicio_semana = hoje - timedelta(days=hoje.weekday())
    fim_semana = inicio_semana + timedelta(days=6)

    linhas_dias = db.execute(
        """SELECT date(s.concluida_em) AS dia, SUM(s.duracao_minutos) AS minutos
           FROM sessoes_estudo s
           JOIN disciplinas d ON d.id = s.id_disciplina
           JOIN modulos m ON m.id = d.id_modulo
           WHERE m.id_usuario = ? AND date(s.concluida_em) BETWEEN ? AND ?
           GROUP BY date(s.concluida_em)""",
        (id_usuario, inicio_semana.isoformat(), fim_semana.isoformat()),
    ).fetchall()
    minutos_por_dia = {r["dia"]: (r["minutos"] or 0) for r in linhas_dias}

    dias_semana = []
    horas_estudo = []
    for i in range(7):
        d = inicio_semana + timedelta(days=i)
        dias_semana.append(NOMES_DIA[d.weekday()])
        horas_estudo.append(round(minutos_por_dia.get(d.isoformat(), 0) / 60, 1))

    total_horas = round(sum(horas_estudo), 1)
    tem_dados_reais = total_horas > 0

    # ---- Distribuição REAL de tempo por módulo (gráfico de rosca) ----
    # Prioriza a semana atual; se ainda não houve sessão essa semana, cai
    # pro total acumulado de cada módulo (também real, só que histórico).
    linhas_modulos_semana = db.execute(
        """SELECT m.nome AS modulo, SUM(s.duracao_minutos) AS minutos
           FROM sessoes_estudo s
           JOIN disciplinas d ON d.id = s.id_disciplina
           JOIN modulos m ON m.id = d.id_modulo
           WHERE m.id_usuario = ? AND date(s.concluida_em) BETWEEN ? AND ?
           GROUP BY m.id
           ORDER BY minutos DESC""",
        (id_usuario, inicio_semana.isoformat(), fim_semana.isoformat()),
    ).fetchall()

    if linhas_modulos_semana and sum((r["minutos"] or 0) for r in linhas_modulos_semana) > 0:
        base_modulos = linhas_modulos_semana
    else:
        base_modulos = db.execute(
            """SELECT m.nome AS modulo, SUM(d.minutos_estudados) AS minutos
               FROM disciplinas d
               JOIN modulos m ON m.id = d.id_modulo
               WHERE m.id_usuario = ?
               GROUP BY m.id
               ORDER BY minutos DESC""",
            (id_usuario,),
        ).fetchall()

    minutos_total_modulos = sum((r["minutos"] or 0) for r in base_modulos)
    modulos_labels = []
    modulos_pct = []
    for r in base_modulos:
        minutos = r["minutos"] or 0
        if minutos <= 0:
            continue
        modulos_labels.append(r["modulo"])
        modulos_pct.append(round(minutos / minutos_total_modulos * 100, 1) if minutos_total_modulos else 0)

    # ---- Humor: ainda não existe captura direta de humor por dia no banco,
    # então estimamos a partir da consistência real de horas estudadas (mais
    # horas que a média da semana = humor estimado mais alto), em vez de
    # sortear números aleatórios como antes. ----
    humor_status, media_humor = _estimar_humor_semana(horas_estudo, total_horas)

    # ---- Sincronia: aderência real às horas estudadas frente a uma meta
    # semanal de referência (14h), em vez de um número aleatório. ----
    META_SEMANAL_HORAS = 14
    pct_extra = min(100, round((total_horas / META_SEMANAL_HORAS) * 100)) if tem_dados_reais else 0

    if media_humor >= 4 and total_horas >= 14:
        nivel = "alto"
    elif media_humor >= 2.8:
        nivel = "médio"
    else:
        nivel = "baixo"

    # ---- Análise da semana: IA de verdade quando configurada e há dados
    # reais pra analisar; cai pra texto estático (igual às metas/microtarefas)
    # quando a IA não está aplicada, falha, ou ainda não há dados reais. ----
    analise_ia = None
    if tem_dados_reais:
        analise_ia = gerar_analise_desempenho_ia(
            username_limpo, dias_semana, horas_estudo, humor_status,
            total_horas, media_humor, pct_extra, list(zip(modulos_labels, modulos_pct)),
        )
    usando_ia_analise = analise_ia is not None
    analise_texto = analise_ia if usando_ia_analise else _analise_estatica_desempenho(nivel, username_limpo)

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
        pct_extra=pct_extra,
        modulos_labels=modulos_labels,
        modulos_pct=modulos_pct,
        analise_texto=analise_texto,
        usando_ia_analise=usando_ia_analise,
        status=status,
    )


# ---------------------------------------------------------------------------
# Comunidades — amigos, chat privado e grupos
# ---------------------------------------------------------------------------
# A maior parte de quem usa o Focus é criança, então TODO envio de mensagem
# (privada ou em grupo) passa pelo filtro abaixo antes de ser salvo. Nada
# aqui decide "é crime" ou coisa do tipo — é só uma triagem básica de
# linguagem e de padrões de risco (pedir endereço, pedir segredo, marcar
# encontro), no espírito de segurança infantil, não um sistema jurídico.

PALAVRAS_IMPROPRIAS = {
    "porra", "caralho", "merda", "puta", "putaria", "fdp", "pqp", "arrombado",
    "cuzao", "cuzão", "buceta", "piroca", "pinto", "viado", "corno", "otario",
    "otário", "idiota", "imbecil", "retardado", "vagabunda", "vagabundo",
    "safada", "safado", "gostosa", "gostoso", "nude", "nudes", "pelada",
    "pelado", "sexo", "transar", "pornô", "porno",
}

# Padrões de risco relacionados a aliciamento — ficam no nível de padrão
# (pedir endereço, pedir segredo, marcar encontro), sem listar frases prontas
# categorizadas, só o suficiente pra reconhecer a tentativa.
PADROES_SUSPEITOS = [
    (re.compile(r"\bqual\b(?:\s+\S+){0,3}\s+endere[cç]o\b", re.IGNORECASE), "pediu_endereco"),
    (re.compile(r"\bonde\b(?:\s+\S+){0,3}\s+(mora|fica\s+sua\s+casa)\b", re.IGNORECASE), "pediu_localizacao"),
    (re.compile(r"\b(numero|número)\b(?:\s+\S+){0,3}\s+(telefone|whats\s?app|zap)\b", re.IGNORECASE), "pediu_contato_externo"),
    (re.compile(r"\bn[aã]o\b(?:\s+\S+){0,3}\s+(conta|conte|fala|fale)\b(?:\s+\S+){0,3}\s+pais\b", re.IGNORECASE), "pediu_segredo"),
    (re.compile(r"\bsegredo\b(?:\s+\S+){0,4}\s+(nosso|entre\s+(a\s+)?gente|só\s+nosso)\b", re.IGNORECASE), "pediu_segredo"),
    (re.compile(r"\bencontrar\b(?:\s+\S+){0,4}\s+(pessoalmente|escondido|sem\s+ningu[eé]m\s+saber)\b", re.IGNORECASE), "pediu_encontro"),
    (re.compile(r"\bfoto\b(?:\s+\S+){0,4}\s+(sem\s+roupa|pelad)", re.IGNORECASE), "pediu_foto_intima"),
]

REGEX_LINK = re.compile(r"(https?://\S+|www\.\S+|\b[a-z0-9-]+\.(com|net|org|br|io|gg|app|me)\b)", re.IGNORECASE)

# Mesmo limite do maxlength do campo no HTML — aqui é o que realmente vale,
# já que o front-end sozinho não impede um POST direto com texto maior.
LIMITE_TAMANHO_MENSAGEM = 1000


def _linkificar_html(texto):
    """Mesma lógica da função linkificar() do comunidades.js (escapa e só
    depois transforma URL em <a>), só que rodando no servidor — sem isso,
    uma mensagem com link só ficava clicável enquanto chegava ao vivo via
    poll; ao recarregar a página (que renderiza pelo Jinja) ela virava
    texto puro de novo."""
    escapado = str(markup_escape(texto))

    def _sub(match):
        url = match.group(0)
        href = url if url.lower().startswith("http") else f"https://{url}"
        return f'<a href="{href}" target="_blank" rel="noopener noreferrer">{url}</a>'

    return Markup(REGEX_LINK.sub(_sub, escapado))


app.jinja_env.filters["linkificar_html"] = _linkificar_html

MOTIVOS_LEGIVEIS = {
    "linguagem_impropria": "Linguagem imprópria bloqueada pelo filtro de segurança.",
    "pediu_endereco": "Mensagem bloqueada: pediu endereço.",
    "pediu_localizacao": "Mensagem bloqueada: pediu localização.",
    "pediu_contato_externo": "Mensagem bloqueada: pediu contato fora do site.",
    "pediu_segredo": "Mensagem bloqueada: pediu segredo dos pais/responsáveis.",
    "pediu_encontro": "Mensagem bloqueada: tentativa de marcar encontro.",
    "pediu_foto_intima": "Mensagem bloqueada: pediu foto imprópria.",
    "link_nao_permitido": "Link bloqueado (não permitido nesta conversa).",
}

# Texto mostrado pro RESPONSÁVEL no painel — fica no nível de padrão (o que
# aconteceu), sem reproduzir a mensagem em si.
MOTIVOS_LEGIVEIS_RESPONSAVEL = {
    "linguagem_impropria": "Uma mensagem com linguagem imprópria foi bloqueada antes de ser enviada.",
    "pediu_endereco": "Alguém pediu o endereço do seu filho(a) em uma conversa — a mensagem foi bloqueada.",
    "pediu_localizacao": "Alguém perguntou onde seu filho(a) mora — a mensagem foi bloqueada.",
    "pediu_contato_externo": "Alguém pediu um contato fora do site (telefone/WhatsApp) — a mensagem foi bloqueada.",
    "pediu_segredo": "Alguém pediu pro seu filho(a) guardar segredo dos pais — a mensagem foi bloqueada.",
    "pediu_encontro": "Alguém tentou marcar um encontro pessoal — a mensagem foi bloqueada.",
    "pediu_foto_intima": "Alguém pediu uma foto imprópria — a mensagem foi bloqueada.",
    "bloqueio_automatico": "Esse contato foi bloqueado automaticamente após comportamento suspeito repetido.",
    "removido_do_grupo_automatico": "Seu filho(a) foi removido de um grupo automaticamente após várias mensagens bloqueadas.",
}


def _normalizar_texto(texto):
    return re.sub(r"[^a-zà-ú0-9\s]", "", texto.lower())


def _motivo_conteudo_suspeito(texto):
    palavras = set(_normalizar_texto(texto).split())
    if palavras & PALAVRAS_IMPROPRIAS:
        return "linguagem_impropria"
    for regex, motivo in PADROES_SUSPEITOS:
        if regex.search(texto):
            return motivo
    return None


def moderar_mensagem(texto, permitir_links):
    """Retorna (permitida, motivo). Quando não permitida, 'motivo' é uma das
    chaves de MOTIVOS_LEGIVEIS — usado tanto pra resposta ao usuário quanto
    pro registro do alerta de segurança."""
    motivo = _motivo_conteudo_suspeito(texto)
    if motivo:
        return False, motivo
    if REGEX_LINK.search(texto) and not permitir_links:
        return False, "link_nao_permitido"
    return True, None


def moderar_imagem(caminho_arquivo):
    """Gancho pra moderação automática de imagem (nudez/violência/etc). O
    Focus não tem, hoje, um serviço de visão computacional integrado — isso
    exigiria uma API de moderação de imagem de verdade. Por enquanto toda
    imagem enviada é aceita e fica só marcada como conteúdo de mídia, então
    quem administra o site deve avaliar contratar um serviço de moderação
    de imagens antes de abrir isso pra muitos usuários."""
    return True


def _registrar_alerta(db, id_usuario, autor_id, motivo, contexto, grupo_id=None):
    db.execute(
        """INSERT INTO alertas_seguranca (id_usuario, autor_id, motivo, contexto, grupo_id)
           VALUES (?, ?, ?, ?, ?)""",
        (id_usuario, autor_id, motivo, contexto, grupo_id),
    )


LIMITE_ALERTAS_AUTO_BLOQUEIO = 3
LIMITE_ALERTAS_AUTO_KICK_GRUPO = 5
MOTIVOS_GRAVES = {"pediu_endereco", "pediu_localizacao", "pediu_contato_externo",
                  "pediu_segredo", "pediu_encontro", "pediu_foto_intima"}


def _remover_amizade(db, a, b):
    db.execute(
        """DELETE FROM amizades
           WHERE (solicitante_id = ? AND destinatario_id = ?) OR (solicitante_id = ? AND destinatario_id = ?)""",
        (a, b, b, a),
    )


def _verificar_auto_bloqueio(db, id_usuario, autor_id):
    """Se a mesma pessoa acumular vários alertas graves contra o mesmo
    usuário, o sistema bloqueia esse contato automaticamente (sem esperar
    o usuário bloquear na mão), desfaz a amizade entre os dois (igual o
    bloqueio manual já faz) e deixa registrado o motivo."""
    ja_bloqueado = db.execute(
        "SELECT 1 FROM bloqueios WHERE usuario_id = ? AND bloqueado_id = ?",
        (id_usuario, autor_id),
    ).fetchone()
    if ja_bloqueado:
        return
    placeholders = ",".join("?" for _ in MOTIVOS_GRAVES)
    total = db.execute(
        f"""SELECT COUNT(*) AS t FROM alertas_seguranca
            WHERE id_usuario = ? AND autor_id = ? AND motivo IN ({placeholders})""",
        (id_usuario, autor_id, *MOTIVOS_GRAVES),
    ).fetchone()["t"]
    if total >= LIMITE_ALERTAS_AUTO_BLOQUEIO:
        db.execute(
            "INSERT OR IGNORE INTO bloqueios (usuario_id, bloqueado_id, motivo) VALUES (?, ?, 'automatico')",
            (id_usuario, autor_id),
        )
        _remover_amizade(db, id_usuario, autor_id)
        _registrar_alerta(db, id_usuario, autor_id, "bloqueio_automatico", "sistema")


def _tem_controle_parental_ativo(db, id_usuario):
    return db.execute(
        "SELECT 1 FROM vinculos_parentais WHERE id_usuario = ? AND ativo = 1 AND status = 'ativo'",
        (id_usuario,),
    ).fetchone() is not None


def _usuario_resumo(db, usuario_id):
    return db.execute(
        "SELECT id, username, nome, foto_perfil, status_disponibilidade, ultimo_ping FROM usuarios WHERE id = ?",
        (usuario_id,),
    ).fetchone()


def _amizade_entre(db, a, b):
    return db.execute(
        """SELECT * FROM amizades
           WHERE (solicitante_id = ? AND destinatario_id = ?)
              OR (solicitante_id = ? AND destinatario_id = ?)""",
        (a, b, b, a),
    ).fetchone()


def _sao_amigos(db, a, b):
    amizade = _amizade_entre(db, a, b)
    return bool(amizade and amizade["status"] == "aceito")


def _algum_bloqueio(db, a, b):
    return db.execute(
        """SELECT 1 FROM bloqueios
           WHERE (usuario_id = ? AND bloqueado_id = ?) OR (usuario_id = ? AND bloqueado_id = ?)""",
        (a, b, b, a),
    ).fetchone() is not None


# Permissões de cada papel dentro de um grupo. O criador (quem fundou o
# grupo) tem tudo que um admin tem, mais o que só ele pode fazer:
# excluir o grupo e promover outras pessoas a admin.
PERMISSOES_GRUPO = {
    "criador": {
        "excluir_grupo", "remover_membros", "adicionar_membros",
        "aplicar_castigo", "excluir_mensagens", "conceder_admin", "bloquear_chat",
    },
    "admin": {
        "adicionar_membros", "remover_membros", "excluir_mensagens",
        "aplicar_castigo", "bloquear_chat",
    },
    "membro": set(),
}


def _tem_permissao_grupo(papel, permissao):
    return permissao in PERMISSOES_GRUPO.get(papel, set())


def _membro_grupo(db, grupo_id, usuario_id):
    return db.execute(
        "SELECT * FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ?",
        (grupo_id, usuario_id),
    ).fetchone()


def _mensagem_para_json(db, m):
    autor = _usuario_resumo(db, m["remetente_id"])
    return {
        "id": m["id"],
        "autor_id": m["remetente_id"],
        "autor_username": autor["username"] if autor else "?",
        "autor_foto": (autor["foto_perfil"] if autor else None) or "img/ex1.png",
        "tipo": m["tipo"],
        "conteudo": m["conteudo"],
        "fixada": bool(m["fixada"]),
        "criado_em": m["criado_em"],
    }


def _salvar_imagem_generica(arquivo, pasta_disco, pasta_relativa, moderar=True):
    """Versão genérica de _salvar_imagem_chat, usada também pra foto de
    perfil e ícone de grupo — mesma validação de extensão/moderação, só
    muda a pasta de destino."""
    if not arquivo or not arquivo.filename:
        return None
    nome = secure_filename(arquivo.filename)
    ext = nome.rsplit(".", 1)[-1].lower() if "." in nome else ""
    if ext not in EXTENSOES_IMAGEM_PERMITIDAS:
        return None
    nome_final = f"{secrets.token_hex(16)}.{ext}"
    caminho_disco = os.path.join(pasta_disco, nome_final)
    arquivo.save(caminho_disco)
    if moderar and not moderar_imagem(caminho_disco):
        os.remove(caminho_disco)
        return None
    return f"{pasta_relativa}/{nome_final}"


def _salvar_imagem_chat(arquivo):
    """Valida e salva uma imagem enviada no chat. Retorna o caminho relativo
    (pra usar em url_for('static', filename=...)) ou None se inválida."""
    return _salvar_imagem_generica(arquivo, UPLOAD_DIR_CHAT, "uploads/chat")


def _contexto_nav_comunidades(db, id_usuario):
    u = db.execute(
        "SELECT foto_perfil, username, status_disponibilidade FROM usuarios WHERE id = ?",
        (id_usuario,),
    ).fetchone()
    return {
        "username_exibir": u["username"] or "usuário",
        "foto_perfil": u["foto_perfil"] or "img/ex1.png",
        "status": u["status_disponibilidade"] or "online",
    }


@app.route("/comunidades")
@login_required
def comunidades():
    db = get_db()
    id_usuario = session["usuario_id"]

    amigos_rows = db.execute(
        """SELECT u.id, u.username, u.nome, u.foto_perfil, u.status_disponibilidade, u.ultimo_ping
           FROM amizades a
           JOIN usuarios u ON u.id = (CASE WHEN a.solicitante_id = ? THEN a.destinatario_id ELSE a.solicitante_id END)
           WHERE (a.solicitante_id = ? OR a.destinatario_id = ?) AND a.status = 'aceito'
             AND NOT EXISTS (
                 SELECT 1 FROM bloqueios b
                 WHERE (b.usuario_id = ? AND b.bloqueado_id = u.id) OR (b.usuario_id = u.id AND b.bloqueado_id = ?)
             )
           ORDER BY u.username""",
        (id_usuario, id_usuario, id_usuario, id_usuario, id_usuario),
    ).fetchall()
    amigos = [
        {**dict(r), "status_exibicao": _status_exibicao(r["status_disponibilidade"], r["ultimo_ping"])}
        for r in amigos_rows
    ]

    recebidas = db.execute(
        """SELECT a.id AS amizade_id, u.id, u.username, u.foto_perfil
           FROM amizades a JOIN usuarios u ON u.id = a.solicitante_id
           WHERE a.destinatario_id = ? AND a.status = 'pendente'
           ORDER BY a.criado_em DESC""",
        (id_usuario,),
    ).fetchall()

    enviadas = db.execute(
        """SELECT a.id AS amizade_id, u.id, u.username, u.foto_perfil
           FROM amizades a JOIN usuarios u ON u.id = a.destinatario_id
           WHERE a.solicitante_id = ? AND a.status = 'pendente'
           ORDER BY a.criado_em DESC""",
        (id_usuario,),
    ).fetchall()

    bloqueados = db.execute(
        """SELECT b.id AS bloqueio_id, u.id, u.username, u.foto_perfil
           FROM bloqueios b JOIN usuarios u ON u.id = b.bloqueado_id
           WHERE b.usuario_id = ?
           ORDER BY u.username""",
        (id_usuario,),
    ).fetchall()

    grupos = db.execute(
        """SELECT g.id, g.nome, g.descricao, g.foto, gm.papel,
                  (SELECT COUNT(*) FROM grupo_membros gm2 WHERE gm2.grupo_id = g.id) AS total_membros
           FROM grupo_membros gm JOIN grupos g ON g.id = gm.grupo_id
           WHERE gm.usuario_id = ?
           ORDER BY g.nome""",
        (id_usuario,),
    ).fetchall()

    erro = session.pop("com_erro", None)

    return render_template(
        "comunidades.html",
        amigos=amigos,
        recebidas=recebidas,
        enviadas=enviadas,
        bloqueados=bloqueados,
        grupos=grupos,
        erro=erro,
        **_contexto_nav_comunidades(db, id_usuario),
    )


@app.route("/comunidades/buscar")
@login_required
def comunidades_buscar():
    """Busca ao vivo pro campo 'Adicionar amigo' — mostra foto + username
    de quem bate com o termo, em vez do usuário ter que digitar o
    username exato às cegas."""
    db = get_db()
    id_usuario = session["usuario_id"]
    termo = request.args.get("q", "").strip().lstrip("@")
    if len(termo) < 2:
        return jsonify(resultados=[])
    linhas = db.execute(
        """SELECT id, username, foto_perfil FROM usuarios
           WHERE username LIKE ? AND id != ?
           ORDER BY username LIMIT 6""",
        (f"%{termo}%", id_usuario),
    ).fetchall()
    resultados = []
    for u in linhas:
        if _algum_bloqueio(db, id_usuario, u["id"]):
            continue
        resultados.append({
            "id": u["id"],
            "username": u["username"],
            "foto": u["foto_perfil"] or "img/ex1.png",
            "ja_amigo": bool(_sao_amigos(db, id_usuario, u["id"])),
        })
    return jsonify(resultados=resultados)


@app.route("/comunidades/solicitar", methods=["POST"])
@login_required
def comunidades_solicitar():
    db = get_db()
    id_usuario = session["usuario_id"]
    username = request.form.get("username", "").strip().lstrip("@")

    alvo = db.execute("SELECT id FROM usuarios WHERE username = ?", (username,)).fetchone()
    if not alvo:
        session["com_erro"] = "Não encontramos ninguém com esse nome de usuário."
    elif alvo["id"] == id_usuario:
        session["com_erro"] = "Você não pode adicionar a si mesmo."
    elif _algum_bloqueio(db, id_usuario, alvo["id"]):
        session["com_erro"] = "Não foi possível enviar o pedido de amizade."
    else:
        existente = _amizade_entre(db, id_usuario, alvo["id"])
        if existente:
            session["com_erro"] = "Já existe um pedido ou amizade com esse usuário."
        else:
            db.execute(
                "INSERT INTO amizades (solicitante_id, destinatario_id, status) VALUES (?, ?, 'pendente')",
                (id_usuario, alvo["id"]),
            )
            db.commit()
    return redirect(url_for("comunidades"))


@app.route("/comunidades/solicitacao/<int:amizade_id>/aceitar", methods=["POST"])
@login_required
def comunidades_aceitar(amizade_id):
    db = get_db()
    db.execute(
        "UPDATE amizades SET status = 'aceito' WHERE id = ? AND destinatario_id = ? AND status = 'pendente'",
        (amizade_id, session["usuario_id"]),
    )
    db.commit()
    return redirect(url_for("comunidades"))


@app.route("/comunidades/solicitacao/<int:amizade_id>/recusar", methods=["POST"])
@login_required
def comunidades_recusar(amizade_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    db.execute(
        """DELETE FROM amizades WHERE id = ? AND status = 'pendente'
           AND (solicitante_id = ? OR destinatario_id = ?)""",
        (amizade_id, id_usuario, id_usuario),
    )
    db.commit()
    return redirect(url_for("comunidades"))


@app.route("/comunidades/amigo/<int:amigo_id>/remover", methods=["POST"])
@login_required
def comunidades_remover_amigo(amigo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    db.execute(
        """DELETE FROM amizades WHERE status = 'aceito'
           AND ((solicitante_id = ? AND destinatario_id = ?) OR (solicitante_id = ? AND destinatario_id = ?))""",
        (id_usuario, amigo_id, amigo_id, id_usuario),
    )
    db.commit()
    return redirect(url_for("comunidades"))


@app.route("/comunidades/amigo/<int:amigo_id>/bloquear", methods=["POST"])
@login_required
def comunidades_bloquear(amigo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    db.execute(
        "INSERT OR IGNORE INTO bloqueios (usuario_id, bloqueado_id, motivo) VALUES (?, ?, 'manual')",
        (id_usuario, amigo_id),
    )
    db.execute(
        """DELETE FROM amizades
           WHERE (solicitante_id = ? AND destinatario_id = ?) OR (solicitante_id = ? AND destinatario_id = ?)""",
        (id_usuario, amigo_id, amigo_id, id_usuario),
    )
    db.commit()
    return redirect(request.referrer or url_for("comunidades"))


@app.route("/comunidades/amigo/<int:amigo_id>/desbloquear", methods=["POST"])
@login_required
def comunidades_desbloquear(amigo_id):
    db = get_db()
    db.execute(
        "DELETE FROM bloqueios WHERE usuario_id = ? AND bloqueado_id = ?",
        (session["usuario_id"], amigo_id),
    )
    db.commit()
    return redirect(url_for("comunidades"))


@app.route("/comunidades/perfil/<int:usuario_id>")
@login_required
def comunidades_perfil(usuario_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    # "Voltar" nessa tela deve levar pra onde a pessoa realmente veio (o
    # grupo, a DM, a lista de amigos) e não sempre pro hub genérico de
    # Comunidades. Só aceita caminhos internos (começando com "/" e não
    # "//") pra não virar um redirect aberto pra outro site.
    origem = request.args.get("origem", "")
    if not origem or not origem.startswith("/") or origem.startswith("//"):
        origem = url_for("comunidades")
    perfil = db.execute("SELECT * FROM usuarios WHERE id = ?", (usuario_id,)).fetchone()
    if not perfil:
        abort(404)
    if usuario_id != id_usuario and _algum_bloqueio(db, id_usuario, usuario_id):
        abort(403)

    amizade = _amizade_entre(db, id_usuario, usuario_id)
    if amizade and amizade["status"] == "aceito":
        relacao = "amigo"
    elif amizade and amizade["solicitante_id"] == id_usuario:
        relacao = "pendente_enviado"
    elif amizade:
        relacao = "pendente_recebido"
    else:
        relacao = "nenhum"

    eu_bloqueei = db.execute(
        "SELECT 1 FROM bloqueios WHERE usuario_id = ? AND bloqueado_id = ?", (id_usuario, usuario_id)
    ).fetchone() is not None

    return render_template(
        "comunidades_perfil.html",
        perfil=perfil,
        relacao=relacao,
        amizade_id=amizade["id"] if amizade else None,
        eu_bloqueei=eu_bloqueei,
        origem=origem,
        status_exibicao=_status_exibicao(perfil["status_disponibilidade"], perfil["ultimo_ping"]),
        **_contexto_nav_comunidades(db, id_usuario),
    )


# ---- Chat privado (DM) ----------------------------------------------------

@app.route("/comunidades/dm/<int:amigo_id>")
@login_required
def comunidades_dm(amigo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    if not _sao_amigos(db, id_usuario, amigo_id) or _algum_bloqueio(db, id_usuario, amigo_id):
        session["com_erro"] = "Vocês precisam ser amigos (e não estar bloqueados) pra conversar."
        return redirect(url_for("comunidades"))

    # mesma lógica do "Voltar" do perfil: usa de onde a pessoa veio (perfil
    # do amigo, lista de amigos) em vez de sempre cair no hub genérico.
    origem = request.args.get("origem", "")
    if not origem or not origem.startswith("/") or origem.startswith("//"):
        origem = url_for("comunidades")

    amigo = _usuario_resumo(db, amigo_id)
    mensagens_rows = db.execute(
        """SELECT * FROM mensagens
           WHERE (remetente_id = ? AND destinatario_id = ?) OR (remetente_id = ? AND destinatario_id = ?)
           ORDER BY id ASC LIMIT 300""",
        (id_usuario, amigo_id, amigo_id, id_usuario),
    ).fetchall()
    mensagens = [_mensagem_para_json(db, m) for m in mensagens_rows]

    return render_template(
        "comunidades_dm.html",
        amigo=amigo,
        amigo_status=_status_exibicao(amigo["status_disponibilidade"], amigo["ultimo_ping"]),
        mensagens=mensagens,
        ultimo_id=mensagens[-1]["id"] if mensagens else 0,
        session_id=id_usuario,
        origem=origem,
        **_contexto_nav_comunidades(db, id_usuario),
    )


@app.route("/comunidades/dm/<int:amigo_id>/mensagem", methods=["POST"])
@login_required
def comunidades_dm_mensagem(amigo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    if not _sao_amigos(db, id_usuario, amigo_id) or _algum_bloqueio(db, id_usuario, amigo_id):
        return jsonify(ok=False, motivo="Vocês não podem trocar mensagens."), 403

    arquivo = request.files.get("imagem")
    if arquivo and arquivo.filename:
        caminho = _salvar_imagem_chat(arquivo)
        if not caminho:
            return jsonify(ok=False, motivo="Arquivo de imagem inválido."), 400
        cur = db.execute(
            "INSERT INTO mensagens (remetente_id, destinatario_id, tipo, conteudo) VALUES (?, ?, 'imagem', ?)",
            (id_usuario, amigo_id, caminho),
        )
        db.commit()
        nova = db.execute("SELECT * FROM mensagens WHERE id = ?", (cur.lastrowid,)).fetchone()
        return jsonify(ok=True, mensagem=_mensagem_para_json(db, nova))

    texto = request.form.get("texto", "").strip()[:LIMITE_TAMANHO_MENSAGEM]
    if not texto:
        return jsonify(ok=False, motivo="Mensagem vazia."), 400

    permitido, motivo = moderar_mensagem(texto, permitir_links=False)
    if not permitido:
        _registrar_alerta(db, id_usuario, id_usuario, motivo, "dm")
        if motivo in MOTIVOS_GRAVES:
            _registrar_alerta(db, amigo_id, id_usuario, motivo, "dm")
            _verificar_auto_bloqueio(db, amigo_id, id_usuario)
        db.commit()
        return jsonify(ok=False, motivo=MOTIVOS_LEGIVEIS.get(motivo, "Mensagem bloqueada.")), 400

    tipo = "link" if REGEX_LINK.search(texto) else "texto"
    cur = db.execute(
        "INSERT INTO mensagens (remetente_id, destinatario_id, tipo, conteudo) VALUES (?, ?, ?, ?)",
        (id_usuario, amigo_id, tipo, texto),
    )
    db.commit()
    nova = db.execute("SELECT * FROM mensagens WHERE id = ?", (cur.lastrowid,)).fetchone()
    return jsonify(ok=True, mensagem=_mensagem_para_json(db, nova))


@app.route("/comunidades/dm/<int:amigo_id>/mensagens")
@login_required
def comunidades_dm_poll(amigo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    apos = request.args.get("apos", 0, type=int)
    rows = db.execute(
        """SELECT * FROM mensagens
           WHERE ((remetente_id = ? AND destinatario_id = ?) OR (remetente_id = ? AND destinatario_id = ?))
             AND id > ?
           ORDER BY id ASC""",
        (id_usuario, amigo_id, amigo_id, id_usuario, apos),
    ).fetchall()
    return jsonify(mensagens=[_mensagem_para_json(db, m) for m in rows])


@app.route("/comunidades/dm/<int:amigo_id>/fixar/<int:msg_id>", methods=["POST"])
@login_required
def comunidades_dm_fixar(amigo_id, msg_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    msg = db.execute("SELECT * FROM mensagens WHERE id = ?", (msg_id,)).fetchone()
    if not msg or msg["destinatario_id"] not in (id_usuario, amigo_id) or msg["remetente_id"] not in (id_usuario, amigo_id):
        abort(404)
    db.execute("UPDATE mensagens SET fixada = NOT fixada WHERE id = ?", (msg_id,))
    db.commit()
    return jsonify(ok=True)


# ---- Grupos -----------------------------------------------------------

@app.route("/comunidades/grupos/criar", methods=["POST"])
@login_required
def comunidades_criar_grupo():
    db = get_db()
    id_usuario = session["usuario_id"]
    nome = request.form.get("nome", "").strip()
    descricao = request.form.get("descricao", "").strip()[:280]
    membros_ids = request.form.getlist("membros")

    if not nome:
        session["com_erro"] = "Dê um nome pro grupo."
        return redirect(url_for("comunidades"))

    cur = db.execute(
        "INSERT INTO grupos (nome, descricao, criador_id) VALUES (?, ?, ?)",
        (nome, descricao, id_usuario),
    )
    grupo_id = cur.lastrowid
    db.execute(
        "INSERT INTO grupo_membros (grupo_id, usuario_id, papel) VALUES (?, ?, 'criador')",
        (grupo_id, id_usuario),
    )
    for membro_id in membros_ids:
        try:
            membro_id = int(membro_id)
        except ValueError:
            continue
        if _sao_amigos(db, id_usuario, membro_id):
            db.execute(
                "INSERT OR IGNORE INTO grupo_membros (grupo_id, usuario_id, papel) VALUES (?, ?, 'membro')",
                (grupo_id, membro_id),
            )
    db.commit()
    return redirect(url_for("comunidades_grupo", grupo_id=grupo_id))


@app.route("/comunidades/grupo/<int:grupo_id>")
@login_required
def comunidades_grupo(grupo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    membro = _membro_grupo(db, grupo_id, id_usuario)
    if not membro:
        abort(403)

    grupo = db.execute("SELECT * FROM grupos WHERE id = ?", (grupo_id,)).fetchone()
    if not grupo:
        abort(404)

    membros_rows = db.execute(
        """SELECT u.id, u.username, u.foto_perfil, u.status_disponibilidade, u.ultimo_ping, gm.papel
           FROM grupo_membros gm JOIN usuarios u ON u.id = gm.usuario_id
           WHERE gm.grupo_id = ?
           ORDER BY CASE gm.papel WHEN 'criador' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END, u.username""",
        (grupo_id,),
    ).fetchall()
    membros = [
        {**dict(m), "status_exibicao": _status_exibicao(m["status_disponibilidade"], m["ultimo_ping"])}
        for m in membros_rows
    ]

    meus_amigos = db.execute(
        """SELECT u.id, u.username, u.foto_perfil FROM amizades a
           JOIN usuarios u ON u.id = (CASE WHEN a.solicitante_id = ? THEN a.destinatario_id ELSE a.solicitante_id END)
           WHERE (a.solicitante_id = ? OR a.destinatario_id = ?) AND a.status = 'aceito'
             AND u.id NOT IN (SELECT usuario_id FROM grupo_membros WHERE grupo_id = ?)
           ORDER BY u.username""",
        (id_usuario, id_usuario, id_usuario, grupo_id),
    ).fetchall()

    mensagens_rows = db.execute(
        "SELECT * FROM mensagens WHERE grupo_id = ? ORDER BY id ASC LIMIT 300",
        (grupo_id,),
    ).fetchall()
    mensagens = [_mensagem_para_json(db, m) for m in mensagens_rows]

    meu_papel = membro["papel"]
    eh_admin = meu_papel in ("admin", "criador")
    permissoes = {p: _tem_permissao_grupo(meu_papel, p) for p in (
        "excluir_grupo", "remover_membros", "adicionar_membros",
        "aplicar_castigo", "excluir_mensagens", "conceder_admin", "bloquear_chat",
    )}
    banidos = []
    if eh_admin:
        banidos = db.execute(
            """SELECT u.id, u.username FROM grupo_banidos gb
               JOIN usuarios u ON u.id = gb.usuario_id
               WHERE gb.grupo_id = ? ORDER BY u.username""",
            (grupo_id,),
        ).fetchall()

    castigados_rows = db.execute(
        """SELECT usuario_id, expira_em FROM grupo_castigos
           WHERE grupo_id = ? AND expira_em > CURRENT_TIMESTAMP""",
        (grupo_id,),
    ).fetchall()
    castigados = {c["usuario_id"]: c["expira_em"] for c in castigados_rows}

    return render_template(
        "comunidades_grupo.html",
        grupo=grupo,
        membros=membros,
        meus_amigos=meus_amigos,
        banidos=banidos,
        eh_admin=eh_admin,
        meu_papel=meu_papel,
        permissoes=permissoes,
        castigados=castigados,
        mensagens=mensagens,
        ultimo_id=mensagens[-1]["id"] if mensagens else 0,
        session_id=id_usuario,
        erro=session.pop("com_erro", None),
        **_contexto_nav_comunidades(db, id_usuario),
    )


@app.route("/comunidades/grupo/<int:grupo_id>/mensagem", methods=["POST"])
@login_required
def comunidades_grupo_mensagem(grupo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    membro = _membro_grupo(db, grupo_id, id_usuario)
    if not membro:
        return jsonify(ok=False, motivo="Você não é membro desse grupo."), 403
    grupo = db.execute("SELECT * FROM grupos WHERE id = ?", (grupo_id,)).fetchone()

    eh_admin_ou_criador = membro["papel"] in ("admin", "criador")
    if grupo["chat_bloqueado"] and not eh_admin_ou_criador:
        return jsonify(ok=False, motivo="O chat deste grupo foi bloqueado por um admin."), 403

    castigo = db.execute(
        "SELECT expira_em FROM grupo_castigos WHERE grupo_id = ? AND usuario_id = ? AND expira_em > CURRENT_TIMESTAMP",
        (grupo_id, id_usuario),
    ).fetchone()
    if castigo:
        return jsonify(ok=False, motivo=f"Você está de castigo neste grupo até {castigo['expira_em']}."), 403

    arquivo = request.files.get("imagem")
    if arquivo and arquivo.filename:
        caminho = _salvar_imagem_chat(arquivo)
        if not caminho:
            return jsonify(ok=False, motivo="Arquivo de imagem inválido."), 400
        cur = db.execute(
            "INSERT INTO mensagens (remetente_id, grupo_id, tipo, conteudo) VALUES (?, ?, 'imagem', ?)",
            (id_usuario, grupo_id, caminho),
        )
        db.commit()
        nova = db.execute("SELECT * FROM mensagens WHERE id = ?", (cur.lastrowid,)).fetchone()
        return jsonify(ok=True, mensagem=_mensagem_para_json(db, nova))

    texto = request.form.get("texto", "").strip()[:LIMITE_TAMANHO_MENSAGEM]
    if not texto:
        return jsonify(ok=False, motivo="Mensagem vazia."), 400

    permitido, motivo = moderar_mensagem(texto, permitir_links=bool(grupo["permitir_links"]))
    if not permitido:
        _registrar_alerta(db, id_usuario, id_usuario, motivo, "grupo", grupo_id=grupo_id)
        if motivo in MOTIVOS_GRAVES:
            # Conta só os alertas graves DESSE grupo (não de todos os grupos
            # que o usuário participa), pra remover a pessoa do grupo onde o
            # comportamento realmente aconteceu.
            total_no_grupo = db.execute(
                """SELECT COUNT(*) AS t FROM alertas_seguranca
                   WHERE id_usuario = ? AND contexto = 'grupo' AND grupo_id = ? AND motivo IN (%s)""" % (
                    ",".join("?" for _ in MOTIVOS_GRAVES)
                ),
                (id_usuario, grupo_id, *MOTIVOS_GRAVES),
            ).fetchone()["t"]
            if total_no_grupo >= LIMITE_ALERTAS_AUTO_KICK_GRUPO:
                db.execute(
                    "DELETE FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ?",
                    (grupo_id, id_usuario),
                )
                # Banido de verdade: sem isso, um admin podia readicionar a
                # pessoa na hora e a remoção automática não valia nada.
                db.execute(
                    "INSERT OR IGNORE INTO grupo_banidos (grupo_id, usuario_id) VALUES (?, ?)",
                    (grupo_id, id_usuario),
                )
                _registrar_alerta(db, id_usuario, id_usuario, "removido_do_grupo_automatico", "grupo", grupo_id=grupo_id)
        db.commit()
        return jsonify(ok=False, motivo=MOTIVOS_LEGIVEIS.get(motivo, "Mensagem bloqueada.")), 400

    tipo = "link" if REGEX_LINK.search(texto) else "texto"
    cur = db.execute(
        "INSERT INTO mensagens (remetente_id, grupo_id, tipo, conteudo) VALUES (?, ?, ?, ?)",
        (id_usuario, grupo_id, tipo, texto),
    )
    db.commit()
    nova = db.execute("SELECT * FROM mensagens WHERE id = ?", (cur.lastrowid,)).fetchone()
    return jsonify(ok=True, mensagem=_mensagem_para_json(db, nova))


@app.route("/comunidades/grupo/<int:grupo_id>/mensagens")
@login_required
def comunidades_grupo_poll(grupo_id):
    db = get_db()
    if not _membro_grupo(db, grupo_id, session["usuario_id"]):
        abort(403)
    apos = request.args.get("apos", 0, type=int)
    rows = db.execute(
        "SELECT * FROM mensagens WHERE grupo_id = ? AND id > ? ORDER BY id ASC",
        (grupo_id, apos),
    ).fetchall()
    return jsonify(mensagens=[_mensagem_para_json(db, m) for m in rows])


@app.route("/comunidades/grupo/<int:grupo_id>/fixar/<int:msg_id>", methods=["POST"])
@login_required
def comunidades_grupo_fixar(grupo_id, msg_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or membro["papel"] not in ("admin", "criador"):
        abort(403)
    db.execute("UPDATE mensagens SET fixada = NOT fixada WHERE id = ? AND grupo_id = ?", (msg_id, grupo_id))
    db.commit()
    return jsonify(ok=True)


@app.route("/comunidades/grupo/<int:grupo_id>/mensagem/<int:msg_id>/excluir", methods=["POST"])
@login_required
def comunidades_grupo_excluir_mensagem(grupo_id, msg_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or not _tem_permissao_grupo(membro["papel"], "excluir_mensagens"):
        abort(403)
    db.execute("DELETE FROM mensagens WHERE id = ? AND grupo_id = ?", (msg_id, grupo_id))
    db.commit()
    return jsonify(ok=True)


@app.route("/comunidades/grupo/<int:grupo_id>/editar", methods=["POST"])
@login_required
def comunidades_grupo_editar(grupo_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or membro["papel"] not in ("admin", "criador"):
        abort(403)
    nome = request.form.get("nome", "").strip()
    descricao = request.form.get("descricao", "").strip()[:280]
    foto = request.form.get("foto", "").strip()
    arquivo_foto = request.files.get("foto_arquivo")
    if arquivo_foto and arquivo_foto.filename:
        salva = _salvar_imagem_generica(arquivo_foto, UPLOAD_DIR_GRUPO, "uploads/grupo")
        if salva:
            foto = salva
    permitir_links = 1 if request.form.get("permitir_links") == "on" else 0
    if nome:
        db.execute(
            "UPDATE grupos SET nome = ?, descricao = ?, foto = ?, permitir_links = ? WHERE id = ?",
            (nome, descricao, foto, permitir_links, grupo_id),
        )
        db.commit()
    return redirect(url_for("comunidades_grupo", grupo_id=grupo_id))


@app.route("/comunidades/grupo/<int:grupo_id>/excluir", methods=["POST"])
@login_required
def comunidades_grupo_excluir(grupo_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or not _tem_permissao_grupo(membro["papel"], "excluir_grupo"):
        abort(403)
    db.execute("DELETE FROM grupos WHERE id = ?", (grupo_id,))
    db.commit()
    return redirect(url_for("comunidades"))


@app.route("/comunidades/grupo/<int:grupo_id>/membro/<int:usuario_id>/conceder-admin", methods=["POST"])
@login_required
def comunidades_grupo_conceder_admin(grupo_id, usuario_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or not _tem_permissao_grupo(membro["papel"], "conceder_admin"):
        abort(403)
    db.execute(
        "UPDATE grupo_membros SET papel = 'admin' WHERE grupo_id = ? AND usuario_id = ? AND papel = 'membro'",
        (grupo_id, usuario_id),
    )
    db.commit()
    return redirect(url_for("comunidades_grupo", grupo_id=grupo_id))


@app.route("/comunidades/grupo/<int:grupo_id>/membro/<int:usuario_id>/castigo", methods=["POST"])
@login_required
def comunidades_grupo_castigo(grupo_id, usuario_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or not _tem_permissao_grupo(membro["papel"], "aplicar_castigo"):
        abort(403)
    alvo = _membro_grupo(db, grupo_id, usuario_id)
    if not alvo or alvo["papel"] != "membro":
        abort(403)
    minutos = request.form.get("minutos", type=int) or 0
    minutos = max(1, min(minutos, 60 * 24 * 30))  # entre 1 minuto e 30 dias
    db.execute(
        """INSERT INTO grupo_castigos (grupo_id, usuario_id, expira_em)
           VALUES (?, ?, datetime(CURRENT_TIMESTAMP, ?))
           ON CONFLICT(grupo_id, usuario_id) DO UPDATE SET expira_em = excluded.expira_em""",
        (grupo_id, usuario_id, f"+{minutos} minutes"),
    )
    db.commit()
    return redirect(url_for("comunidades_grupo", grupo_id=grupo_id))


@app.route("/comunidades/grupo/<int:grupo_id>/membro/<int:usuario_id>/perdoar", methods=["POST"])
@login_required
def comunidades_grupo_perdoar(grupo_id, usuario_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or not _tem_permissao_grupo(membro["papel"], "aplicar_castigo"):
        abort(403)
    db.execute("DELETE FROM grupo_castigos WHERE grupo_id = ? AND usuario_id = ?", (grupo_id, usuario_id))
    db.commit()
    return redirect(url_for("comunidades_grupo", grupo_id=grupo_id))


@app.route("/comunidades/grupo/<int:grupo_id>/bloquear-chat", methods=["POST"])
@login_required
def comunidades_grupo_bloquear_chat(grupo_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or not _tem_permissao_grupo(membro["papel"], "bloquear_chat"):
        abort(403)
    db.execute("UPDATE grupos SET chat_bloqueado = NOT chat_bloqueado WHERE id = ?", (grupo_id,))
    db.commit()
    return redirect(url_for("comunidades_grupo", grupo_id=grupo_id))


@app.route("/comunidades/grupo/<int:grupo_id>/membro/adicionar", methods=["POST"])
@login_required
def comunidades_grupo_adicionar_membro(grupo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    membro = _membro_grupo(db, grupo_id, id_usuario)
    if not membro or not _tem_permissao_grupo(membro["papel"], "adicionar_membros"):
        abort(403)
    novo_id = request.form.get("usuario_id", type=int)
    banido = db.execute(
        "SELECT 1 FROM grupo_banidos WHERE grupo_id = ? AND usuario_id = ?", (grupo_id, novo_id)
    ).fetchone() if novo_id else None
    if novo_id and banido:
        session["com_erro"] = "Essa pessoa foi removida automaticamente desse grupo e está banida. Desbanir antes de readicionar."
    elif novo_id and _sao_amigos(db, id_usuario, novo_id):
        db.execute(
            "INSERT OR IGNORE INTO grupo_membros (grupo_id, usuario_id, papel) VALUES (?, ?, 'membro')",
            (grupo_id, novo_id),
        )
        db.commit()
    return redirect(url_for("comunidades_grupo", grupo_id=grupo_id))


@app.route("/comunidades/grupo/<int:grupo_id>/banido/<int:usuario_id>/desbanir", methods=["POST"])
@login_required
def comunidades_grupo_desbanir(grupo_id, usuario_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or membro["papel"] not in ("admin", "criador"):
        abort(403)
    db.execute(
        "DELETE FROM grupo_banidos WHERE grupo_id = ? AND usuario_id = ?", (grupo_id, usuario_id)
    )
    db.commit()
    return redirect(url_for("comunidades_grupo", grupo_id=grupo_id))


@app.route("/comunidades/grupo/<int:grupo_id>/membro/<int:usuario_id>/remover", methods=["POST"])
@login_required
def comunidades_grupo_remover_membro(grupo_id, usuario_id):
    db = get_db()
    membro = _membro_grupo(db, grupo_id, session["usuario_id"])
    if not membro or not _tem_permissao_grupo(membro["papel"], "remover_membros"):
        abort(403)
    alvo = _membro_grupo(db, grupo_id, usuario_id)
    if not alvo or alvo["papel"] == "criador":
        abort(403)
    # Um admin comum não pode remover outro admin — só o criador pode.
    if alvo["papel"] == "admin" and membro["papel"] != "criador":
        abort(403)
    db.execute(
        "DELETE FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ?",
        (grupo_id, usuario_id),
    )
    db.commit()
    return redirect(url_for("comunidades_grupo", grupo_id=grupo_id))


@app.route("/comunidades/grupo/<int:grupo_id>/sair", methods=["POST"])
@login_required
def comunidades_grupo_sair(grupo_id):
    db = get_db()
    id_usuario = session["usuario_id"]
    db.execute(
        "DELETE FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ?",
        (grupo_id, id_usuario),
    )
    restantes = db.execute(
        "SELECT COUNT(*) AS t FROM grupo_membros WHERE grupo_id = ?", (grupo_id,)
    ).fetchone()["t"]
    if restantes == 0:
        db.execute("DELETE FROM grupos WHERE id = ?", (grupo_id,))
    else:
        ainda_tem_admin = db.execute(
            "SELECT 1 FROM grupo_membros WHERE grupo_id = ? AND papel IN ('admin', 'criador')", (grupo_id,)
        ).fetchone()
        if not ainda_tem_admin:
            proximo = db.execute(
                "SELECT usuario_id FROM grupo_membros WHERE grupo_id = ? ORDER BY entrou_em ASC LIMIT 1",
                (grupo_id,),
            ).fetchone()
            if proximo:
                db.execute(
                    "UPDATE grupo_membros SET papel = 'admin' WHERE grupo_id = ? AND usuario_id = ?",
                    (grupo_id, proximo["usuario_id"]),
                )
    db.commit()
    return redirect(url_for("comunidades"))


if __name__ == "__main__":
    app.run(debug=True, host="0.0.0.0")
