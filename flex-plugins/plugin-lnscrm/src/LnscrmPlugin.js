import { FlexPlugin } from '@twilio/flex-plugin';

const PLUGIN_NAME = 'LnscrmPlugin';

function getConfig() {
  const cfg = (typeof window !== 'undefined' && window.LNSCRM_FLEX) || {};
  return {
    screenPopBase: cfg.screenPopBase || '',
    lookupApi: cfg.lookupApi || '',
    apiKey: cfg.apiKey || '',
  };
}

function extractPhone(task) {
  if (!task || !task.attributes) {
    return '';
  }
  const a = task.attributes;
  const candidates = [
    a.from,
    a.caller,
    a.outbound_to,
    a.to,
    a.called,
    a.name,
    a.customerAddress,
  ];
  for (const c of candidates) {
    if (typeof c === 'string' && c.trim()) {
      return c.replace(/^client:/i, '').replace(/^whatsapp:/i, '').trim();
    }
  }
  return '';
}

function screenPopUrl(task) {
  const { screenPopBase } = getConfig();
  if (!screenPopBase) {
    return 'about:blank';
  }
  const phone = extractPhone(task);
  if (!phone) {
    return screenPopBase;
  }
  const sep = screenPopBase.includes('?') ? '&' : '?';
  return `${screenPopBase}${sep}phone=${encodeURIComponent(phone)}`;
}

async function fetchCrmLookup(phone) {
  const { lookupApi, apiKey } = getConfig();
  if (!lookupApi || !apiKey || !phone) {
    return null;
  }
  const url = `${lookupApi}?phone=${encodeURIComponent(phone)}`;
  const res = await fetch(url, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      'X-API-Key': apiKey,
    },
  });
  if (!res.ok) {
    console.warn('[LNSCRM Flex] lookup failed', res.status);
    return null;
  }
  return res.json();
}

export default class LnscrmPlugin extends FlexPlugin {
  constructor() {
    super(PLUGIN_NAME);
  }

  /**
   * @param {typeof import('@twilio/flex-ui')} flex
   * @param {import('@twilio/flex-ui').Manager} manager
   */
  async init(flex, manager) {
    flex.CRMContainer.defaultProps.uriCallback = (task) => screenPopUrl(task);

    flex.Actions.addListener('afterAcceptTask', async (payload) => {
      try {
        const phone = extractPhone(payload.task);
        const data = await fetchCrmLookup(phone);
        if (data) {
          console.info('[LNSCRM Flex] CRM context', data.display_name, data.client?.id || null);
          // Stash on task attributes for other plugins / UI (local only).
          if (payload.task && data.client?.crm_url) {
            // no-op write — Flex task attributes are server-owned; log only
          }
        }
      } catch (err) {
        console.warn('[LNSCRM Flex] afterAcceptTask lookup error', err);
      }
    });

    manager.events.addListener('selectedViewChanged', () => {
      // Ensures CRMContainer refreshes when navigating tasks
    });
  }
}
