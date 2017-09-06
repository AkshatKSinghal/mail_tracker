# Mail Tracker

API Reference: https://app.swaggerhub.com/apis/akshat.kumar.singhal/EmailTracker/1.0.0

How it works:
- Tracker link can be generated via /token/new API
- Tracking happens via encoded link. /token/track/{token_id}
- Stats for specific token: /stats/{token_id}
- Overall Stats: /stats/all

DB:
MySQL Database (schema attached in db.sql)
Use of triggers to update the following:
- Bucketed count for total, unique opens
- Overall open count, unique count, first open, last_open for each token

Table logic:
- tokens table can be easily partitioned on created date range
- tokens which are actually used have record in token_summary table, this helps in directly accessing and filtering the in-use tokens
- summary of open counts, is maintained in a separate summary table, updated via trigger

IMPORTANT NOTE: The code has not yet been tested