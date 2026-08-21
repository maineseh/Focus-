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
    with open(os.path.join(BASE_DIR, "schema.sql"), "r", encoding="utf-8") as f:
        conn.executescript(f.read())
    conn.commit()
    conn.close()


def init_app(app):
    app.teardown_appcontext(close_db)
    with app.app_context():
        init_db()
