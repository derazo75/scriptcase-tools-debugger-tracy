# scriptcase-tools-debugger-tracy
A debugging toolkit that adds custom System Info and Request panels to the Tracy Bar, featuring a zero-interference SQL Proxy Class. It enables real-time monitoring of server health, global variables, and database performance (including network payload estimation) without disrupting Scriptcase's internal data pointers.

Tracy Debugger Power-Up for Scriptcase
--------------------------------------

### Installation Guide

Follow these steps to integrate the Tracy Debugger toolkit into your environment using a global prepend approach (apache mod_rewrite required).

* * * * *

#### 1\. Directory Setup

Create a dedicated directory for the debugger at your web server's document root.

Bash

```
mkdir /var/www/html/tracy

```

#### 2\. Deployment

Clone or copy the following files from the repository into the newly created `tracy` folder:

-   `tracy.php` (The core configuration and Proxy class)

-   `composer.json`

#### 3\. Dependency Management

Navigate to the directory and install the necessary Tracy packages via Composer to ensure all debugging components are available.

Bash

```
cd /var/www/html/tracy
composer require tracy/tracy

```

#### 4\. Global Integration (Auto-Prepend)

To enable the debugger across your applications without modifying each individual script, create or edit a `.htaccess` file in your target project folders. Add the following directive to automatically include the debugger at the start of every PHP execution:

Apache

```
# Force the debugger to load before any other script
php_value auto_prepend_file "/var/www/html/tracy/tracy.php"

```

* * * * *

This integration enhances the **Tracy PHP Debugger** with custom panels and a specialized Proxy class designed to solve the common "blind spots" in Scriptcase development and PHP server monitoring.

### Key Components

#### 1\. System Information Panel (ℹ️)

Monitor your server's vital signs at a glance:

-   **PHP Version & Server Software:** Instant environment identification.

-   **Peak Memory Usage:** Track the maximum RAM consumed by the current script.

-   **CPU Load:** Real-time server load averages.

-   **Quick Usage Guide:** A built-in reference for `Timer`, `bdump`, and `variable tracing` to help your team use Tracy effectively.

#### 2\. Enhanced Request Panel (🌐)

A dedicated space to inspect the current HTTP lifecycle:

-   **Method & URI:** Full visibility of the request path and protocol.

-   **Superglobals:** Beautifully formatted dumps of `$_GET`, `$_POST`, `$_COOKIE`, and `$_SERVER`.

-   **Response Codes:** Track HTTP status codes as they are generated.

#### 3\. SQL DebugProxy

The core of this toolkit is the `DebugPDOProxy` class, specifically tuned for ADOdb/Scriptcase:

-   **Zero-Pointer Interference:** Estimates query result sizes using a non-intrusive "net weight" algorithm. It calculates the network payload without moving the `RecordSet` cursor, preventing empty Grids or broken Forms.

-   **Size transferred estimation:** Intelligently filters out ADOdb associative/numeric overhead to show you the approximate data size transferred from the DB.

-   **Transaction Tracking:** Monitors `BeginTrans`, `Commit`, and `Rollback` events.

-   **Complete Metadata:** Captures `Last Insert ID`, error messages, affected rows, and execution time in milliseconds.

### Implementation

Simply include the file and wrap your Scriptcase connection in the `onScriptInit` event:

PHP

```
// Standard bar dump
$this->Db = new DebugPDOProxy($this->Db, 1);
// Direct on-screen dump
$this->Db = new DebugPDOProxy($this->Db, 0);
```

### Information

For a deep dive into using Tracy within the Scriptcase environment or to explore the full capabilities of the Tracy Debugger engine, please refer to the following resources:

-   **Scriptcase Community Guide:** [Debugging Tools - Use Nette Tracy in Scriptcase](https://forum.scriptcase.net/t/debugging-tools-use-nette-tracy-to-debug-code-in-scriptcase/38434)
-   **Official Tracy Documentation:** [Tracy User Guide](https://tracy.nette.org/en/guide)
