# KernelRotationPlannerTest — superseded

The former `KernelRotationPlannerTest.php` covered the V1 interface of
`KernelRotationPlanner`: `buildDepthNeedMatrix()`, `chooseDepth()`,
`loadDomains()`, and `advanceDomainIndex()`.

Those methods were removed during the V3 migration. Coverage was replaced by
the current rotation planner and pipeline orchestration suites. This document
is intentionally not discoverable by PHPUnit.