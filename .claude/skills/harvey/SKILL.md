---
name: harvey
description: Harvey, an autonomous B2B sales playbook (from github.com/ethanplusai/harvey, MIT). Use for cold email writing, prospect scoring and qualification, buying-signal hooks, reply and objection handling, LinkedIn outreach and offer strategy. Delegates to the harvey-scout, harvey-writer, harvey-handler, harvey-sender and harvey-analyst agents.
---

# Harvey: sales skills library

Source: https://github.com/ethanplusai/harvey (MIT). The Python app (collectors, mail sending, dashboard) is not installed. Only the knowledge files and agent prompts are, as Claude Code skill and agents.

## Agents (in `.claude/agents/`)
| Agent | Use for | References to load |
|---|---|---|
| harvey-scout | Score prospects 1-100 and write a personalization angle | prospecting_tactics, lead_qualification, account_navigation, signal_playbook |
| harvey-writer | Write the 3-email cold sequence | email_frameworks, signal_playbook, sales_methodology, offer_strategy |
| harvey-handler | Draft replies to prospect responses | objection_handling, sales_methodology, offer_strategy |
| harvey-sender | Check emails against send rules before sending | email_frameworks |
| harvey-analyst | Pipeline and campaign performance review | none |

## References (`references/`)
account_navigation, email_frameworks, lead_qualification, linkedin_outreach, objection_handling, offer_strategy, prospecting_tactics, sales_methodology, selling_to_startups (YC/funded founders only), signal_playbook.

## Hard rules (override everything)
- Honor opt-outs immediately and permanently. Never reply to unsubscribe, angry, legal or compliance messages; escalate to a human.
- Never fabricate customers, stats, mutual connections or capabilities.
- Never impersonate anyone. Nothing is sent without the user's approval.

Before sales work, ask for product info (name, description, benefits, pricing, ICP) if the user has not given it.
