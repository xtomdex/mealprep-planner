# mealprep-planner

**A demo project, not a production application.** It shows how I build Symfony backends: a small, real domain implemented with a layered architecture, enforced boundaries and an automated test suite.

The domain is adapted from my own meal-prep product: pick dishes for the week → break them down into prep components → aggregate a shopping list with quantities (a bill of materials). Everything else in this repository is the engineering around that core.

## Status

Step 0 of the plan: project skeleton and quality gates. The domain, application layer, REST API, production Docker image and deployment arrive in the next steps.

| Gate | Tool |
|---|---|
| Tests | PHPUnit 12 (unit / integration / functional) |
| Static analysis | PHPStan level 8 + strict rules, Doctrine and Symfony extensions |
| Code style | PHP-CS-Fixer (Symfony rules, strict types, final classes) |
| Architecture | deptrac — layer and module rules in `deptrac.yaml` |
| CI | GitHub Actions on every push |

## Run it

Requirements: PHP 8.5 with `bcmath` and `pdo_pgsql`, Composer, Docker.

```bash
composer install
make up        # PostgreSQL 17 in Docker, waits until healthy
make db-init   # dev and test databases + migrations
make check     # code style, static analysis, architecture rules, tests
```

`make help` lists all targets.

## How this was built

Built with AI pair-programming (Claude Code). The architecture and every decision are mine; I review every commit and can explain any line of it.

## License

MIT — see [LICENSE](LICENSE).
