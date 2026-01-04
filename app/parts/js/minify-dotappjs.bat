terser dotapp.js -o dotapp.min.js --keep-classnames --mangle reserved=['$dotapp'] --mangle-props reserved=['#exchange','exchange','Content-Type','dotapp','dotbridge'] --name-cache map.json

call terser dotapp.reactive.js -o dotapp.reactive.min.js

call terser dotapp.template.js -o dotapp.template.min.js