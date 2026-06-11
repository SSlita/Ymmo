"""
ymmo/python/analyse.py
----------------------
Analyse de données immobilières Ymmo.

Modules :
  1. Nettoyage & validation des données
  2. Rapport de ventes (CSV + console)
  3. Statistiques descriptives
  4. Prédiction de prix par régression linéaire (scikit-learn)
  5. Identification des zones attractives
  6. Score de popularité des biens (tendance)
  7. Export des résultats enrichis

Usage :
  python analyse.py              → rapport complet
  python analyse.py --rapport    → rapport CSV uniquement
  python analyse.py --predictions → prédictions uniquement
  python analyse.py --zones       → analyse des villes
"""

import argparse
import os
import sys
from pathlib import Path

import numpy as np
import pandas as pd
import matplotlib
matplotlib.use("Agg")   # rendu sans interface graphique (serveur)
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.linear_model import LinearRegression
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import mean_absolute_error, r2_score

from db import load_transactions, load_biens, load_offres, load_demandes

OUTPUT_DIR = Path(__file__).parent / "output"
OUTPUT_DIR.mkdir(exist_ok=True)

sns.set_theme(style="whitegrid", palette="muted")
PALETTE = ["#0A1628", "#C9A84C", "#162240", "#E5C97A", "#5A5752"]


# ============================================================
# 1. NETTOYAGE DES DONNÉES
# ============================================================

def nettoyer_transactions(df: pd.DataFrame) -> pd.DataFrame:
    """Nettoyage et typage des transactions."""
    if df.empty:
        return df

    # Typage des dates
    df["date_transaction"] = pd.to_datetime(df["date_transaction"], errors="coerce")
    df["created_at"]       = pd.to_datetime(df["created_at"], errors="coerce")

    # Suppression des lignes avec prix nul ou négatif
    avant = len(df)
    df = df[df["prix_final"] > 0]
    apres = len(df)
    if avant != apres:
        print(f"  ⚠ {avant - apres} transaction(s) ignorée(s) (prix invalide)")

    # Colonnes calculées
    df["annee"]     = df["date_transaction"].dt.year
    df["mois"]      = df["date_transaction"].dt.month
    df["mois_nom"]  = df["date_transaction"].dt.strftime("%b %Y")
    df["trimestre"] = df["date_transaction"].dt.to_period("Q").astype(str)

    # Écart prix final vs affiché (si disponible)
    if "prix_affiche" in df.columns:
        df["ecart_pct"] = ((df["prix_final"] - df["prix_affiche"]) / df["prix_affiche"] * 100).round(2)

    return df.sort_values("date_transaction").reset_index(drop=True)


def nettoyer_biens(df: pd.DataFrame) -> pd.DataFrame:
    """Nettoyage et enrichissement des biens."""
    if df.empty:
        return df

    df["created_at"] = pd.to_datetime(df["created_at"], errors="coerce")

    # Prix au m²
    df["prix_m2"] = np.where(df["surface"] > 0, (df["prix"] / df["surface"]).round(2), np.nan)

    # Score de popularité (0–100) : pondère vues, demandes, offres
    max_vues     = df["nb_vues"].max() or 1
    max_demandes = df["nb_demandes"].max() or 1
    max_offres   = df["nb_offres"].max() or 1

    df["score_popularite"] = (
        df["nb_vues"]     / max_vues     * 40
      + df["nb_demandes"] / max_demandes * 35
      + df["nb_offres"]   / max_offres   * 25
    ).round(1)

    return df.reset_index(drop=True)


# ============================================================
# 2. RAPPORT DE VENTES
# ============================================================

