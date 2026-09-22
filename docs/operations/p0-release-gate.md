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

The report schema is `dkmodul/p0-release-gate-report.schema.json`. Runtime collection, append-only persistence and production signing are subsequent gates; until they are connected, the product remains `p0-draft`.
