# LNSCRM Flex Plugin (Option B)

Agents work in **Twilio Flex**. This plugin screen-pops LNSCRM client context in the Flex CRM panel.

## Prerequisites

1. Twilio account with **Flex** enabled  
2. LNSCRM **Twilio** integration connected (same account)  
3. LNSCRM **Twilio Flex** integration connected → copy the **plugin API key** and URLs from Integrations

## Configure

Create `public/appConfig.js` (or set env before build) with:

```js
window.LNSCRM_FLEX = {
  crmBaseUrl: 'https://YOUR-CRM-HOST',           // e.g. https://acme.lnscrm.com
  screenPopBase: 'https://YOUR-CRM-HOST/flex/screen-pop/YOUR_WEBHOOK_KEY',
  lookupApi: 'https://YOUR-CRM-HOST/api/flex/crm/lookup',
  apiKey: 'flex_…'                               // from Integrations → Twilio Flex
};
```

## Develop / deploy

```bash
cd flex-plugins/plugin-lnscrm
npm install
npm start          # local Flex plugin with Twilio CLI login
npm run deploy     # twilio flex:plugins:deploy (requires Twilio Flex CLI)
```

Or with the Twilio Flex Plugins CLI:

```bash
twilio flex:plugins:create   # if scaffolding fresh
twilio flex:plugins:deploy --major --changelog "LNSCRM CRM screen-pop"
twilio flex:plugins:release --plugin plugin-lnscrm@1.0.0
```

## TaskRouter webhook

In Twilio Console → TaskRouter → Workspace → **Event Callbacks**, set:

`https://YOUR-CRM-HOST/webhooks/flex/YOUR_WEBHOOK_KEY/events`

Subscribe at least to: `reservation.created`, `reservation.accepted`, `reservation.completed`, `task.completed`.

## What it does

- Sets `CRMContainer` URL to LNSCRM screen-pop with the task caller/callee phone  
- On task accept, fetches JSON CRM lookup (optional logging / future UI components)