def rapport_ventes(df_t: pd.DataFrame) -> pd.DataFrame:
    """Génère un rapport de ventes mensuel et l'exporte en CSV."""
    print("\n── Rapport de ventes ─────────────────────────────────")

    if df_t.empty:
        print("  Aucune transaction disponible.")
        return pd.DataFrame()

    rapport = (
        df_t.groupby("mois_nom")
        .agg(
            nb_transactions=("id", "count"),
            ca_total=("prix_final", "sum"),
            ca_moyen=("prix_final", "mean"),
            commission_totale=("commission", "sum"),
            prix_min=("prix_final", "min"),
            prix_max=("prix_final", "max"),
        )
        .reset_index()
    )
    rapport["ca_total"]          = rapport["ca_total"].round(2)
    rapport["ca_moyen"]          = rapport["ca_moyen"].round(2)
    rapport["commission_totale"] = rapport["commission_totale"].round(2)

    # Export CSV
    path_csv = OUTPUT_DIR / "rapport_ventes.csv"
    rapport.to_csv(path_csv, index=False, encoding="utf-8-sig")
    print(f"  ✓ Exporté → {path_csv}")

    # Affichage console
    print(f"\n  Période  : {df_t['date_transaction'].min().date()} → {df_t['date_transaction'].max().date()}")
    print(f"  CA total : {df_t['prix_final'].sum():,.0f} €")
    print(f"  Moy/mois : {df_t['prix_final'].sum() / max(len(rapport), 1):,.0f} €")
    print(f"  Nb tx    : {len(df_t)}")
    print(f"\n  Répartition par type de transaction :")
    for typ, grp in df_t.groupby("type_transaction"):
        print(f"    {typ:12s} → {len(grp):3d} tx  |  {grp['prix_final'].sum():>12,.0f} €")

    # Graphique CA par mois
    if len(rapport) > 1:
        fig, axes = plt.subplots(1, 2, figsize=(14, 5))
        rapport.plot.bar(x="mois_nom", y="ca_total", ax=axes[0],
                         color=PALETTE[0], legend=False)
        axes[0].set_title("Chiffre d'affaires par mois")
        axes[0].set_xlabel("")
        axes[0].set_ylabel("CA (€)")
        axes[0].tick_params(axis="x", rotation=45)
        for p in axes[0].patches:
            axes[0].annotate(f"{p.get_height()/1000:.0f}k",
                             (p.get_x() + p.get_width() / 2, p.get_height()),
                             ha="center", va="bottom", fontsize=8)

        rapport.plot.bar(x="mois_nom", y="nb_transactions", ax=axes[1],
                         color=PALETTE[1], legend=False)
        axes[1].set_title("Nombre de transactions par mois")
        axes[1].set_xlabel("")
        axes[1].set_ylabel("Transactions")
        axes[1].tick_params(axis="x", rotation=45)

        plt.tight_layout()
        fig.savefig(OUTPUT_DIR / "rapport_ventes.png", dpi=150, bbox_inches="tight")
        plt.close(fig)
        print(f"  ✓ Graphique → {OUTPUT_DIR / 'rapport_ventes.png'}")

    return rapport


# ============================================================
# 3. STATISTIQUES DESCRIPTIVES
# ============================================================

def statistiques_descriptives(df_t: pd.DataFrame, df_b: pd.DataFrame) -> None:
    """Affiche et exporte les statistiques descriptives."""
    print("\n── Statistiques descriptives ─────────────────────────")

    if not df_t.empty:
        stats_t = df_t["prix_final"].describe().round(2)
        print("\n  Prix des transactions :")
        print(f"    Médiane  : {df_t['prix_final'].median():>12,.0f} €")
        print(f"    Moyenne  : {df_t['prix_final'].mean():>12,.0f} €")
        print(f"    Écart-type: {df_t['prix_final'].std():>11,.0f} €")
        print(f"    Min/Max  : {df_t['prix_final'].min():,.0f} € / {df_t['prix_final'].max():,.0f} €")

        if "ecart_pct" in df_t.columns:
            print(f"\n  Écart offre finale vs prix affiché :")
            print(f"    Moy : {df_t['ecart_pct'].mean():.1f}%  |  Médiane : {df_t['ecart_pct'].median():.1f}%")

    if not df_b.empty:
        print(f"\n  Prix au m² (biens actifs) :")
        pm2 = df_b["prix_m2"].dropna()
        print(f"    Médiane  : {pm2.median():>8,.0f} €/m²")
        print(f"    Moyenne  : {pm2.mean():>8,.0f} €/m²")

        # Répartition par type
        print(f"\n  Répartition du portefeuille :")
        for typ, grp in df_b.groupby("type"):
            pct = len(grp) / len(df_b) * 100
            print(f"    {typ:14s} {len(grp):3d} biens ({pct:.0f}%)")

    # Export stats globales
    if not df_t.empty:
        stats = {
            "nb_transactions": len(df_t),
            "ca_total": round(float(df_t["prix_final"].sum()), 2),
            "prix_median": round(float(df_t["prix_final"].median()), 2),
            "prix_moyen": round(float(df_t["prix_final"].mean()), 2),
            "commission_totale": round(float(df_t["commission"].sum()), 2),
        }
        pd.DataFrame([stats]).to_csv(OUTPUT_DIR / "stats_globales.csv", index=False, encoding="utf-8-sig")
        print(f"\n  ✓ Stats exportées → {OUTPUT_DIR / 'stats_globales.csv'}")


