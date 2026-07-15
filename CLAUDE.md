follow laravel coding standard

make security good

prevent n+1 query

optimize code and easy to read

## Architecture — Parent-Child CRM

### Overview

Master CRM (parent) oversees multiple child CRMs.
Children operate independently, parent has visibility across all.

### Data Organization

Parent stores:

- Corporate administrators
- Global reports and dashboards
- Company-wide settings
- Consolidated analytics

Each child stores:

- Leads and customers
- Advertisers and affiliates
- Distribution rules
- Sales pipelines

### User Permissions

- Parent Admin → view all companies, global users, all reports
- Child Admin → manage only their company
- Agent → view only assigned data

### Database Design

- Each tenant has a unique `tenant_id`
- Master DB stores aggregated data per tenant
- Child data stays in child Supabase DB

### Shared vs Separate

Shared (in Master):

- Global user roles
- Company settings
- Consolidated reports

Separate (stays in Child):

- Leads
- Advertisers
- Affiliates
- Distribution rules
- Activities
