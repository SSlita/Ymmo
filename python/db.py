"""
ymmo/python/db.py
-----------------
Connexion à la base de données Ymmo via pymysql + pandas.
Principe SRP : ce module ne fait que la connexion/lecture.
"""

import os
import pandas as pd
import pymysql
from dotenv import load_dotenv

load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))


def get_connection() -> pymysql.connections.Connection:
    """Retourne une connexion pymysql configurée depuis .env"""
    return pymysql.connect(
        host=os.getenv("DB_HOST", "localhost"),
        port=int(os.getenv("DB_PORT", 3306)),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASS", ""),
        database=os.getenv("DB_NAME", "ymmo"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )


def read_sql(query: str, params: tuple = ()) -> pd.DataFrame:
    """Exécute une requête SELECT et retourne un DataFrame pandas."""
    conn = get_connection()
    try:
        return pd.read_sql(query, conn, params=params)
    finally:
        conn.close()


def load_transactions() -> pd.DataFrame:
    return read_sql("""
        SELECT t.id, t.prix_final, t.commission, t.type_transaction,
               t.date_transaction, t.created_at,
               b.type AS bien_type, b.ville, b.cp, b.surface, b.prix AS prix_affiche,
               b.operation,
               CONCAT(u.prenom,' ',u.nom) AS agent_nom,
               u.id AS agent_id
        FROM transactions t
        JOIN biens b ON b.id = t.bien_id
        JOIN users u ON u.id = t.agent_id
        ORDER BY t.date_transaction ASC
    """)


def load_biens() -> pd.DataFrame:
    return read_sql("""
        SELECT b.id, b.titre, b.type, b.statut, b.operation, b.prix,
               b.surface, b.pieces, b.ville, b.cp, b.created_at,
               COUNT(DISTINCT d.id) AS nb_demandes,
               COUNT(DISTINCT o.id) AS nb_offres,
               COUNT(DISTINCT v.id) AS nb_vues
        FROM biens b
        LEFT JOIN demandes d    ON d.bien_id = b.id
        LEFT JOIN offres o      ON o.bien_id = b.id
        LEFT JOIN vues_biens v  ON v.bien_id = b.id
        WHERE b.statut != 'archive'
        GROUP BY b.id
    """)


def load_offres() -> pd.DataFrame:
    return read_sql("""
        SELECT o.*, b.ville, b.type AS bien_type, b.prix AS prix_affiche,
               b.operation, b.surface
        FROM offres o
        JOIN biens b ON b.id = o.bien_id
    """)


def load_demandes() -> pd.DataFrame:
    return read_sql("""
        SELECT d.id, d.statut, d.created_at,
               b.ville, b.type AS bien_type, b.prix, b.operation
        FROM demandes d
        JOIN biens b ON b.id = d.bien_id
    """)
