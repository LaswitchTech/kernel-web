- `https://kernel-web.local/admin/developer/scaffold`:
  - The page shows up above the topbar, and the topbar is not fixed to the top of the page. The topbar should be fixed to the top of the page and the content should be displayed below it.
- `https://kernel-web.local/admin/settings`:
  - There is still some left overs from NetMon. Notably the monitoring `Default Check Interval` setting, which should be removed.
  - There is also left overs from the notification system, which I am not sure should be there since I haven't check if we started to implement the notification system or not. The `Notification Settings` section should be removed if we haven't started to implement the notification system, otherwise it should be implemented within the settings page using a hook. Because of course settings should support hooks so plugins can expend the settings page with their own settings.
  - Have we implemented SMTP settings yet? Maybe it should be a plugin as well and the default method to send email would be using the `mail()` function, and if the user wants to use SMTP they can install the SMTP plugin and configure it within the settings page using a hook.
- Auth:
  - We should implement a "Remember Me" functionality for the login page, which allows users to stay logged in for a longer period of time without having to re-enter their credentials. This can be achieved by setting a long expiration time for the authentication cookie when the "Remember Me" option is selected.
  - We should also implement a "Forgot Password" functionality, which allows users to reset their password if they forget it. This can be achieved by sending a password reset link to the user's email address, which they can use to create a new password.
  - Additionally, we should consider implementing two-factor authentication (2FA) for added security. This can be achieved by requiring users to enter a second form of authentication, such as a code sent to their phone, in addition to their password when logging in.
  - We should also make sure users valiate their email addresses during registration to ensure that they have access to the email address they provided. This can be achieved by sending a verification email with a link that the user must click to verify their email address before they can log in.
  - We should implement user registration functionality, allowing new users to create accounts on the platform. This can be achieved by creating a registration form that collects necessary information from the user, such as their email address and password, and then creates a new user account in the database.
- Mailer:
  - We should implement a mailer system that allows us to send emails from the application. This can be achieved by creating a Mailer class that uses the `mail()` function by default, but also allows for SMTP configuration through a plugin. The Mailer class should have methods for sending different types of emails, such as password reset emails, notification emails, etc.
  - We should also implement email templates that can be used for different types of emails. This will allow us to maintain a consistent look and feel for all emails sent from the application and make it easier to customize the content of the emails.
  - We should also consider implementing a queue system for sending emails, especially if we expect to send a large number of emails. This can help improve performance and ensure that emails are sent in a timely manner without overwhelming the server.
  - We should also implement a way to extend the mailer system through plugins, allowing developers to add new email types or customize existing ones without modifying the core codebase. This can be achieved by providing hooks in the Mailer class that plugins can use to register their own email types and templates.
  - We should also implement a way to add attachments to emails, allowing users to send files along with their emails. This can be achieved by adding a method to the Mailer class that allows users to specify files to be attached to the email, and ensuring that the email is properly formatted to include the attachments when sent.
  - We should also allow the Mailer to support SMTP through a plugin, which would allow users to configure their SMTP settings within the settings page using a hook. This would provide more flexibility for users who want to use SMTP for sending emails instead of the default `mail()` function.
  - Finally, we should implement error handling for the mailer system, ensuring that any issues with sending emails are properly logged and that users are notified if their email could not be sent. This can be achieved by catching exceptions thrown by the mailer methods and logging the errors, as well as providing feedback to the user when an email fails to send.
