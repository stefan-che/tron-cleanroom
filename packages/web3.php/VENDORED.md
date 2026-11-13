# Vendored: iexbase/web3.php 2.0.1

- Origin: github.com/iexbase/web3.php (deleted upstream, HTTP 404); code from the web3p/web3.php fork network.
- Commit: f25ed954a7586ead86046dd7e02a333a8098511b
- Zip sha256: 0c2606838f3c855689b7ed7038f4dcef57cec5c8d9442a75d06ff49ec3088777
- Why: required by iexbase/tron-api ^5.0; without a copy a clean `composer install` fails.
- Wired in via a `path` repository in ../../composer.json (symlink=false, version pinned to 2.0.1).
- Only change: composer.json `php` constraint `^7.1` -> `^7.1 || ^8.0`.
- License: MIT (see LICENSE).
