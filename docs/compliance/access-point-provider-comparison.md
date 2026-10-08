# Access Point provider comparison: Inexchange vs Maventa

**Date:** 2026-10-08  
**Purpose:** Provider selection for the provider-neutral ADR-0024 architecture.

## Executive conclusion

Both providers are technically suitable for the neutral adapter contract.

**Current recommendation: start with Inexchange as adapter #1, but do not make a final commercial commitment until the three open questions are answered.**

Why:

- The already-reviewed Inexchange partner API maps unusually directly to the intended adapter flow: company registration/status, per-client token lifecycle, upload/send/status, inbound polling and handled acknowledgement.
- Inexchange publicly describes both outbound and inbound e-invoicing and Peppol access through its network. citeturn1search1turn1search15
- Maventa has a more modern and broader documented REST surface, including webhooks, company/network setup and separate invoice/document APIs. Its REST API is explicitly the recommended path for new integrations. citeturn0search5turn0search6turn0search4
- Maventa therefore looks stronger as a second adapter and as a reference for optional webhook capability, but its partner onboarding requires a vendor API key and integration validation/agreement before production. citeturn0search10turn0search16

## Comparison

| Area | Inexchange | Maventa | Assessment |
|---|---|---|---|
| Provider-neutral fit | Strong | Strong | Both |
| Auth model | API key + ClientToken per company/user | OAuth2; company UUID + user API key + vendor API key | Both workable |
| Company onboarding | Async register/status flow documented in reviewed API | Company/settings APIs; partner/vendor model | Inexchange simpler for current adapter |
| Outbound | Upload -> send -> status / ERP document reference | Invoice/document APIs + delivery events | Both |
| Inbound | Poll incoming -> download -> handled | Receive APIs + files + webhooks/polling | Maventa richer |
| Webhooks | Not found in reviewed Inexchange API reference | Supported/documented | Maventa advantage |
| Polling | Core mechanism | Supported fallback | Both |
| Document scope | Invoices/e-orders and broader network services | Invoices plus general documents/orders | Both |
| Format conversion | Must be clarified for exact API enum/behaviour | Platform supports routing/conversion features | Both, but validate exact P0 semantics |
| Peppol | Publicly supported | Publicly supported | Both |
| Test environment | Reviewed test API available | Dedicated test environment + Swagger | Both |
| Production onboarding | Commercial/API partner terms still to clarify | Integration validation + agreement + production partner setup | Both require commercial work |
| Core risk | DocumentFormat and automated receiving activation need confirmation | More moving parts/credential model | Manageable |
| Best role now | First adapter candidate | Second adapter / portability test | Recommended |

Maventa's official documentation explicitly says new integrations should use REST rather than SOAP, and documents webhooks for invoice status/receiving flows. citeturn0search5turn0search14turn0search15

## Architecture consequence

Do **not** add provider-specific fields to `DkAccessPointProvider` to make either provider easier.

Instead:

- define the neutral contract first;
- implement a fake adapter against the contract;
- implement Inexchange;
- implement Maventa later as a compatibility test;
- expose provider-specific connection settings through the adapter's declared schema.

If Maventa needs webhooks and Inexchange does not, the neutral capability model should express:

`webhook_events = supported | unsupported`

The core must continue to work correctly with polling-only providers.

## Decision gate

Proceed with Inexchange adapter development now, but treat production provider selection as **conditional** on written answers to:

1. Exact outbound `DocumentFormat` values and handling of OIOUBL 2.02 vs Peppol BIS Billing 3.0.
2. Full API automation of Danish NemHandel/Peppol receiving registration.
3. Partner API price, DPA, data-processing location/roles, governing law and exit/portability terms.

No provider-specific architecture change should be made until those answers are available.