- Messenger (SMS):
  - We should implement a messenger system that allows us to send SMS messages from the application. This can be achieved by creating a Messenger class that uses a third-party SMS API (such as Twilio) to send messages. The Messenger class should have methods for sending different types of messages, such as notification messages, verification codes, etc.
  - We should also implement message templates that can be used for different types of messages. This will allow us to maintain a consistent look and feel for all messages sent from the application and make it easier to customize the content of the messages.
  - We should also consider implementing a queue system for sending messages, especially if we expect to send a large number of messages. This can help improve performance and ensure that messages are sent in a timely manner without overwhelming the server.
  - We should also implement a way to extend the messenger system through plugins, allowing developers to add new message types or customize existing ones without modifying the core codebase. This can be achieved by providing hooks in the Messenger class that plugins can use to register their own message types and templates.
  - We should then create a plugin for Twilio that integrates with the Messenger class, allowing users to configure their Twilio settings within the settings page using a hook. This would provide a convenient way for users to set up their SMS messaging without having to modify the core codebase.
  - We should also create a plugin for Telico that integrates with the Messenger class, allowing users to configure their Telico settings within the settings page using a hook. This would provide an alternative option for users who want to use Telico for sending SMS messages instead of Twilio. See `/Users/louis/Projects/LaswitchTech/core/lib/plugins/telico/Helper.php` to understand how to integrate with the Telico API.
  - Finally, we should implement error handling for the messenger system, ensuring that any issues with sending messages are properly logged and that users are notified if their message could not be sent. This can be achieved by catching exceptions thrown by the messenger methods and logging the errors, as well as providing feedback to the user when a message fails to send.
- Database Management:
  - We should also add compatibility to the database management system for PostgreSQL and MySQL, in addition to SQLite. This would allow users to choose the database system that best suits their needs and preferences. We can achieve this by implementing a database abstraction layer that supports multiple database systems, allowing us to easily switch between them without modifying the core codebase.
- Testing:
  - We should test all existing CRUD operations such as users, groups, permissions, tokens, etc.

---

## Debugging Notes — Global View Context

### 2026-05-22: Global View Context Architecture Issue

**Symptoms:**
- Dev tools offcanvas disappeared on some views
- User menu breaks on pages like /admin/permissions
- $config, $user, and current user variables not consistently available

**Root Cause Analysis:**

The controller → view → layout pipeline is broken:

1. Controllers set `$principal`, `$permissions`, `$appName`, `$displayName` in local scope via `ctx()` (e.g. `PermissionController::ctx()`)
2. Controllers do **not** set `$config`, `$user`, or `$auth` in the layout scope
3. Controllers read `$config` from the container (local variable) but never extract it to the view
4. `$auth` (AuthService) is in the container but never in the view scope
5. `ViewGlobals::varsFromScope()` at the top of each layout captures `get_defined_vars()` — this includes `$principal` and `$permissions` from the controller, but NOT `$config`
6. Partial files (user-menu, dev-tools) access globals like `$currentUserDisplayName` (from ViewGlobals) and `$config`/$appConfig (from dev-tools) — but `$config` was never set in scope
7. Defensive fallbacks in partials (silent `return`, `isset()` checks) mask the real issue

**The old core framework's approach** (`/Users/louis/Projects/LaswitchTech/core/src/Bootstrap.php`):

- Bootstrap declares all global objects in `Default` array with scope constraints
- Each object is instantiated as a global variable (`global $CONFIG`, `global $AUTH`, etc.)
- Layout templates access these as instance properties (`$this->Config`, `$this->Auth`, `$this->Request`)
- **Every template gets the same objects, guaranteed**

**Design direction:** Create a single guaranteed context entry point at the top of every layout that returns all global objects + derived variables in one call. See DESIGN.md § "Global View Context Design" for the full design.

### Lessons Learned

1. **Never add defensive silent returns to partials** — they hide the real architecture bug. If a variable is missing, it should throw or log, not silently pass.
2. **The context pipeline (controller → view → layout) is fragile** — variables leak or drop out at each boundary. A guaranteed context layer at the layout entry point is the only reliable solution.
3. **Container bindings ≠ view scope** — `$container->get('config')` works in controllers, but the layout scope is a completely different PHP variable scope. Values in the container do not auto-flow to views.
4. **`get_defined_vars()` is a band-aid, not a design** — it works when controllers happen to set the right variables, but it's non-deterministic and hard to reason about.
5. **Global objects are the right pattern here** — the old core framework proved this. Every layout accesses `$CONFIG`, `$AUTH`, `$REQUEST` etc. as guaranteed globals. No resolution order, no fallback chains, no missing variables.
6. **ViewGlobals::varsFromScope() is a patch** — it was designed to work around the missing globals by peeking at scope. It should be replaced by a proper `globalContext()` that resolves from the container directly.
