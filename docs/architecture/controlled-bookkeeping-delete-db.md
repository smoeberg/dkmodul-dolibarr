# Controlled bookkeeping delete database identity

The controlled bookkeeping delete path must use a dedicated MariaDB account that is
not used by normal Dolibarr SQL traffic.

Required production provisioning:

- account: `dkmodul_delete`
- host: the application host pattern used by the deployment
- privileges: SELECT and DELETE on `llx_accounting_bookkeeping`; SELECT and INSERT
  on `llx_dk_audit_event`
- the ordinary Dolibarr database account must not be used for the controlled delete
  connection
- the password must be supplied to PHP as `DOLI_DKMODUL_DELETE_DB_PASSWORD`
  and the username as `DOLI_DKMODUL_DELETE_DB_USER`
- credentials must be stored as deployment secrets, never in the repository

The bookkeeping DELETE trigger checks the authenticated client identity via
MariaDB `USER()`, not `CURRENT_USER()`. `CURRENT_USER()` in trigger/routine
execution can represent the trigger/routine definer rather than the authenticated
client, so it must not be used as the caller gate.

The application service opens a separate Dolibarr DB connection with the dedicated
identity. Audit append and bookkeeping DELETE then execute on that same connection
and transaction. This keeps audit failure and delete failure rollback-coupled while
preventing a normal SQL session from authorizing an unvalidated DELETE.

The database identity is a deployment control. The repository does not contain a
production password.
