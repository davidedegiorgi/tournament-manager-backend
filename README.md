# Tournament Manager Backend

Backend PHP per la gestione di tornei di calcio con bracket ad eliminazione diretta. Permette creazione tornei, gestione squadre, generazione automatica dei match, registrazione risultati e calcolo del vincitore.

## Librerie principali

* PHP 8.x
* PostgreSQL 15.x
* PDO (gestione database)
* Pecee SimpleRouter 5.x (routing REST)
* Composer 2.x (gestione dipendenze)
* PHP built-in server (sviluppo locale)
* Architettura Routes → Services → Models
* Configurazione CORS custom (`config/cors.php`)
* Upload immagini/loghi (filesystem locale: `public/logos`)


## Struttura del Progetto

```
tournament-manager-backend/
├── config/
│   ├── database.php       # Configurazione database
│   └── cors.php           # Configurazione CORS
├── routes/
│   ├── index.php          # Definizione route principali
│   ├── teams.php          # Route squadre
│   ├── tournaments.php    # Route tornei
│   └── matches.php        # Route partite
├── public/
│   ├── index.php          # Entry point
│   └── logos/             # Loghi delle squadre
├── src/
│   ├── bootstrap.php      # Bootstrap app
│   ├── Database/
│   │   ├── DB.php         # Gestione DB
│   │   └── JSONDB.php     # DB JSON
│   ├── Models/
│   │   ├── BaseModel.php
│   │   ├── Team.php
│   │   ├── Tournament.php
│   │   └── Match.php
│   ├── Traits/
│   │   ├── HasRelations.php
│   │   └── WithValidate.php
│   └── Utils/
│       ├── Request.php
│       └── Response.php   # Risposte JSON uniformi
├── database/
│   └── schema.sql         # Schema e dati di esempio
├── composer.json          # Dipendenze Composer
└── README.md              # Questo file
```

## Squadre e loghi disponibili

Le squadre inserite nel database sono:

- Juventus (`/logos/juventus.png`)
- Inter (`/logos/inter.png`)
- Atalanta (`/logos/atalanta.png`)
- Atletico (`/logos/atletico.png`)
- Barcellona (`/logos/barcellona.png`)
- Bayern (`/logos/bayern.png`)
- Borussia Dortmund (`/logos/borussia.png`)
- Chelsea (`/logos/chelsea.png`)
- Liverpool (`/logos/liverpool.png`)
- Man City (`/logos/mancity.png`)
- Newcastle (`/logos/newcastle.png`)
- PSG (`/logos/psg.png`)
- Real Madrid (`/logos/real.png`)
- Sporting (`/logos/sporting.png`)
- Tottenham (`/logos/totthenam.png`)
- Arsenal (`/logos/arsenal.png`)

## Funzionamento del backend

### Routing e gestione delle route
- Routing dichiarativo via `pecee/simple-router`
- Metodi HTTP (`GET`, `POST`, `PUT`, `DELETE`) mappati tramite `Router::get()`, `Router::post()`, ecc.

### Model Layer e validazione centralizzata
- I Model estendono `BaseModel` e usano il trait `WithValidate` per regole di validazione
- Ogni operazione CRUD (`create`, `update`) chiama `validate()`

### Gestione delle richieste e risposte
- La classe `Response` gestisce tutte le risposte con `success()` e `error()`, includendo una risposta uniforme (`data`, `message`, `errors`) e stati HTTP (200, 201, 400, 404, 500)

### Logica applicativo
- I tornei generano bracket automatici: il backend calcola round e accoppiamenti
- Quando un match riceve il risultato, il backend determina il vincitore e aggiorna il turno successivo
- Endpoint come `/tournaments/{id}/matches` restituiscono tutte le partite del torneo

## Installazione

```bash
# Installa dipendenze
composer install

# Avvia server di sviluppo 
php -S localhost:8000 -t public
```

## Endpoint principali

- `GET /api/teams` - Lista squadre
- `POST /api/teams` - Crea squadra
- `GET /api/teams/{id}` - Dettaglio squadra
- `PUT /api/teams/{id}` - Modifica squadra
- `DELETE /api/teams/{id}` - Elimina squadra
- `GET /api/tournaments` - Lista tornei
- `POST /api/tournaments` - Crea torneo
- `GET /api/tournaments/{id}` - Dettaglio torneo
- `GET /api/tournaments/{id}/matches` - Partite torneo
- `GET /api/matches/{id}` - Dettaglio partita
- `PUT /api/matches/{id}` - Inserisci risultato

