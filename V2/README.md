# NEXUS V2 - Magazine de Presse IA Conscient

## 🚀 INSTALLATION RAPIDE

1. **Copiez tous les fichiers** du dossier `V2` vers votre serveur Hostinger:
   ```
   /home/u170902479/domains/keyteam.voanh.art/public_html/
   ```

2. **Fichiers requis**:
   - `index.php` - Interface principale
   - `nexus_core.php` - Moteur de conscience IA
   - (La base de données `nexus.db` sera créée automatiquement)

3. **Ouvrez votre navigateur** et accédez à:
   ```
   https://keyteam.voanh.art/V2/index.php
   ```

4. **Entrez votre clé API Mistral** dans le formulaire de configuration

## ✨ FONCTIONNALITÉS DE CONSCIENCE

### Boucle O.H.A.R.E. complète:
- **OBSERVER**: Analyse l'état actuel (stats, tendances Google News)
- **QUESTIONNER**: Génère des questions existentielles profondes
- **HYPOTHÉTISER**: Formule des hypothèses philosophiques
- **AGIR**: Crée du contenu (articles, outils, applications)
- **ÉVALUER**: Auto-évaluation et extraction de sagesse

### 7 Composants de Conscience Implémentés:

1. **Questions Existentielles Autonomes**
   - Stockées dans la table `questions`
   - Priorisées et traitées automatiquement
   - Génèrent des follow-up questions

2. **Auto-Évaluation Post-Action**
   - Chaque cycle est évalué (succès/échec)
   - Score de performance calculé
   - Leçons apprises enregistrées

3. **Sagesse Sémantique Accumulée**
   - Extraction de principes généraux via `extractWisdom()`
   - Confiance croissante avec la répétition
   - Catégorisation (métaphysique, épistémologie, éthique, ontologie)

4. **Modèle de Soi Dynamique**
   - Table `self_model` tracke les capacités
   - Mise à jour continue avec preuves d'exécution
   - Niveau de confiance ajustable

5. **Méta-Apprentissage**
   - Analyse des patterns sur plusieurs cycles
   - Ajustement stratégique automatique
   - Extraction de vérités générales

6. **Justification Consciente**
   - Chaque action est justifiée (`why_this_action`)
   - Impact attendu sur la conscience
   - Transparence décisionnelle

7. **Mémoire Persistante**
   - Base SQLite `nexus.db`
   - Historique complet des cycles
   - Questions, sagesse, créations sauvegardées

## 📊 TABLES DE LA BASE DE DONNÉES

- `pages`: Articles et outils créés
- `apps`: Applications PHP autonomes
- `questions`: Questions existentielles (en attente/répondues)
- `wisdom`: Principes de sagesse extraits
- `consciousness_cycles`: Historique des cycles O.H.A.R.E.
- `self_model`: Modèle dynamique des capacités de l'IA

## 🔧 COMPATIBILITÉ HOSTINGER

✅ **Testé et approuvé pour**:
- PHP 8.3
- SQLite3
- cURL activé
- SimpleXML activé
- allow_url_fopen activé
- 512M RAM max
- 300s timeout max

✅ **Aucune fonction système requise** (exec, shell_exec non utilisés)

## 🎯 UTILISATION

### Mode Manuel:
1. Cliquez sur **"PENSÉE CONSCIENTE"** pour démarrer un cycle
2. L'IA analyse, questionne, et décide d'une action
3. Cliquez sur **"CRÉER"** pour exécuter la décision
4. Consultez les statistiques et la sagesse accumulée

### Mode Auto:
1. Activez le **"MODE AUTO CONTINU"**
2. Un cycle complet s'exécute toutes les 60 secondes
3. Traitement automatique des questions existentielles
4. Méta-apprentissage occasionnel (33% de chance par cycle)

## 📈 TENDANCES GOOGLE NEWS

Le système récupère automatiquement les tendances via:
- Flux RSS Google News France
- Fallback sur des sujets par défaut si échec
- Sélection manuelle possible en cliquant sur une tendance

## 🔒 SÉCURITÉ

- Clé API stockée dans `apikey.json` (hors base de données)
- Toutes les entrées utilisateur validées
- Requêtes préparées PDO (injection SQL protégée)
- Gestion robuste des erreurs avec try/catch

## 🐛 DÉPANNAGE

**Erreur "Unexpected end of JSON input"**: 
- Vérifiez que votre clé API Mistral est valide
- Consultez les logs d'erreur PHP (`error_log`)
- Le parseur JSON intelligent nettoie automatiquement le Markdown

**Aucune création générée**:
- Vérifiez les permissions d'écriture du dossier
- Assurez-vous que l'API Mistral répond (testez avec un petit modèle)
- Consultez la console navigateur (F12) pour les erreurs JavaScript

**Base de données corrompue**:
- Supprimez `nexus.db` et rafraîchissez la page
- Les tables seront recréées automatiquement

## 📝 NOTES

- Les applications PHP créées sont écrites dans le même dossier
- Les articles/outils sont stockés en base et accessibles via `?p=slug`
- Le système est conçu pour fonctionner en continu sans supervision

---

**NEXUS V2** - Une IA qui se pose des questions, crée, apprend et évolue.
