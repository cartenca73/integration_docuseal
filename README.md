# DocuSeal Integration for Nextcloud

[![Nextcloud](https://img.shields.io/badge/Nextcloud-28--34-blue?logo=nextcloud)](https://nextcloud.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple?logo=php)](https://php.net)
[![Vue 3](https://img.shields.io/badge/Vue-3-green?logo=vue.js)](https://vuejs.org)
[![License](https://img.shields.io/badge/License-AGPL--3.0-red)](LICENSE)

Integrazione completa di **DocuSeal Enterprise** (self-hosted) con Nextcloud per la firma elettronica dei documenti direttamente dall'app File.

---

## Funzionalita

### Firma Documenti
- **Firma diretta** - Click destro su PDF, DOCX o immagine -> invia per la firma
- **Firma via template** - Usa template DocuSeal preconfigurati con anteprima campi/ruoli
- **Firma embedded** - I firmatari possono firmare direttamente dentro Nextcloud (iframe)
- **Firma via email** - DocuSeal invia email ai firmatari con link di firma
- **Destinatari multipli** - Utenti Nextcloud (autocomplete) o email esterne

### Tracking e Gestione
- **Stato in tempo reale** - Sidebar del file con stato di ogni firmatario e barra progresso
- **Notifiche Nextcloud** - Firmato, rifiutato, completato, scaduto
- **Download automatico** - Il PDF firmato viene salvato automaticamente nella stessa cartella
- **Audit trail** - Timeline completa con download del log di audit PDF
- **Reinvio promemoria** - Reinvia notifica a chi non ha ancora firmato
- **Annullamento** - Annulla richieste di firma pendenti
- **Scadenza** - Imposta data di scadenza sulle richieste

### Integrazioni Nextcloud
- **Unified Search** - Cerca richieste di firma dalla barra di ricerca
- **Dashboard Widget** - Widget con stato firme pendenti
- **Activity** - Log attivita integrato con l'app Activity
- **CSP Policy** - Content Security Policy automatica per iframe embedding

### Sicurezza
- **API Key criptata** - Stored con ICrypto di Nextcloud
- **Webhook HMAC-SHA256** - Validazione webhook con secret condiviso
- **Controllo accesso file** - Verifica permessi prima di ogni operazione

### Multilingue
Traduzioni complete: **Italiano**, **English**, **Deutsch**, **Francais**, **Espanol**

La lingua viene selezionata automaticamente in base alle impostazioni Nextcloud dell'utente.

---

## Requisiti

| Componente | Versione |
|---|---|
| Nextcloud | 28 - 34 |
| PHP | 8.1+ |
| DocuSeal Enterprise | Self-hosted |
| Node.js | 20+ (solo per build) |

---

## Installazione

### 1. Copia l'app

```bash
cp -r integration_docuseal /path/to/nextcloud/apps/
chown -R www-data:www-data /path/to/nextcloud/apps/integration_docuseal
```

### 2. Build frontend

```bash
cd /path/to/nextcloud/apps/integration_docuseal
npm install --legacy-peer-deps
npm run build
```

### 3. Abilita l'app

```bash
sudo -u www-data php occ app:enable integration_docuseal
```

### 4. Configura

1. Vai su **Impostazioni -> Account connessi -> DocuSeal**
2. Inserisci l'URL del tuo server DocuSeal (es. `https://docuseal.example.com`)
3. Inserisci la API Key (la trovi in DocuSeal: Impostazioni -> API)
4. Clicca **Salva** - la connessione viene testata automaticamente

### 5. Configura Webhook (opzionale ma consigliato)

Nel pannello admin di DocuSeal:
1. Vai su **Impostazioni -> Webhook**
2. Inserisci l'URL: `https://tuonextcloud.com/apps/integration_docuseal/webhook`
3. (Opzionale) Configura un secret condiviso per la validazione HMAC

---

## Utilizzo

### Richiedere una firma

1. Vai nell'app **File** di Nextcloud
2. Click destro su un file PDF, DOCX o immagine
3. Seleziona **"Richiedi firma con DocuSeal"**
4. Scegli la modalita:
   - **Invio diretto** - Il file viene inviato cosi com'e
   - **Template DocuSeal** - Usa un template preconfigurato
5. Aggiungi i destinatari (utenti NC o email)
6. Opzioni: email, firma embedded, scadenza, messaggio personalizzato
7. Clicca **Richiedi firma**

### Monitorare le firme

- Apri la **sidebar** di un file per vedere lo stato delle firme
- Usa la **barra di ricerca** per trovare richieste di firma
- Controlla il **Dashboard** per una panoramica
- Ricevi **notifiche** in tempo reale

### Audit Trail

Dalla sidebar del file, clicca **"Audit trail"** per vedere:
- Timeline completa (inviato, aperto, firmato, rifiutato)
- Download del log di audit in PDF da DocuSeal

---

## Architettura

```
integration_docuseal/
├── appinfo/           Metadata app + 18 route API
├── lib/
│   ├── Activity/      Integrazione Activity app
│   ├── BackgroundJob/ Polling stato ogni 15 min
│   ├── Controller/    3 controller (Config, DocuSeal, Webhook)
│   ├── Dashboard/     Widget Dashboard
│   ├── Db/            2 entita + 2 mapper (requests + submitters)
│   ├── Listener/      CSP per iframe
│   ├── Migration/     Schema database
│   ├── Notification/  4 tipi di notifica
│   ├── Search/        Unified Search provider
│   ├── Service/       API service + utilities
│   └── Settings/      Pannello admin
├── src/               Vue 3 components
├── l10n/              5 lingue
└── tests/             PHPUnit tests
```

### Database

Due tabelle:
- `oc_docuseal_requests` - Richieste di firma (user, file, submission, status)
- `oc_docuseal_submitters` - Firmatari (email, status, embed URL)

### API Endpoints

| Metodo | Endpoint | Descrizione |
|---|---|---|
| GET | `/info` | Verifica configurazione |
| GET | `/templates` | Lista template DocuSeal |
| GET | `/templates/{id}` | Dettaglio template |
| POST | `/sign/direct/{fileId}` | Firma diretta file |
| POST | `/sign/template` | Firma via template |
| GET | `/requests` | Lista richieste utente |
| GET | `/requests/{id}` | Dettaglio richiesta |
| GET | `/requests/file/{fileId}` | Richieste per file |
| POST | `/requests/{id}/resend/{submitterId}` | Reinvia promemoria |
| POST | `/requests/{id}/cancel` | Annulla richiesta |
| GET | `/requests/{id}/audit` | Audit trail |
| GET | `/embed/{requestId}` | URL firma embedded |
| POST | `/webhook` | Webhook DocuSeal |

---

## Sviluppo

```bash
# Setup
make dev-setup

# Build sviluppo
npm run dev

# Watch mode
npm run watch

# Lint
make lint

# Test
make test

# Build produzione
npm run build

# Package per distribuzione
make appstore
```

---

## Tipi di file supportati

| Tipo | Estensioni | Firma diretta | Template |
|---|---|---|---|
| PDF | .pdf | Si | Si |
| Word | .docx, .doc | Si | Si |
| Immagini | .png, .jpg, .jpeg | Si | Si |

---

## Licenza

AGPL-3.0-or-later

Sviluppato da **GEST CE** per l'integrazione con DocuSeal Enterprise.
