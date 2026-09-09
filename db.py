import sqlite3
import os
from flask import g

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(BASE_DIR, "instance", "focus.db")


def get_db():
    """Abre (ou reaproveita) a conexão SQLite da requisição atual."""
    if "db" not in g:
        g.db = sqlite3.connect(DB_PATH)
        g.db.row_factory = sqlite3.Row
        g.db.execute("PRAGMA foreign_keys = ON")
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

    conn.commit()


def init_app(app):
    app.teardown_appcontext(close_db)
    with app.app_context():
        init_db()
