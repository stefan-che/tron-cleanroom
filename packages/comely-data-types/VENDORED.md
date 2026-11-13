# Vendored: comely-io/data-types 1.0.34

- Origin: github.com/comely-io/data-types (abandoned upstream, last release 2020).
- Commit: 833ecf364a99aa5cd161e0ebd1191860921834c6 (tag 1.0.34, the version pinned in composer.lock before vendoring).
- Zip: https://api.github.com/repos/comely-io/data-types/zipball/833ecf364a99aa5cd161e0ebd1191860921834c6
- Zip sha256 (downloaded 2026-11-04): 617b1eff1be5dde2641a46190b2b87ab050f4fff3621f3d01758257bcae41d29
- Verified: zip contents are byte-identical to a `git clone` checked out at the commit above (`diff -r`).
- Why: required by iexbase/tron-api ^5.0; upstream declares `php: ^7.2`, so a clean
  `composer install` on PHP 8.x fails without `--ignore-platform-req=php`.
- Wired in via a `path` repository in ../../composer.json (symlink=false, version pinned to 1.0.34).
- Only change: composer.json `php` constraint `^7.2` -> `^7.2 || ^8.0`.
- License: MIT (see LICENSE).
