# CHOIX_TECHNIQUES.md

## 1. Bibliotheque PDF : TCPDF
TCPDF a ete choisi car ses primitives de dessin (Rect, Line, Cell, SetFillColor) permettent de generer le graphique en barres en PHP pur sans JavaScript ni image externe, comme exige en Partie 3.2.

## 2. Strategie filtres dynamiques (Partie 1.3)
Approche : tableau $conditions[] + tableau $bindings[] nommes. Toutes les conditions sont ajoutees dynamiquement selon les filtres actifs, puis assemblees avec implode(' AND ', $conditions). Aucune valeur utilisateur n'est concatenee dans la chaine SQL.

## 3. Prevention doublons d'alerte email (Partie 2.2)
Approche : colonne alert_sent TINYINT dans la table events. Avant d'envoyer, on verifie events.alert_sent = 0. Apres envoi reussi, on UPDATE events SET alert_sent = 1. Avantage sur les fichiers lock : persistant en cas de redemarrage serveur, atomique avec la transaction PDO, auditable en BD.

## 4. Architecture retenue
Structure procedurale avec classes statiques (SendConfirmation, AlertMailer) pour la separation des responsabilites sans surcharge MVC complete, adaptee au temps d'examen de 3h.

## 5. Bonus MVC
Une architecture MVC complete a ete ajoutee sans modifier le frontend original :

- `public/index.php` est le Front Controller unique de la version MVC.
- `core/Router.php` dispatch les routes vers les controleurs.
- `core/Database.php` implemente un Singleton PDO partage.
- `core/Controller.php` est la classe abstraite commune.
- `app/Models/*` contient uniquement les requetes PDO.
- `app/Controllers/*` contient la logique metier : evenements, API, emails, PDF et dashboard.
- `app/Views/*` contient uniquement l'affichage HTML, sans requete SQL.

Les anciens endpoints (`api/events.php`, `events/register.php`, etc.) sont conserves pour garantir que le frontend fourni par l'examen reste identique et continue de fonctionner. La version MVC est accessible via `public/index.php?route=events`, avec des routes JSON equivalentes comme `public/index.php?route=api/events` et `public/index.php?route=api/stats`.
