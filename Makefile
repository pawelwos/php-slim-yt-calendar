CSS_IN  = resources/css/app.css
CSS_OUT = public_html/css/app.css

.PHONY: css watch serve
css:
	tailwindcss -i $(CSS_IN) -o $(CSS_OUT) --minify
watch:
	tailwindcss -i $(CSS_IN) -o $(CSS_OUT) --watch
serve:
	php -S localhost:8080 -t public_html

.PHONY: sync sync-dry authorize backup test
sync:
	php cron/sync-youtube.php
sync-dry:
	php cron/sync-youtube.php --dry-run --days=30
authorize:
	php cron/authorize-youtube.php
test:
	php tests/effective_date.php && php tests/sync.php
# Text dump of the database: small, diff-friendly snapshot for git
backup:
	@mkdir -p backup
	sqlite3 storage/youtube.db .dump > backup/youtube.sql
	@echo "Wrote backup/youtube.sql"
