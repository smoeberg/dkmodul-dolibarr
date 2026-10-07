# P0 release gate

`DkP0ReleaseGate` produces a deterministic, machine-readable decision report for a specific product release and deployment. The report is not itself a claim of approval: every passing gate requires a SHA-256 hash of independently verified evidence.

## Required gates

1. `product-manifest`
2. `deployment-attestation`
3. `backup-retention-restore`
4. `compliance-monitoring`
5. `access-point-certification`
6. `security-risk-evidence`

Each gate is `PASS`, `FAIL` or `BLOCKED`. Missing gates become `BLOCKED`; unknown and duplicate gates are rejected. A technical failure makes the overall decision `FAIL`, while missing external approval or evidence makes it `BLOCKED`. Only six passing gates produce `PASS`.

The canonical JSON payload is hashed into `report_sha256`. The hash excludes only itself and can be placed in the existing signed deployment/release evidence envelope. This makes the decision reproducible and externally signable without embedding providers, credentials or customer-specific hosting in source code.

## Trust boundary

The evaluator validates completeness, state transitions and evidence hashes. A collector must derive gate states from the product manifest, signed deployment attestation, append-only backup/restore evidence, compliance checks and approved security/access-point records. A caller must never translate an unverified free-text assertion into `PASS`.

The report schema is `dkmodul/p0-release-gate-report.schema.json`.

`DkP0ReleaseGateCollector` derives product/access-point state from the pinned
manifest and operational state from fresh append-only compliance checks. It
persists canonical JSON and its verified hash in `llx_dk_p0_release_report`;
database triggers reject updates and deletes. Security/risk evidence remains
`BLOCKED` until a signed production evidence source is connected. Production signing of the complete report is now wired through `DkP0ReleaseSigningKeyProvider`. The private key is supplied at runtime via `DKMODUL_P0_SIGNING_PRIVATE_KEY_PATH` (preferred) or `DKMODUL_P0_SIGNING_PRIVATE_KEY`; the key is never stored in the module database or source tree. A configured provider changes `security-risk-evidence` to `PASS`, signs the final report with RSA-SHA256 and self-verifies the signature before persistence. Without a provider the gate remains `BLOCKED`.


## Security/risk evidence

`DkP0SecurityRiskEvidence` accepts only a signed JSON envelope using RSA-SHA256 and an active key from the manifest-pinned attestation trust store. The signed payload must identify the deployment, an approved reviewer, an active approval period, and SHA-256 fingerprints for the risk register and control set.

The collector reads the evidence from `DKMODUL_P0_SECURITY_RISK_EVIDENCE_PATH`. The same pinned trust-store file used for deployment attestation is used as the trust boundary. Missing, expired, tampered, untrusted or deployment-mismatched evidence remains `BLOCKED`.

The evidence file is deployment-specific and is not generated automatically by the module; approval remains an external controlled process.
