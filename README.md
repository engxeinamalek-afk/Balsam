# Balsam
A modern Laravel REST API for clinic management systems, handling appointments, medical records, and staff administration.

## ⏱️ Artisan Commands & Scheduling

All scheduled tasks are configured centrally in `routes/console.php` using the `Asia/Damascus` timezone (`UTC+3`) so they execute at exact local clinic hours regardless of the server's UTC clock. None of the commands accept positional arguments; instead, all inputs are passed via validated named options, keeping the commands thin while delegating business logic to dedicated Service classes.

---

### 1. Custom Artisan Commands

#### 1. `appointments:send-reminders`
Sends SMS reminders to patients who have confirmed appointments scheduled for the next day.

* **Schedule & Protections:** Runs **daily at 18:00 (`Asia/Damascus`)** so patients receive reminders the evening before their appointments during active hours without late-night disturbance. Guarded with `withoutOverlapping(30)`, `onOneServer()`, and `evenInMaintenanceMode()` so reminders still go out during maintenance. Output is logged to `storage/logs/reminders.log`, and failures trigger a critical log alert.
* **Options:**
  * `--date=` *(Optional)*: Target appointment date in `YYYY-MM-DD` format (defaults to tomorrow in `Asia/Damascus` timezone).
  * `--branch=` *(Optional)*: Specific branch ID to filter reminders for a single clinic branch.
  * `--run-dry` *(Flag)*: Simulates execution to preview eligible recipients without dispatching SMS jobs or updating reminder flags in the database.
* **Validation & Exit Codes:** Validates `--date` (`date_format:Y-m-d`) and `--branch` (`exists:branches,id`). Returns `Command::INVALID` on validation failure, `Command::FAILURE` if failure simulation is active, and `Command::SUCCESS` upon completion.
* **Outputs:** Prints a formatted console table displaying `Reference`, `Patient`, `Phone`, `Branch`, and `Time` for all target appointments, followed by a summary count of queued reminders (or `No pending reminders found for date: YYYY-MM-DD.`).

#### 2. `appointments:mark-no-show`
Finds past appointments whose patients did not check in after the allowed grace period and transitions their status to `no_show`.

* **Schedule & Protections:** Runs **every 15 minutes (`Asia/Damascus`)** with `withoutOverlapping(15)` and `onOneServer()`, logging output to `storage/logs/no-show.log` with `onFailure()` critical logging.
* **Options:**
  * `--grace=` *(Optional, default: `15`)*: Grace period in minutes after the scheduled start time before marking an appointment as a no-show (integer between `0` and `1440`).
  * `--branch=` *(Optional)*: Filters overdue appointments by a specific branch ID.
  * `--run-dry` *(Flag)*: Previews eligible overdue appointments without modifying database records.
* **Validation & Exit Codes:** Validates `--grace` (`integer|min:0|max:1440`) and `--branch` (`exists:branches,id`), returning `Command::INVALID` on invalid inputs, `Command::FAILURE` on simulated failure, and `Command::SUCCESS` otherwise.
* **Outputs:** Renders a console table listing `Reference`, `Patient`, `Branch`, `Date`, and `Time` for all affected appointments, followed by a total counter summary (or `No overdue appointments found.`).
* **Why Model Updates Instead of a Bulk Query?** `NoShowService` updates Eloquent models individually/in chunks rather than running a single raw `DB::table()->update()` query. While bulk SQL is faster, updating via Eloquent ensures `AppointmentObserver` fires for every appointment—recording the status change in the polymorphic audit log and automatically banning the patient for 30 days if they reach 3 no-shows within 30 days.

#### 3. `patients:lift-bans`
Automatically lifts online booking restrictions for patients whose 30-day ban period has expired.

* **Schedule & Protections:** Runs **daily at 00:05 (`Asia/Damascus`)** right after midnight so eligible patients regain booking access at the start of the new day. Guarded with `withoutOverlapping(30)` and `onOneServer()`, with output logged to `storage/logs/lift-bans.log`.
* **Options:**
  * `--run-dry` *(Flag)*: Previews patients whose bans have expired without updating the database.
* **Exit Codes:** Returns `Command::FAILURE` when failure simulation is enabled and `Command::SUCCESS` upon completion.
* **Outputs:** Displays a console table with `ID`, `Patient Name`, `Phone`, and `Banned Until` for every unbanned patient, followed by a summary count (or `No expired patient bans found.`).

#### 4. `reports:monthly`
Generates the monthly performance report for each clinic branch and saves it as a `JSON` or `CSV` file in storage.

* **Schedule & Protections:** Runs **monthly on the 1st at 02:00 (`Asia/Damascus`)** during zero clinic traffic. Guarded with `withoutOverlapping(60)` and `onOneServer()`, with output logged to `storage/logs/monthly-reports.log`.
* **Options:**
  * `--month=` *(Optional)*: Target month in `YYYY-MM` format (defaults to the previous month in `Asia/Damascus` timezone).
  * `--branch=` *(Optional)*: Specific branch ID to generate a report for a single branch.
  * `--format=` *(Optional, default: `json`)*: Output file format (`json` or `csv`).
  * `--sync` *(Flag)*: Generates the report immediately via `BranchReportService` instead of dispatching a background job.
  * `--run-dry` *(Flag)*: Simulates execution to preview report statistics without saving any file.