# ============================================================
# 4. PRÉDICTION DE PRIX (Machine Learning)
# ============================================================

def prediction_prix(df_b: pd.DataFrame) -> pd.DataFrame:
    """
    Régression pour prédire le prix d'un bien en fonction de ses caractéristiques.
    Modèles : LinearRegression + RandomForest (comparaison).
    """
    print("\n── Prédiction de prix ────────────────────────────────")

    features = ["surface", "pieces", "chambres", "prix_m2"]
    df = df_b.dropna(subset=features + ["prix"]).copy()

    if len(df) < 10:
        print("  Données insuffisantes (< 10 biens) pour entraîner un modèle.")
        return pd.DataFrame()

    # Encodage des variables catégorielles
    le_type = LabelEncoder()
    le_op   = LabelEncoder()
    le_op.fit(["vente", "location"])

    df["type_enc"]      = le_type.fit_transform(df["type"].astype(str))
    df["operation_enc"] = le_op.transform(df["operation"].astype(str))
    features_enc = features + ["type_enc", "operation_enc"]

    X = df[features_enc].fillna(0)
    y = df["prix"]

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

    resultats = {}

    # Régression linéaire
    lr = LinearRegression()
    lr.fit(X_train, y_train)
    y_pred_lr = lr.predict(X_test)
    resultats["LinearRegression"] = {
        "mae": mean_absolute_error(y_test, y_pred_lr),
        "r2":  r2_score(y_test, y_pred_lr),
    }

    # Random Forest
    rf = RandomForestRegressor(n_estimators=100, random_state=42, max_depth=8)
    rf.fit(X_train, y_train)
    y_pred_rf = rf.predict(X_test)
    resultats["RandomForest"] = {
        "mae": mean_absolute_error(y_test, y_pred_rf),
        "r2":  r2_score(y_test, y_pred_rf),
    }

    print(f"\n  Entraînement sur {len(X_train)} biens, test sur {len(X_test)} :")
    for nom, res in resultats.items():
        print(f"    {nom:20s} MAE={res['mae']:>10,.0f}€  R²={res['r2']:.3f}")

    # Importance des features (Random Forest)
    importances = pd.Series(rf.feature_importances_, index=features_enc).sort_values(ascending=True)
    print(f"\n  Importance des variables (Random Forest) :")
    for feat, imp in importances.items():
        bar = "█" * int(imp * 40)
        print(f"    {feat:20s} {bar} {imp:.3f}")

    # Prédictions sur tout le dataset (enrichissement)
    df["prix_predit_rf"] = rf.predict(X).round(0)
    df["ecart_prediction"] = ((df["prix_predit_rf"] - df["prix"]) / df["prix"] * 100).round(1)

    # Export
    cols_export = ["id", "titre", "type", "ville", "surface", "prix", "prix_m2",
                   "prix_predit_rf", "ecart_prediction", "score_popularite"]
    cols_export = [c for c in cols_export if c in df.columns]
    df[cols_export].to_csv(OUTPUT_DIR / "predictions_prix.csv", index=False, encoding="utf-8-sig")
    print(f"\n  ✓ Prédictions exportées → {OUTPUT_DIR / 'predictions_prix.csv'}")

    # Graphique prédit vs réel
    fig, ax = plt.subplots(figsize=(8, 6))
    ax.scatter(y_test, y_pred_rf, alpha=0.6, color=PALETTE[0], edgecolors="white", s=60)
    lims = [min(y_test.min(), y_pred_rf.min()), max(y_test.max(), y_pred_rf.max())]
    ax.plot(lims, lims, "--", color=PALETTE[1], linewidth=1.5, label="Prédiction parfaite")
    ax.set_title("Prix réel vs prix prédit (Random Forest)")
    ax.set_xlabel("Prix réel (€)")
    ax.set_ylabel("Prix prédit (€)")
    ax.legend()
    plt.tight_layout()
    fig.savefig(OUTPUT_DIR / "prediction_prix.png", dpi=150, bbox_inches="tight")
    plt.close(fig)
    print(f"  ✓ Graphique → {OUTPUT_DIR / 'prediction_prix.png'}")

    return df[cols_export] if cols_export else df


