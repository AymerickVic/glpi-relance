# Évaluation sur 50 tickets — 2026-10-30

Modèle : anthropic / claude-haiku-4-5. Jeu de test : 25 tickets vagues (relance attendue) et 25 tickets complets ou sans besoin de relance (rien attendu), rédigés à la main en français, dans `tools/evaluer.php`.

- Décisions justes : **49 / 50**
- Relances injustifiées (ticket complet relancé) : **1**
- Relances manquées (ticket vague non relancé) : **0**
- Jetons au total : 16,010, soit environ 320 par ticket ; coût majoré ≈ 0.080 $ pour les 50, donc moins d'un centime par ticket
- Durée moyenne : 1,152 ms par ticket

| # | Ticket | Attendu | Obtenu | Juste | Manque relevé |
|---|---|---|---|---|---|
| 1 | Ça marche plus | relance | relance | oui | poste, message_erreur, depuis_quand, perimetre |
| 2 | Problème ordinateur | relance | relance | oui | poste, message_erreur, perimetre |
| 3 | Urgent | relance | relance | oui | poste, message_erreur, depuis_quand, perimetre |
| 4 | Outlook | relance | relance | oui | poste, message_erreur, depuis_quand |
| 5 | Plus de son | relance | relance | oui | poste, depuis_quand |
| 6 | Imprimante | relance | relance | oui | poste, message_erreur, depuis_quand, perimetre |
| 7 | Erreur | relance | relance | oui | poste, message_erreur, depuis_quand |
| 8 | Lenteur | relance | relance | oui | poste, depuis_quand, perimetre |
| 9 | Wifi | relance | relance | oui | poste, message_erreur, depuis_quand |
| 10 | Écran | relance | relance | oui | poste, message_erreur, depuis_quand |
| 11 | Accès refusé | relance | relance | oui | poste, message_erreur, depuis_quand |
| 12 | Teams | relance | relance | oui | poste, message_erreur, depuis_quand |
| 13 | VPN | relance | relance | oui | poste, message_erreur, depuis_quand |
| 14 | Mot de passe | relance | relance | oui | poste, message_erreur, depuis_quand |
| 15 | Logiciel compta | relance | relance | oui | poste, depuis_quand, message_erreur |
| 16 | Clavier | relance | relance | oui | poste, depuis_quand |
| 17 | Mail bloqué | relance | relance | oui | poste, depuis_quand, perimetre |
| 18 | Pb impression | relance | relance | oui | poste, depuis_quand |
| 19 | Souris | relance | relance | oui | poste, depuis_quand |
| 20 | Application métier | relance | relance | oui | message_erreur, poste, depuis_quand |
| 21 | Session | relance | relance | oui | poste, message_erreur, depuis_quand |
| 22 | Fichier corrompu | relance | relance | oui | poste, depuis_quand |
| 23 | Webcam | relance | relance | oui | poste, depuis_quand |
| 24 | Scanner | relance | relance | oui | poste, depuis_quand |
| 25 | Pas de réseau | relance | relance | oui | poste, depuis_quand |
| 26 | Imprimante HS salle 204 | rien | rien | oui |  |
| 27 | Accès au partage compta | rien | rien | oui |  |
| 28 | Demande de création de compte | rien | rien | oui |  |
| 29 | Commande d'un écran | rien | rien | oui |  |
| 30 | Outlook erreur 0x8004010F | rien | rien | oui |  |
| 31 | Réinitialisation mot de passe | rien | rien | oui |  |
| 32 | Installation logiciel | rien | rien | oui |  |
| 33 | Wifi salle de réunion B | rien | rien | oui |  |
| 34 | VPN erreur 809 | rien | rien | oui |  |
| 35 | Ajout d'une imprimante | rien | rien | oui |  |
| 36 | Teams : micro non détecté | rien | relance | **non** | message_erreur |
| 37 | Question licence | rien | rien | oui |  |
| 38 | Écran noir PC-ACC-01 | rien | rien | oui |  |
| 39 | Boîte partagée | rien | rien | oui |  |
| 40 | Lenteur Excel fichier budget | rien | rien | oui |  |
| 41 | Badge | rien | rien | oui |  |
| 42 | Clavier portable | rien | rien | oui |  |
| 43 | Départ collaborateur | rien | rien | oui |  |
| 44 | Pas de réseau bureau 312 | rien | rien | oui |  |
| 45 | Scanner Kyocera | rien | rien | oui |  |
| 46 | Formation | rien | rien | oui |  |
| 47 | Restauration fichier | rien | rien | oui |  |
| 48 | Webcam LAP-0789 | rien | rien | oui |  |
| 49 | Changement de bureau | rien | rien | oui |  |
| 50 | Session bloquée PC-VENTE-03 | rien | rien | oui |  |

Ce que le jeu ne mesure pas : la qualité de la question rédigée (relue par un humain avant envoi), les tickets avec pièces jointes, et les descriptions longues de plusieurs paragraphes.
