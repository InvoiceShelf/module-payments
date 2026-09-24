# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

### [1.1.1](https://github.com/InvoiceShelf/module-payments/compare/v1.1.0...v1.1.1) (2026-09-24)


### Bug Fixes

* work on InvoiceShelf 2.3.0 and later: the store uses Pinia 3's `defineStore(id, options)` form and the host's HTTP client instead of the removed `window.axios`
* build releases against InvoiceShelf's `2.x` branch with its toolchain (PHP 8.4, Node 24, pnpm)


### Documentation

* say the module is for InvoiceShelf 2.x only, add a compatibility table and the cache step after installing

## [1.1.0](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/compare/v1.0.0...v1.1.0) (2024-09-30)


### Features

* update namespace to App\ and add backwards compatibility ([3e82b8d](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/3e82b8d40c42101bc101635a51ce2f3fdfd9bea3))


### Documentation

* add browser data clearing step ([cc2c14c](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/cc2c14ce0f161c41cfb412eff2629c63f2a83eac))


### Chores

* add SECURITY.md ([909a536](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/909a53610dd27ab30fdf9256cc2f33a191709853))
* update README.md ([0f4a78c](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/0f4a78c69580c724b0e45b338938d0a405ec7f87))

## 1.0.0 (2024-09-03)


### Bug Fixes

* change localisation function from `$tc` to `$t` ([352dd33](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/352dd33ffec454442237eee3a4e7ef7cb9043ca4))


### Chores

* add husky and commitlint ([728231e](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/728231ed73d3387eafa5892a9ac0d2487a501cbe))
* add LICENSE ([b0a23f3](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/b0a23f3e5be79709badbffd0b394941579a910cf))
* add standard-version ([47e7dd5](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/47e7dd5729a6639eb0fb8e891bd8edd84560fcc9))
* create a README.md file ([04f899b](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/04f899bf0fd030fb09b04b29455f0c80ce134af4))
* migrate all references of Crater to Invoice Shelf ([0b7c6ec](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/0b7c6ec4c451fdd2705e2257a8827950a7cd93a8))
* re-init module git tracking ([4a8a301](https://github.com/rihards-simanovics/InvoiceShelf-payment-module/commits/4a8a3013e7d27fcf151d9c06904b731f0f83ee2a))
