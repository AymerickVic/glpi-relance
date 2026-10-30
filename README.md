# GLPI Assist

Plugin GLPI 11 : relance automatique des tickets incomplets.

À la création d'un ticket, le plugin le met dans une file. Une tâche planifiée la vide chaque minute, demande au modèle de votre choix ce qui manque (poste, message d'erreur, depuis quand, périmètre) et, s'il manque quelque chose, dépose un suivi privé de trois lignes avec la question prête à envoyer au demandeur. Si rien ne manque, il ne poste rien. Il n'écrit jamais au demandeur lui-même.

Le plugin tourne dans votre GLPI. La seule connexion sortante va vers le fournisseur que vous avez choisi (OpenAI, Anthropic, ou un Ollama sur votre réseau), avec votre clé. Ce qui est envoyé et ce qui ne l'est jamais : voir la page « sécurité et données » du site.

## Installation

1. Copier le dossier `glpiassist` dans `plugins/` de votre GLPI 11.
2. Configuration > Plugins : installer, puis activer.
3. Configuration > Plugins > GLPI Assist > Configurer : fournisseur, modèle, clé, catégories exclues, puis « Activer ».
4. Vérifier avec « Diagnostic », qui teste aussi la connexion au modèle.

Le mode « à blanc » n'appelle aucun modèle : il sert à vérifier que le circuit (file, tâche, suivi) fonctionne avant de mettre une clé.

## Licence

GPLv3 ou ultérieure, comme GLPI. Le code est libre ; la clé de licence (à venir) sert aux mises à jour et au support.
