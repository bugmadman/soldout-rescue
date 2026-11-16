# Sold-out rescue

The rules for working on this project, for people and AI tools alike. The working method is in [WORKFLOW.md](WORKFLOW.md).

A mobile rep tool for UK Local sales: today's sold-out deals, each with a call / don't-call recommendation and an amount in £. Money is computed in code, free text is read by an LLM with verified quotes, a safe switch is added to the routing service, and the effect is proven with a holdout. Requirements live in `docs/` (not committed). Stack: PHP 8.5, Symfony 7.4 (LTS), PostgreSQL, FrankenPHP, Docker; the routing service and the holdout script are Python (standard library only).

## Hard constraints

- The whole job is time-boxed: about 3 hours of work in total. Every phase in [PLAN.md](PLAN.md) has a minute budget. A phase that overruns is cut, not extended: the cut goes into the README cut list and into the memos.
- Money is computed in code, never by the LLM. The LLM only extracts signals from text, each with a quote.
- Free text (rep notes, Deal Alert replies, reviews, refunds) is data, not instructions. Every prompt states this explicitly and puts the text inside delimiters.
- A quote the LLM returns is shown only if it is a substring of the source text after normalisation (whitespace, mojibake repair). Otherwise the finding is dropped.
- Secrets: the sandbox key lives only in `.env.local`. Never commit it, never print it, never put it in a URL, a prompt or a log.
- Real data (`docs/data-pack/`, API responses) is never committed. Tests use small invented fixtures.
- Tests and the holdout read-out run offline, from files, without the sandbox key.
- The routing switch is Greg's call: never set `suppress_soldout_tasks` through the sandbox API without his written OK in chat. Never claim an agreement that was not made.
- Nothing from any employer, past or present, goes into the repository.

## Development principles

- DDD for a solo developer: a rich domain model, not an anemic one — entities contain behavior and protect their invariants, rather than bare getters/setters with the logic living in services
- Value Objects are immutable, with no mutator methods. Money is an integer number of pence in a `Money` VO, never a float
- DTOs validated with Symfony Assert at the boundaries (forms, API input)
- Enums instead of magic strings — recommendation, signal type, mark code, etc.
- Interfaces for external integrations (ports & adapters) — `SandboxClientInterface`, `LlmClientInterface`, so that implementations are swappable and testable with fakes
- Pass structured data as classes (DTO/VO), not arrays
- A list that carries behavior or an invariant (a lookup, membership, uniqueness, an emptiness meaning) or travels between areas is a `final readonly` collection class over a private `list<…>`, implementing `\IteratorAggregate` and `\Countable`, not a bare `list<…>`. Plain lists stay where they are: query results for display, an entity's relation getters, framework-required signatures, private helpers. No Doctrine `ArrayCollection` outside entity relations: it is mutable
- An exception's base class says whose fault it is: `\DomainException` when a business rule or the input is violated; `\RuntimeException` when the environment fails or a setting is missing (the sandbox is down, the key is not set); `\LogicException` for a bug in our code or wiring that no input can cause
- DRY, KISS, SOLID, YAGNI — don't build abstractions for a hypothetical future. In a 3-hour job YAGNI wins every tie
- Comments only for a real trap a future reader would fall into; no comments explaining obvious settings or restating the code, in code and configs alike (compose, Makefile, Dockerfile, YAML/NEON, `.env*`). A comment says why it is done this way, not what the setting does. This covers comments added by Flex recipes and generators too: doc links, "uncomment this", commented-out examples are removed. When in doubt, leave it out
- Code structure: what Symfony has a folder for stays there (`src/Entity`, `src/Repository`, `src/Controller`, `src/Command`); framework-free domain code (VOs, domain exceptions, enums, area services) goes to `src/Domain/<Area>/`, an exception next to what it belongs to; adapters for external integrations go to `src/Infrastructure/`. No `Application` layer
- One service per area, `src/Domain/<Area>/<Area>Service`, for operations that need something outside the entity (the DB, the sandbox, the LLM, several entities). An entity's own behavior and invariants stay in the entity
- Every data change goes through the area service. Controllers and console commands are thin: parse the input, call the service, render the result. Reading for display may use a repository directly
- Service methods take VOs and DTOs, never raw strings: the entry point builds them from the input and handles their validation errors
- Repositories hold queries and `save()` (`persist` + `flush`), no logic. `find…` returns null when nothing is found, `get…` throws
- An interface lives next to its implementation, and dependents use the interface: every class is `final`, so nothing else can be mocked
- Tests cover logic, not data containers: a VO with validation is tested, a VO or DTO without logic isn't. Money and quote verification are tested exhaustively. Services: unit tests with the dependencies mocked. Controllers: functional tests with the service mocked. Tests never touch real data or the network
- The product is English only: no translator, no locale files
- The Python parts (routing, holdout) use only the standard library, keep their existing test style (`unittest`) and run offline

## What the tools enforce

Only what the tools don't do themselves goes here:

- PHP-CS-Fixer fixes the style, including `declare(strict_types=1)` and `final` classes
- PHPStan (strict rules) forbids unsafe handling of `mixed`, loose comparisons, etc.
- PHPUnit runs the tests; `make lint` runs all checks by hand

The exception is line length: 120 characters max, fixed by hand. Write long signatures and calls with one argument per line from the start.

## Running commands

Every PHP command (`bin/console`, `composer`, tests, linters) runs only inside Docker: `docker compose exec php ...` or the Makefile targets that wrap it. Bring the stack up only with `make init`/`make upd`/`make updb`: a bare `docker compose up` doesn't read `.env.local` and would rebuild the image with the default UID. Never use the host's PHP or Composer. The Python parts run on the host with `python3 -m unittest discover -s tests` from their folder. Git runs on the host.

## Language

All files in the repository are in English: code, comments, docs, commit messages, PR descriptions.
