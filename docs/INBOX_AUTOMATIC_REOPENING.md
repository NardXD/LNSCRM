# Inbox Automatic Reopening

## What automatic reopening does

When a customer sends a new email to an archived or snoozed conversation, the inbox automatically moves that conversation back to **Open**. This helps the team see and respond to new customer activity without manually checking archived conversations.

The current assignee is kept. The conversation is also marked unread so the new email is visible to the team.

## User scenario: customer replies to an archived conversation

**Given:**

- A team member has archived a customer conversation.
- The conversation is assigned to Maria.
- The customer replies to the same email thread the following day.

**When:**

- The mailbox synchronizes the new customer email.

**Then:**

- The conversation moves from **Archived** to **Open**.
- Maria remains assigned to the conversation.
- The conversation is marked unread.
- The newest email appears in the existing conversation.
- The activity history records that a new customer email reopened the conversation.

This works whether the customer replies to the same Outlook email thread or starts another email conversation that the CRM groups with the same contact.

## Trigger flow

1. A new email arrives in the connected Outlook inbox.
2. The CRM synchronizes the mailbox, normally within approximately one minute.
3. The CRM verifies that the email is a new inbound message and not a previously synchronized copy.
4. The CRM locates the existing conversation, either by the Outlook thread or by eligible contact grouping.
5. If the conversation is archived or snoozed and the safety checks pass, it is moved to **Open**.
6. The existing assignee is retained, the conversation becomes unread, and a reopening activity is recorded.

## When a message triggers reopening

All of the following must be true:

- It is a new inbound email in the Inbox folder.
- The conversation is currently archived or snoozed.
- The email is newer than the conversation's previous latest message.
- The email was received within the last three days.
- The email has not already been synchronized.

## When a message does not trigger reopening

The conversation remains in its current state when:

- Outlook returns a message the CRM has already synchronized.
- The email is outbound, a draft, spam, trash, or sent mail.
- The conversation is already Open.
- The email is older than the latest message already stored in the conversation.
- The email is more than three days old, such as an older message imported during mailbox backfill.

## Additional examples

### Same Outlook thread

A customer replies using **Reply** in their email application. Outlook keeps the same thread ID. If the CRM conversation is archived, the new reply reopens it.

### New subject from the same customer

A customer starts a new email with a different subject. If contact grouping joins it to an archived conversation, the new email reopens that conversation.

### Historical mailbox import

An administrator connects an existing mailbox containing old messages. Messages older than three days are imported for history but do not reopen archived conversations.

### Repeated synchronization

Outlook returns the same message during a later synchronization. The CRM recognizes its message ID and does not reopen the conversation again or create a duplicate activity.

## Troubleshooting

If a new customer email does not reopen a conversation, check:

- Whether the email appears in the CRM conversation.
- Whether the conversation was archived or snoozed when the email arrived.
- Whether the message is inbound and located in Outlook's Inbox.
- Whether the message timestamp is newer than the conversation's previous latest message.
- Whether mailbox synchronization and its queue worker are running.
- Whether the email arrived within the three-day reopening window.