# ============================================================
# 5. ANALYSE DES ZONES ATTRACTIVES
# ============================================================

def zones_attractives(df_b: pd.DataFrame, df_t: pd.DataFrame) -> pd.DataFrame:
    """
    Identifie les villes les plus attractives en croisant :
    - nombre de biens disponibles
    - prix moyen au m²
    - volume de transactions
    - nombre de demandes
    """
    print("\n── Zones attractives ─────────────────────────────────")

    if df_b.empty:
        print("  Aucun bien disponible.")
        return pd.DataFrame()

    zones = (
        df_b.groupby("ville")
        .agg(
            nb_biens=("id", "count"),
            prix_moyen=("prix", "mean"),
            prix_m2_moyen=("prix_m2", "mean"),
            demandes_totales=("nb_demandes", "sum"),
            offres_totales=("nb_offres", "sum"),
            score_pop_moyen=("score_popularite", "mean"),
        )
        .reset_index()
    )
    zones["prix_moyen"]    = zones["prix_moyen"].round(0)
    zones["prix_m2_moyen"] = zones["prix_m2_moyen"].round(0)
    zones["score_pop_moyen"] = zones["score_pop_moyen"].round(1)

    # Enrichir avec transactions si disponibles
    if not df_t.empty and "ville" in df_t.columns:
        tx_ville = df_t.groupby("ville").agg(
            nb_transactions=("id", "count"),
            ca_ville=("prix_final", "sum"),
        ).reset_index()
        zones = zones.merge(tx_ville, on="ville", how="left").fillna(0)

    # Score d'attractivité composite (normalisé 0–100)
    for col in ["nb_biens", "demandes_totales", "score_pop_moyen"]:
        max_val = zones[col].max() or 1
        zones[col + "_norm"] = zones[col] / max_val * 100

    zones["score_attractivite"] = (
        zones["nb_biens_norm"]       * 0.25
      + zones["demandes_totales_norm"] * 0.45
      + zones["score_pop_moyen_norm"]  * 0.30
    ).round(1)

    zones = zones.drop(columns=[c for c in zones.columns if c.endswith("_norm")])
    zones = zones.sort_values("score_attractivite", ascending=False)

    print(f"\n  Top 5 villes les plus attractives :")
    for i, row in zones.head(5).iterrows():
        print(f"    {int(row.name)+1 if isinstance(row.name,int) else i+1}. {row['ville']:20s}"
              f"  score={row['score_attractivite']:.0f}/100"
              f"  {row['nb_biens']:.0f} biens"
              f"  {row['prix_m2_moyen']:,.0f} €/m²")

    # Export
    zones.to_csv(OUTPUT_DIR / "zones_attractives.csv", index=False, encoding="utf-8-sig")
    print(f"\n  ✓ Zones exportées → {OUTPUT_DIR / 'zones_attractives.csv'}")

    # Graphique top villes
    if len(zones) >= 3:
        top = zones.head(8)
        fig, ax = plt.subplots(figsize=(10, 5))
        bars = ax.barh(top["ville"], top["score_attractivite"], color=PALETTE[0])
        ax.bar_label(bars, fmt="%.0f", padding=4, fontsize=9)
        ax.set_title("Score d'attractivité par ville")
        ax.set_xlabel("Score (0–100)")
        ax.invert_yaxis()
        plt.tight_layout()
        fig.savefig(OUTPUT_DIR / "zones_attractives.png", dpi=150, bbox_inches="tight")
        plt.close(fig)
        print(f"  ✓ Graphique → {OUTPUT_DIR / 'zones_attractives.png'}")

    return zones


# ============================================================
# 6. PRÉDICTION DE VENTE (probabilité sous 30 jours)
# ============================================================

