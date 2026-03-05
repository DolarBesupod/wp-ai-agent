---
description: Generate a PHPUnit test for a PHP class following Arrange-Act-Assert
parameters:
  file_path:
    type: string
    description: Path to the PHP file containing the class to test
    required: true
requires_confirmation: true
---

Generate a PHPUnit test for the class in this file:

@$file_path

Requirements:
- **Test class name:** `{ClassName}Test` in a namespace that mirrors the source, replacing the root namespace segment with `Tests\Unit` (e.g. `Automattic\WpAiAgent\Core\Agent\Agent` → `Tests\Unit\Core\Agent\AgentTest`)
- **File location:** mirror the source path under `tests/Unit/` (e.g. `src/Core/Agent/Agent.php` → `tests/Unit/Core/Agent/AgentTest.php`)
- **Dependencies:** mock all constructor parameters using `$this->createMock(InterfaceName::class)`
- **Structure:** Arrange → Act → Assert with descriptive variable names
- **Method naming:** `test_{methodName}_{scenario}_{expectedOutcome}` (e.g. `test_execute_withMissingArg_throwsException`)
- **Coverage:** one test per public method — happy path, invalid input, edge cases, exception paths
- **Annotation:** `@covers ClassName` on the test class

Use write_file to save the test to the correct path under `tests/Unit/`.
