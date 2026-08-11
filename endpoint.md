POST {SUPABASE_URL}/functions/v1/send-lead

Headers:
Content-Type: application/json
Api-Key: <admin_api_keys key>
(also accepts api-key or X-Api-Key)

Health check (no body validation, still requires a valid Api-Key):
{ "health_check": true }

Parameters

Required

┌───────────────┬────────┬─────────────────────────────────────────────────────────────────────────────────────┐
│ Field │ Type │ Notes │
├───────────────┼────────┼─────────────────────────────────────────────────────────────────────────────────────┤
│ affiliate_id │ uuid │ must exist in affiliates, active, not deleted │
├───────────────┼────────┼─────────────────────────────────────────────────────────────────────────────────────┤
│ advertiser_id │ uuid │ must exist in advertisers; lead is routed directly here │
├───────────────┼────────┼─────────────────────────────────────────────────────────────────────────────────────┤
│ firstname │ string │ max 100 chars │
├───────────────┼────────┼─────────────────────────────────────────────────────────────────────────────────────┤
│ lastname │ string │ max 100 chars │
├───────────────┼────────┼─────────────────────────────────────────────────────────────────────────────────────┤
│ email │ string │ valid format, max 255 chars, rejected if disposable domain │
├───────────────┼────────┼─────────────────────────────────────────────────────────────────────────────────────┤
│ mobile │ string │ digits validated per-country (e.g. US/CA/GB/DE/FR/AU/IL rules), 7–15 digits overall │
├───────────────┼────────┼────────────────────────────────────────────────────────┤
│ country_code │ string │ 2-letter ISO code │
├───────────────┼────────┼────────────────────────────────────────────────────────┤
│ ip_address │ string │ deduped against existing leads │
└───────────────┴────────┴─────────────────────────────────────────────────────────────────────────────────────┘

Optional

┌───────────────────────────┬────────┬────────────────────────────────────────┐
│ Field │ Type │ Notes │
├───────────────────────────┼────────┼─────────────────────────────────────────────────────────────────────┤
│ country │ string │ free text │
├───────────────────────────┼────────┼─────────────────────────────────────────────────────────────────────┤
│ custom1, custom2, custom3 │ string │ truncated to │
├───────────────────────────┼────────┼─────────────────────────────────────────────────────────────────────┤
│ offer_name │ string │ truncated to │
├───────────────────────────┼────────┼─────────────────────────────────────────────────────────────────────┤
│ comment │ string │ truncated to │
├───────────────────────────┼────────┼─────────────────────────────────────────────────────────────────────┤
│ locale │ string │ truncated to 20 chars; auto-filled from country_code map if omitted │
├───────────────────────────┼────────┼────────────────────────────────────────┤
│ click_id │ string │ truncated to 255 chars │
├───────────────────────────┼────────┼─────────────────────────────────────────────────────────────────────┤
│ click_ip │ string │ │
├───────────────────────────┼────────┼─────────────────────────────────────────────────────────────────────┤
│ click_ua │ string │ truncated to 500 chars │
├───────────────────────────┼────────┼────────────────────────────────────────┤
│ time_to_click │ number │ rounded to integer │
├───────────────────────────┼────────┼─────────────────────────────────────────────────────────────────────┤
│ password │ string │ truncated to │
├───────────────────────────┼────────┼─────────────────────────────────────────────────────────────────────┤
│ currency │ string │ truncated to 10 chars │
├───────────────────────────┼────────┼────────────────────────────────────────┤
│ aff_sub │ string │ truncated to 255 chars │
└───────────────────────────┴────────┴─────────────────────────────────────────────────────────────────────┘

Example request

{
"affiliate_id": "3f9c...uuid",
"advertiser_id": "8a2e...uuid",
"firstname": "John",
"lastname": "Doe",
"email": "john@example.com",
"mobile": "5551234567",
"country_code": "US",
"ip_address": "203.0.113.5",
"offer_name": "Q3 Promo",
"aff_sub": "campaign_42"
}

Response

Success or rejection is whatever distribute-lead returns (advertiser eligibility, working hours, caps, adapter result, external_lead_id, autologin_url, etc.), merged with:
{ "lead_id": "...", "request_id": "..." }
Validation/lookup failures return 400 (missing fieliser_id not found), 409 (duplicate email/IP), or 422(validation/country not allowed) before it ever reaches distribute-lead.
