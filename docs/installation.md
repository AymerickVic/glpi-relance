# Installer Relance

Durée : dix minutes. Prérequis : GLPI 11, PHP 8.2 ou plus, l'extension curl, et une clé API OpenAI ou Anthropic, ou un Ollama joignable depuis le serveur GLPI. Testé sur GLPI 11.0.12, PHP 8.2 à 8.5, MariaDB 11.

## 1. Déposer le plugin

Décompressez `relance-<version>.zip` dans le dossier `plugins/` de votre GLPI. Vous devez obtenir `plugins/relance/setup.php`. Le dossier doit appartenir au même utilisateur que le serveur web.

En ligne de commande, depuis le dossier GLPI :

```bash
php bin/console glpi:plugin:install relance --username=<votre identifiant GLPI>
php bin/console glpi:plugin:activate relance
```

Ou dans l'interface : Configuration > Plugins > Relance > Installer, puis Activer.

L'installation crée quatre tables préfixées `glpi_plugin_relance_`, un profil « Relance (robot) » (lecture des tickets, ajout et lecture de suivis, rien d'autre), un utilisateur technique « relance-robot » avec ce profil sur l'entité racine, récursif, sans source d'authentification donc sans connexion possible, et trois actions automatiques.

## 2. Configurer

Configuration > Plugins > Relance > Configurer, ou Configuration > Générale > onglet Relance.

- Fournisseur : OpenAI, Anthropic ou Ollama. Le mode « à blanc » n'appelle aucun modèle et sert à vérifier le circuit.
- Modèle : par exemple `gpt-4o-mini`, `claude-haiku-4-5`, ou `qwen2.5:14b` pour Ollama.
- Clé API : stockée chiffrée avec le mécanisme de GLPI. Laisser vide pour ne pas la changer.
- Catégories jamais traitées : les tickets de ces catégories ne sont pas envoyés au modèle. Mettez-y RH, paie, médical, accès, ou toute catégorie où des secrets circulent.
- Activer : Oui.

Cliquez « Diagnostic » : la page vérifie la version, l'utilisateur technique, l'action automatique, et teste la connexion au modèle. Si votre GLPI passe par un proxy, le plugin utilise celui configuré dans Configuration > Générale.

## 3. L'action automatique

La tâche `processqueue` (Configuration > Actions automatiques) vide la file chaque minute. GLPI la lance en mode CLI si votre cron système appelle `front/cron.php` chaque minute, comme la documentation GLPI le recommande ; sinon elle tourne lors de la navigation des utilisateurs, et le suivi tombe à la première page ouverte par un technicien. Un passage traite au plus 20 tickets et s'arrête après 40 secondes pour reprendre au suivant.

Si le fournisseur ne répond pas, le ticket est réessayé trois fois puis marqué en échec dans la page Diagnostic. Le ticket lui-même n'est jamais modifié.

## 4. Par entité

Chaque entité peut avoir sa propre configuration (onglet Relance dans la fiche de l'entité) : clé, modèle, activation, catégories exclues. Une entité sans configuration propre hérite de l'entité parente la plus proche qui en a une. Un infogéreur active ainsi le plugin client par client, avec la clé de chacun s'il le souhaite.

## 5. Vérifier

Créez un ticket avec un titre vague (« ça ne marche plus ») : dans la minute, un suivi privé signé « Relance (robot) » apparaît avec ce qui manque et la question prête. En bas du ticket, pour un technicien, « Envoyer la relance au demandeur » l'ajoute en suivi public, signé par lui, et met le ticket en attente. « Ignorer » retire les boutons. Un demandeur ne voit jamais ces boutons.

Créez un ticket complet (poste, message d'erreur, depuis quand) : rien n'apparaît. C'est voulu.

Les tickets datés de plus d'une heure à leur création (reprises, migrations par API) sont ignorés.

## Désinstaller

Configuration > Plugins > Désinstaller. Les tables du plugin sont supprimées. Les suivis déjà écrits restent dans vos tickets. L'utilisateur technique est désactivé, pas supprimé, pour que les suivis gardent leur auteur. Le profil « Relance (robot) » reste, vide ; supprimez-le à la main si vous le souhaitez.
