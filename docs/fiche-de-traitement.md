# Fiche de traitement — Relance (à compléter par le responsable du traitement)

Ce document est un modèle pré-rempli pour votre registre des traitements (RGPD, art. 30) ou votre documentation loi 25. Les champs entre crochets sont à compléter.

**Responsable du traitement** : [votre organisation]. Relance est un plugin installé dans votre GLPI ; son éditeur ne reçoit, ne stocke et ne traite aucune donnée.

**Finalité** : aider les techniciens du support à traiter les tickets plus vite, en identifiant les informations manquantes dans une demande et en préparant la question à poser au demandeur. Base légale proposée : intérêt légitime (bon fonctionnement du support interne). [À confirmer par votre DPO.]

**Personnes concernées** : demandeurs de tickets (salariés, [clients, prestataires]).

**Données traitées** : titre et description du ticket (tronquée à 2 000 caractères), suivis publics. Le nom du demandeur et des techniciens est remplacé par un jeton avant tout envoi. Ne sont jamais envoyés : pièces jointes, suivis privés, tâches, champs du demandeur (nom, e-mail, téléphone, lieu, matricule), champs personnalisés, historique.

**Masquage** : avant envoi, le plugin masque par motifs ce qui ressemble à un mot de passe, un IBAN, un numéro d'assurance sociale ou de sécurité sociale, un numéro de carte, une adresse e-mail, un numéro de téléphone. Ce masquage n'est pas exhaustif. Les catégories de tickets contenant des données sensibles ([RH, paie, médical, accès]) sont exclues du traitement dans la configuration du plugin.

**Destinataires et sous-traitants** : le fournisseur de modèle choisi par vous, au titre de votre propre contrat :
- [ ] OpenAI, via l'API, sous les conditions API d'OpenAI (pas d'entraînement sur les données API ; rétention selon votre contrat, zéro sur demande).
- [ ] Anthropic, via l'API, sous les conditions commerciales d'Anthropic (pas d'entraînement ; rétention selon votre contrat).
- [ ] Ollama sur votre infrastructure : aucun destinataire externe.

**Transferts hors UE / hors Québec** : oui si OpenAI ou Anthropic (États-Unis), encadrés par les clauses contractuelles du fournisseur [référence du DPA signé]. Au Québec, une évaluation des facteurs relatifs à la vie privée (EFVP) est requise avant la première communication hors Québec : voir `efvp-modele.md`. Non si Ollama local.

**Durées de conservation** : le plugin ne conserve aucun texte envoyé ni reçu. Journal de métadonnées (horodatage, numéro de ticket, fournisseur, modèle, nombre de jetons, durée, code de réponse, nombre de masquages) : 90 jours, puis purge automatique. Les suivis écrits dans les tickets suivent la durée de conservation de vos tickets.

**Sécurité** : clé API stockée chiffrée dans la base GLPI (chiffrement natif GLPI, clé hors du répertoire web), lisible uniquement par les profils ayant le droit de configuration. Connexion au fournisseur en HTTPS. Le plugin n'écrit jamais au demandeur : un technicien clique pour envoyer la relance.

**Droits des personnes** : exercés selon votre procédure habituelle pour les tickets GLPI. Le plugin n'ajoute aucune donnée nouvelle sur la personne.

**Réversibilité** : désactiver le plugin ou retirer la clé arrête tout traitement. La désinstallation supprime les tables du plugin.

Dernière mise à jour du modèle : octobre 2026, Relance 0.1.
