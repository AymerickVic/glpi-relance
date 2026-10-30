# Journal des versions

## 0.1.0 — 30 octobre 2026

Première version, pour des testeurs.

- À la création d'un ticket, une tâche planifiée demande au modèle configuré ce qui manque (poste, message d'erreur, depuis quand, périmètre) et dépose un suivi privé de trois lignes avec la question prête. Si rien ne manque, rien n'est posté.
- Boutons « Envoyer la relance au demandeur » (suivi public signé par le technicien, ticket en attente) et « Ignorer », réservés aux techniciens.
- Fournisseurs : OpenAI, Anthropic, Ollama, et un mode à blanc sans appel.
- Masquage avant envoi (mots de passe, IBAN, cartes, NAS, NIR, e-mails, téléphones) ; catégories exclues ; pièces jointes, suivis privés et champs du demandeur jamais envoyés.
- Utilisateur technique « relance-robot » avec un profil dédié (lecture des tickets, ajout de suivis), sans possibilité de connexion.
- Configuration par entité avec héritage.
- Page de diagnostic ; journal de métadonnées purgé à 90 jours.
- Clé de licence facultative : sans clé, aucun appel vers un serveur tiers.
- Mesure sur 50 tickets : 49 décisions justes, 1 relance injustifiée, 0 manquée (`docs/evaluation.md`).
