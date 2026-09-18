# Development / integration runtime

The integration reference currently targets **Dolibarr 24.0.1 + PHP 8.2 + MariaDB 11.4**.

Start it with:

```bash
docker compose -f dev/docker-compose.integration.yml up -d
```

Dolibarr will be available at http://localhost:8080.

Development credentials:

- user: `admin`
- password: `admin`

The local `dkmodul/` directory is mounted into Dolibarr's `custom/dkmodul` path.

This stack is for integration/compliance testing only. It is not the production reference hosting configuration from ADR-0002.

## Compliance test target

The automated end-to-end flow will:

1. enable Advanced Accounting and DK module,
2. install DB guards,
3. create a bookkeeping movement,
4. validate/lock it,
5. prove financial UPDATE fails,
6. prove DELETE fails,
7. prove operational metadata such as `date_export` may still change,
8. verify the audit hash chain.
