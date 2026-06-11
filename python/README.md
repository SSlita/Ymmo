# Ymmo — Module Python / Analyse de données

Ce module Python réalise le traitement et l'analyse des données immobilières
de la plateforme Ymmo, conformément aux exigences de la compétence
**"Analyse et manipulation de données en Python"** du projet B2 INFRA & DEV.

---

## Installation

```bash
cd ymmo/python
pip install -r requirements.txt
```

> Python 3.10+ recommandé.

---

## Configuration

```bash
cp .env.example .env
# Éditer .env avec vos identifiants MySQL XAMPP
```

Contenu de `.env` :
```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ymmo
DB_USER=root
DB_PASS=
```

---

## Utilisation

```bash
# Analyse complète (rapport + prédictions + zones)
python analyse.py

# Rapport de ventes uniquement
python analyse.py --rapport

# Prédictions de prix et probabilité de vente
python analyse.py --predictions

# Analyse des zones géographiques attractives
python analyse.py --zones
```

---

## Fichiers générés (`python/output/`)

| Fichier | Contenu |
|---|---|
| `rapport_ventes.csv` | CA mensuel, nb transactions, commissions |
| `rapport_ventes.png` | Graphique CA et nb tx par mois |
| `stats_globales.csv` | KPIs globaux (CA total, médiane, moyenne…) |
| `predictions_prix.csv` | Prix réel vs prix prédit par ML pour chaque bien |
| `prediction_prix.png` | Scatter plot réel vs prédit |
| `prediction_vente.csv` | Score de probabilité de vente sous 30j par bien |
| `zones_attractives.csv` | Score d'attractivité par ville |
| `zones_attractives.png` | Top villes classées par score |

---

## Architecture du code

```
python/
├── .env.example      # Template de configuration BDD
├── requirements.txt  # Dépendances Python
├── db.py             # Connexion BDD + loaders (SRP)
├── analyse.py        # Script principal (6 modules)
└── output/           # Fichiers générés (gitignored)
```

### Modules dans `analyse.py`

1. **Nettoyage des données** — typage des dates, suppression des anomalies,
   calcul du prix au m², score de popularité.

2. **Rapport de ventes** — agrégation mensuelle, export CSV + graphique.

3. **Statistiques descriptives** — médiane, moyenne, écart-type,
   répartition par type de bien, analyse de l'écart négociation.

4. **Prédiction de prix** — `LinearRegression` et `RandomForestRegressor`
   comparés (MAE, R²). Features : surface, pièces, type, opération.

5. **Zones attractives** — score composite (biens, demandes, prix/m²)
   pour identifier les villes à fort potentiel.

6. **Probabilité de vente rapide** — heuristique pondérée (vues,
   demandes, offres, compétitivité du prix) → recommandation en 4 niveaux.

---

## Principes appliqués

- **SRP** : `db.py` ne fait que la connexion, `analyse.py` contient toute la logique
- **DRY** : fonctions de nettoyage centralisées, réutilisées partout
- **KISS** : chaque fonction fait une seule chose, documentée
- **pandas** pour manipulation tabulaire
- **scikit-learn** pour ML (régression, random forest)
- **matplotlib / seaborn** pour les graphiques
