# CLAUDE.md

## Project Overview
This project is an Organizational Identity Source (OIS) plugin for COmanage
Registry version 4.x. The COmanage Registry code repository for version 4.x is
at https://github.com/Internet2/comanage-registry and the technical manual is
in the wiki at
https://spaces.at.internet2.edu/spaces/COmanage/pages/17105978/COmanage+Registry+Technical+Manual
Version 4.x of COmanage Registry uses the CakePHP version 2.x model view
controller (MVC) framework.

The plugin inventories and retrieves user accounts and groups from Microsoft
Entra (via the Microsoft Graph API) so they can be synchronized into Registry
as Organizational Identities, CO Groups, and Unix cluster groups. See
`README.md` for the documentation index; the pages live under `docs/`.

## Directory and File Structure & Key Details
- `Config/Schema/schema.xml`: database table definitions in AdoDb XML format.
- `Controller`: controllers used in the MVC framework.
- `Lib/lang.php`: text localization file for the plugin since COmanage Registry
  does not use the standard CakePHP 2.x approach to text localization.
- `Model`: models used in the MVC framework. `EntraSourceBackend.php` extends
  the Registry `OrgIdentitySourceBackend` class and holds most of the plugin
  logic, including the calls to the Microsoft Graph API.
- `View`: view files used in the MVC framework, following the Registry
  conventions (a single `fields.inc` used as the template for add and edit).
- `docs`: documentation for CILogon staff and maintainers (sync behavior,
  configuration, troubleshooting, Entra contract, assumptions and gaps,
  developer guide), indexed from `README.md`. `docs/plans/` holds planning
  artifacts and is not part of the user-facing set.

## Coding Style & Conventions
- Language: PHP version 8.3 is preferred.
- Naming convention: Follow the convention used by COmanage Registry 4.x.
- Use jQuery for dynamic HTML in view files. More but shorter lines of jQuery
  are preferred over long lines of jQuery code.
- Double slashes are preferred for comments.
- Put user-facing strings in `Lib/lang.php` rather than hard-coding them in
  controllers or views.

## Testing & Verification
- There is no automated test suite yet. Lint changed PHP with `php -l <file>`
  to catch syntax errors before treating a change as complete.
- Behavior that talks to Microsoft Entra / Graph or to the database cannot be
  verified from this repository alone; validate such changes manually in a
  running COmanage Registry with a reachable Entra tenant.

## Do's & Don'ts
- Do: Respect existing code style and patterns but suggest alternatives
  that provide generally cleaner and more maintainable code.
- Do: When a change alters plugin behavior, update the affected `docs/` pages
  in the same pull request. Cite code by file and function name, not line
  number. Keep each fact on the page that owns it (Missouri-specific values in
  `docs/assumptions-and-gaps.md`, Graph calls and permissions in
  `docs/entra-contract.md`, sync timing and created objects in
  `docs/how-sync-works.md`, field meanings in `docs/configuration.md`, log
  messages in `docs/developer-guide.md`) and link to it from elsewhere.
- Don't: Put hostnames, credentials, or the names of Missouri pipeline or
  provisioner configurations in `docs/`; the repository is public.
- Don't: Introduce new dependencies without approval.
- Don't: Commit credentials, tenant IDs, or client secrets.

## Git, Remotes, and Pushing
This repository is set up for the GitHub machine account `skoranda-agent`; the
global "Machine account" rules apply. It has three remotes (confirm with
`git remote -v`; all HTTPS):
- `bot` -> `https://github.com/skoranda-agent/EntraSource.git`, the machine
  account's fork. Claude pushes feature branches here.
- `upstream` -> `https://github.com/cilogon/EntraSource.git`, the canonical
  repository. Pull requests target it.
- `origin` -> `https://github.com/skoranda/EntraSource.git`, the developer's
  personal fork. Claude does not push here.

The machine account has read-only access to `cilogon/EntraSource` and write
access only to its own fork, and GitHub enforces that. The limit on writing
upstream is therefore held by GitHub, not only by these instructions.

Rules:
- **Never commit to `main`.** Create a branch first and commit there. If a
  commit lands on `main` by mistake, move it onto a branch.
- Before any push or pull request, verify both: `gh api user --jq .login`
  prints `skoranda-agent`, and `git remote get-url bot` is
  `https://github.com/skoranda-agent/EntraSource.git`. If either check fails,
  stop and tell the developer; never log in or switch `gh` accounts.
- **Shipping flow:** push the feature branch to `bot`, then open a
  ready-for-review pull request on `upstream`:
  `gh pr create --repo cilogon/EntraSource --base main --head skoranda-agent:<branch>`.
  The developer reviews and merges there. There is no `origin` pull request
  step and no second pull request.
- Claude may manage that pull request: edit its title and body, push follow-up
  commits to its branch, reply to review comments, and read CI results.
  Force-pushing a `bot` branch, closing a pull request, or deleting a `bot`
  branch needs the developer's approval each time.
- **Upstream CI on bot pull requests:** a pull request from a fork may wait for
  the developer's approval before Actions run, and it gets no repository
  secrets. A pull request showing no checks is not green.
- **Never** push to `upstream` or `origin` (by remote name or by URL), push or
  force-push `main` on any remote, or approve or merge any pull request. Those
  stay with the developer.

Recording where work landed:
- When recording where work landed -- in a plan, a doc, or a commit message --
  cite the **upstream** pull request, owner-qualified (`cilogon/EntraSource#N`),
  and only once it has merged. While the work is still unmerged, name the
  branch or the pull request and say the merge is pending. Recover merged
  numbers from `git log --merges main`.
