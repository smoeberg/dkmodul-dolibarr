# Inexchange settings

The P0 GUI exposes the non-secret Inexchange configuration in Dolibarr.

## Persisted settings

The module stores only these non-secret values as Dolibarr constants:

- DKMODUL_INEXCHANGE_BASE_URL
- DKMODUL_INEXCHANGE_ERP_ID

The base URL must use HTTPS.

## Runtime secrets

The following values are deliberately not persisted by the module:

- DKMODUL_INEXCHANGE_API_KEY
- DKMODUL_INEXCHANGE_CLIENT_TOKEN

They must be injected by the deployment/runtime secret mechanism.

The GUI only reports whether each secret is configured. It never renders the secret value.

## Connection test

The Test Inexchange-forbindelse action uses the configured base URL and runtime secrets to execute the existing DkInexchangeAccessPointProvider::health() check.

A successful test means the configured credentials can reach the Inexchange API. It does not constitute company registration, NemHandel/Peppol onboarding, or a production certification result.
