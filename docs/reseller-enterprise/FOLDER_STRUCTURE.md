# Reseller Enterprise — Folder Structure

```
app/
├── Console/Commands/
│   └── ResellerAutoSuspendLowBalanceCommand.php
├── Http/
│   ├── Controllers/Reseller/
│   │   ├── ResellerEnterpriseController.php
│   │   ├── ResellerSubResellerCreateController.php
│   │   ├── ResellerCustomerTransferController.php
│   │   ├── ResellerApiKeyController.php
│   │   ├── ResellerBrandingController.php
│   │   └── ResellerInternalTicketController.php
│   └── Middleware/
│       ├── EnsureResellerIpAllowed.php
│       └── AuthenticateResellerApiKey.php
├── Models/
│   ├── Reseller.php (+ enterprise relations)
│   ├── ResellerWalletTransaction.php
│   ├── ResellerCommissionTier.php
│   ├── ResellerCustomerTransfer.php
│   ├── ResellerApiKey.php
│   └── …
└── Services/Resellers/
    ├── ResellerHierarchyService.php
    ├── ResellerWalletLedgerService.php
    ├── ResellerQuotaService.php
    └── …

config/reseller_enterprise.php

database/migrations/2026_06_01_120000_reseller_enterprise_upgrade.php

docs/reseller-enterprise/

resources/views/reseller/enterprise/

tests/Feature/ResellerEnterpriseTest.php
```