* **Validation & Exit Codes:** Validates `--month` (`date_format:Y-m`), `--branch` (`exists:branches,id`), and `--format` (`in:json,csv`). Returns `Command::INVALID` on validation errors, `Command::FAILURE` if failure simulation is active, and `Command::SUCCESS` otherwise.
* **Outputs:**
  * In `--run-dry` or `--sync` mode: Renders a console summary table (`Branch ID`, `Branch Name`, `Month`, `Appointments`, `No-Shows`, `Busiest Doctor`, `Doctor Appts`) along with the saved file path (for `--sync`).
  * In normal/scheduled mode: Dispatches `GenerateMonthlyBranchReportJob` to the `reports` queue and prints a confirmation message.

---

### 2. Server Cron & Local Execution

On a production server, add this single Cron entry (`crontab -e`) to trigger the Laravel Scheduler every minute:

```bash
php artisan schedule:run
```

To run and test the scheduler locally during development:

```bash
php artisan schedule:work
```

#### Schedule List Output (`php artisan schedule:list`)
*(Cron expressions appear in server UTC time, matching `18:00` and `00:05` in `Asia/Damascus`)*

```text
  0    15 * * *  php artisan appointments:send-reminders ...................................................... Next Due: 2 hours from now 
  5    21 * * *  php artisan patients:lift-bans ............................................................... Next Due: 8 hours from now  
  */15 *  * * *  php artisan appointments:mark-no-show ....................................................... Next Due: 1 minute from now  
  0    2  1 * *  php artisan reports:monthly ................................................................... Next Due: 6 days from now  
```

---

### 3. Command Scenarios & Expected Behavior

#### Scenario A: Appointment Reminders (`--run-dry` & Idempotency)
1. **Dry Run Preview (`php artisan appointments:send-reminders --run-dry`):**
   Displays a console table of tomorrow's eligible appointments and summary counters without dispatching any jobs or changing reminder flags in the database.
2. **First Actual Run (`php artisan appointments:send-reminders`):**
   Pushes reminder jobs onto the `reminders` Redis queue and logs the output to `storage/logs/reminders.log`. In **Laravel Horizon**, you will see the exact number of jobs queued and processed under the `reminders` queue, while simulated SMS messages appear in `storage/logs/laravel.log`.
3. **Second Consecutive Run (`php artisan appointments:send-reminders`):**
   Outputs `No pending reminders found for date: YYYY-MM-DD.` because tomorrow's appointments are already flagged as reminded. In **Laravel Horizon**, `0` new jobs are dispatched, guaranteeing idempotency and preventing duplicate notifications.

#### Scenario B: Marking No-Shows & Automatic Patient Ban
Running `php artisan appointments:mark-no-show` transitions overdue appointments to `no_show` and logs the affected records to `storage/logs/no-show.log`. Whenever a patient reaches **3 no-shows within 30 days**, `AppointmentObserver` automatically sets their 30-day online booking ban (`banned_until`). Any subsequent API attempt to book an appointment for that patient is rejected with a `403` / validation error.

#### Scenario C: Simulating Command Failure & Alerting
1. Set `SIMULATE_COMMAND_FAILURE=true` in your `.env` file (matching `app.simulate_command_failure`).
2. Run `php artisan schedule:test` locally and select any scheduled command.
3. The command aborts with `Command::FAILURE` (non-zero exit code), triggering the `onFailure()` hook in `routes/console.php`. A `CRITICAL: Scheduled command [...] failed!` alert is written directly to `storage/logs/laravel.log`.

## 🏗️ Architecture: Observers vs. Events & Listeners

In **Balsam**, side effects are cleanly separated into **Model Observers** (synchronous data-integrity rules that must always run regardless of how a model is modified) and **Events & Listeners** (asynchronous business reactions triggered after database transactions succeed).

### 1. Model Observers (Synchronous)

* **`AppointmentObserver`**
  * **Reference Number & Active Slot (`creating` / `updating`):** Automatically generates a unique, sequential branch reference number (`DAM-2026-000154`) using `lockForUpdate()` before insertion, and syncs `active_slot` based on booking status. Running this in the observer guarantees every appointment gets a valid reference number whether created via the API, reception desk, or seeders.
  * **Available Slots Cache Invalidation (`created` / `updated` / `deleted`):** Immediately clears the Redis cache key (`doctor:{id}:branch:{id}:available_slots:{date}`) upon creation, deletion, or when `status` changes to `cancelled` or `no_show`. Checking `wasChanged('status')` ensures the cache is only cleared when slot availability is actually affected.
  * **Updating `last_visit_at` & 3-Strike Ban (`updated`):** When an appointment status transitions to `arrived` or `completed`, it updates the patient's `last_visit_at` using `saveQuietly()` / `updateQuietly()` to prevent infinite observer loops. Similarly, when status becomes `no_show`, it counts no-shows within the past month and sets a 30-day ban (`banned_until`) via `saveQuietly()` once a patient reaches 3 strikes.

* **`MedicalRecordObserver` & `PatientObserver`**
  * **Polymorphic Audit Logging (`updated`):** Tracks changes strictly on sensitive auditable fields (`diagnosis`, `prescription` for medical records; `name`, `phone`, `email` for patients) using `getChanges()` and `getOriginal()`. Every modification—along with the user ID, timestamp, old values, and new values—is written synchronously to `audit_logs` so compliance records are never missed, even during bulk scripts.

---

### 2. Business Events & Listeners (Asynchronous)

* **`AppointmentBooked` → `SendBookingConfirmation` & `AppointmentCancelled` → `SendAppointmentCancellation`**
  * **Why Events & Listeners?** Booking and cancelling are explicit domain actions decoupled from model persistence. Both listeners run **asynchronously** in the background on a high-priority Redis queue (`high`) and implement `ShouldDispatchAfterCommit`. This keeps API responses fast while guaranteeing that patients never receive confirmation or cancellation SMS messages if the database transaction fails and rolls back.
