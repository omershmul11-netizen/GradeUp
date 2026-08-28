# Security policy

## Secrets

Do not commit API keys, database credentials, local configuration files, or production database exports. Use environment variables or an ignored `config.local.php` file based on `config.local.example.php`.

If a credential is accidentally committed, revoke it at the provider, create a replacement, and remove the exposed value from Git history.

## Data

Only synthetic demo records belong in this repository. Do not commit real student, parent, teacher, or school data.

## Reporting

Please report security issues privately to the repository owner instead of opening a public issue containing exploit details or sensitive data.
