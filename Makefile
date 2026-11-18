CSS_IN  = resources/css/app.css
CSS_OUT = public_html/css/app.css

.PHONY: css watch serve
css:
	tailwindcss -i $(CSS_IN) -o $(CSS_OUT) --minify
watch:
	tailwindcss -i $(CSS_IN) -o $(CSS_OUT) --watch
serve:
	php -S localhost:8080 -t public_html
