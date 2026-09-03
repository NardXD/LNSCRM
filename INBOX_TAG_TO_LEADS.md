# `inbox:tag-to-leads` — bulk-save tagged inbox emails as leads

Console command that saves every `/inbox` email carrying a given tag or label as a CRM lead,
without clicking "Save as lead" one by one. It reuses the exact same name/phone/email extraction
and duplicate-detection rules as that button, so it won't create duplicate leads.

Command class: `app/Console/Commands/SaveTaggedInboxAsLeads.php`

## Usage

```bash
php artisan inbox:tag-to-leads <tag> [options]
```

Always try `--dry-run` first:

```bash
php artisan inbox:tag-to-leads Inquiry --shared-inbox="Sales Inbox" --dry-run
```

Then drop `--dry-run` to actually create/attach:

```bash
php artisan inbox:tag-to-leads Inquiry --shared-inbox="Sales Inbox"
```

## Arguments

| Argument | Description |
|---|---|
| `tag` | Name of the tag/label to match, e.g. `Inquiry`. Matched case-insensitively. |

## Options

| Option | Description |
|---|---|
| `--shared-inbox=` | Restrict to one shared mailbox, by ID **or** name (e.g. `--shared-inbox="Sales Inbox"` or `--shared-inbox=3`). Omit to search every mailbox in the company. |
| `--user=` | ID of the user the created leads/activity log entries are attributed to. No mailbox membership is required — this command is meant to run from the server, not through the UI. If omitted, defaults to the `--shared-inbox`'s creator; if that can't be resolved either, the command errors out asking for `--user`. |
| `--company=` | Restrict to one company ID. Defaults to the `--shared-inbox`'s or `--user`'s company. |
| `--source=` | Value stored in the lead's `source` field. Default: `Inbox tag import`. |
| `--limit=` | Max number of conversations to process, oldest first. Default: **no limit** — every match is processed. Use this to test on a small batch before a full run. |
| `--dry-run` | Preview what would happen — no leads are created, no emails are attached, no database writes happen at all. |

## Where "tag" is looked up

The inbox page renders a conversation's label pills from two different tables, and a label typed
in the UI could live in either one:

- **`inbox_tags`** — plain tags added directly on a conversation.
- **`lead_labels`** — used for labels applied to a conversation *before* it has a lead (this is
  what Front-imported tags use, via `inbox_conversation_lead_label`).

The command checks both by name and matches conversations tagged in either table, so you don't
need to know which one a given label actually lives in.

## What it does, per matching conversation

1. Finds inbox conversations (in the target company/mailbox) that:
   - carry the given tag or label,
   - are **not merged** into another conversation, and
   - **don't already have a lead attached** (`lead_id IS NULL`).
2. Re-hydrates the Outlook body (fetches the full HTML if only a preview is cached) and runs it
   through `MessageContactExtractor` — the same extractor the "Save as lead" button uses — to pull
   a name, phone number(s), and email(s) out of the message body.
3. **Skips** the conversation if no name *and* no phone/email could be found (logged with a reason).
4. Checks for an existing lead whose phone or email already matches (same dedupe rule
   `LeadsController::findIdentityConflict` uses for the UI):
   - **Match found** → attaches this email to that existing lead instead of creating a duplicate.
   - **No match** → creates a new `Lead` with the extracted name/phone/email, status defaulted via
     `LeadStatus::fallbackSlug()`, and `source` set to `--source`.
5. Attaches the conversation to the lead (sets `inbox_conversations.lead_id`) via
   `LeadInboxAttachService::attach()`. This only works for **shared**-type mailboxes — personal
   inboxes can't be attached to leads (a real business rule enforced app-wide, not something this
   command bypasses). Attach failures are reported per-conversation rather than silently skipped.
6. Prints a per-conversation result line, then a summary: how many leads were **created**, how
   many were **matched** to an existing lead, and how many were **skipped**.

## Notes / gotchas

- **No mailbox-membership requirement.** The normal "Save as lead" flow in the UI requires the
  acting user to be a member of the shared mailbox before an email can be attached to a lead
  (`LeadInboxAttachService::assertInboxAccess`). This command calls `attach()` with
  `requireMembership: false`, since it's a trusted backend job run from the server rather than a
  user action through the UI. `--user` is only used for attribution (who shows up as the actor in
  the lead's activity log), not for permission checks.
- **Idempotent-ish, but not resumable by itself.** Once a conversation gets a `lead_id`, it drops
  out of the query on the next run — so re-running the same command is safe and will only pick up
  newly tagged / still-unattached conversations. There's no "already processed, skip" marker
  beyond that.
- **Outlook hydration can be slow.** Each conversation triggers a Microsoft Graph call if its body
  isn't already fully cached. For a large batch, use `--limit=` to run it in chunks rather than
  all at once.
- **This doesn't retroactively fix already-created leads.** If a lead was already created earlier
  with bad data (e.g. a phone number saved as the name), this command won't touch it — it only
  acts on conversations that don't have a `lead_id` yet.
