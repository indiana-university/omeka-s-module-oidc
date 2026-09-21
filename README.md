
# Omeka-s-module-OIDC
Omeka S module to provide OIDC authentication

## Setup
Begin by downloading the software using one of the two methods below.

Option 1: Use git clone to install the module in your Omeka-S modules folder, e.g.:
```
cd <path>/omeka-s/modules 
git clone https://github.iu.edu/RDServices/Omeka-s-module-OIDC.git OIDC
```
Option 2: Download a zip file of the module from https://github.iu.edu/RDServices/Omeka-s-module-OIDC and (if necessary) unzip it. Then move the module to your Omeka-S modules folder, e.g.:

```
mkdir -p <path>/omeka-s/modules/OIDC
cp -r <path-to-zip-file> <path>/omeka-s/modules/OIDC
```

After the module is in place, run composer to install required packages:
```
cd OIDC
composer install
```

Add the OIDC client and secret to /config/local.config.php in your Omeka installation. e.g.:
```
'oidc' => [
    'client_id' => '*****',
    'client_secret' => '*****',
],
```

In the module configuration, enter the exact HTTPS issuer URI published in the
provider metadata, for example `https://idp.example.edu`. Do not enter the full
`/.well-known/openid-configuration` document URL. The discovered issuer must
match this value exactly, and the authorization, token, JWKS, and UserInfo
endpoints must use the same HTTPS origin.

### Diagnostic logging

The module normally records only a generic authentication failure. To investigate
an OIDC provider problem, enable **OIDC diagnostic logging** in the module
configuration and reproduce the login failure. Diagnostic entries include the
exception type and message and, when UserInfo lacks an email address, the names
of the returned claims, but not their values. Omeka logging must also be enabled
for these entries to be recorded.

When the Omeka S **Log** module is enabled with its default configuration, no
additional integration is needed: OIDC uses the shared `Omeka\Logger` service,
so entries are available in the Log module's admin log and in
`logs/application.log`. If the Log module's writers or severity filters have
been customized, ensure that warning and notice messages are retained.

Without the Log module, enable Omeka's core logger in the site's
`config/local.config.php`, for example:

```php
'logger' => [
    'log' => true,
],
```

The module does not override Omeka's global logging configuration. Because
provider or library exception text is included, protect access to the log and
disable OIDC diagnostic logging after troubleshooting.

## Development

The supported development and deployment runtime is PHP 8.2. Install dependencies
and run the required pull-request checks with:

```console
composer install
composer validate --strict
composer audit --locked
composer lint
composer test
```

The unit suite uses deterministic fixtures under `tests/fixtures` and does not
contact a live identity provider or require production credentials.

CI also runs `tests/smoke/omeka.php` against Omeka S 4.2.1. To run that check
locally, install Omeka S and set `OMEKA_PATH` to its root directory:

```console
OMEKA_PATH=/path/to/omeka-s php tests/smoke/omeka.php
```

## Releases

Every releasable code or production-dependency change must update `version` in
`config/module.ini` in the same pull request. Choose the next unused semantic
version after the latest repository tag. Documentation-only, test-only, and
CI-only changes do not require a version bump unless they are intentionally
being published as a release.

After the pull request is merged and CI passes, create an immutable tag matching
the declared module version at the merge commit, then publish the corresponding
GitHub Release. Never reuse or move an existing release tag.
