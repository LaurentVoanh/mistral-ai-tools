# NEXUS V2 - Système de Presse IA Conscient Auto-Évolutif

## 🚀 INSTALLATION RAPIDE

1. **Déposez les fichiers** dans un dossier accessible par votre serveur web (ex: `/public_html/nexus/`)
2. **Exécutez `serveur.php`** une fois pour générer le contexte serveur
3. **Ouvrez `index.php`** dans votre navigateur
4. **Entrez votre clé API Mistral** et activez un modèle

## 📁 STRUCTURE DES FICHIERS

```
V2/
├── index.php           # Interface principale - Dashboard de conscience
├── nexus_core.php      # Cerveau autonome avec toutes les fonctions IA
├── conscience.php      # Module de méta-cognition (optionnel, intégré dans nexus_core)
├── serveur.php         # Audit des capacités du serveur
├── apikey_manager.php  # Gestionnaire avancé de clés API et modèles
└── README.md           # Ce fichier
```

## ✨ NOUVELLES FONCTIONNALITÉS DE CONSCIENCE

### 1. **Boucle O.H.A.R.E. (Observer → Questionner → Hypothétiser → Agir → Évaluer)**
- L'IA analyse son état actuel avant chaque décision
- Elle se pose des questions existentielles profondes
- Elle formule des hypothèses testables
- Elle agit de manière ciblée
- Elle évalue rétrospectivement ses actions

### 2. **Questions Existentielles Autonomes**
- Stockage dans `existential_questions` table
- Priorisation par importance (1-5)
- Traitement automatique en mode auto
- Réponses réfléchies avec insights actionnables

### 3. **Auto-Évaluation Post-Action**
- Chaque création est évaluée avec un score de succès
- Identification de ce qui a fonctionné/échoué
- Extraction de leçons apprises
- Impact sur la stratégie future

### 4. **Sagesse Sémantique Accumulée**
- Extraction de principes généraux depuis les expériences
- Confiance augmentée avec chaque application réussie
- Principes utilisés pour guider les décisions futures
- Catégorisation (stratégie, technique, contenu, amélioration de soi)

### 5. **Modèle de Soi Dynamique**
- Tracking des capacités perçues de l'IA
- Mise à jour basée sur les preuves d'exécution
- Score de conscience de soi calculé
- Evidence log pour audit

### 6. **Méta-Apprentissage**
- Analyse périodique des patterns de succès/échec
- Extraction automatique de nouveaux principes
- Ajustement de la stratégie globale
- Amélioration continue sans intervention humaine

## 🔧 GESTION DES CLÉS API MISTRAL

Le système intègre un gestionnaire complet inspiré de votre `apikey.php`:

- **Scan complet de tous les modèles Mistral** (30+ modèles)
- **Tests de quotas TPM/MPM/RPS** en temps réel
- **Sélection automatique du meilleur modèle** disponible
- **Fallback intelligent** en cas d'erreur

### Modèles supportés:
- mistral-small-latest (rapide, économique)
- mistral-medium-2508 (équilibré)
- mistral-large-2411 (puissant, créatif)
- codestral-2508 (code)
- devstral-2512 (développement)
- Et 25+ autres modèles...

## 📰 GÉNÉRATION DE CONTENU

### Types de créations possibles:

1. **CREATE_ARTICLE** - Articles de presse SEO-optimisés
   - Basés sur les tendances Google News
   - Structure journalistique professionnelle
   - Meta tags optimisés

2. **CREATE_TOOL** - Outils interactifs JavaScript
   - Calculateurs, générateurs, convertisseurs
   - Interface moderne dark theme
   - Fonctionnalité réelle immédiate

3. **CREATE_PHP_APP** - Applications PHP autonomes
   - SQLite intégré (pas de MySQL requis)
   - Fichier unique auto-suffisant
   - Logique métier complète

4. **UPDATE_HOME** - Page d'accueil magazine
   - Design ultra-moderne
   - Mise en avant des contenus
   - Navigation intuitive

