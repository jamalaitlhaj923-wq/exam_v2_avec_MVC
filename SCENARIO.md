# SCENARIO.md - EventHub Pro - Tests bout-en-bout

## Environnement
- PHP 8.2 / MySQL 8.0 / Apache
- Mailtrap (sandbox SMTP) pour les emails
- TCPDF 6.x pour la generation PDF

## Etape 1 - Creation de l'evenement
Action : POST /events/create.php avec { title: "DevFest Marrakech 2026", capacity: 5, ... }
Resultat : event_id retourne, ligne visible en BD (SELECT * FROM events WHERE id = X)

## Etape 2 - 4 inscriptions successives
Action : POST /events/register.php x 4 avec emails distincts
Resultat : 4 lignes dans registrations, 4 emails dans Mailtrap, compteur mis a jour en JS

## Etape 3 - Seuil 80% (4eme inscrit = 80%)
Action : 4eme inscription (4/5 = 80%)
Resultat : email d'alerte envoye a orga@ensa.ma avec rapport PDF en piece jointe
           events.alert_sent = 1 en BD (verifier : SELECT alert_sent FROM events WHERE id = X)

## Etape 4 - Evenement complet (5eme inscrit)
Action : 5eme inscription
Resultat : bouton "S'inscrire" remplace par "Complet" sans rechargement (update DOM via JS)
           Tentative 6eme inscription -> reponse { success: false, error: "Evenement complet." }

## Etape 5 - Telechargement rapport PDF
Action : GET /pdf/report.php?event_id=X
Resultat : PDF 3 pages telecharge (resume, liste inscrits, graphique barres)

## Etape 6 - Desinscription
Action : GET /events/unsubscribe.php?token=TOKEN_INSCRIT
Resultat : ligne supprimee de registrations, places liberees, interface mise a jour
