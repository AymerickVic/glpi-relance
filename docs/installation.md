# Installer GLPI Assist

Durée : dix minutes. Prérequis : GLPI 11, PHP 8.2 ou plus, l'extension curl, et une clé API OpenAI ou Anthropic, ou un Ollama joignable depuis le serveur GLPI.

## 1. Déposer le plugin

Décompressez `glpiassist-<version>.zip` dans le dossier `plugins/` de votre GLPI. Vous devez obtenir `plugins/glpiassist/setup.php`. Le dossier doit appartenir au même utilisateur que le serveur web.

En ligne de commande, depuis le dossier GLPI :

```bash
php bin/console glpi:plugin:install glpiassist --username=<votre identifiant GLPI>
php bin/console glpi:plugin:activate glpiassist
```

Ou dans l'interface : Configuration > Plugins > GLPI Assist > Installer, puis Activer.

L'installation crée quatre tables préfixées `glpi_plugin_glpiassist_`, un utilisateur technique « glpiassist » (profil Technicien, entité racine, récursif ; c'est lui qui signe les suivis privés) et deux actions automatiques.

## 2. Configurer

Configuration > Plugins > GLPI Assist > Configurer, ou Configuration > Générale > onglet GLPI Assist.

- Fournisseur : OpenAI, Anthropic ou Ollama. Le mode « à blanc » n'appelle aucun modèle et sert à vérifier le circuit.
- Modèle : par exemple `gpt-4o-mini`, `claude-haiku-4-5`, ou `qwen2.5:14b` pour Ollama.
- Clé API : stockée chiffrée avec le mécanisme de GLPI. Laisser vide pour ne pas la changer.
- Catégories jamais traitées : les tickets de ces catégories ne sont pas envoyés au modèle. Mettez-y RH, paie, médical, accès, ou toute catégorie où des secrets circulent.
- Activer : Oui.

Cliquez « Diagnostic » : la page vérifie la version, l'utilisateur technique, l'action automatique, et teste la connexion au modèle.

## 3. L'action automatique

La tâche `processqueue` (Configuration > Actions automatiques) vide la file chaque minute. Par défaut elle est en mode GLPI, donc exécutée lors de la navigation des utilisateurs. Pour un délai régulier, passez-la en mode CLI et assurez-vous que le cron système de GLPI (`front/cron.php`) tourne chaque minute, comme la documentation GLPI le recommande.

## 4. Par entité

Chaque entité peut avoir sa propre configuration (onglet GLPI Assist dans la fiche de l'entité) : clé, modèle, activation. Une entité sans configuration propre hérite de l'entité parente la plus proche qui en a une. Un infogéreur active ainsi le plugin client par client, avec la clé de chacun s'il le souhaite.

## 5. Vérifier

Créez un ticket avec un titre vague (« ça ne marche plus ») : dans la minute, un suivi privé signé « GLPI Assist » apparaît avec ce qui manque et la question prête. En bas du ticket, « Envoyer la relance au demandeur » l'ajoute en suivi public, signé par vous, et met le ticket en attente. « Ignorer » retire les boutons.

Créez un ticket complet (poste, message d'erreur, depuis quand) : rien n'apparaît. C'est voulu.

## Désinstaller

Configuration > Plugins > Désinstaller. Les tables du plugin sont supprimées. Les suivis déjà écrits restent dans vos tickets. L'utilisateur technique est désactivé, pas supprimé, pour que les suivis gardent leur auteur.
