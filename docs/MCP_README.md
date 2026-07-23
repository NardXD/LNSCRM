# CRM MCP Server (Model Context Protocol)

This MCP server connects the ItsWorkPlace CRM to **Claude AI**, allowing Claude to query live client data, tickets, invoices, deals, and activity logs directly.

## Setup

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Generate API Key

```bash
php artisan mcp:create-key --company=1
```

Replace `1` with your company ID. Optionally add a label:

```bash
php artisan mcp:create-key --company=1 --name="Production Claude Key"
```

Keys are **read-only** by default. To allow creating/updating records, add `--write`:

```bash
php artisan mcp:create-key --company=1 --name="Automation (write)" --write
```

**Save the API key** — it is shown only once.

### 3. MCP Server URL

| Environment | URL |
|-------------|-----|
| Production | `https://crm.airbs.com/mcp` |
| Local | `http://localhost/mcp` or `http://your-app.test/mcp` |

### 4. Configure Claude

In Claude (e.g., Claude Desktop, Claude for API, or your MCP client):

- **URL**: `https://crm.airbs.com/mcp` (HTTPS required in production)
- **Header**: `X-API-Key: {your-api-key}`

## Protocol

- **Transport**: MCP Streamable HTTP (JSON-RPC over POST)
- **Authentication**: API key in `X-API-Key` header
- **Reference**: [Model Context Protocol - Transports](https://modelcontextprotocol.io/docs/concepts/transports)

## Endpoints (Tools)

Claude uses these tools to query your CRM. Data is scoped to the company linked to your API key.

| Tool | Description | Arguments |
|------|-------------|-----------|
| `get_clients` | Full client list with contacts | `status`, `search` (optional) |
| `get_client` | Single client profile | `id` (required) |
| `get_deals` | All deals/projects with status | `status` (optional) |
| `get_deal` | Single deal detail | `id` (required) |
| `get_tickets` | Support tickets (open/closed/pending) | `status`, `priority` (optional) |
| `get_ticket` | Single ticket detail | `id` (required) |
| `get_invoices` | All invoices with status | `status` (optional) |
| `get_invoices_by_client` | Invoices for a specific client | `client_id` (required) |
| `get_activity` | Notes & activity log per client | `client_id` (required) |

### REST-style equivalent mapping

If calling as REST API (outside MCP):

| REST-style | MCP Tool |
|------------|----------|
| GET /clients | `get_clients` |
| GET /clients/{id} | `get_client` with `id` |
| GET /deals | `get_deals` |
| GET /deals/{id} | `get_deal` with `id` |
| GET /tickets | `get_tickets` |
| GET /tickets/{id} | `get_ticket` with `id` |
| GET /invoices | `get_invoices` |
| GET /invoices/{client_id} | `get_invoices_by_client` with `client_id` |
| GET /activity/{client_id} | `get_activity` with `client_id` |

## Example: Manual JSON-RPC Call

```bash
curl -X POST https://crm.airbs.com/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "X-API-Key: mcp_your_key_here" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2025-03-26",
      "capabilities": {},
      "clientInfo": {"name": "test", "version": "1.0"}
    }
  }'
```

## Security

- Use **HTTPS** in production
- Rotate API keys periodically
- Revoke compromised keys by deleting the record from `mcp_api_keys`
- Each key is tied to one company; data is isolated by company

## Troubleshooting

| Issue | Fix |
|-------|-----|
| 401 Unauthorized | Check `X-API-Key` header and that the key exists |
| 404 | Ensure the URL ends with `/mcp` |
| No data returned | Verify the API key's company has clients/deals/etc. |
