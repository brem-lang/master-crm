# Future: Per-Child Database Split

This is a design note, not implemented code. It describes the intended path for
giving each child CRM its own physically separate database (per `CLAUDE.md`'s
"child data stays in child Supabase DB"), once child-side data actually exists.

## Why this isn't built yet

Today, `companies` and `users` live in one shared database, with `users.company_id`
scoping visibility (see `app/Policies/UserPolicy.php`). No child-side models
(leads, customers, advertisers, affiliates, distribution rules, sales pipelines)
exist yet. Building the connection-switching machinery before that schema exists
would be speculative — the details (which models need it, how connections are
pooled, how migrations run per-tenant) depend on data that doesn't exist.

## Intended pattern, once child-side models are added

1. **`companies.db_connection`** (JSON column, already added in the companies
   migration) stores each child's connection info — host, port, database name,
   username, and a reference to a secret (e.g. a vault key or encrypted value),
   never a plaintext credential.

2. **`TenantConnectionResolver` service** (future `app/Services/TenantConnectionResolver.php`):
   given a `Company`, registers a dynamic Laravel connection at runtime:

   ```php
   config(["database.connections.tenant_{$company->id}" => [...]]);
   DB::purge("tenant_{$company->id}");
   ```

   This is Laravel's standard dynamic-multi-db pattern — no package needed.

3. **Tenant-scoped models** (future `Lead`, `Advertiser`, etc.) resolve their
   `$connection` dynamically per request, based on the authenticated user's
   company — likely via a shared `TenantModel` abstract base class rather than
   repeating connection-resolution logic per model.

4. **Parent-side consolidated reporting**: a future `ReportAggregationService`
   iterates `Company::where('is_active', true)->get()`, resolves each one's
   connection via `TenantConnectionResolver`, runs identical read-only aggregate
   queries against each, and merges results in PHP — not a cross-database SQL
   join, since child databases are physically separate.

## What NOT to do prematurely

- Don't add `$connection` resolution to `User`/`Company` — those are parent-side
  models and always live in the master database.
- Don't build `TenantConnectionResolver` until there's a real tenant-scoped
  model to point it at; an untested abstraction here is more likely to be wrong
  in its details (pooling, migration strategy, secret handling) than useful.