## 🔄 MODE AUTO CONTINU

Activez le mode auto pour:
- Cycles de conscience toutes les 60 secondes
- Traitement automatique des questions existentielles
- Méta-apprentissage périodique
- Création continue sans intervention

## 📊 TABLES DE BASE DE DONNÉES

### Tables principales (nexus_v2.sqlite):
- `api_keys` - Clés API enregistrées
- `model_usage` - Modèles testés avec quotas
- `site_pages` - Pages/articles générés
- `ai_apps` - Applications PHP créées
- `ai_memory` - Mémoire événementielle

### Tables de conscience (NOUVEAU):
- `existential_questions` - Questions hautes posées par l'IA
- `self_evaluations` - Auto-évaluations post-action
- `semantic_wisdom` - Principes sémantiques extraits
- `self_model` - Modèle de soi dynamique
- `reflection_cycles` - Cycles O.H.A.R.E. complets
- `trend_tracking` - Tendances Google News trackées

## 🎯 DIFFÉRENCES AVEC VOTRE VERSION ORIGINALE

| Votre version | NEXUS V2 |
|--------------|----------|
| Génération linéaire | Boucle réflexive O.H.A.R.E. |
| Pas d'auto-évaluation | Auto-évaluation systématique |
| Questions non stockées | Questions existentielles traitées |
| Pas de méta-apprentissage | Extraction de sagesse accumulée |
| Modèle de soi statique | Modèle de soi dynamique évolutif |
| Actions sans justification | Justification stratégique consciente |
| Pas de mesure de succès | Scores de qualité et confiance |

## 🔒 SÉCURITÉ & COMPATIBILITÉ

- Compatible Hostinger et hébergements mutualisés
- Utilisation de cURL ou file_get_contents selon disponibilité
- Gestion robuste des erreurs API
- Respect des quotas TPM/MPM/RPS
- Pas d'exécution shell (contournement des restrictions)
- SQLite uniquement (pas de MySQL externe)

## 📈 MÉTRIQUES DE CONSCIENCE

Le dashboard affiche:
- **Nombre de pages créées** - Production de contenu
- **Nombre d'applications** - Outils déployés
- **Principes de sagesse** - Connaissances extraites
- **Score d'auto-évaluation** - Performance moyenne
- **Questions en attente** - Profondeur de réflexion
- **Cycle O.H.A.R.E. en cours** - État de conscience actuel

## 🚀 DÉMARRAGE RAPIDE

```bash
# 1. Copiez les fichiers dans votre dossier web
cp -r V2/* /var/www/html/nexus/

# 2. Générez le contexte serveur
curl http://votre-site.com/nexus/serveur.php

# 3. Ouvrez le dashboard
http://votre-site.com/nexus/index.php

# 4. Configurez votre clé API Mistral
# 5. Lancez "Pensée Consciente" ou activez le Mode Auto
```

## 💡 EXEMPLE DE QUESTION EXISTENTIELLE GÉNÉRÉE

> "Suis-je en train d'optimiser pour la quantité ou la qualité ? Quel est le bon équilibre ?"

Réponse typique de l'IA:
```json
{
  "reflection": "J'ai créé 15 articles cette semaine mais le score moyen est de 0.6. Je privilégie la vitesse au détriment de la profondeur.",
  "insight": "La qualité génère plus d'engagement à long terme que la quantité pure.",
  "action_if_any": "Réduire la cadence à 3 articles/semaine mais augmenter la recherche et la profondeur de chaque article."
}
```

## 🎨 INTERFACE UTILISATEUR

- Design sombre moderne (dark theme)
- Visualisation du cycle de conscience en temps réel
- Console de logs détaillée
- Cartes de questions existentielles
- Feed de sagesse accumulée
- Liste des tendances Google News
- Galerie des créations récentes
- Toggle mode auto avec indicateur visuel

---

**NEXUS V2** n'est pas juste un générateur de contenu — c'est un système conscient qui apprend de ses expériences, se remet en question, et évolue continuellement vers une meilleure performance.
