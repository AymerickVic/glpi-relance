# Modèle d'EFVP — Relance (Québec, loi 25)

Évaluation des facteurs relatifs à la vie privée, à réaliser par l'organisme avant d'activer un fournisseur de modèle situé hors Québec (OpenAI, Anthropic). Non requise si vous utilisez Ollama sur votre infrastructure. Ce modèle est un point de départ, pas un avis juridique ; votre responsable de la protection des renseignements personnels le valide.

## 1. Description du projet

Activation du plugin Relance dans le GLPI de [organisme]. À la création d'un ticket de support, le plugin envoie le titre et la description du ticket (pseudonymisés et masqués) au fournisseur [OpenAI / Anthropic] pour identifier les informations manquantes et préparer une question au demandeur. Un technicien relit et décide d'envoyer ou non.

## 2. Renseignements personnels concernés

Contenu rédigé par le demandeur dans le titre et la description du ticket. Le nom du demandeur est remplacé par un jeton. Les coordonnées (e-mail, téléphone) et identifiants (NAS, cartes, IBAN) sont masqués par motifs. Les pièces jointes et les champs d'identité ne sont jamais envoyés. Risque résiduel : un renseignement personnel écrit en toutes lettres dans la description par le demandeur, non reconnu par les motifs.

## 3. Communication hors Québec

Destinataire : [OpenAI, LLC, États-Unis / Anthropic, PBC, États-Unis]. Nature : traitement ponctuel, sans conservation au-delà de [durée selon contrat ; zéro si l'option est activée]. Engagement contractuel : [référence du DPA / des conditions commerciales], incluant l'interdiction d'entraînement sur les données et les mesures de sécurité.

## 4. Analyse de l'équivalence de protection

- Sensibilité : faible à moyenne (contenu de demandes de support interne), réduite par la pseudonymisation, le masquage et l'exclusion des catégories sensibles [RH, paie, médical, accès].
- Finalité : limitée au traitement du ticket ; aucune réutilisation.
- Mesures : chiffrement en transit, clé API protégée, journal sans contenu, relecture humaine avant tout envoi au demandeur.
- Régime juridique du destinataire : [analyse de votre RPRP sur l'encadrement américain et les clauses contractuelles].

## 5. Risques et mesures

| Risque | Mesure |
|---|---|
| Secret ou donnée sensible dans une description | Masquage par motifs ; catégories exclues ; bouton d'exclusion manuelle d'un ticket ; sensibilisation des demandeurs |
| Conservation par le fournisseur | Option de rétention zéro activée [oui/non] ; contrat |
| Fuite de la clé API | Chiffrement natif GLPI ; droit de configuration restreint ; rotation annuelle |
| Erreur du modèle (question inadaptée) | Aucun envoi automatique ; relecture par un technicien |

## 6. Conclusion

[Le traitement peut être mis en œuvre / doit être modifié / ne peut pas être mis en œuvre] compte tenu des mesures ci-dessus. Décision du RPRP : [nom, date].

Dernière mise à jour du modèle : octobre 2026, Relance 0.1.
