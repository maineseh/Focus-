import sqlite3
import os
from flask import g

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(BASE_DIR, "instance", "focus.db")


def get_db():
    """Abre (ou reaproveita) a conexão SQLite da requisição atual."""
    if "db" not in g:
        g.db = sqlite3.connect(DB_PATH, timeout=15)
        g.db.row_factory = sqlite3.Row
        g.db.execute("PRAGMA foreign_keys = ON")
        # WAL permite leituras e a escrita acontecerem ao mesmo tempo, em vez
        # de travarem uma na outra. Sem isso, o heartbeat de atividade (que
        # dispara sozinho a cada minuto) podia disputar o "lock" do arquivo
        # com o envio de uma mensagem no chat e atrasar o envio até a outra
        # escrita liberar — é exatamente esse tipo de atraso no chat que o
        # WAL evita.
        g.db.execute("PRAGMA journal_mode = WAL")
        g.db.execute("PRAGMA busy_timeout = 15000")
    return g.db


def close_db(e=None):
    db = g.pop("db", None)
    if db is not None:
        db.close()


def init_db():
    """Cria as tabelas se ainda não existirem (equivalente ao banco de dados.sql)."""
    os.makedirs(os.path.join(BASE_DIR, "instance"), exist_ok=True)
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    with open(os.path.join(BASE_DIR, "schema.sql"), "r", encoding="utf-8") as f:
        conn.executescript(f.read())
    conn.commit()
    _migrar_colunas_novas(conn)
    conn.close()