def prediction_vente(df_b: pd.DataFrame, df_o: pd.DataFrame) -> pd.DataFrame:
    """
    Score de probabilité de vente rapide (< 30j) pour les biens disponibles.
    Basé sur : vues, demandes, offres, ratio prix affiché vs marché local.
    """
    print("\n── Prédiction de vente rapide ────────────────────────")

    df = df_b[df_b["statut"] == "disponible"].copy()
    if df.empty:
        print("  Aucun bien disponible.")
        return pd.DataFrame()

    # Ratio prix vs médiane locale
    prix_median_ville = df.groupby("ville")["prix"].transform("median")
    df["ratio_prix"]  = (df["prix"] / prix_median_ville).clip(0.5, 2.0)

    # Score de prédiction (heuristique pondérée)
    # Biens avec beaucoup de vues/demandes et prix compétitif ont plus de chances
    max_v = df["nb_vues"].max() or 1
    max_d = df["nb_demandes"].max() or 1
    max_o = df["nb_offres"].max() or 1

    df["prob_vente_30j"] = (
        (df["nb_vues"]     / max_v * 30)
      + (df["nb_demandes"] / max_d * 40)
      + (df["nb_offres"]   / max_o * 20)
      + ((2.0 - df["ratio_prix"]) / 1.5 * 10)   # prix compétitif = +10 pts
    ).clip(0, 100).round(1)

    df["recommandation"] = pd.cut(
        df["prob_vente_30j"],
        bins=[0, 25, 50, 75, 100],
        labels=["Faible activité", "Activité modérée", "Forte demande", "Vente imminente"],
        include_lowest=True,
    )

    print(f"\n  Biens avec probabilité de vente rapide élevée :")
    top_biens = df.nlargest(5, "prob_vente_30j")[["titre", "ville", "prix", "prob_vente_30j", "recommandation"]]
    for _, row in top_biens.iterrows():
        print(f"    • {str(row['titre'])[:30]:30s} {row['ville']:15s} "
              f"{row['prob_vente_30j']:5.0f}%  [{row['recommandation']}]")

    # Export
    export_cols = ["id", "titre", "type", "ville", "prix", "surface",
                   "nb_vues", "nb_demandes", "nb_offres",
                   "prob_vente_30j", "recommandation", "score_popularite"]
    export_cols = [c for c in export_cols if c in df.columns]
    df[export_cols].sort_values("prob_vente_30j", ascending=False).to_csv(
        OUTPUT_DIR / "prediction_vente.csv", index=False, encoding="utf-8-sig"
    )
    print(f"\n  ✓ Prédictions exportées → {OUTPUT_DIR / 'prediction_vente.csv'}")

    return df[export_cols]


# ============================================================
# MAIN
# ============================================================

def main(args):
    print("=" * 58)
    print("  Ymmo — Analyse de données immobilières")
    print("=" * 58)

    # Chargement
    print("\n── Chargement des données ────────────────────────────")
    try:
        df_t = load_transactions()
        df_b = load_biens()
        df_o = load_offres()
        print(f"  Transactions : {len(df_t)}")
        print(f"  Biens        : {len(df_b)}")
        print(f"  Offres       : {len(df_o)}")
    except Exception as e:
        print(f"\n  ✗ Erreur de connexion BDD : {e}")
        print("  → Vérifiez le fichier python/.env (copié depuis .env.example)")
        sys.exit(1)

    # Nettoyage
    df_t = nettoyer_transactions(df_t)
    df_b = nettoyer_biens(df_b)

    run_all = not (args.rapport or args.predictions or args.zones)

    if run_all or args.rapport:
        rapport_ventes(df_t)
        statistiques_descriptives(df_t, df_b)

    if run_all or args.predictions:
        prediction_prix(df_b)
        prediction_vente(df_b, df_o)

    if run_all or args.zones:
        zones_attractives(df_b, df_t)

    print(f"\n{'=' * 58}")
    print(f"  Terminé — fichiers dans : {OUTPUT_DIR.resolve()}")
    print(f"{'=' * 58}\n")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Analyse de données Ymmo")
    parser.add_argument("--rapport",     action="store_true", help="Rapport de ventes uniquement")
    parser.add_argument("--predictions", action="store_true", help="Prédictions de prix/vente uniquement")
    parser.add_argument("--zones",       action="store_true", help="Analyse des zones attractives uniquement")
    main(parser.parse_args())
