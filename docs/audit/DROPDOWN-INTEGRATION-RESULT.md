# Dropdown Integration Result

`tests/Feature/DropdownContractTest.php` exercised 38 master-data option resources with `options=1` and fiscal-year context. Result: **1 test, 114 assertions passed** on isolated staging MariaDB.

The dependent chain test also verifies Donor → Grant → Project → Activity → Budget Line using the selected donor, grant, project, and fiscal year. Focused regression result: **2 tests, 141 assertions passed**.

Covered endpoint contracts include organization, department, staff, fiscal year, periods, donor, funding source, grant, program, project, activity, budget, bank, vendor, item/service, COA, tax, currency, payment method, reporting dimensions, and approval matrices.

Browser dependent-field reset, loading/error rendering, and role-specific option visibility remain NOT TESTED.

Web Expense and Procurement forms now filter Project/Budget Line options from the selected parent and clear child Budget Line values when Donor/Project changes. Frontend regression: **9 files, 63 tests passed** and production build passed. Visual browser execution remains NOT TESTED.