def _migrar_colunas_novas(conn):
    """Adiciona colunas novas em bancos que já existiam antes delas serem
    criadas (CREATE TABLE IF NOT EXISTS não altera tabelas já existentes)."""
    colunas_usuarios = {row[1] for row in conn.execute("PRAGMA table_info(usuarios)")}

    novas_colunas_usuarios = {
        "bio": "VARCHAR(280) DEFAULT ''",
        "nome_exibicao": "VARCHAR(100) DEFAULT ''",
        "pronomes": "VARCHAR(50) DEFAULT ''",
        "mostrar_nome_real": "BOOLEAN DEFAULT 0",
        "status_disponibilidade": "VARCHAR(20) DEFAULT 'online'",
        "codigo_recuperacao": "VARCHAR(255)",
        "codigo_expiracao": "DATETIME",
        "ultimo_ping": "TIMESTAMP",
    }

    for coluna, definicao in novas_colunas_usuarios.items():
        if coluna not in colunas_usuarios:
            conn.execute(f"ALTER TABLE usuarios ADD COLUMN {coluna} {definicao}")

    # Preenche o nome de exibição com o nome já cadastrado, pra quem já tinha conta
    conn.execute(
        "UPDATE usuarios SET nome_exibicao = nome WHERE nome_exibicao IS NULL OR nome_exibicao = ''"
    )

    # Migração da tabela disciplinas (id_modulo, anotacoes, minutos_estudados)
    colunas_disciplinas = {row[1] for row in conn.execute("PRAGMA table_info(disciplinas)")}
    novas_colunas_disciplinas = {
        "id_modulo": "INTEGER",
        "anotacoes": "TEXT DEFAULT ''",
        "desenho": "TEXT DEFAULT ''",
        "minutos_estudados": "INTEGER DEFAULT 0",
    }
    for coluna, definicao in novas_colunas_disciplinas.items():
        if coluna not in colunas_disciplinas:
            conn.execute(f"ALTER TABLE disciplinas ADD COLUMN {coluna} {definicao}")

    # "ordem" é tratada à parte: só recebe o valor inicial (baseado no ID) na
    # primeira vez que a coluna é criada, pra não sobrescrever reordenações
    # que o usuário já tenha feito em execuções futuras.
    if "ordem" not in colunas_disciplinas:
        conn.execute("ALTER TABLE disciplinas ADD COLUMN ordem INTEGER DEFAULT 0")
        conn.execute("UPDATE disciplinas SET ordem = id")

    colunas_modulos = {row[1] for row in conn.execute("PRAGMA table_info(modulos)")}
    if "ordem" not in colunas_modulos:
        conn.execute("ALTER TABLE modulos ADD COLUMN ordem INTEGER DEFAULT 0")
        conn.execute("UPDATE modulos SET ordem = id")

    # Migração da tabela agenda_metas (titulo separado da descrição, e data de conclusão
    # pra dar pra listar/ordenar o histórico de metas concluídas)
    colunas_metas = {row[1] for row in conn.execute("PRAGMA table_info(agenda_metas)")}
    novas_colunas_metas = {
        "titulo": "VARCHAR(150) DEFAULT ''",
        "concluida_em": "TIMESTAMP",
        "gerado_por_ia": "BOOLEAN DEFAULT 0",
    }
    for coluna, definicao in novas_colunas_metas.items():
        if coluna not in colunas_metas:
            conn.execute(f"ALTER TABLE agenda_metas ADD COLUMN {coluna} {definicao}")

    # Migração da tabela responsaveis (Controle Parental passou a exigir senha
    # própria do responsável, em vez de só o link mágico)
    colunas_responsaveis = {row[1] for row in conn.execute("PRAGMA table_info(responsaveis)")}
    if "senha_hash" not in colunas_responsaveis:
        conn.execute("ALTER TABLE responsaveis ADD COLUMN senha_hash VARCHAR(255)")

    # Migração da tabela vinculos_parentais (aprovação de vínculo pedido
    # pelo responsável, em vez de só o filho gerando o link)
    colunas_vinculos = {row[1] for row in conn.execute("PRAGMA table_info(vinculos_parentais)")}
    if "status" not in colunas_vinculos:
        conn.execute("ALTER TABLE vinculos_parentais ADD COLUMN status VARCHAR(20) DEFAULT 'ativo'")
        conn.execute("UPDATE vinculos_parentais SET status = 'ativo' WHERE status IS NULL")

    # Migração da tabela alertas_seguranca (grupo_id, pra contar alertas por
    # grupo em vez de somar todos os grupos do usuário juntos)
    colunas_alertas = {row[1] for row in conn.execute("PRAGMA table_info(alertas_seguranca)")}
    if "grupo_id" not in colunas_alertas:
        conn.execute("ALTER TABLE alertas_seguranca ADD COLUMN grupo_id INTEGER")

    # Quem já tinha disciplinas sem módulo (de antes dos módulos existirem)
    # ganha um módulo padrão automaticamente, pra não perder nada.
    orfas = conn.execute(
        "SELECT DISTINCT id_usuario FROM disciplinas WHERE id_modulo IS NULL"
    ).fetchall()
    for row in orfas:
        id_usuario = row["id_usuario"]
        cur = conn.execute(
            "INSERT INTO modulos (id_usuario, nome) VALUES (?, ?)",
            (id_usuario, "Meus Estudos"),
        )
        novo_modulo_id = cur.lastrowid
        conn.execute(
            "UPDATE disciplinas SET id_modulo = ? WHERE id_usuario = ? AND id_modulo IS NULL",
            (novo_modulo_id, id_usuario),
        )

    # Migração da tabela grupos (bloqueio de chat) e do sistema de papéis
    # dos grupos (agora com 3 níveis: criador, admin, membro — antes só
    # existia 'admin'/'membro', então quem criou o grupo era indistinguível
    # de um admin promovido depois).
    colunas_grupos = {row[1] for row in conn.execute("PRAGMA table_info(grupos)")}
    if "chat_bloqueado" not in colunas_grupos:
        conn.execute("ALTER TABLE grupos ADD COLUMN chat_bloqueado BOOLEAN DEFAULT 0")

    conn.execute(
        """UPDATE grupo_membros SET papel = 'criador'
           WHERE papel = 'admin' AND usuario_id = (
               SELECT criador_id FROM grupos WHERE grupos.id = grupo_membros.grupo_id
           )"""
    )

    conn.execute(
        """CREATE TABLE IF NOT EXISTS grupo_castigos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            grupo_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            expira_em TIMESTAMP NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(grupo_id, usuario_id),
            FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        )"""
    )

    conn.commit()


def init_app(app):
    app.teardown_appcontext(close_db)
    with app.app_context():
        init_db()
